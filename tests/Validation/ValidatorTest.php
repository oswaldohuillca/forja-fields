<?php
/**
 * Validación de los campos obligatorios.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Validation\Validator;

beforeEach( function () {
	$this->validator = new Validator();
} );

it( 'no protesta por un campo opcional vacío', function () {
	$field = forja_test_field( array( 'type' => 'text' ) );

	expect( $this->validator->validate( $field, '' ) )->toBe( '' );
} );

it( 'protesta por un campo obligatorio vacío', function () {
	$field = forja_test_field( array( 'type' => 'text', 'label' => 'Titular', 'required' => true ) );

	expect( $this->validator->validate( $field, '' ) )->toContain( 'Titular' );
} );

it( 'trata los espacios en blanco como vacío', function () {
	$field = forja_test_field( array( 'type' => 'text', 'label' => 'Titular', 'required' => true ) );

	expect( $this->validator->validate( $field, '   ' ) )->not->toBe( '' );
} );

it( 'acepta el cero como valor relleno', function () {
	// `empty()` diría que 0 está vacío, y un número a cero o un booleano en
	// falso son valores legítimos.
	$number  = forja_test_field( array( 'type' => 'number', 'label' => 'Cantidad', 'required' => true ) );
	$boolean = forja_test_field( array( 'type' => 'true_false', 'label' => 'Activo', 'required' => true ) );

	expect( $this->validator->validate( $number, 0 ) )->toBe( '' )
		->and( $this->validator->validate( $boolean, 0 ) )->toBe( '' );
} );

it( 'protesta por una selección múltiple obligatoria y vacía', function () {
	$field = forja_test_field(
		array( 'type' => 'checkbox', 'label' => 'Turnos', 'required' => true, 'choices' => array( 'a' ) )
	);

	expect( $this->validator->validate( $field, array() ) )->not->toBe( '' )
		->and( $this->validator->validate( $field, array( 'a' ) ) )->toBe( '' );
} );

it( 'permite añadir reglas propias con un filtro', function () {
	$field = forja_test_field( array( 'type' => 'text', 'label' => 'Titular' ) );

	$rule = static function ( string $error, $value ): string {
		return mb_strlen( (string) $value ) < 5 ? 'Demasiado corto.' : $error;
	};

	add_filter( 'forja/validate_field', $rule, 10, 3 );

	expect( $this->validator->validate( $field, 'abc' ) )->toBe( 'Demasiado corto.' )
		->and( $this->validator->validate( $field, 'suficientemente largo' ) )->toBe( '' );

	remove_filter( 'forja/validate_field', $rule, 10 );
} );
