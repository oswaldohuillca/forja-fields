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
 * El escritorio no consulta la API desde el navegador: le pide los iconos a
 * este WordPress (`Ajax\Iconify`), que los trae por lotes y los cachea. Ver
 * `svgs()`.
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
	 * Colecciones animadas, que el selector no ofrece.
	 *
	 * Sus trazos empiezan ocultos y aparecen con `<animate>`, que `sanitize()`
	 * quita al incrustar el icono. En la parte pública se verían como una raya
	 * suelta.
	 */
	public const ANIMATED = array( 'line-md', 'svg-spinners' );

	/**
	 * Atributos de SVG que distinguen mayúsculas.
	 *
	 * `wp_kses()` los pasa a minúsculas. Incrustado en HTML el navegador los
	 * corrige por su cuenta, pero no en un contexto XML: un feed, un sitemap o
	 * un `<img>` con el SVG en una dirección `data:`, que es como pinta el
	 * selector las miniaturas. Ahí `gradientunits` no significa nada.
	 */
	private const CASED_ATTRIBUTES = array(
		'viewBox',
		'preserveAspectRatio',
		'gradientUnits',
		'gradientTransform',
		'clipPathUnits',
		'maskUnits',
		'maskContentUnits',
		'patternUnits',
		'patternContentUnits',
		'patternTransform',
	);

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
	 * Si el icono pertenece a una colección animada.
	 *
	 * @param string $name Nombre en formato `coleccion:icono`.
	 * @return bool True si es animado.
	 */
	public static function is_animated( string $name ): bool {
		return in_array( strtok( $name, ':' ), self::ANIMATED, true );
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

		$key    = self::cache_key( $name );
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
	 * Devuelve el SVG de varios iconos, trayendo de una vez los que falten.
	 *
	 * Es lo que usa el selector del escritorio. Pedir cada miniatura como una
	 * imagen aparte eran 96 peticiones por página de resultados, y unas cuantas
	 * búsquedas bastaban para que Cloudflare, delante de la API pública,
	 * bloqueara la IP un rato (HTTP 429, «error code: 1015»).
	 *
	 * Aquí los que falten en la caché se agrupan por colección y se piden con
	 * `/{coleccion}.json?icons=a,b,c`, todas las colecciones en paralelo. La API
	 * no admite varias colecciones en una petición, y una página de resultados
	 * mezcla unas cuarenta. Cada icono se guarda con la misma clave que
	 * `svg()`, así que el que elige el editor ya está en caché para la parte
	 * pública.
	 *
	 * @param array<int, string> $names Nombres en formato `coleccion:icono`.
	 * @return array<string, string> SVG saneado por nombre. Faltan los que no
	 *                               existen y los que no se pudieron traer.
	 */
	public static function svgs( array $names ): array {
		$found   = array();
		$missing = array();

		foreach ( array_unique( $names ) as $name ) {
			if ( ! is_string( $name ) || ! self::is_valid_name( $name ) ) {
				continue;
			}

			$cached = get_transient( self::cache_key( $name ) );

			if ( is_string( $cached ) ) {
				$found[ $name ] = $cached;
				continue;
			}

			[ $collection, $icon ]    = explode( ':', $name, 2 );
			$missing[ $collection ][] = $icon;
		}

		if ( array() !== $missing ) {
			$found += self::fetch_collections( $missing );
		}

		// Un nombre que no existe se cachea como cadena vacía, para no volver a
		// preguntarlo; al navegador no le sirve de nada.
		return array_filter( $found, static fn ( string $svg ): bool => '' !== $svg );
	}

	/**
	 * Construye el SVG de un icono a partir de la respuesta JSON de su colección.
	 *
	 * Las dimensiones y el desplazamiento del icono sustituyen a los de la
	 * colección, y estos a los de Iconify por defecto (16 × 16, en el origen).
	 * Un alias apunta a otro icono con `parent`, y puede encadenarse.
	 *
	 * Los iconos con `hFlip`, `vFlip` o `rotate` no se construyen aquí: son
	 * raros, y un SVG mal girado es peor que pedirlo hecho. Para esos se
	 * devuelve null y quien llama lo pide en `.svg`, que la API ya transforma.
	 *
	 * @param array<string, mixed> $data Respuesta de `/{coleccion}.json`.
	 * @param string               $icon Nombre del icono, sin la colección.
	 * @return string|null SVG saneado, cadena vacía si no existe, o null si
	 *                     lleva transformaciones.
	 */
	public static function from_collection( array $data, string $icon ): ?string {
		$icons   = is_array( $data['icons'] ?? null ) ? $data['icons'] : array();
		$aliases = is_array( $data['aliases'] ?? null ) ? $data['aliases'] : array();
		$props   = array();
		$name    = $icon;

		// Se recorre la cadena de alias hasta un icono. Las propiedades del
		// alias más cercano mandan, así que no se pisan al subir. Diez saltos
		// bastan de sobra y cortan un ciclo si la respuesta lo trajera.
		for ( $depth = 0; $depth < 10 && ! isset( $icons[ $name ] ); $depth++ ) {
			$alias = $aliases[ $name ] ?? null;

			if ( ! is_array( $alias ) || ! is_string( $alias['parent'] ?? null ) ) {
				return '';
			}

			$props += $alias;
			$name   = $alias['parent'];
		}

		if ( ! is_array( $icons[ $name ] ?? null ) ) {
			return '';
		}

		$props += $icons[ $name ];

		foreach ( array( 'hFlip', 'vFlip', 'rotate' ) as $transform ) {
			if ( ! empty( $props[ $transform ] ) ) {
				return null;
			}
		}

		$dimension = static fn ( string $key, int $fallback ): float => (float) ( $props[ $key ] ?? $data[ $key ] ?? $fallback );

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="%s %s %s %s">%s</svg>',
			self::number( $dimension( 'left', 0 ) ),
			self::number( $dimension( 'top', 0 ) ),
			self::number( $dimension( 'width', 16 ) ),
			self::number( $dimension( 'height', 16 ) ),
			(string) ( $props['body'] ?? '' )
		);

		return self::sanitize( $svg );
	}

	/**
	 * Trae de la API los iconos que faltan, una petición por colección.
	 *
	 * @param array<string, array<int, string>> $missing Iconos por colección.
	 * @return array<string, string> SVG por nombre, también los vacíos.
	 */
	private static function fetch_collections( array $missing ): array {
		$requests = array();

		foreach ( $missing as $collection => $icons ) {
			$requests[ $collection ] = array(
				'url'  => sprintf(
					'%s/%s.json?icons=%s',
					self::api_url(),
					rawurlencode( $collection ),
					implode( ',', array_map( 'rawurlencode', $icons ) )
				),
				'type' => 'GET',
			);
		}

		$responses = \WpOrg\Requests\Requests::request_multiple( $requests, array( 'timeout' => 10 ) );
		$found     = array();

		foreach ( $missing as $collection => $icons ) {
			$response = $responses[ $collection ] ?? null;

			// Un 429 o un corte no se cachean: se reintentará la próxima vez.
			if ( ! $response instanceof \WpOrg\Requests\Response || 200 !== $response->status_code ) {
				continue;
			}

			$data = json_decode( $response->body, true );
			$data = is_array( $data ) ? $data : array();

			foreach ( $icons as $icon ) {
				$name = $collection . ':' . $icon;
				$svg  = self::from_collection( $data, $icon );

				if ( null === $svg ) {
					// Lleva transformaciones: la API lo da hecho. `svg()` lo
					// cachea por su cuenta.
					$found[ $name ] = self::svg( $name );
					continue;
				}

				set_transient( self::cache_key( $name ), $svg, '' === $svg ? DAY_IN_SECONDS : self::CACHE_TTL );

				$found[ $name ] = $svg;
			}
		}

		return $found;
	}

	/**
	 * Clave de caché de un icono, compartida por `svg()` y `svgs()`.
	 *
	 * @param string $name Nombre en formato `coleccion:icono`.
	 * @return string Nombre del transitorio.
	 */
	private static function cache_key( string $name ): string {
		return 'forja_icon_' . md5( $name );
	}

	/**
	 * Escribe una dimensión sin decimales sobrantes.
	 *
	 * @param float $value Número.
	 * @return string `24` en vez de `24.0`, pero `0.5` tal cual.
	 */
	private static function number( float $value ): string {
		return rtrim( rtrim( sprintf( '%.4F', $value ), '0' ), '.' );
	}

	/**
	 * Limpia el SVG que devuelve la API.
	 *
	 * Viene de un servicio externo y acaba dentro de la página, así que se
	 * limita a las etiquetas y atributos que un icono necesita. Nada de
	 * `script`, `foreignObject`, enlaces, animaciones ni manejadores de
	 * eventos.
	 *
	 * @param string $svg Contenido descargado.
	 * @return string SVG saneado, o cadena vacía si no parece un SVG.
	 */
	public static function sanitize( string $svg ): string {
		$svg = trim( $svg );

		if ( ! str_starts_with( $svg, '<svg' ) ) {
			return '';
		}

		$shared = array(
			'id'                => true,
			'class'             => true,
			'color'             => true,
			'fill'              => true,
			'fill-rule'         => true,
			'fill-opacity'      => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'stroke-linecap'    => true,
			'stroke-linejoin'   => true,
			'stroke-miterlimit' => true,
			'stroke-dasharray'  => true,
			'stroke-dashoffset' => true,
			'stroke-opacity'    => true,
			'opacity'           => true,
			'transform'         => true,
			'clip-rule'         => true,
			'clip-path'         => true,
			'mask'              => true,
		);

		$allowed = array(
			'svg'            => $shared + array(
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'aria-hidden' => true,
				'role'        => true,
				'focusable'   => true,
			),
			'g'              => $shared,
			'path'           => $shared + array( 'd' => true ),
			'circle'         => $shared + array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
			'ellipse'        => $shared + array(
				'cx' => true,
				'cy' => true,
				'rx' => true,
				'ry' => true,
			),
			'rect'           => $shared + array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
			),
			'line'           => $shared + array(
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			),
			'polyline'       => $shared + array( 'points' => true ),
			'polygon'        => $shared + array( 'points' => true ),
			'defs'           => $shared,
			'title'          => array(),
			// Gradientes, máscaras y recortes: los usan los iconos a color.
			'mask'           => $shared + array(
				'x'                => true,
				'y'                => true,
				'width'            => true,
				'height'           => true,
				'maskunits'        => true,
				'maskcontentunits' => true,
			),
			'clippath'       => $shared + array( 'clippathunits' => true ),
			'lineargradient' => $shared + array(
				'x1'                => true,
				'y1'                => true,
				'x2'                => true,
				'y2'                => true,
				'gradientunits'     => true,
				'gradienttransform' => true,
			),
			'radialgradient' => $shared + array(
				'cx'                => true,
				'cy'                => true,
				'r'                 => true,
				'fx'                => true,
				'fy'                => true,
				'gradientunits'     => true,
				'gradienttransform' => true,
			),
			'stop'           => array(
				'offset'       => true,
				'stop-color'   => true,
				'stop-opacity' => true,
			),
		);

		$clean = wp_kses( $svg, $allowed );

		// `wp_kses()` deja las etiquetas como venían, pero pasa los atributos a
		// minúsculas. Ver CASED_ATTRIBUTES.
		foreach ( self::CASED_ATTRIBUTES as $attribute ) {
			$clean = (string) preg_replace( '/\b' . strtolower( $attribute ) . '=/', $attribute . '=', $clean );
		}

		return $clean;
	}
}
