<?php
/**
 * Acceso a los iconos de Iconify.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Resuelve nombres de icono a SVG.
 *
 * El escritorio consulta la API de Iconify directamente desde el navegador
 * —su CORS lo permite y es lo que hace icones.js.org—, así que no hace falta
 * ningún endpoint propio para buscar.
 *
 * En la parte pública es distinto: usar el componente web de Iconify añadiría
 * una dependencia de JavaScript para el visitante y una petición por icono en
 * cada carga. Aquí se trae el SVG una sola vez, se guarda en un transitorio y
 * se incrusta en línea: sin JavaScript, sin salto de maquetado y indexable.
 */
final class Iconify {

	/**
	 * Cuánto se conserva un SVG ya descargado.
	 *
	 * Los iconos de una versión publicada no cambian —la propia API los sirve
	 * como `immutable`—, así que se guardan durante mucho tiempo.
	 */
	private const CACHE_TTL = MONTH_IN_SECONDS;

	/**
	 * Comprueba que el nombre tenga la forma `coleccion:icono`.
	 *
	 * Importa porque el nombre acaba formando parte de una URL. Se aceptan sólo
	 * minúsculas, dígitos y guiones a cada lado de los dos puntos.
	 *
	 * @param string $name Nombre a comprobar.
	 * @return bool True si el nombre es válido.
	 */
	public static function is_valid_name( string $name ): bool {
		return 1 === preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?:[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $name );
	}

	/**
	 * Dirección base de la API.
	 *
	 * @return string URL base, sin barra final.
	 */
	public static function api_url(): string {
		/**
		 * Permite apuntar a una instancia propia de la API de Iconify.
		 *
		 * Iconify es autoalojable, así que una instalación con requisitos de
		 * privacidad o sin salida a internet puede servir los iconos desde su
		 * propia infraestructura sin tocar el código.
		 *
		 * @param string $url Dirección base de la API.
		 */
		return untrailingslashit( (string) apply_filters( 'forja/iconify_api', 'https://api.iconify.design' ) );
	}

	/**
	 * Devuelve el SVG de un icono, listo para incrustar.
	 *
	 * @param string $name Nombre en formato `coleccion:icono`.
	 * @return string SVG saneado, o cadena vacía si no se pudo obtener.
	 */
	public static function svg( string $name ): string {
		if ( ! self::is_valid_name( $name ) ) {
			return '';
		}

		$key    = 'forja_icon_' . md5( $name );
		$cached = get_transient( $key );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		[ $collection, $icon ] = explode( ':', $name, 2 );

		$response = wp_remote_get(
			sprintf( '%s/%s/%s.svg', self::api_url(), rawurlencode( $collection ), rawurlencode( $icon ) ),
			array( 'timeout' => 5 )
		);

		$svg = '';

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$svg = self::sanitize( (string) wp_remote_retrieve_body( $response ) );
		}

		/*
		 * El fallo también se cachea, aunque poco tiempo: si la API está caída
		 * o el nombre no existe, no tiene sentido reintentarlo en cada carga de
		 * cada página.
		 */
		set_transient( $key, $svg, '' === $svg ? HOUR_IN_SECONDS : self::CACHE_TTL );

		return $svg;
	}

	/**
	 * Limpia el SVG que devuelve la API.
	 *
	 * Viene de un servicio externo y acaba dentro de la página, así que se
	 * limita a las etiquetas y atributos que un icono necesita. Nada de
	 * `script`, `foreignObject` ni manejadores de eventos.
	 *
	 * @param string $svg Contenido descargado.
	 * @return string SVG saneado, o cadena vacía si no parece un SVG.
	 */
	private static function sanitize( string $svg ): string {
		$svg = trim( $svg );

		if ( ! str_starts_with( $svg, '<svg' ) ) {
			return '';
		}

		$shared = array(
			'fill'             => true,
			'fill-rule'        => true,
			'fill-opacity'     => true,
			'stroke'           => true,
			'stroke-width'     => true,
			'stroke-linecap'   => true,
			'stroke-linejoin'  => true,
			'stroke-dasharray' => true,
			'opacity'          => true,
			'transform'        => true,
			'clip-rule'        => true,
			'class'            => true,
		);

		$allowed = array(
			'svg'      => $shared + array(
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'aria-hidden' => true,
				'role'        => true,
				'focusable'   => true,
			),
			'g'        => $shared,
			'path'     => $shared + array( 'd' => true ),
			'circle'   => $shared + array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
			'ellipse'  => $shared + array(
				'cx' => true,
				'cy' => true,
				'rx' => true,
				'ry' => true,
			),
			'rect'     => $shared + array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
			),
			'line'     => $shared + array(
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			),
			'polyline' => $shared + array( 'points' => true ),
			'polygon'  => $shared + array( 'points' => true ),
			'defs'     => $shared,
			'title'    => array(),
		);

		$clean = wp_kses( $svg, $allowed );

		/*
		 * `wp_kses()` pasa los atributos a minúsculas, y `viewBox` es el único
		 * de la lista que distingue mayúsculas. Al incrustarlo en HTML el
		 * navegador lo corrige por su cuenta, pero no si el SVG acaba en un
		 * contexto XML —un feed, un sitemap—, así que se restaura.
		 */
		return (string) preg_replace( '/\bviewbox=/i', 'viewBox=', $clean );
	}
}
