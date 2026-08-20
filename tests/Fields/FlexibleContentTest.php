<?php
/**
 * Contenido flexible: capas, orden y almacenamiento.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Fields\FlexibleContent;

beforeEach( function () {
	$this->field = forja_test_field(
		array(
			'type'    => 'flexible_content',
			'name'    => 'secciones',
			'label'   => 'Secciones',
			'layouts' => array(
				'banner' => array(
					'label'      => 'Banner',
					'sub_fields' => array(
						array( 'type' => 'text', 'name' => 'titulo' ),
					),
				),
				'texto'  => array(
					'label'      => 'Texto',
					'sub_fields' => array(
						array( 'type' => 'textarea', 'name' => 'cuerpo' ),
					),
				),
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

	$this->write = function ( array $rows ): array {
		return $this->field->write_value( $rows, $this->get, $this->set, $this->delete );
	};
} );

it( 'guarda la lista ordenada de capas y los valores por posición', function () {
	( $this->write )(
		array(
			array( FlexibleContent::LAYOUT_KEY => 'banner', 'titulo' => 'Hola' ),
			array( FlexibleContent::LAYOUT_KEY => 'texto', 'cuerpo' => 'Lorem' ),
		)
	);

	expect( $this->store['secciones'] )->toBe( array( 'banner', 'texto' ) )
		->and( $this->store['secciones_0_titulo'] )->toBe( 'Hola' )
		->and( $this->store['secciones_1_cuerpo'] )->toBe( 'Lorem' );
} );

it( 'usa la posición en la lista como índice, no la posición dentro de su capa', function () {
	( $this->write )(
		array(
			array( FlexibleContent::LAYOUT_KEY => 'texto', 'cuerpo' => 'Primero' ),
			array( FlexibleContent::LAYOUT_KEY => 'texto', 'cuerpo' => 'Segundo' ),
		)
	);

	expect( $this->store['secciones_0_cuerpo'] )->toBe( 'Primero' )
		->and( $this->store['secciones_1_cuerpo'] )->toBe( 'Segundo' );
} );

it( 'descarta las filas sin capa o con una capa desconocida', function () {
	( $this->write )(
		array(
			array( FlexibleContent::LAYOUT_KEY => 'banner', 'titulo' => 'Válida' ),
			array( 'titulo' => 'Sin capa' ),
			array( FlexibleContent::LAYOUT_KEY => 'inventada', 'titulo' => 'Falsa' ),
		)
	);

	expect( $this->store['secciones'] )->toBe( array( 'banner' ) );
} );

it( 'nunca guarda la fila plantilla', function () {
	( $this->write )(
		array(
			'0'                           => array( FlexibleContent::LAYOUT_KEY => 'banner', 'titulo' => 'Real' ),
			FlexibleContent::CLONE_INDEX  => array( FlexibleContent::LAYOUT_KEY => 'banner', 'titulo' => 'Plantilla' ),
		)
	);

	expect( $this->store['secciones'] )->toBe( array( 'banner' ) );
} );

it( 'limpia los subcampos de la capa anterior cuando una posición cambia de tipo', function () {
	( $this->write )( array( array( FlexibleContent::LAYOUT_KEY => 'banner', 'titulo' => 'Antes' ) ) );

	expect( $this->store )->toHaveKey( 'secciones_0_titulo' );

	( $this->write )( array( array( FlexibleContent::LAYOUT_KEY => 'texto', 'cuerpo' => 'Después' ) ) );

	// Sin esta limpieza, `secciones_0_titulo` quedaría huérfano bajo el mismo
	// prefijo que ahora ocupa otra capa.
	expect( $this->store )->not->toHaveKey( 'secciones_0_titulo' )
		->and( $this->store['secciones_0_cuerpo'] )->toBe( 'Después' );
} );

it( 'borra las claves de las filas que se quitan', function () {
	( $this->write )(
		array(
			array( FlexibleContent::LAYOUT_KEY => 'banner', 'titulo' => 'Uno' ),
			array( FlexibleContent::LAYOUT_KEY => 'texto', 'cuerpo' => 'Dos' ),
		)
	);

	( $this->write )( array( array( FlexibleContent::LAYOUT_KEY => 'banner', 'titulo' => 'Uno' ) ) );

	expect( $this->store['secciones'] )->toBe( array( 'banner' ) )
		->and( $this->store )->not->toHaveKey( 'secciones_1_cuerpo' );
} );

it( 'lee las filas con su capa y sus valores', function () {
	$this->store = array(
		'secciones'          => array( 'banner', 'texto' ),
		'secciones_0_titulo' => 'Hola',
		'secciones_1_cuerpo' => 'Lorem',
	);

	$rows = $this->field->read_value( $this->get );

	expect( $rows )->toHaveCount( 2 )
		->and( $rows[0][ FlexibleContent::LAYOUT_KEY ] )->toBe( 'banner' )
		->and( $rows[0]['titulo'] )->toBe( 'Hola' )
		->and( $rows[1]['cuerpo'] )->toBe( 'Lorem' );
} );

it( 'omite al leer las capas que ya no están declaradas', function () {
	$this->store = array(
		'secciones'          => array( 'banner', 'retirada' ),
		'secciones_0_titulo' => 'Hola',
	);

	// Los datos siguen en la base de datos, pero no hay con qué pintarlos.
	expect( $this->field->read_value( $this->get ) )->toHaveCount( 1 );
} );

it( 'devuelve una lista vacía cuando no hay nada guardado', function () {
	expect( $this->field->read_value( $this->get ) )->toBe( array() );
} );

it( 'respeta los límites de filas', function () {
	$limitado = forja_test_field(
		array(
			'type'    => 'flexible_content',
			'name'    => 'secciones',
			'label'   => 'Secciones',
			'max'     => 1,
			'layouts' => array(
				'banner' => array( 'label' => 'Banner', 'sub_fields' => array() ),
			),
		)
	);

	$errors = $limitado->write_value(
		array(
			array( FlexibleContent::LAYOUT_KEY => 'banner' ),
			array( FlexibleContent::LAYOUT_KEY => 'banner' ),
		),
		$this->get,
		$this->set,
		$this->delete
	);

	expect( $errors )->toHaveCount( 1 )
		->and( $this->store )->toBe( array() );
} );
