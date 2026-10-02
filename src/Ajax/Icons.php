<?php
/**
 * Intermediario del selector de iconos con la API de Iconify.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Ajax;

use Forja\Icons\Iconify;

defined( 'ABSPATH' ) || exit;

/**
 * Busca iconos y entrega sus SVG por lotes al selector del escritorio.
 *
 * El navegador no habla con Iconify. Una página de resultados son 96
 * miniaturas, y pedirlas una a una bastaba para que Cloudflare, delante de la
 * API pública, bloqueara la IP un rato. Aquí el navegador hace una petición por
 * búsqueda y otra por página, y el servidor trae lo que falte por colecciones y
 * lo cachea para todos los editores (`Iconify::svgs()`).
 *
 * Usa admin-ajax y no la API REST por lo mismo que `Search`: lo piden
 * pantallas del escritorio, con nonce, y funciona igual con los enlaces
 * permanentes «simples».
 */
final class Icons {

	/**
	 * Acción de admin-ajax para buscar.
	 */
	private const SEARCH_ACTION = 'forja_icons_search';

	/**
	 * Acción de admin-ajax para traer los SVG de una página.
	 */
	private const SVG_ACTION = 'forja_icons_svg';

	/**
	 * Acción del nonce, común a las dos peticiones.
	 */
	private const NONCE_ACTION = 'forja_icons';

	/**
	 * Cuánto se recuerda una búsqueda.
	 *
	 * Menos que un icono: el catálogo de Iconify crece, y una búsqueda de ayer
	 * puede tener resultados nuevos hoy.
	 */
	private const SEARCH_TTL = DAY_IN_SECONDS;

	/**
	 * Tope de iconos por petición de SVG.
	 *
	 * El selector pinta 96 por página. Con margen, pero acotado: esto sale a
	 * Iconify y no debe poder pedirse el catálogo entero de una vez.
	 */
	private const MAX_ICONS = 200;

	/**
	 * Cuántos resultados se piden por búsqueda.
	 *
	 * 999 es el máximo de la API: pedir más devuelve un 400. Es el mismo tope
	 * con el que trabaja icon-sets.iconify.design, que para «home» muestra
	 * once páginas. Con un límite bajo la API reparte un icono por colección
	 * en vez de devolver los mejores, y el catálogo parece pobre.
	 */
	private const SEARCH_LIMIT = 999;

	/**
	 * Engancha los dos endpoints.
	 *
	 * Sólo para usuarios identificados: `wp_ajax_nopriv_` no se registra a
	 * propósito, o cualquiera podría usar el sitio como intermediario gratuito
	 * de Iconify.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::SEARCH_ACTION, array( $this, 'search' ) );
		add_action( 'wp_ajax_' . self::SVG_ACTION, array( $this, 'svg' ) );
	}

	/**
	 * Atributos `data-` que el selector necesita para llamar aquí.
	 *
	 * Se emiten desde el campo para que el JavaScript no repita las cadenas:
	 * si cambiaran aquí, allí seguirían las viejas sin ningún aviso.
	 *
	 * @return array<string, string> Atributos.
	 */
	public static function attributes(): array {
		return array(
			'data-nonce'         => wp_create_nonce( self::NONCE_ACTION ),
			'data-search-action' => self::SEARCH_ACTION,
			'data-svg-action'    => self::SVG_ACTION,
		);
	}

	/**
	 * Busca en Iconify y devuelve los nombres, sin las colecciones animadas.
	 *
	 * @return void
	 */
	public function search(): void {
		$this->authorize();

		$query    = isset( $_REQUEST['query'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['query'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Comprobado en authorize().
		$prefixes = isset( $_REQUEST['prefixes'] ) ? (string) preg_replace( '/[^a-z0-9,-]/', '', (string) wp_unslash( $_REQUEST['prefixes'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Comprobado en authorize(); la expresión deja sólo nombres de colección.

		if ( mb_strlen( $query ) < 2 ) {
			wp_send_json_success( array( 'icons' => array() ) );
		}

		$params = array_filter(
			array(
				'query'    => $query,
				'limit'    => self::SEARCH_LIMIT,
				'prefixes' => $prefixes,
			)
		);

		$key   = 'forja_icons_search_' . md5( (string) wp_json_encode( $params ) );
		$icons = get_transient( $key );

		if ( ! is_array( $icons ) ) {
			$response = wp_remote_get(
				add_query_arg( array_map( 'rawurlencode', $params ), Iconify::api_url() . '/search' ),
				array( 'timeout' => 10 )
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				wp_send_json_error( array( 'message' => 'upstream' ), 502 );
			}

			$data  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$icons = is_array( $data['icons'] ?? null ) ? $data['icons'] : array();

			set_transient( $key, $icons, self::SEARCH_TTL );
		}

		$icons = array_values(
			array_filter(
				$icons,
				static fn ( $name ): bool => is_string( $name )
					&& Iconify::is_valid_name( $name )
					&& ! Iconify::is_animated( $name )
			)
		);

		wp_send_json_success( array( 'icons' => $icons ) );
	}

	/**
	 * Devuelve el SVG de una lista de iconos, separados por comas.
	 *
	 * @return void
	 */
	public function svg(): void {
		$this->authorize();

		$raw   = isset( $_REQUEST['icons'] ) ? (string) wp_unslash( $_REQUEST['icons'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Comprobado en authorize(); cada nombre se valida en Iconify::svgs().
		$names = array_slice( array_filter( explode( ',', $raw ) ), 0, self::MAX_ICONS );

		wp_send_json_success( array( 'icons' => (object) Iconify::svgs( $names ) ) );
	}

	/**
	 * Corta la petición si no trae el nonce o no la hace alguien que edita.
	 *
	 * @return void
	 */
	private function authorize(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
	}
}
