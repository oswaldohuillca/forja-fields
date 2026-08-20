<?php
/**
 * Arranque de la suite de tests.
 *
 * Son tests de integración, no unitarios: cargan un WordPress de verdad. El
 * código de Forja se apoya en `get_post_type()`, `sanitize_email()`,
 * `wp_get_attachment_url()` y una docena más de funciones del núcleo. Simular
 * todo eso costaría más que ejecutarlo, y probaría los dobles en lugar del
 * comportamiento real.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Registry\FieldRegistry;

/*
 * WordPress se carga aquí, antes que nada.
 *
 * `bootstrap.php` de Forja se incluye desde el autoload de Composer, que Pest
 * ya ha ejecutado, y se salió sin hacer nada porque `ABSPATH` no existía. Al
 * cargar WordPress se vuelve a incluir —esta vez desde el `vendor/` del tema—
 * y es entonces cuando el paquete se registra y arranca.
 */
if ( ! defined( 'ABSPATH' ) ) {
	$forja_wp_load = getenv( 'FORJA_WP_LOAD' );

	if ( ! is_string( $forja_wp_load ) || ! is_readable( $forja_wp_load ) ) {
		$forja_wp_load = '/var/www/html/wp-load.php';
	}

	if ( ! is_readable( $forja_wp_load ) ) {
		exit( "No se encuentra wp-load.php. Define FORJA_WP_LOAD con su ruta.\n" );
	}

	require_once $forja_wp_load;
}

/**
 * Construye un campo suelto, sin necesidad de declarar una caja.
 *
 * @param array<string, mixed> $args Configuración del campo.
 * @return \Forja\Fields\Field Campo instanciado.
 */
function forja_test_field( array $args ): \Forja\Fields\Field {
	static $registry = null;

	if ( null === $registry ) {
		$registry = new FieldRegistry();
	}

	return $registry->make( array_merge( array( 'name' => 'campo' ), $args ) );
}

/**
 * Pinta un campo y devuelve su markup.
 *
 * @param \Forja\Fields\Field $field Campo a pintar.
 * @param mixed               $value Valor actual.
 * @return string Markup generado.
 */
function forja_test_render( \Forja\Fields\Field $field, mixed $value = '' ): string {
	$renderer = new \Forja\Render\Renderer();

	ob_start();
	$renderer->render_field_wrap( $field, $value, 'forja' );

	return (string) ob_get_clean();
}
