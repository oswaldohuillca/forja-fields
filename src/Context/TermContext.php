<?php
/**
 * Contexto de edición de términos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Context;

use Forja\Registry\Box;

defined( 'ABSPATH' ) || exit;

/**
 * Monta las cajas declaradas con `object_type => 'term'` en las pantallas de
 * taxonomías.
 *
 * Hay dos pantallas con formas distintas y conviene tenerlas presentes:
 *
 * - **Alta**, en la columna izquierda del listado: los campos van apilados en
 *   un `div`.
 * - **Edición**, la pantalla propia del término: los campos van en filas de una
 *   `form-table` del escritorio, así que el envoltorio es un `tr`.
 *
 * En ambas las instrucciones se colocan bajo el control, no bajo la etiqueta,
 * porque es lo que hace el resto del escritorio.
 *
 * @see secure-custom-fields/includes/forms/form-taxonomy.php
 */
final class TermContext extends Context {

	/**
	 * Engancha el contexto a WordPress.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'add_fields' ) );

		add_action( 'created_term', array( $this, 'save' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'save' ), 10, 3 );
	}

	/**
	 * Declara los campos en cada taxonomía que tenga cajas.
	 *
	 * @return void
	 */
	public function add_fields(): void {
		foreach ( get_taxonomies( array(), 'names' ) as $taxonomy ) {
			if ( array() === $this->boxes->for_subtype( 'term', (string) $taxonomy ) ) {
				continue;
			}

			add_action( "{$taxonomy}_add_form_fields", array( $this, 'render_add' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_edit' ), 10, 2 );
		}
	}

	/**
	 * Pinta los campos en la pantalla de alta.
	 *
	 * @param string $taxonomy Taxonomía en curso.
	 * @return void
	 */
	public function render_add( string $taxonomy ): void {
		foreach ( $this->boxes->for_subtype( 'term', $taxonomy ) as $box ) {
			wp_nonce_field( $this->nonce_action( $box ), $this->nonce_name( $box ) );

			printf( '<h2>%s</h2>', esc_html( (string) $box->get( 'title', $box->id() ) ) );

			echo '<div class="acf-fields -clear">';

			// Un término que aún no existe no tiene valores que leer.
			$this->renderer->render_fields( $box->fields(), array(), self::INPUT_PREFIX, 'field' );

			echo '</div>';
		}
	}

	/**
	 * Pinta los campos en la pantalla de edición.
	 *
	 * @param \WP_Term $term     Término en edición.
	 * @param string   $taxonomy Taxonomía en curso.
	 * @return void
	 */
	public function render_edit( \WP_Term $term, string $taxonomy ): void {
		$storage = $this->storage->for( 'term' );

		foreach ( $this->boxes->for_subtype( 'term', $taxonomy ) as $box ) {
			if ( ! $box->matches_object( $term ) ) {
				continue;
			}

			// El nonce va en su propia fila: la pantalla ya está dentro de una
			// tabla y un input suelto rompería el maquetado.
			echo '<tr><td colspan="2">';
			wp_nonce_field( $this->nonce_action( $box ), $this->nonce_name( $box ) );
			printf( '<h2>%s</h2>', esc_html( (string) $box->get( 'title', $box->id() ) ) );
			echo '</td></tr>';

			$this->renderer->render_fields(
				$box->fields(),
				$this->read( $box, $storage, $term->term_id ),
				self::INPUT_PREFIX,
				'field',
				'tr'
			);
		}
	}

	/**
	 * Guarda los valores enviados.
	 *
	 * @param int    $term_id  Identificador del término.
	 * @param int    $tt_id    Identificador de la relación de taxonomía.
	 * @param string $taxonomy Taxonomía del término.
	 * @return void
	 */
	public function save( int $term_id, int $tt_id, string $taxonomy ): void {
		unset( $tt_id );

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$storage   = $this->storage->for( 'term' );
		$submitted = $this->submitted();
		$errors    = array();

		foreach ( $this->boxes->for_subtype( 'term', $taxonomy ) as $box ) {
			// Sin nonce la caja no se pintó en este formulario, así que no se
			// toca nada: puede ser una importación o una edición rápida.
			if ( ! $this->verify_nonce( $box ) ) {
				continue;
			}

			$errors = array_merge( $errors, $this->write( $box, $storage, $term_id, $submitted ) );
		}

		if ( array() !== $errors ) {
			set_transient( 'forja_errors_term_' . get_current_user_id() . '_' . $term_id, $errors, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Identificador de la caja, para el nonce.
	 *
	 * @param Box $box Caja.
	 * @return string Nombre del campo oculto.
	 */
	protected function nonce_name( Box $box ): string {
		return 'forja_term_nonce_' . $box->id();
	}
}
