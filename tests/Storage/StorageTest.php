<?php
/**
 * Capa de almacenamiento.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Storage\MetaStorage;
use Forja\Storage\OptionStorage;
use Forja\Storage\StorageFactory;

it( 'resuelve una implementación por cada tipo de objeto', function () {
	$factory = new StorageFactory();

	expect( $factory->for( 'post' ) )->toBeInstanceOf( MetaStorage::class )
		->and( $factory->for( 'term' ) )->toBeInstanceOf( MetaStorage::class )
		->and( $factory->for( 'user' ) )->toBeInstanceOf( MetaStorage::class )
		->and( $factory->for( 'comment' ) )->toBeInstanceOf( MetaStorage::class )
		->and( $factory->for( 'option' ) )->toBeInstanceOf( OptionStorage::class );
} );

it( 'reutiliza la misma instancia', function () {
	$factory = new StorageFactory();

	expect( $factory->for( 'post' ) )->toBe( $factory->for( 'post' ) );
} );

it( 'protesta ante un tipo de objeto desconocido', function () {
	( new StorageFactory() )->for( 'galaxia' );
} )->throws( InvalidArgumentException::class );

describe( 'metadatos', function () {
	beforeEach( function () {
		$this->post_id = wp_insert_post(
			array(
				'post_title'  => 'Entrada de prueba',
				'post_status' => 'draft',
			)
		);

		$this->storage = ( new StorageFactory() )->for( 'post' );
	} );

	afterEach( function () {
		wp_delete_post( $this->post_id, true );
	} );

	it( 'devuelve null cuando la clave no existe', function () {
		expect( $this->storage->get( $this->post_id, 'inexistente' ) )->toBeNull();
	} );

	it( 'distingue una clave vacía de una clave ausente', function () {
		// Es la razón de usar `metadata_exists()` en lugar de comprobar si el
		// valor es falsy: un campo guardado vacío no es un campo sin guardar.
		$this->storage->update( $this->post_id, 'vacia', '' );

		expect( $this->storage->get( $this->post_id, 'vacia' ) )->toBe( '' )
			->and( $this->storage->get( $this->post_id, 'ausente' ) )->toBeNull();
	} );

	it( 'guarda y recupera un array', function () {
		$this->storage->update( $this->post_id, 'lista', array( 'a', 'b' ) );

		expect( $this->storage->get( $this->post_id, 'lista' ) )->toBe( array( 'a', 'b' ) );
	} );

	it( 'considera correcto guardar el mismo valor dos veces', function () {
		// `update_metadata()` devuelve false si el valor no cambia; el contrato
		// de la capa dice que eso sigue siendo un guardado correcto.
		$this->storage->update( $this->post_id, 'clave', 'valor' );

		expect( $this->storage->update( $this->post_id, 'clave', 'valor' ) )->toBeTrue();
	} );

	it( 'elimina una clave', function () {
		$this->storage->update( $this->post_id, 'clave', 'valor' );
		$this->storage->delete( $this->post_id, 'clave' );

		expect( $this->storage->get( $this->post_id, 'clave' ) )->toBeNull();
	} );
} );

describe( 'opciones', function () {
	it( 'prefija la opción con el identificador del objeto', function () {
		$storage = ( new StorageFactory() )->for( 'option' );

		$storage->update( 'ajustes', 'color', 'azul' );

		expect( get_option( 'ajustes_color' ) )->toBe( 'azul' )
			->and( $storage->get( 'ajustes', 'color' ) )->toBe( 'azul' )
			->and( $storage->get( 'otra_pagina', 'color' ) )->toBeNull();

		$storage->delete( 'ajustes', 'color' );
	} );

	it( 'distingue una opción falsy de una ausente', function () {
		$storage = ( new StorageFactory() )->for( 'option' );

		$storage->update( 'ajustes', 'cero', '0' );

		expect( $storage->get( 'ajustes', 'cero' ) )->toBe( '0' )
			->and( $storage->get( 'ajustes', 'nada' ) )->toBeNull();

		$storage->delete( 'ajustes', 'cero' );
	} );
} );
