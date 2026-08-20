<?php
/**
 * Grupo de subcampos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

beforeEach( function () {
	$this->field = forja_test_field(
		array(
			'type'       => 'group',
			'name'       => 'direccion',
			'sub_fields' => array(
				array( 'type' => 'text', 'name' => 'calle' ),
				array( 'type' => 'text', 'name' => 'ciudad' ),
				array( 'type' => 'number', 'name' => 'numero' ),
			),
		)
	);

	$this->store = array();

	$this->get    = function ( string $key ) {
		return $this->store[ $key ] ?? null;
	};
	$this->set    = function ( string $key, $value ): bool {
		$this->store[ $key ] = $value;

		return true;
	};
	$this->delete = function ( string $key ): bool {
		unset( $this->store[ $key ] );

		return true;
	};
} );

it( 'compone las claves sin índice, a diferencia del repetidor', function () {
	expect( $this->field->sub_key( 'calle' ) )->toBe( 'direccion_calle' );
} );

it( 'guarda una clave por subcampo', function () {
	$this->field->write_value(
		array( 'calle' => 'Av. Larco', 'ciudad' => 'Lima', 'numero' => '123' ),
		$this->get,
		$this->set,
		$this->delete
	);

	expect( $this->store )->toBe(
		array(
			'direccion_calle'  => 'Av. Larco',
			'direccion_ciudad' => 'Lima',
			'direccion_numero' => 123,
		)
	);
} );

it( 'sanea cada subcampo con sus propias reglas', function () {
	$this->field->write_value(
		array( 'calle' => '  <b>Av. Larco</b>  ', 'numero' => 'no es número' ),
		$this->get,
		$this->set,
		$this->delete
	);

	expect( $this->store['direccion_calle'] )->toBe( 'Av. Larco' )
		->and( $this->store['direccion_numero'] )->toBe( '' );
} );

it( 'ignora las claves que no son subcampos declarados', function () {
	$this->field->write_value(
		array( 'calle' => 'Av. Larco', 'intruso' => 'no debería guardarse' ),
		$this->get,
		$this->set,
		$this->delete
	);

	expect( $this->store )->not->toHaveKey( 'direccion_intruso' );
} );

it( 'lee los valores de todos los subcampos', function () {
	$this->store = array(
		'direccion_calle'  => 'Av. Larco',
		'direccion_ciudad' => 'Lima',
	);

	$values = $this->field->read_value( $this->get );

	expect( $values['calle'] )->toBe( 'Av. Larco' )
		->and( $values['ciudad'] )->toBe( 'Lima' )
		->and( $values )->toHaveKey( 'numero' );
} );

it( 'no almacena su valor con la clave a secas', function () {
	expect( $this->field->stores_value() )->toBeFalse();
} );
