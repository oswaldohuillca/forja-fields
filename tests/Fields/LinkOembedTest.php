<?php
/**
 * Enlace y contenido incrustado.
 *
 * @package Forja
 */

declare( strict_types = 1 );

describe( 'enlace', function () {
	beforeEach( function () {
		$this->field = forja_test_field( array( 'type' => 'link' ) );
	} );

	it( 'guarda las tres claves saneadas', function () {
		expect(
			$this->field->sanitize(
				array( 'title' => '  <b>Apros</b>  ', 'url' => 'apros.pe', 'target' => '_blank' )
			)
		)->toBe(
			array( 'title' => 'Apros', 'url' => 'http://apros.pe', 'target' => '_blank' )
		);
	} );

	it( 'descarta un destino que no sea _blank', function () {
		$link = $this->field->sanitize(
			array( 'url' => 'https://apros.pe', 'target' => 'javascript:alert(1)' )
		);

		expect( $link['target'] )->toBe( '' );
	} );

	it( 'trata un enlace sin URL como vacío', function () {
		// Así la plantilla puede comprobarlo con un simple `if`.
		expect( $this->field->sanitize( array( 'title' => 'Sólo texto' ) ) )->toBe( '' )
			->and( $this->field->sanitize( '' ) )->toBe( '' );
	} );

	it( 'devuelve el array por defecto y null si está vacío', function () {
		expect( $this->field->format_value( array( 'url' => 'https://apros.pe', 'title' => 'Apros' ) ) )
			->toBeArray()
			->toHaveKeys( array( 'title', 'url', 'target' ) )
			->and( $this->field->format_value( '' ) )->toBeNull();
	} );

	it( 'devuelve sólo la URL si se pide', function () {
		$field = forja_test_field( array( 'type' => 'link', 'return_format' => 'url' ) );

		expect( $field->format_value( array( 'url' => 'https://apros.pe' ) ) )->toBe( 'https://apros.pe' )
			->and( $field->format_value( '' ) )->toBe( '' );
	} );

	it( 'pinta el ancla que entiende el modal del núcleo', function () {
		$html = forja_test_render( $this->field, array( 'url' => 'https://apros.pe', 'title' => 'Apros' ) );

		expect( $html )->toContain( 'class="link-node"' )
			->and( $html )->toContain( 'acf-link -value' )
			->and( $html )->toContain( 'name="forja[campo][url]"' );
	} );
} );

describe( 'contenido incrustado', function () {
	beforeEach( function () {
		$this->field = forja_test_field( array( 'type' => 'oembed' ) );
	} );

	it( 'guarda la dirección, no el HTML', function () {
		// El HTML de un proveedor cambia con el tiempo; guardarlo dejaría
		// vídeos rotos.
		expect( $this->field->sanitize( '  https://youtube.com/watch?v=abc  ' ) )
			->toBe( 'https://youtube.com/watch?v=abc' )
			->and( $this->field->sanitize( '' ) )->toBe( '' );
	} );

	it( 'devuelve la dirección tal cual si se pide', function () {
		$field = forja_test_field( array( 'type' => 'oembed', 'return_format' => 'url' ) );

		expect( $field->format_value( 'https://apros.pe' ) )->toBe( 'https://apros.pe' );
	} );

	it( 'devuelve cadena vacía cuando no hay nada', function () {
		expect( $this->field->format_value( '' ) )->toBe( '' );
	} );

	it( 'pinta el campo de búsqueda con las dimensiones declaradas', function () {
		$field = forja_test_field( array( 'type' => 'oembed', 'width' => 800, 'height' => 450 ) );
		$html  = forja_test_render( $field, '' );

		expect( $html )->toContain( 'class="acf-oembed-search"' )
			->and( $html )->toContain( 'data-width="800"' )
			->and( $html )->toContain( 'data-height="450"' );
	} );
} );
