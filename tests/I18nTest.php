<?php
/**
 * Estado de la internacionalización.
 *
 * @package Forja
 */

declare( strict_types = 1 );

/**
 * Raíz del paquete.
 *
 * @return string Ruta absoluta.
 */
function forja_test_root(): string {
	return dirname( __DIR__ );
}

it( 'extrae las cadenas sin encontrar ningún problema', function () {
	$output = array();
	$status = 0;

	exec( 'php ' . escapeshellarg( forja_test_root() . '/tools/make-pot.php' ) . ' 2>&1', $output, $status );

	// El extractor sale con 1 si alguna llamada usa un dominio que no es el
	// nuestro, o si el texto no es una cadena literal. Ambas cosas significan
	// lo mismo: esa cadena no se va a traducir nunca.
	expect( $status )->toBe( 0, implode( "\n", $output ) );
} );

it( 'tiene la plantilla al día respecto al código', function () {
	$pot = forja_test_root() . '/languages/forja-fields.pot';

	expect( is_readable( $pot ) )->toBeTrue();

	$before = (string) file_get_contents( $pot );

	exec( 'php ' . escapeshellarg( forja_test_root() . '/tools/make-pot.php' ) . ' 2>&1' );

	// Si esto falla, el archivo ya está regenerado: basta con confirmarlo en el
	// commit. Evita que la plantilla se quede atrás sin que nadie lo note.
	expect( (string) file_get_contents( $pot ) )
		->toBe( $before, 'La plantilla estaba desactualizada; se ha regenerado, revísala y añádela al commit.' );
} );

it( 'declara el dominio de texto que carga el plugin', function () {
	$api = (string) file_get_contents( forja_test_root() . '/src/Plugin.php' );

	expect( $api )->toContain( "load_textdomain( 'forja-fields'" );
} );
