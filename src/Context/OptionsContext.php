<?php
/**
 * Contexto de páginas de opciones.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Context;

use Forja\Fields\Composite;
use Forja\Registry\Box;
use Forja\Registry\BoxRegistry;
use Forja\Render\Renderer;
use Forja\Storage\StorageFactory;
use Forja\Validation\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Monta las cajas declaradas con `object_type => 'option'` en su propia
 * pantalla del escritorio.
 *
 * A diferencia del contexto de entradas, aquí no hay un objeto al que
 * asociarse: los valores viven en la tabla de opciones, prefijados con el
 * identificador de la caja para que dos páginas no se pisen las claves.
 */
final class OptionsContext {

	/**
	 * Prefijo del atributo «name» de todos los controles.
	 */
	private const INPUT_PREFIX = 'forja';

	/**
	 * Registro de cajas.
	 *
	 * @var BoxRegistry
	 */
	private BoxRegistry $boxes;

	/**
	 * Renderizador de campos.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * Fábrica de almacenamiento.
	 *
	 * @var StorageFactory
	 */
	private StorageFactory $storage;

	/**
	 * Validador de los valores enviados.
	 *
	 * @var Validator
	 */
	private Validator $validator;

	/**
	 * Constructor.
	 *
	 * @param BoxRegistry    $boxes     Registro de cajas.
	 * @param Renderer       $renderer  Renderizador de campos.
	 * @param StorageFactory $storage   Fábrica de almacenamiento.
	 * @param Validator      $validator Validador de los valores enviados.
	 */
	public function __construct( BoxRegistry $boxes, Renderer $renderer, StorageFactory $storage, Validator $validator ) {
		$this->boxes     = $boxes;
		$this->renderer  = $renderer;
		$this->storage   = $storage;
		$this->validator = $validator;
	}

	/**
	 * Engancha el contexto a WordPress.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
	}

	/**
	 * Declara una entrada de menú por cada caja de opciones.
	 *
	 * @return void
	 */
	public function add_pages(): void {
		foreach ( $this->boxes->for_subtype( 'option', '' ) as $box ) {
			$capability = (string) $box->get( 'capability', 'manage_options' );
			$slug       = $this->page_slug( $box );
			$title      = (string) $box->get( 'title', $box->id() );
			$parent     = (string) $box->get( 'parent_slug', '' );

			$render = function () use ( $box ): void {
				$this->render_page( $box );
			};

			if ( '' !== $parent ) {
				add_submenu_page( $parent, $title, $title, $capability, $slug, $render );

				continue;
			}

			// `menu_title` viene declarado como cadena vacía, así que el
			// respaldo del segundo argumento de get() nunca entraría.
			$menu_title = (string) $box->get( 'menu_title', '' );
			$menu_title = '' === $menu_title ? $title : $menu_title;

			add_menu_page(
				$title,
				$menu_title,
				$capability,
				$slug,
				$render,
				(string) $box->get( 'icon', 'dashicons-admin-generic' ),
				(int) $box->get( 'position', 80 )
			);
		}
	}

