<?php
/**
 * Fechas, horas y color.
 *
 * @package Forja
 */

declare( strict_types = 1 );

describe( 'selector de fecha', function () {
	beforeEach( function () {
		$this->field = forja_test_field( array( 'type' => 'date_picker' ) );
	} );

	it( 'guarda en el formato Ymd de ACF', function () {
		expect( $this->field->sanitize( '2026-08-19' ) )->toBe( '20260819' );
	} );

	it( 'descarta una fecha imposible en lugar de desplazarla', function () {
		// `strtotime` aceptaría el mes 13 pasándolo a enero del año siguiente;
		// aquí se rechaza.
		expect( $this->field->sanitize( '2026-13-01' ) )->toBe( '' )
			->and( $this->field->sanitize( 'mañana' ) )->toBe( '' )
			->and( $this->field->sanitize( '' ) )->toBe( '' );
	} );

	it( 'devuelve lo almacenado cuando no se pide formato', function () {
		expect( $this->field->format_value( '20260819' ) )->toBe( '20260819' );
	} );

	it( 'aplica el formato de retorno declarado', function () {
		$field = forja_test_field(
			array( 'type' => 'date_picker', 'return_format' => 'd/m/Y' )
		);

		expect( $field->format_value( '20260819' ) )->toBe( '19/08/2026' );
	} );

	it( 'devuelve cadena vacía si lo almacenado no es una fecha', function () {
		$field = forja_test_field(
			array( 'type' => 'date_picker', 'return_format' => 'd/m/Y' )
		);

		expect( $field->format_value( 'basura' ) )->toBe( '' );
	} );
} );

describe( 'selector de hora', function () {
	beforeEach( function () {
		$this->field = forja_test_field( array( 'type' => 'time_picker' ) );
	} );

	it( 'guarda en el formato H:i:s de ACF', function () {
		expect( $this->field->sanitize( '14:30' ) )->toBe( '14:30:00' );
	} );

	it( 'rechaza una hora inválida', function () {
		expect( $this->field->sanitize( '25:00' ) )->toBe( '' );
	} );

	it( 'aplica el formato de retorno declarado', function () {
		$field = forja_test_field(
			array( 'type' => 'time_picker', 'return_format' => 'g:i a' )
		);

		expect( $field->format_value( '14:30:00' ) )->toBe( '2:30 pm' );
	} );
} );

describe( 'selector de fecha y hora', function () {
	it( 'guarda en el formato Y-m-d H:i:s de ACF', function () {
		$field = forja_test_field( array( 'type' => 'date_time_picker' ) );

		expect( $field->sanitize( '2026-08-19T14:30' ) )->toBe( '2026-08-19 14:30:00' );
	} );

	it( 'aplica el formato de retorno declarado', function () {
		$field = forja_test_field(
			array( 'type' => 'date_time_picker', 'return_format' => 'd/m/Y H:i' )
		);

		expect( $field->format_value( '2026-08-19 14:30:00' ) )->toBe( '19/08/2026 14:30' );
	} );
} );

describe( 'selector de color', function () {
	it( 'acepta un hexadecimal', function () {
		$field = forja_test_field( array( 'type' => 'color_picker' ) );

		expect( $field->sanitize( '#FF0000' ) )->toBe( '#FF0000' )
			->and( $field->sanitize( '  #0783be  ' ) )->toBe( '#0783be' );
	} );

	it( 'descarta lo que no sea un color', function () {
		// Un valor arbitrario acabaría interpolado en un atributo `style`.
		$field = forja_test_field( array( 'type' => 'color_picker' ) );

		expect( $field->sanitize( 'rojo' ) )->toBe( '' )
			->and( $field->sanitize( 'expression(alert(1))' ) )->toBe( '' )
			->and( $field->sanitize( '' ) )->toBe( '' );
	} );

	it( 'sólo acepta rgba cuando se habilita la opacidad', function () {
		$sin  = forja_test_field( array( 'type' => 'color_picker' ) );
		$con  = forja_test_field( array( 'type' => 'color_picker', 'enable_opacity' => true ) );
		$rgba = 'rgba(255, 0, 0, 0.5)';

		expect( $sin->sanitize( $rgba ) )->toBe( '' )
			->and( $con->sanitize( $rgba ) )->toBe( $rgba )
			->and( $con->sanitize( 'rgba(1,2)' ) )->toBe( '' );
	} );
} );
