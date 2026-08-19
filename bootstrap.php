<?php
/**
 * Punto de entrada del paquete.
 *
 * Forja se puede consumir de dos maneras:
 *
 * 1. Como paquete de Composer dentro de un tema o de otro plugin:
 *        require_once __DIR__ . '/vendor/autoload.php';
 *    El autoload de Composer incluye este archivo automáticamente.
 *
 * 2. Como plugin de WordPress, activándolo desde el escritorio.
 *
 * Como las dos vías pueden coexistir en la misma instalación (el tema trae su
 * copia y además hay un plugin activo), cada copia se limita a anunciarse aquí
 * con su versión y su ruta. Cuando todo está cargado gana la versión más alta y
 * sólo esa arranca. Es el mismo mecanismo que usa CMB2, y evita el choque de
 * clases duplicadas que Composer no puede resolver cuando cada extensión tiene
 * su propio directorio `vendor`.
 *
 * @package Forja
 */

declare( strict_types = 1 );

/*
 * Aquí NO se usa el habitual `defined( 'ABSPATH' ) || exit;`.
 *
 * Composer incluye este archivo desde `vendor/autoload.php`, así que lo carga
 * cualquier herramienta de línea de comandos del proyecto —phpcs, phpunit, un
 * script propio— fuera de WordPress. Un `exit` ahí las mata en silencio, sin
 * mensaje y con código 0, que es un fallo muy desagradable de diagnosticar.
 *
 * Con `return` el archivo tampoco hace nada fuera de WordPress, pero deja
 * seguir al proceso.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! isset( $GLOBALS['forja_candidates'] ) ) {
	$GLOBALS['forja_candidates'] = array();
}

$GLOBALS['forja_candidates'][] = array(
	'version' => '0.1.0',
	'dir'     => __DIR__,
);

if ( ! function_exists( 'forja_boot_highest_version' ) ) {

	/**
	 * Arranca la copia de Forja con la versión más alta.
	 *
	 * @return void
	 */
	function forja_boot_highest_version(): void {
		$candidates = $GLOBALS['forja_candidates'] ?? array();

		if ( array() === $candidates ) {
			return;
		}

		usort(
			$candidates,
			static fn ( array $a, array $b ): int => version_compare( $b['version'], $a['version'] )
		);

		$winner = $candidates[0];

		define( 'FORJA_VERSION', $winner['version'] );
		define( 'FORJA_DIR', $winner['dir'] );

		require_once $winner['dir'] . '/includes/api.php';

		forja( $winner['dir'] )->boot();
	}

	/**
	 * Se engancha en `after_setup_theme`, que es el primer momento en el que ya
	 * se han cargado tanto los plugins como el `functions.php` del tema. Así se
	 * ven todas las copias antes de elegir.
	 */
	add_action( 'after_setup_theme', 'forja_boot_highest_version', 0 );
}
