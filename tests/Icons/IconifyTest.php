<?php
/**
 * Conversión de la respuesta JSON de Iconify a SVG, y saneado.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Icons\Iconify;

/**
 * Respuesta de `/{coleccion}.json` con la forma que devuelve la API.
 *
 * Las dimensiones por defecto van en la raíz, y cada icono o alias puede
 * sustituirlas.
 *
 * @param array<string, mixed> $icons   Iconos por nombre.
 * @param array<string, mixed> $aliases Alias por nombre.
 * @return array<string, mixed> Respuesta.
 */
function forja_test_collection( array $icons, array $aliases = array() ): array {
	return array(
		'prefix'  => 'demo',
		'width'   => 24,
		'height'  => 24,
		'icons'   => $icons,
		'aliases' => $aliases,
	);
}

describe( 'de JSON a SVG', function () {
	it( 'usa las dimensiones de la colección', function () {
		$data = forja_test_collection( array( 'casa' => array( 'body' => '<path d="M1 1h2"/>' ) ) );

		expect( Iconify::from_collection( $data, 'casa' ) )->toBe(
			'<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path d="M1 1h2" /></svg>'
		);
	} );

	it( 'deja que el icono sustituya dimensiones y desplazamiento', function () {
		$data = forja_test_collection(
			array(
				'ancho' => array(
					'body'   => '<path d="M0 0"/>',
					'width'  => 32,
					'left'   => -4,
					'top'    => 2.5,
				),
			)
		);

		expect( Iconify::from_collection( $data, 'ancho' ) )->toContain( 'viewBox="-4 2.5 32 24"' );
	} );

	it( 'aplica los valores por defecto de Iconify si la colección no trae', function () {
		$data = array( 'icons' => array( 'suelto' => array( 'body' => '<path d="M0 0"/>' ) ) );

		expect( Iconify::from_collection( $data, 'suelto' ) )->toContain( 'viewBox="0 0 16 16"' );
	} );

	it( 'resuelve un alias por su parent', function () {
		$data = forja_test_collection(
			array( 'casa' => array( 'body' => '<path d="M1 1"/>' ) ),
			array( 'hogar' => array( 'parent' => 'casa' ) )
		);

		expect( Iconify::from_collection( $data, 'hogar' ) )->toContain( '<path d="M1 1" />' );
	} );

	it( 'recorre alias encadenados y respeta las dimensiones del más cercano', function () {
		$data = forja_test_collection(
			array( 'casa' => array( 'body' => '<path d="M1 1"/>', 'width' => 20 ) ),
			array(
				'hogar'    => array( 'parent' => 'casa', 'width' => 28 ),
				'vivienda' => array( 'parent' => 'hogar', 'height' => 30 ),
			)
		);

		// width del alias intermedio (28) gana al del icono (20); height, al de
		// la colección.
		expect( Iconify::from_collection( $data, 'vivienda' ) )->toContain( 'viewBox="0 0 28 30"' );
	} );

	it( 'devuelve vacío si el icono no existe o el alias apunta a la nada', function () {
		$data = forja_test_collection( array(), array( 'roto' => array( 'parent' => 'falta' ) ) );

		expect( Iconify::from_collection( $data, 'nada' ) )->toBe( '' )
			->and( Iconify::from_collection( $data, 'roto' ) )->toBe( '' );
	} );

	it( 'corta un ciclo de alias', function () {
		$data = forja_test_collection(
			array(),
			array(
				'a' => array( 'parent' => 'b' ),
				'b' => array( 'parent' => 'a' ),
			)
		);

		expect( Iconify::from_collection( $data, 'a' ) )->toBe( '' );
	} );

	it( 'deja para la API los iconos con volteos o giros', function () {
		$data = forja_test_collection(
			array( 'flecha' => array( 'body' => '<path d="M0 0"/>' ) ),
			array(
				'izquierda' => array( 'parent' => 'flecha', 'hFlip' => true ),
				'abajo'     => array( 'parent' => 'flecha', 'rotate' => 1 ),
			)
		);

		// null significa «pídelo hecho»; construirlo aquí sin la transformación
		// pintaría la flecha al revés.
		expect( Iconify::from_collection( $data, 'izquierda' ) )->toBeNull()
			->and( Iconify::from_collection( $data, 'abajo' ) )->toBeNull()
			->and( Iconify::from_collection( $data, 'flecha' ) )->not->toBeNull();
	} );

	it( 'sanea el cuerpo que viene de la API', function () {
		$data = forja_test_collection(
			array( 'malo' => array( 'body' => '<path d="M0 0" onload="alert(1)"/><script>alert(2)</script>' ) )
		);

		$svg = (string) Iconify::from_collection( $data, 'malo' );

		expect( $svg )->toContain( '<path d="M0 0" />' )
			->and( $svg )->not->toContain( 'onload' )
			->and( $svg )->not->toContain( '<script' );
	} );
} );

