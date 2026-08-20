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
 * Aquí NO se usa el habitual `defined( 'ABSPATH' ) || exit;`, ni tampoco un
 * `return` temprano.
 *
 * Composer lleva el registro de los archivos de autoload en
 * `$GLOBALS['__composer_autoload_files']`, que es **global entre
 * autoloaders**: dos `vendor/` distintos con el mismo paquete comparten el
 * identificador, así que este archivo se incluye una única vez en toda la
 * petición. Si esa vez ocurriera antes de que WordPress esté cargado —una
 * herramienta de línea de comandos, otro paquete que arranque antes— y
 * saliéramos aquí, ya no habría segunda oportunidad: el paquete nunca
 * arrancaría.
 *
 * Por eso se registra siempre y sólo se difiere lo que necesita WordPress.
 */

if ( ! isset( $GLOBALS['forja_candidates'] ) ) {
	$GLOBALS['forja_candidates'] = array();
}

$GLOBALS['forja_candidates'][] = array(
	'version' => '0.1.0',
	'dir'     => __DIR__,
);

if ( ! function_exists( 'forja_boot_highest_version' ) ) {
	// La API son sólo definiciones de función: cargarla fuera de WordPress no
	// ejecuta nada y deja el paquete utilizable desde un script suelto.
	require_once __DIR__ . '/includes/api.php';


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

		if ( ! defined( 'FORJA_VERSION' ) ) {
			define( 'FORJA_VERSION', $winner['version'] );
			define( 'FORJA_DIR', $winner['dir'] );
		}

		forja( $winner['dir'] )->boot();
	}

	/*
	 * Se engancha en `after_setup_theme`, que es el primer momento en el que ya
	 * se han cargado tanto los plugins como el `functions.php` del tema. Así se
	 * ven todas las copias antes de elegir.
	 *
	 * Sin WordPress delante no hay nada que enganchar, pero las clases y la API
	 * quedan igualmente disponibles.
	 */
	if ( function_exists( 'add_action' ) ) {
		add_action( 'after_setup_theme', 'forja_boot_highest_version', 0 );
	}
}
