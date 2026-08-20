<?php
/**
 * Contexto de edición de posts y CPTs.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Context;

use Forja\Registry\Box;

defined( 'ABSPATH' ) || exit;

/**
 * Monta las cajas en la pantalla de edición de entradas y guarda sus valores.
 *
 * @see secure-custom-fields/includes/forms/form-post.php
 */
final class PostContext extends Context {

	/**
	 * Engancha el contexto a WordPress.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_errors' ) );
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

		$storage         = $this->storage->for( 'post' );
		$values          = $this->read( $box, $storage, $post->ID );
		$label_placement = 'left' === $box->get( 'label_placement' ) ? '-left' : '-top';

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

		$storage   = $this->storage->for( 'post' );
		$submitted = $this->submitted();
		$errors    = array();

		foreach ( $this->boxes->for_subtype( 'post', $post->post_type ) as $box ) {
			// Si el nonce no viaja en la petición, la caja no se pintó en este
			// formulario (edición rápida, REST, importaciones). Saltarla evita
			// borrar datos existentes.
			if ( ! $this->verify_nonce( $box ) ) {
				continue;
			}

			$errors = array_merge( $errors, $this->write( $box, $storage, $post_id, $submitted ) );
		}

		$this->store_errors( $post_id, $errors );
	}

	/**
	 * Guarda los errores para mostrarlos tras la redirección.
	 *
	 * @param int                $post_id Identificador de la entrada.
	 * @param array<int, string> $errors  Mensajes de error.
	 * @return void
	 */
	private function store_errors( int $post_id, array $errors ): void {
		$key = $this->errors_key( $post_id );

		if ( array() === $errors ) {
			delete_transient( $key );

			return;
		}

		set_transient( $key, $errors, MINUTE_IN_SECONDS );
	}

	/**
	 * Pinta los errores de la última grabación.
	 *
	 * @return void
	 */
	public function render_errors(): void {
		$post_id = (int) get_the_ID();

		if ( $post_id <= 0 ) {
			return;
		}

		$key    = $this->errors_key( $post_id );
		$errors = get_transient( $key );

		if ( ! is_array( $errors ) || array() === $errors ) {
			return;
		}

		delete_transient( $key );

		$items = implode(
			'',
			array_map(
				static fn ( string $error ): string => '<li>' . esc_html( $error ) . '</li>',
				$errors
			)
		);

		printf(
			'<div class="notice notice-error"><p><strong>Forja:</strong> %s</p><ul class="ul-disc">%s</ul></div>',
			esc_html__( 'no se guardaron algunos campos.', 'forja-fields' ),
			wp_kses_post( $items )
		);
	}

	/**
	 * Clave del transitorio donde viajan los errores.
	 *
	 * Va por usuario para que dos editores trabajando a la vez no se crucen
	 * los avisos.
	 *
	 * @param int $post_id Identificador de la entrada.
	 * @return string Clave del transitorio.
	 */
	private function errors_key( int $post_id ): string {
		return 'forja_errors_' . get_current_user_id() . '_' . $post_id;
	}
}