describe( 'saneado', function () {
	it( 'quita scripts, objetos externos, enlaces, animaciones y eventos', function () {
		$svg = Iconify::sanitize(
			'<svg viewBox="0 0 24 24" onclick="x()"><script>x()</script>'
			. '<foreignObject><div>x</div></foreignObject>'
			. '<a href="javascript:x()"><path d="M0 0"/></a>'
			. '<path d="M1 1"><animate attributeName="d" values="M0 0;M1 1"/></path></svg>'
		);

		expect( $svg )->not->toContain( 'script' )
			->and( $svg )->not->toContain( 'foreignObject' )
			->and( $svg )->not->toContain( 'href' )
			->and( $svg )->not->toContain( 'animate' )
			->and( $svg )->not->toContain( 'onclick' )
			->and( $svg )->toContain( '<path d="M1 1">' );
	} );

	it( 'conserva gradientes, máscaras y recortes de los iconos a color', function () {
		$svg = Iconify::sanitize(
			'<svg viewBox="0 0 24 24"><defs><linearGradient id="g" gradientUnits="userSpaceOnUse">'
			. '<stop offset="0" stop-color="#f00"/></linearGradient>'
			. '<clipPath id="c"><rect width="24" height="24"/></clipPath></defs>'
			. '<path fill="url(#g)" clip-path="url(#c)" d="M0 0"/></svg>'
		);

		expect( $svg )->toContain( '<linearGradient id="g" gradientUnits="userSpaceOnUse">' )
			->and( $svg )->toContain( 'stop-color="#f00"' )
			->and( $svg )->toContain( '<clipPath id="c">' )
			->and( $svg )->toContain( 'clip-path="url(#c)"' );
	} );

	it( 'devuelve las mayúsculas a los atributos que las necesitan', function () {
		// `wp_kses()` los deja en minúsculas, y en un `<img>` con el SVG en una
		// dirección `data:` —contexto XML— `viewbox` no significa nada.
		$svg = Iconify::sanitize( '<svg viewBox="0 0 1 1"><radialGradient gradientTransform="scale(2)"/></svg>' );

		expect( $svg )->toContain( 'viewBox="0 0 1 1"' )
			->and( $svg )->toContain( 'gradientTransform="scale(2)"' );
	} );

	it( 'rechaza lo que no empieza como un SVG', function () {
		expect( Iconify::sanitize( 'error code: 1015' ) )->toBe( '' )
			->and( Iconify::sanitize( '<html><svg></svg></html>' ) )->toBe( '' );
	} );
} );

describe( 'lotes y colecciones animadas', function () {
	it( 'sirve de la caché sin salir a la red y descarta los nombres inválidos', function () {
		set_transient( 'forja_icon_' . md5( 'demo:en-cache' ), '<svg>en caché</svg>', HOUR_IN_SECONDS );

		// El nombre inválido ni siquiera llega a formar una URL.
		expect( Iconify::svgs( array( 'demo:en-cache', '../../etc:passwd', 'sin-dos-puntos' ) ) )
			->toBe( array( 'demo:en-cache' => '<svg>en caché</svg>' ) );

		delete_transient( 'forja_icon_' . md5( 'demo:en-cache' ) );
	} );

	it( 'no devuelve los iconos que se sabe que no existen', function () {
		set_transient( 'forja_icon_' . md5( 'demo:no-existe' ), '', HOUR_IN_SECONDS );

		expect( Iconify::svgs( array( 'demo:no-existe' ) ) )->toBe( array() );

		delete_transient( 'forja_icon_' . md5( 'demo:no-existe' ) );
	} );

	it( 'reconoce las colecciones animadas', function () {
		expect( Iconify::is_animated( 'line-md:home' ) )->toBeTrue()
			->and( Iconify::is_animated( 'svg-spinners:180-ring' ) )->toBeTrue()
			->and( Iconify::is_animated( 'mdi:home' ) )->toBeFalse();
	} );
} );
