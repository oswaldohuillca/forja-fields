<?php
/**
 * Galería de imágenes.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Validation\Validator;

beforeEach( function () {
	$this->field = forja_test_field( array( 'type' => 'gallery', 'label' => 'Galería' ) );
} );

it( 'guarda una lista de identificadores', function () {
	$a = forja_test_attachment();
	$b = forja_test_attachment();

	expect( $this->field->sanitize( array( (string) $a, (string) $b ) ) )->toBe( array( $a, $b ) );

	wp_delete_attachment( $a, true );
	wp_delete_attachment( $b, true );
} );

it( 'descarta lo que no sea una imagen de la mediateca', function () {
	$imagen = forja_test_attachment();
	$texto  = forja_test_attachment( 'text/plain', 'txt' );

	expect( $this->field->sanitize( array( (string) $imagen, (string) $texto, '999999', 'abc' ) ) )
		->toBe( array( $imagen ) );

	wp_delete_attachment( $imagen, true );
	wp_delete_attachment( $texto, true );
} );

it( 'descarta los duplicados', function () {
	$id = forja_test_attachment();

	expect( $this->field->sanitize( array( (string) $id, (string) $id ) ) )->toBe( array( $id ) );

	wp_delete_attachment( $id, true );
} );

it( 'devuelve una lista vacía cuando no hay nada', function () {
	expect( $this->field->sanitize( '' ) )->toBe( array() )
		->and( $this->field->format_value( '' ) )->toBe( array() );
} );

it( 'omite al leer los adjuntos que ya no existen', function () {
	// Borrar una imagen deja su identificador huérfano en la lista.
	$id = forja_test_attachment();
	wp_delete_attachment( $id, true );

	expect( $this->field->format_value( array( $id ) ) )->toBe( array() );
} );

describe( 'límites', function () {
	it( 'rechaza menos imágenes de las exigidas', function () {
		$field = forja_test_field(
			array( 'type' => 'gallery', 'label' => 'Galería', 'min' => 2 )
		);

		expect( ( new Validator() )->validate( $field, array( 1 ) ) )->toContain( 'Galería' );
	} );

	it( 'rechaza más imágenes de las permitidas', function () {
		$field = forja_test_field(
			array( 'type' => 'gallery', 'label' => 'Galería', 'max' => 2 )
		);

		expect( ( new Validator() )->validate( $field, array( 1, 2, 3 ) ) )->not->toBe( '' );
	} );

	it( 'acepta una cantidad dentro de los límites', function () {
		$field = forja_test_field(
			array( 'type' => 'gallery', 'label' => 'Galería', 'min' => 1, 'max' => 3 )
		);

		expect( ( new Validator() )->validate( $field, array( 1, 2 ) ) )->toBe( '' );
	} );
} );

it( 'pinta la rejilla con una plantilla para las imágenes nuevas', function () {
	$id   = forja_test_attachment();
	$html = forja_test_render( $this->field, array( $id ) );

	expect( $html )->toContain( 'class="acf-gallery-attachments"' )
		->and( $html )->toContain( 'acf-gallery-template' )
		->and( $html )->toContain( 'name="forja[campo][]"' )
		// Una miniatura real más la plantilla. Se cuenta con la comilla final
		// para no contar también el contenedor `acf-gallery-attachments`.
		->and( substr_count( $html, 'class="acf-gallery-attachment"' ) )->toBe( 2 );

	wp_delete_attachment( $id, true );
} );
