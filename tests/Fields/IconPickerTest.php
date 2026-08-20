<?php
/**
 * Selector de icono.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Icons\Iconify;

describe( 'nombres de icono', function () {
	it( 'acepta la forma coleccion:icono', function () {
		expect( Iconify::is_valid_name( 'mdi:home' ) )->toBeTrue()
			->and( Iconify::is_valid_name( 'dashicons:admin-home' ) )->toBeTrue();
	} );

	it( 'rechaza lo que no encaje', function () {
		// El nombre acaba formando parte de una URL contra la API.
		expect( Iconify::is_valid_name( 'mdi:../../etc/passwd' ) )->toBeFalse()
			->and( Iconify::is_valid_name( 'MDI:Home' ) )->toBeFalse()
			->and( Iconify::is_valid_name( 'sin-dos-puntos' ) )->toBeFalse()
			->and( Iconify::is_valid_name( 'mdi:' ) )->toBeFalse()
			->and( Iconify::is_valid_name( '' ) )->toBeFalse();
	} );

	it( 'no intenta descargar un nombre inválido', function () {
		expect( Iconify::svg( 'mdi:../evil' ) )->toBe( '' );
	} );
} );

describe( 'campo', function () {
	beforeEach( function () {
		$this->field = forja_test_field( array( 'type' => 'icon_picker' ) );
	} );

	it( 'guarda tipo y valor', function () {
		expect( $this->field->sanitize( array( 'type' => 'iconify', 'value' => 'mdi:home' ) ) )
			->toBe( array( 'type' => 'iconify', 'value' => 'mdi:home' ) );
	} );

	it( 'entiende un nombre suelto como forma corta', function () {
		expect( $this->field->sanitize( 'tabler:brand-github' ) )
			->toBe( array( 'type' => 'iconify', 'value' => 'tabler:brand-github' ) );
	} );

	it( 'descarta un nombre que no encaje', function () {
		expect( $this->field->sanitize( array( 'type' => 'iconify', 'value' => 'mdi:../evil' ) ) )->toBe( '' )
			->and( $this->field->sanitize( '' ) )->toBe( '' );
	} );

	it( 'sigue entendiendo los tipos que guarda ACF', function () {
		// Un sitio existente puede traer dashicons, adjuntos o URLs.
		expect( $this->field->sanitize( array( 'type' => 'dashicons', 'value' => 'admin-home' ) ) )
			->toBe( array( 'type' => 'dashicons', 'value' => 'admin-home' ) )
			->and( $this->field->sanitize( array( 'type' => 'url', 'value' => 'apros.pe/i.svg' ) ) )
			->toBe( array( 'type' => 'url', 'value' => 'http://apros.pe/i.svg' ) );
	} );

	it( 'devuelve el array por defecto y null si está vacío', function () {
		expect( $this->field->format_value( array( 'type' => 'iconify', 'value' => 'mdi:home' ) ) )
			->toBe( array( 'type' => 'iconify', 'value' => 'mdi:home' ) )
			->and( $this->field->format_value( '' ) )->toBeNull();
	} );

	it( 'devuelve sólo el nombre si se pide', function () {
		$field = forja_test_field( array( 'type' => 'icon_picker', 'return_format' => 'string' ) );

		expect( $field->format_value( array( 'type' => 'iconify', 'value' => 'mdi:home' ) ) )->toBe( 'mdi:home' );
	} );

	it( 'pinta el buscador con la API y las colecciones declaradas', function () {
		$field = forja_test_field(
			array( 'type' => 'icon_picker', 'collections' => array( 'mdi', 'tabler' ) )
		);

		$html = forja_test_render( $field, '' );

		expect( $html )->toContain( 'class="acf-icon-picker-search"' )
			->and( $html )->toContain( 'data-collections="mdi,tabler"' )
			->and( $html )->toContain( 'api.iconify.design' );
	} );

	it( 'permite apuntar a una instancia propia de la API', function () {
		$propia = static fn (): string => 'https://iconos.apros.pe';

		add_filter( 'forja/iconify_api', $propia );

		expect( Iconify::api_url() )->toBe( 'https://iconos.apros.pe' );

		remove_filter( 'forja/iconify_api', $propia );
	} );
} );
