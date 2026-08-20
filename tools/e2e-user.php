<?php
/**
 * Prepara el usuario con el que entran los tests de navegador.
 *
 * Playwright necesita una sesión del escritorio, y no hay forma de adivinar la
 * contraseña de las cuentas del sitio. Este script crea —o restablece— una
 * cuenta dedicada con credenciales conocidas.
 *
 * Uso, desde la raíz de WordPress:
 *
 *     php wp-content/packages/forja/tools/e2e-user.php
 *
 * Es una herramienta de desarrollo: crea un administrador con una contraseña
 * fija. No la ejecutes en producción.
 *
 * @package Forja
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	exit( "Sólo por línea de comandos.\n" );
}

$forja_wp_load = getenv( 'FORJA_WP_LOAD' );

if ( ! is_string( $forja_wp_load ) || ! is_readable( $forja_wp_load ) ) {
	$forja_wp_load = '/var/www/html/wp-load.php';
}

if ( ! is_readable( $forja_wp_load ) ) {
	exit( "No se encuentra wp-load.php. Define FORJA_WP_LOAD con su ruta.\n" );
}

require_once $forja_wp_load;

$forja_login = getenv( 'FORJA_E2E_USER' ) ?: 'forja_e2e';
$forja_pass  = getenv( 'FORJA_E2E_PASS' ) ?: 'forja-e2e-pass';

$forja_user = get_user_by( 'login', $forja_login );

if ( $forja_user instanceof WP_User ) {
	wp_set_password( $forja_pass, $forja_user->ID );

	printf( "Usuario «%s» ya existía; contraseña restablecida.\n", $forja_login );
} else {
	$forja_id = wp_insert_user(
		array(
			'user_login' => $forja_login,
			'user_pass'  => $forja_pass,
			'role'       => 'administrator',
		)
	);

	if ( is_wp_error( $forja_id ) ) {
		exit( 'No se pudo crear el usuario: ' . $forja_id->get_error_message() . "\n" );
	}

	printf( "Usuario «%s» creado (id %d).\n", $forja_login, $forja_id );
}

// Los tests editan una categoría, que es una pantalla clásica y por tanto más
// estable que el editor de bloques. Cualquier instalación trae al menos una.
$forja_terms = get_terms(
	array(
		'taxonomy'   => 'category',
		'hide_empty' => false,
		'number'     => 1,
	)
);

if ( is_array( $forja_terms ) && array() !== $forja_terms ) {
	printf( "Categoría para las pruebas: FORJA_E2E_TERM=%d\n", $forja_terms[0]->term_id );
}
