<?php
/**
 * Saneado de los valores que llegan del formulario.
 *
 * @package Forja
 */

declare( strict_types = 1 );

it( 'recorta y limpia el texto', function () {
	$field = forja_test_field( array( 'type' => 'text' ) );

	expect( $field->sanitize( '  Hola <script>alert(1)</script> mundo  ' ) )
		->toBe( 'Hola mundo' );
} );

it( 'conserva los saltos de línea en el área de texto', function () {
	$field = forja_test_field( array( 'type' => 'textarea' ) );

	expect( $field->sanitize( "Uno\nDos" ) )->toBe( "Uno\nDos" );
} );

it( 'distingue el número vacío del cero', function () {
	$field = forja_test_field( array( 'type' => 'number' ) );

	expect( $field->sanitize( '' ) )->toBe( '' )
		->and( $field->sanitize( '0' ) )->toBe( 0 )
		->and( $field->sanitize( '12.50' ) )->toBe( 12.5 )
		->and( $field->sanitize( 'no es un número' ) )->toBe( '' );
} );

it( 'acota el rango a sus extremos', function () {
	$field = forja_test_field( array( 'type' => 'range', 'min' => 0, 'max' => 100 ) );

	expect( $field->sanitize( '999' ) )->toBe( 100 )
		->and( $field->sanitize( '-50' ) )->toBe( 0 )
		->and( $field->sanitize( '42' ) )->toBe( 42 );
} );

it( 'descarta un correo inválido', function () {
	$field = forja_test_field( array( 'type' => 'email' ) );

	expect( $field->sanitize( '  HOLA@oswa.dev  ' ) )->toBe( 'HOLA@oswa.dev' )
		->and( $field->sanitize( 'esto no es un correo' ) )->toBe( '' );
} );

it( 'completa el esquema de una URL', function () {
	$field = forja_test_field( array( 'type' => 'url' ) );

	expect( $field->sanitize( 'oswa.dev/ruta?a=1' ) )->toBe( 'http://oswa.dev/ruta?a=1' )
		->and( $field->sanitize( '' ) )->toBe( '' );
} );

it( 'convierte el booleano a uno o cero', function () {
	$field = forja_test_field( array( 'type' => 'true_false' ) );

	expect( $field->sanitize( '1' ) )->toBe( 1 )
		->and( $field->sanitize( '0' ) )->toBe( 0 )
		->and( $field->sanitize( 'cualquier cosa' ) )->toBe( 0 );
} );

describe( 'campos con opciones', function () {
	$choices = array( 'a' => 'A', 'b' => 'B' );

	it( 'rechaza un valor que no está en la lista', function () use ( $choices ) {
		$field = forja_test_field( array( 'type' => 'radio', 'choices' => $choices ) );

		expect( $field->sanitize( 'a' ) )->toBe( 'a' )
			->and( $field->sanitize( 'inventado' ) )->toBe( '' );
	} );

	it( 'filtra la selección múltiple', function () use ( $choices ) {
		$field = forja_test_field( array( 'type' => 'checkbox', 'choices' => $choices ) );

		expect( $field->sanitize( array( 'a', 'HACKEADO', 'b' ) ) )->toBe( array( 'a', 'b' ) )
			->and( $field->sanitize( '' ) )->toBe( array() );
	} );

	it( 'acepta una lista simple como opciones', function () {
		$field = forja_test_field( array( 'type' => 'select', 'choices' => array( 'norte', 'sur' ) ) );

		expect( $field->sanitize( 'norte' ) )->toBe( 'norte' )
			->and( $field->sanitize( 'este' ) )->toBe( '' );
	} );

	it( 'reindexa la selección múltiple del desplegable', function () {
		$field = forja_test_field(
			array( 'type' => 'select', 'multiple' => true, 'choices' => array( 'norte', 'sur', 'este' ) )
		);

		expect( $field->sanitize( array( 'sur', 'nada', 'este' ) ) )->toBe( array( 'sur', 'este' ) );
	} );
} );

describe( 'formato de retorno', function () {
	it( 'devuelve el número como número, no como cadena', function () {
		// WordPress entrega los metadatos como texto; sin format_value() la
		// plantilla recibiría «1234».
		$field = forja_test_field( array( 'type' => 'number' ) );

		expect( $field->format_value( '1234' ) )->toBe( 1234 )
			->and( $field->format_value( '12.5' ) )->toBe( 12.5 );
	} );

	it( 'distingue un número sin rellenar de un cero', function () {
		$field = forja_test_field( array( 'type' => 'number' ) );

		expect( $field->format_value( '' ) )->toBeNull()
			->and( $field->format_value( '0' ) )->toBe( 0 );
	} );

	it( 'devuelve el booleano como booleano', function () {
		// Devolver «'0'» sería una trampa: en PHP es una cadena no vacía.
		$field = forja_test_field( array( 'type' => 'true_false' ) );

		expect( $field->format_value( '1' ) )->toBeTrue()
			->and( $field->format_value( '0' ) )->toBeFalse()
			->and( $field->format_value( '' ) )->toBeFalse();
	} );

	it( 'el rango siempre devuelve número, nunca null', function () {
		$field = forja_test_field( array( 'type' => 'range', 'min' => 10, 'max' => 100 ) );

		expect( $field->format_value( '42' ) )->toBe( 42 )
			->and( $field->format_value( '' ) )->toBe( 10 );
	} );
} );
