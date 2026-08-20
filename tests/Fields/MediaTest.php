<?php
/**
 * Campos de medios: validación del adjunto y formato de retorno.
 *
 * @package Forja
 */

declare( strict_types = 1 );

/**
 * Crea un adjunto real para la prueba y lo borra al terminar.
 *
 * @param string $mime      Tipo MIME.
 * @param string $extension Extensión del archivo.
 * @return int Identificador del adjunto.
 */
function forja_test_attachment( string $mime = 'image/png', string $extension = 'png' ): int {
	$uploads = wp_upload_dir();
	$path    = $uploads['path'] . '/forja-test-' . uniqid() . '.' . $extension;

	if ( 'png' === $extension ) {
		$image = imagecreatetruecolor( 60, 40 );
		imagepng( $image, $path );
		imagedestroy( $image );
	} else {
		file_put_contents( $path, 'contenido' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	return (int) wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => 'Adjunto de prueba',
			'post_status'    => 'inherit',
		),
		$path
	);
}

describe( 'validación del adjunto', function () {
	it( 'acepta una imagen real', function () {
		$id    = forja_test_attachment();
		$field = forja_test_field( array( 'type' => 'image' ) );

		expect( $field->sanitize( (string) $id ) )->toBe( $id );

		wp_delete_attachment( $id, true );
	} );

	it( 'rechaza un identificador que no existe', function () {
		$field = forja_test_field( array( 'type' => 'image' ) );

		expect( $field->sanitize( '999999' ) )->toBe( '' )
			->and( $field->sanitize( '0' ) )->toBe( '' )
			->and( $field->sanitize( 'abc' ) )->toBe( '' );
	} );

	it( 'rechaza un adjunto que no es imagen en un campo de imagen', function () {
		$id    = forja_test_attachment( 'text/plain', 'txt' );
		$field = forja_test_field( array( 'type' => 'image' ) );

		expect( $field->sanitize( (string) $id ) )->toBe( '' );

		wp_delete_attachment( $id, true );
	} );

	it( 'rechaza un adjunto fuera de los tipos permitidos', function () {
		$id    = forja_test_attachment( 'text/plain', 'txt' );
		$field = forja_test_field( array( 'type' => 'file', 'mime_types' => 'application/pdf' ) );

		expect( $field->sanitize( (string) $id ) )->toBe( '' );

		wp_delete_attachment( $id, true );
	} );

	it( 'acepta los tipos permitidos escritos como extensión', function () {
		$id    = forja_test_attachment( 'text/plain', 'txt' );
		$field = forja_test_field( array( 'type' => 'file', 'mime_types' => 'txt, pdf' ) );

		expect( $field->sanitize( (string) $id ) )->toBe( $id );

		wp_delete_attachment( $id, true );
	} );
} );

describe( 'formato de retorno', function () {
	it( 'devuelve un entero por defecto', function () {
		$id    = forja_test_attachment();
		$field = forja_test_field( array( 'type' => 'image' ) );

		expect( $field->format_value( (string) $id ) )->toBe( $id );

		wp_delete_attachment( $id, true );
	} );

	it( 'devuelve cero cuando no hay nada', function () {
		$field = forja_test_field( array( 'type' => 'image' ) );

		expect( $field->format_value( '' ) )->toBe( 0 )
			->and( $field->format_value( null ) )->toBe( 0 );
	} );

	it( 'devuelve la URL como cadena', function () {
		$id    = forja_test_attachment();
		$field = forja_test_field( array( 'type' => 'image', 'return_format' => 'url' ) );

		expect( $field->format_value( (string) $id ) )->toBeString()->toContain( '.png' );

		wp_delete_attachment( $id, true );
	} );

	it( 'devuelve cadena vacía cuando se pide URL y no hay nada', function () {
		$field = forja_test_field( array( 'type' => 'image', 'return_format' => 'url' ) );

		expect( $field->format_value( '' ) )->toBe( '' );
	} );

	it( 'devuelve el array con los datos del adjunto', function () {
		$id    = forja_test_attachment();
		$field = forja_test_field( array( 'type' => 'image', 'return_format' => 'array' ) );

		expect( $field->format_value( (string) $id ) )
			->toBeArray()
			->toHaveKeys( array( 'id', 'url', 'title', 'filename', 'mime_type', 'alt', 'width', 'height', 'sizes' ) );

		wp_delete_attachment( $id, true );
	} );

	it( 'devuelve null cuando se pide array y no hay nada', function () {
		$field = forja_test_field( array( 'type' => 'image', 'return_format' => 'array' ) );

		expect( $field->format_value( '' ) )->toBeNull();
	} );
} );
