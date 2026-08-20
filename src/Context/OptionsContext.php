<?php
/**
 * Contexto de páginas de opciones.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Context;

use Forja\Registry\Box;

defined( 'ABSPATH' ) || exit;

/**
 * Monta las cajas declaradas con `object_type => 'option'` en su propia
 * pantalla del escritorio.
 *
 * A diferencia del contexto de entradas, aquí no hay un objeto al que
 * asociarse: los valores viven en la tabla de opciones, prefijados con el
 * identificador de la caja para que dos páginas no se pisen las claves.
 */
final class OptionsContext extends Context {

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
		foreach ( $this->boxes->for_object_type( 'option' ) as $box ) {
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

		$errors  = $this->save( $box );
		$storage = $this->storage->for( 'option' );
		$values  = $this->read( $box, $storage, $this->option_prefix( $box ) );

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
	 * Guarda el envío, si lo hay.
	 *
	 * @param Box $box Caja a guardar.
	 * @return array<int, string> Mensajes de error; vacío si no hubo envío o fue correcto.
	 */
	private function save( Box $box ): array {
		// Sin el campo del nonce no hay envío que procesar; distinguir ese caso
		// de un nonce inválido evita mostrar un error de seguridad la primera
		// vez que se abre la pantalla.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Sólo se comprueba la presencia; la validación es la línea siguiente.
		if ( empty( $_POST[ $this->nonce_name( $box ) ] ) ) {
			return array();
		}

		if ( ! $this->verify_nonce( $box ) ) {
			return array( __( 'La comprobación de seguridad falló. Vuelve a intentarlo.', 'forja-fields' ) );
		}

		return $this->write(
			$box,
			$this->storage->for( 'option' ),
			$this->option_prefix( $box ),
			$this->submitted()
		);
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
}
