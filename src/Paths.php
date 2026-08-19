<?php
/**
 * Resolución de rutas y URLs del paquete.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja;

defined( 'ABSPATH' ) || exit;

/**
 * Traduce la ubicación del paquete en el disco a una URL pública.
 *
 * Es la pieza que permite que Forja funcione igual dentro de `plugins/`, de
 * `themes/mi-tema/vendor/apros/forja/` o de `mu-plugins/`. No se puede usar
 * `plugin_dir_url()` porque asume que el archivo cuelga de `WP_PLUGIN_DIR`.
 */
final class Paths {

	/**
	 * Directorio raíz del paquete, normalizado y con barra final.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * URL base ya resuelta.
	 *
	 * @var string|null
	 */
	private ?string $url = null;

	/**
	 * Constructor.
	 *
	 * @param string $dir Directorio raíz del paquete.
	 */
	public function __construct( string $dir ) {
		$this->dir = trailingslashit( wp_normalize_path( $dir ) );
	}

	/**
	 * Ruta absoluta de un archivo del paquete.
	 *
	 * @param string $relative Ruta relativa a la raíz del paquete.
	 * @return string Ruta absoluta.
	 */
	public function dir( string $relative = '' ): string {
		return $this->dir . ltrim( $relative, '/' );
	}

	/**
	 * URL pública de un archivo del paquete.
	 *
	 * @param string $relative Ruta relativa a la raíz del paquete.
	 * @return string URL absoluta.
	 */
	public function url( string $relative = '' ): string {
		if ( null === $this->url ) {
			$this->url = $this->resolve_base_url();
		}

		return $this->url . ltrim( $relative, '/' );
	}

	/**
	 * Deduce la URL base a partir de la ruta en disco.
	 *
	 * Se comprueba primero `WP_CONTENT_DIR` porque cubre plugins, mu-plugins y
	 * temas de una vez, y después `ABSPATH` para instalaciones que sacan el
	 * contenido fuera de lo habitual.
	 *
	 * @return string URL base con barra final.
	 */
	private function resolve_base_url(): string {
		$candidates = array(
			wp_normalize_path( WP_CONTENT_DIR ) => content_url(),
			wp_normalize_path( ABSPATH )        => site_url(),
		);

		$url = '';

		foreach ( $candidates as $base_dir => $base_url ) {
			$base_dir = trailingslashit( $base_dir );

			if ( str_starts_with( $this->dir, $base_dir ) ) {
				$url = trailingslashit( $base_url ) . substr( $this->dir, strlen( $base_dir ) );
				break;
			}
		}

		/**
		 * Permite corregir la URL base cuando el paquete vive tras un enlace
		 * simbólico o fuera del árbol de WordPress.
		 *
		 * @param string $url URL base deducida; cadena vacía si no se pudo deducir.
		 * @param string $dir Directorio raíz del paquete.
		 */
		$url = (string) apply_filters( 'forja/base_url', $url, $this->dir );

		return '' === $url ? '' : trailingslashit( $url );
	}
}
