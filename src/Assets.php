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
	 * Forja sólo encola si encuentra su propio build. Cuando el tema importa
	 * los fuentes en su bundle —que es el modo recomendado— no hay build en el
	 * paquete y aquí no se hace nada, así que no se duplica el CSS.
	 *
	 * @param string $hook_suffix Pantalla actual de administración.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! self::is_field_screen( $hook_suffix ) ) {
			return;
		}

		/**
		 * Permite desactivar el encolado propio de Forja.
		 *
		 * Devuelve false desde el tema si compilas los fuentes de Forja dentro
		 * de tu propio bundle. Sirve para desactivarlo de forma explícita
		 * aunque el paquete traiga artefactos compilados.
		 *
		 * @param bool $enqueue Si Forja debe encolar sus propios assets.
		 */
		if ( ! apply_filters( 'forja/enqueue_assets', true ) ) {
			return;
		}

		$css = 'assets/build/css/forja-input.css';
		$js  = 'assets/build/js/forja-input.js';

		if ( ! is_readable( $this->paths->dir( $css ) ) ) {
			return;
		}

		$this->enqueue_style( 'forja-input', $css );
		$this->enqueue_script( 'forja-input', $js );
	}

	/**
	 * Indica si en esta pantalla del escritorio pueden aparecer campos.
	 *
	 * Es pública y estática porque no la usa sólo Forja: un tema que compile
	 * los fuentes en su propio bundle necesita saber exactamente lo mismo, y
	 * duplicar la lista en cada proyecto la condena a desincronizarse.
	 *
	 * @param string $hook_suffix Pantalla actual de administración.
	 * @return bool True si hay que cargar los assets.
	 */
	public static function is_field_screen( string $hook_suffix ): bool {
		$screens = array(
			// Entradas y CPTs.
			'post.php',
			'post-new.php',
			// Taxonomías: listado con el formulario de alta, y edición.
			'edit-tags.php',
			'term.php',
			// Perfiles.
			'profile.php',
			'user-edit.php',
			'user-new.php',
		);

		/**
		 * Filtra las pantallas donde se cargan los assets.
		 *
		 * Las páginas de opciones tienen un sufijo que depende de su slug, así
		 * que se detectan aparte.
		 *
		 * @param array<int, string> $screens Sufijos de pantalla.
		 */
		$screens = (array) apply_filters( 'forja/asset_screens', $screens );

		// Las páginas de opciones que registra Forja llevan este prefijo.
		if ( str_contains( $hook_suffix, '_page_forja-' ) ) {
			return true;
		}

		return in_array( $hook_suffix, $screens, true );
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
