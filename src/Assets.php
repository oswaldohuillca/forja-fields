<?php
/**
 * Encolado de los assets compilados.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja;

defined( 'ABSPATH' ) || exit;

/**
 * Registra el CSS y el JS que Vite deja en `assets/build`.
 *
 * Las rutas se resuelven con `Paths` y no con `plugin_dir_url()`, porque el
 * paquete puede vivir dentro del `vendor/` de un tema.
 */
final class Assets {

	/**
	 * Resolución de rutas y URLs del paquete.
	 *
	 * @var Paths
	 */
	private Paths $paths;

	/**
	 * Constructor.
	 *
	 * @param Paths $paths Resolutor de rutas.
	 */
	public function __construct( Paths $paths ) {
		$this->paths = $paths;
	}

	/**
	 * Engancha el encolado a WordPress.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Encola los assets en las pantallas donde puede haber campos.
	 *
	 * @param string $hook_suffix Pantalla actual de administración.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		$screens = array( 'post.php', 'post-new.php' );

		if ( ! in_array( $hook_suffix, $screens, true ) ) {
			return;
		}

		$css = 'assets/build/css/forja-input.css';
		$js  = 'assets/build/js/forja-input.js';

		if ( ! is_readable( $this->paths->dir( $css ) ) ) {
			$this->warn_missing_build();

			return;
		}

		$this->enqueue_style( 'forja-input', $css );
		$this->enqueue_script( 'forja-input', $js );
	}

	/**
	 * Avisa de que faltan los assets compilados.
	 *
	 * Sin este aviso el fallo es mudo: los campos se pintan pero sin estilos,
	 * y cuesta relacionarlo con un `bun run build` que no se ejecutó o con un
	 * paquete publicado sin los artefactos dentro.
	 *
	 * @return void
	 */
	private function warn_missing_build(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_action(
			'admin_notices',
			static function (): void {
				printf(
					'<div class="notice notice-warning"><p><strong>Forja:</strong> %s</p></div>',
					esc_html__(
						'faltan los assets compilados, así que los campos se verán sin estilos. Ejecuta «bun run build» en el paquete.',
						'forja-fields'
					)
				);
			}
		);
	}

	/**
	 * Encola una hoja de estilos si existe en el directorio de build.
	 *
	 * @param string $handle        Identificador del recurso.
	 * @param string $relative_path Ruta relativa a la raíz del paquete.
	 * @return void
	 */
	private function enqueue_style( string $handle, string $relative_path ): void {
		$path = $this->paths->dir( $relative_path );

		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_style(
			$handle,
			$this->paths->url( $relative_path ),
			array(),
			$this->version( $path )
		);
	}

	/**
	 * Encola un script si existe en el directorio de build.
	 *
	 * @param string $handle        Identificador del recurso.
	 * @param string $relative_path Ruta relativa a la raíz del paquete.
	 * @return void
	 */
	private function enqueue_script( string $handle, string $relative_path ): void {
		$path = $this->paths->dir( $relative_path );

		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_script(
			$handle,
			$this->paths->url( $relative_path ),
			array(),
			$this->version( $path ),
			true
		);
	}

	/**
	 * Versión del recurso, basada en su fecha de modificación en desarrollo.
	 *
	 * @param string $path Ruta absoluta del recurso.
	 * @return string Cadena de versión para romper la caché.
	 */
	private function version( string $path ): string {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return (string) filemtime( $path );
		}

		return FORJA_VERSION;
	}
}
