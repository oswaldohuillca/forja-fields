<?php
/**
 * Campo repetible: formato de almacenamiento y ciclo de vida de las filas.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Fields\Repeater;

beforeEach( function () {
	$this->field = forja_test_field(
		array(
			'type'       => 'repeater',
			'name'       => 'banner',
			'sub_fields' => array(
				array( 'type' => 'text', 'name' => 'titulo' ),
				array( 'type' => 'number', 'name' => 'orden' ),
			),
		)
	);

	// Almacén en memoria: basta para probar el formato de claves sin tocar
	// la base de datos.
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

it( 'compone las claves con el formato de ACF', function () {
	expect( $this->field->row_key( 0, 'titulo' ) )->toBe( 'banner_0_titulo' )
		->and( $this->field->row_key( 3, 'orden' ) )->toBe( 'banner_3_orden' );
} );

it( 'guarda una clave por subcampo y fila, más el número de filas', function () {
	$this->field->write_value(
		array(
			array( 'titulo' => 'Primera', 'orden' => '1' ),
			array( 'titulo' => 'Segunda', 'orden' => '2' ),
		),
		$this->get,
		$this->set,
		$this->delete
	);

	expect( $this->store )->toBe(
		array(
			'banner_0_titulo' => 'Primera',
			'banner_0_orden'  => 1,
			'banner_1_titulo' => 'Segunda',
			'banner_1_orden'  => 2,
			'banner'          => 2,
		)
	);
} );

it( 'nunca guarda la fila plantilla', function () {
	$this->field->write_value(
		array(
			'0'                    => array( 'titulo' => 'Real' ),
			Repeater::CLONE_INDEX  => array( 'titulo' => 'Plantilla' ),
		),
		$this->get,
		$this->set,
		$this->delete
	);

	expect( $this->store['banner'] )->toBe( 1 )
		->and( $this->store )->not->toHaveKey( 'banner_1_titulo' );
} );

it( 'borra las claves de las filas que se quitan', function () {
	$write = function ( array $rows ): void {
		$this->field->write_value( $rows, $this->get, $this->set, $this->delete );
	};

	$write(
		array(
			array( 'titulo' => 'Uno' ),
			array( 'titulo' => 'Dos' ),
			array( 'titulo' => 'Tres' ),
		)
	);

	expect( $this->store )->toHaveKey( 'banner_2_titulo' );

	$write( array( array( 'titulo' => 'Uno' ) ) );

	// Si no se borraran, reaparecerían al volver a crecer la lista.
	expect( $this->store['banner'] )->toBe( 1 )
		->and( $this->store )->not->toHaveKey( 'banner_1_titulo' )
		->and( $this->store )->not->toHaveKey( 'banner_2_titulo' );
} );

it( 'sanea cada subcampo con sus propias reglas', function () {
	$this->field->write_value(
		array( array( 'titulo' => '  <b>Hola</b>  ', 'orden' => 'no es número' ) ),
		$this->get,
		$this->set,
		$this->delete
	);

	expect( $this->store['banner_0_titulo'] )->toBe( 'Hola' )
		->and( $this->store['banner_0_orden'] )->toBe( '' );
} );

it( 'reindexa las filas al guardar aunque lleguen con huecos', function () {
	$this->field->write_value(
		array(
			'row-5' => array( 'titulo' => 'Primera' ),
			'row-9' => array( 'titulo' => 'Segunda' ),
		),
		$this->get,
		$this->set,
		$this->delete
	);

	expect( $this->store['banner_0_titulo'] )->toBe( 'Primera' )
		->and( $this->store['banner_1_titulo'] )->toBe( 'Segunda' );
} );

it( 'lee las filas que ya estaban almacenadas', function () {
	$this->store = array(
		'banner'          => 2,
		'banner_0_titulo' => 'Uno',
		'banner_1_titulo' => 'Dos',
	);

	$rows = $this->field->read_value( $this->get );

	expect( $rows )->toHaveCount( 2 )
		->and( $rows[0]['titulo'] )->toBe( 'Uno' )
		->and( $rows[1]['titulo'] )->toBe( 'Dos' );
} );

it( 'devuelve una lista vacía cuando no hay nada guardado', function () {
	expect( $this->field->read_value( $this->get ) )->toBe( array() );
} );

it( 'formatea cada subcampo al leer', function () {
	// El subcampo `orden` es numérico, así que su formato se aplica igual que
	// si estuviera suelto.
	$rows = $this->field->format_value(
		array( array( 'titulo' => 'Uno', 'orden' => '3' ) )
	);

	expect( $rows )->toHaveCount( 1 )
		->and( $rows[0]['titulo'] )->toBe( 'Uno' );
} );

it( 'no almacena su valor con la clave a secas', function () {
	// Ocupa muchas claves, así que la capa genérica no debe tocarlo.
	expect( $this->field->stores_value() )->toBeFalse();
} );