	/**
	 * Pinta la pantalla de una caja de opciones.
	 *
	 * @param Box $box Caja a pintar.
	 * @return void
	 */
	private function render_page( Box $box ): void {
		if ( ! current_user_can( (string) $box->get( 'capability', 'manage_options' ) ) ) {
			return;
		}

		$errors = $this->save( $box );
		$values = $this->read( $box );

		printf( '<div class="wrap"><h1>%s</h1>', esc_html( (string) $box->get( 'title', $box->id() ) ) );

		if ( array() !== $errors ) {
			foreach ( $errors as $error ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error ) );
			}
		} elseif ( isset( $_POST[ $this->nonce_name( $box ) ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Comprobado en save().
			printf(
				'<div class="notice notice-success"><p>%s</p></div>',
				esc_html__( 'Ajustes guardados.', 'forja-fields' )
			);
		}

		echo '<form method="post">';

		wp_nonce_field( $this->nonce_action( $box ), $this->nonce_name( $box ) );

		// El mismo envoltorio que en un metabox, para que el CSS portado
		// aplique igual fuera de la pantalla de edición.
		echo '<div class="acf-postbox"><div class="inside"><div class="acf-fields -top">';

		$this->renderer->render_fields(
			$box->fields(),
			$values,
			self::INPUT_PREFIX,
			(string) $box->get( 'instruction_placement', 'label' )
		);

		echo '</div></div></div>';

		submit_button( (string) $box->get( 'button_label', __( 'Guardar ajustes', 'forja-fields' ) ) );

		echo '</form></div>';
	}

	/**
	 * Lee los valores almacenados de una caja.
	 *
	 * @param Box $box Caja a leer.
	 * @return array<string, mixed> Valores indexados por nombre de campo.
	 */
	private function read( Box $box ): array {
		$storage = $this->storage->for( 'option' );
		$prefix  = $this->option_prefix( $box );
		$values  = array();

		$get = static fn ( string $key ): mixed => $storage->get( $prefix, $key );

		foreach ( $box->fields() as $field ) {
			if ( $field instanceof Composite ) {
				$values[ $field->name() ] = $field->read_value( $get );
				continue;
			}

			if ( ! $field->stores_value() ) {
				continue;
			}

			$stored = $get( $field->name() );

			if ( null !== $stored ) {
				$values[ $field->name() ] = $stored;
			}
		}

		return $values;
	}

	/**
	 * Guarda el envío, si lo hay.
	 *
	 * @param Box $box Caja a guardar.
	 * @return array<int, string> Mensajes de error; vacío si no hubo envío o fue correcto.
	 */
	private function save( Box $box ): array {
		$nonce_name = $this->nonce_name( $box );

		if ( empty( $_POST[ $nonce_name ] ) ) {
			return array();
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ $nonce_name ] ) );

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $box ) ) ) {
			return array( __( 'La comprobación de seguridad falló. Vuelve a intentarlo.', 'forja-fields' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Comprobado justo arriba.
		$submitted = wp_unslash( $_POST[ self::INPUT_PREFIX ] ?? array() );

		if ( ! is_array( $submitted ) ) {
			return array();
		}

		$storage = $this->storage->for( 'option' );
		$prefix  = $this->option_prefix( $box );
		$errors  = array();

		foreach ( $box->fields() as $field ) {
			$name = $field->name();

			if ( $field instanceof Composite ) {
				if ( array_key_exists( $name, $submitted ) ) {
					$errors = array_merge(
						$errors,
						$field->write_value(
							$submitted[ $name ],
							static fn ( string $key ): mixed => $storage->get( $prefix, $key ),
							static fn ( string $key, mixed $value ): bool => $storage->update( $prefix, $key, $value ),
							static fn ( string $key ): bool => $storage->delete( $prefix, $key )
						)
					);
				}

				continue;
			}

			if ( ! $field->stores_value() || ! array_key_exists( $name, $submitted ) ) {
				continue;
			}

			$value = $field->sanitize( $submitted[ $name ] );
			$error = $this->validator->validate( $field, $value );

			if ( '' !== $error ) {
				$errors[] = $error;
				continue;
			}

			$storage->update( $prefix, $name, $value );
		}

		return $errors;
	}

	/**
	 * Prefijo con el que se guardan las opciones de una caja.
	 *
	 * @param Box $box Caja.
	 * @return string Prefijo de las opciones.
	 */
	private function option_prefix( Box $box ): string {
		return 'forja_' . $box->id();
	}

	/**
	 * Slug de la pantalla en el escritorio.
	 *
	 * @param Box $box Caja.
	 * @return string Slug del menú.
	 */
	private function page_slug( Box $box ): string {
		return 'forja-' . $box->id();
	}

	/**
	 * Nombre del campo oculto que transporta el nonce.
	 *
	 * @param Box $box Caja.
	 * @return string Nombre del campo.
	 */
	private function nonce_name( Box $box ): string {
		return 'forja_nonce_' . $box->id();
	}

	/**
	 * Acción del nonce.
	 *
	 * @param Box $box Caja.
	 * @return string Acción del nonce.
	 */
	private function nonce_action( Box $box ): string {
		return 'forja_save_' . $box->id();
	}
}
