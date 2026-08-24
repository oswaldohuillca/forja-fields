<?php
/**
 * Búsqueda remota de los campos relacionales.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Ajax;

use Forja\Fields\RelationalField;
use Forja\Registry\BoxRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Responde a las búsquedas del selector de entradas, usuarios y términos.
 *
 * El catálogo de estos campos puede tener miles de elementos, así que no se
 * vuelca en el HTML: se pinta sólo lo ya elegido y lo demás se busca aquí.
 *
 * Quién puede buscar qué lo decide el propio campo: el endpoint no acepta un
 * tipo de contenido ni una taxonomía por parámetro, sino el **nombre de un
 * campo declarado**, y ejecuta la consulta que ese campo define. Así no se
 * puede usar para listar nada que no esté ya expuesto en un formulario.
 */
final class Search {

	/**
	 * Acción de admin-ajax que atiende esta clase.
	 */
	private const ACTION = 'forja_search';

	/**
	 * Registro de cajas, para localizar el campo que busca.
	 *
	 * @var BoxRegistry
	 */
	private BoxRegistry $boxes;

	/**
	 * Constructor.
	 *
	 * @param BoxRegistry $boxes Registro de cajas.
	 */
	public function __construct( BoxRegistry $boxes ) {
		$this->boxes = $boxes;
	}

	/**
	 * Engancha el endpoint.
	 *
	 * Sólo para usuarios identificados: `wp_ajax_nopriv_` no se registra a
	 * propósito, porque esto sirve a las pantallas del escritorio.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Nombre de la acción de admin-ajax.
	 *
	 * Lo emiten los campos en un atributo `data-` para que el JavaScript no
	 * tenga que repetir la cadena: si cambiara aquí, allí seguiría la vieja y
	 * las búsquedas dejarían de responder sin ningún aviso.
	 *
	 * @return string Acción.
	 */
	public static function action(): string {
		return self::ACTION;
	}

	/**
	 * Atiende una petición de búsqueda.
	 *
	 * @return void
	 */
	public function handle(): void {
		$name = isset( $_REQUEST['field'] ) ? sanitize_key( wp_unslash( $_REQUEST['field'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- El nonce se comprueba justo después, contra la acción del campo.

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => 'missing_field' ), 400 );
		}

		$field = $this->boxes->find_field_anywhere( $name );

		if ( ! $field instanceof RelationalField ) {
			wp_send_json_error( array( 'message' => 'unknown_field' ), 404 );
		}

		// El nonce es por campo: uno emitido para otro no sirve aquí.
		check_ajax_referer( $field->search_action(), 'nonce' );

		/**
		 * Capacidad exigida para buscar.
		 *
		 * @param string          $capability Capacidad requerida.
		 * @param RelationalField $field      Campo que responde la búsqueda.
		 */
		$capability = (string) apply_filters( 'forja/search_capability', 'edit_posts', $field );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$term = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$page = isset( $_REQUEST['paged'] ) ? absint( wp_unslash( $_REQUEST['paged'] ) ) : 1;

		$filters = array(
			'post_type' => isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '',
		);

		$results = $field->search( $term, max( 1, $page ), $filters );

		wp_send_json_success(
			array(
				'results' => $results,
				// Una página incompleta significa que no queda nada detrás, así
				// el cliente sabe cuándo dejar de pedir.
				'more'    => count( $results ) >= RelationalField::PER_PAGE,
			)
		);
	}
}
