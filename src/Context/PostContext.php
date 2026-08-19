<?php
/**
 * Contexto de edición de posts y CPTs.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Context;

use Forja\Registry\Box;
use Forja\Registry\BoxRegistry;
use Forja\Render\Renderer;
use Forja\Storage\StorageFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Monta las cajas en la pantalla de edición de entradas y guarda sus valores.
 *
 * @see secure-custom-fields/includes/forms/form-post.php
 */
final class PostContext {

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
	 * Constructor.
	 *
	 * @param BoxRegistry    $boxes    Registro de cajas.
	 * @param Renderer       $renderer Renderizador de campos.
	 * @param StorageFactory $storage  Fábrica de almacenamiento.
	 */
	public function __construct( BoxRegistry $boxes, Renderer $renderer, StorageFactory $storage ) {
		$this->boxes    = $boxes;
		$this->renderer = $renderer;
		$this->storage  = $storage;
	}

	/**
	 * Engancha el contexto a WordPress.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Declara las cajas aplicables a la pantalla actual.
	 *
	 * @param string   $post_type Post type que se está editando.
	 * @param \WP_Post $post      Entrada en edición.
	 * @return void
	 */
	public function add_meta_boxes( string $post_type, \WP_Post $post ): void {
		foreach ( $this->boxes->for_subtype( 'post', $post_type ) as $box ) {
			if ( ! $box->matches_object( $post ) ) {
				continue;
			}

			$meta_box_id = 'forja-' . $box->id();

			add_meta_box(
				$meta_box_id,
				(string) $box->get( 'title', $box->id() ),
				array( $this, 'render_meta_box' ),
				$post_type,
				(string) $box->get( 'context', 'normal' ),
				(string) $box->get( 'priority', 'default' ),
				array( 'forja_box' => $box->id() )
			);

			// Añade la clase que engancha con el CSS portado de ACF, que anula
			// el padding propio de «.inside» para que los campos lleguen al borde.
			add_filter(
				"postbox_classes_{$post_type}_{$meta_box_id}",
				static function ( array $classes ): array {
					$classes[] = 'acf-postbox';

					return $classes;
				}
			);
		}
	}

	/**
	 * Pinta el contenido de una caja.
	 *
	 * @param \WP_Post             $post     Entrada en edición.
	 * @param array<string, mixed> $meta_box Argumentos de add_meta_box().
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post, array $meta_box ): void {
		$box = $this->boxes->get( (string) ( $meta_box['args']['forja_box'] ?? '' ) );

		if ( ! $box instanceof Box ) {
			return;
		}

		wp_nonce_field( $this->nonce_action( $box ), $this->nonce_name( $box ) );

		$values          = array();
		$storage         = $this->storage->for( 'post' );
		$label_placement = 'left' === $box->get( 'label_placement' ) ? '-left' : '-top';

		foreach ( $box->fields() as $field ) {
			if ( ! $field->stores_value() ) {
				continue;
			}

			$stored = $storage->get( $post->ID, $field->name() );

			if ( null !== $stored ) {
				$values[ $field->name() ] = $stored;
			}
		}

		printf( '<div class="acf-fields %s">', esc_attr( $label_placement ) );

		$this->renderer->render_fields(
			$box->fields(),
			$values,
			self::INPUT_PREFIX,
			(string) $box->get( 'instruction_placement', 'label' )
		);

		echo '</div>';
	}

	/**
	 * Guarda los valores enviados.
	 *
	 * @param int      $post_id Identificador de la entrada.
	 * @param \WP_Post $post    Entrada guardada.
	 * @return void
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$storage = $this->storage->for( 'post' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cada caja valida su propio nonce más abajo.
		$submitted = wp_unslash( $_POST[ self::INPUT_PREFIX ] ?? array() );

		if ( ! is_array( $submitted ) ) {
			return;
		}

		foreach ( $this->boxes->for_subtype( 'post', $post->post_type ) as $box ) {
			$nonce_name = $this->nonce_name( $box );

			// Si el nonce no viaja en la petición, la caja no se pintó en este
			// formulario (edición rápida, REST, importaciones). Saltarla evita
			// borrar datos existentes.
			if ( empty( $_POST[ $nonce_name ] ) ) {
				continue;
			}

			$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ $nonce_name ] ) );

			if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $box ) ) ) {
				continue;
			}

			foreach ( $box->fields() as $field ) {
				if ( ! $field->stores_value() ) {
					continue;
				}

				$name = $field->name();

				if ( ! array_key_exists( $name, $submitted ) ) {
					continue;
				}

				$storage->update( $post_id, $name, $field->sanitize( $submitted[ $name ] ) );
			}
		}
	}

	/**
	 * Nombre del campo oculto que transporta el nonce de una caja.
	 *
	 * @param Box $box Caja.
	 * @return string Nombre del campo.
	 */
	private function nonce_name( Box $box ): string {
		return 'forja_nonce_' . $box->id();
	}

	/**
	 * Acción del nonce de una caja.
	 *
	 * @param Box $box Caja.
	 * @return string Acción del nonce.
	 */
	private function nonce_action( Box $box ): string {
		return 'forja_save_' . $box->id();
	}
}
