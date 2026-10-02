<?php
/**
 * Compuestos dentro de compuestos: un repetidor dentro de otro, un grupo
 * dentro de un repetidor, un repetidor dentro de contenido flexible.
 *
 * Antes el compuesto exterior guardaba al interior con el `sanitize()` de un
 * campo simple: PHP avisaba de «Array to string conversion», el aviso rompía la
 * redirección del guardado y el contenido interior se perdía. Las claves que se
 * comprueban son las de ACF, para que un sitio existente se lea sin migrar.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Fields\FlexibleContent;

beforeEach( function () {
	// Almacén en memoria: basta para probar el formato de claves.
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

	$this->write = fn ( $field, $submitted ): array => $field->write_value( $submitted, $this->get, $this->set, $this->delete );

	$this->blocks = forja_test_field(
		array(
			'type'       => 'repeater',
			'name'       => 'bloques',
			'sub_fields' => array(
				array( 'type' => 'text', 'name' => 'titulo' ),
				array(
					'type'       => 'repeater',
					'name'       => 'items',
					'sub_fields' => array(
						array( 'type' => 'text', 'name' => 'item' ),
						array( 'type' => 'number', 'name' => 'orden' ),
					),
				),
			),
		)
	);
} );

describe( 'repetidor dentro de repetidor', function () {
	it( 'guarda con las claves de ACF', function () {
		$errors = ( $this->write )(
			$this->blocks,
			array(
				array(
					'titulo' => 'Uno',
					'items'  => array(
						array( 'item' => 'a', 'orden' => '1' ),
						array( 'item' => 'b', 'orden' => '2' ),
					),
				),
				array(
					'titulo' => 'Dos',
					'items'  => array( array( 'item' => 'c', 'orden' => '3' ) ),
				),
			)
		);

		expect( $errors )->toBe( array() )
			->and( $this->store )->toBe(
				array(
					'bloques_0_titulo'       => 'Uno',
					'bloques_0_items_0_item'  => 'a',
					'bloques_0_items_0_orden' => 1,
					'bloques_0_items_1_item'  => 'b',
					'bloques_0_items_1_orden' => 2,
					'bloques_0_items'         => 2,
					'bloques_1_titulo'        => 'Dos',
					'bloques_1_items_0_item'  => 'c',
					'bloques_1_items_0_orden' => 3,
					'bloques_1_items'         => 1,
					'bloques'                 => 2,
				)
			);
	} );

	it( 'lee lo guardado y lo formatea por niveles', function () {
		( $this->write )(
			$this->blocks,
			array( array( 'titulo' => 'Uno', 'items' => array( array( 'item' => 'a', 'orden' => '7' ) ) ) )
		);

		$value = $this->blocks->format_value( $this->blocks->read_value( $this->get ) );

		// El number interior sale como entero: el formato llega hasta abajo.
		expect( $value )->toBe(
			array(
				array(
					'titulo' => 'Uno',
					'items'  => array( array( 'item' => 'a', 'orden' => 7 ) ),
				),
			)
		);
	} );

	it( 'descarta las filas plantilla de los dos niveles', function () {
		( $this->write )(
			$this->blocks,
			array(
				'acfcloneindex' => array( 'titulo' => '', 'items' => array( 'acfcloneindex' => array( 'item' => '' ) ) ),
				array(
					'titulo' => 'Uno',
					'items'  => array(
						'acfcloneindex' => array( 'item' => '' ),
						array( 'item' => 'a' ),
					),
				),
			)
		);

		expect( $this->store['bloques'] )->toBe( 1 )
			->and( $this->store['bloques_0_items'] )->toBe( 1 )
			->and( array_filter( array_keys( $this->store ), fn ( $key ) => str_contains( $key, 'acfcloneindex' ) ) )->toBe( array() );
	} );

	it( 'acepta una fila exterior sin filas interiores', function () {
		// El oculto del repetidor interior llega como cadena vacía. Era
		// exactamente el envío que rompía el guardado.
		$errors = ( $this->write )( $this->blocks, array( array( 'titulo' => 'Solo', 'items' => '' ) ) );

		expect( $errors )->toBe( array() )
			->and( $this->store['bloques_0_items'] )->toBe( 0 );
	} );

	it( 'al quitar una fila exterior borra también las claves de dentro', function () {
		( $this->write )(
			$this->blocks,
			array(
				array( 'titulo' => 'Uno', 'items' => array( array( 'item' => 'a' ) ) ),
				array( 'titulo' => 'Dos', 'items' => array( array( 'item' => 'b' ), array( 'item' => 'c' ) ) ),
			)
		);

		( $this->write )( $this->blocks, array( array( 'titulo' => 'Uno', 'items' => array( array( 'item' => 'a' ) ) ) ) );

		// Ni una clave de la fila 1: si quedaran, reaparecerían al volver a
		// añadir una segunda fila.
		expect( array_filter( array_keys( $this->store ), fn ( $key ) => str_starts_with( $key, 'bloques_1_' ) ) )->toBe( array() )
			->and( $this->store['bloques'] )->toBe( 1 );
	} );

	it( 'al quitar filas interiores borra sus claves', function () {
		( $this->write )(
			$this->blocks,
			array( array( 'items' => array( array( 'item' => 'a' ), array( 'item' => 'b' ) ) ) )
		);

		( $this->write )( $this->blocks, array( array( 'items' => array( array( 'item' => 'a' ) ) ) ) );

		expect( $this->store )->not->toHaveKey( 'bloques_0_items_1_item' )
			->and( $this->store['bloques_0_items'] )->toBe( 1 );
	} );

	it( 'devuelve los errores del repetidor interior', function () {
		$field = forja_test_field(
			array(
				'type'       => 'repeater',
				'name'       => 'bloques',
				'sub_fields' => array(
					array(
						'type'       => 'repeater',
						'name'       => 'items',
						'label'      => 'Elementos',
						'min'        => 2,
						'sub_fields' => array( array( 'type' => 'text', 'name' => 'item' ) ),
					),
				),
			)
		);

		$errors = ( $this->write )( $field, array( array( 'items' => array( array( 'item' => 'a' ) ) ) ) );

		expect( $errors )->toBe( array( 'Elementos necesita al menos 2 filas.' ) )
			// El interior que no valida no escribe nada, como cualquier compuesto.
			->and( $this->store )->not->toHaveKey( 'bloques_0_items_0_item' );
	} );
} );

it( 'guarda un grupo dentro de un repetidor con las claves de ACF', function () {
	$field = forja_test_field(
		array(
			'type'       => 'repeater',
			'name'       => 'personas',
			'sub_fields' => array(
				array(
					'type'       => 'group',
					'name'       => 'contacto',
					'sub_fields' => array( array( 'type' => 'email', 'name' => 'email' ) ),
				),
			),
		)
	);

	( $this->write )( $field, array( array( 'contacto' => array( 'email' => 'hola@oswa.dev' ) ) ) );

	expect( $this->store )->toBe(
		array(
			'personas_0_contacto_email' => 'hola@oswa.dev',
			'personas'                  => 1,
		)
	)->and( $field->read_value( $this->get ) )->toBe(
		array( array( 'contacto' => array( 'email' => 'hola@oswa.dev' ) ) )
	);
} );

it( 'guarda un repetidor dentro de un grupo', function () {
	$field = forja_test_field(
		array(
			'type'       => 'group',
			'name'       => 'menu',
			'sub_fields' => array(
				array(
					'type'       => 'repeater',
					'name'       => 'enlaces',
					'sub_fields' => array( array( 'type' => 'text', 'name' => 'texto' ) ),
				),
			),
		)
	);

	( $this->write )( $field, array( 'enlaces' => array( array( 'texto' => 'Inicio' ) ) ) );

	expect( $this->store )->toBe(
		array(
			'menu_enlaces_0_texto' => 'Inicio',
			'menu_enlaces'         => 1,
		)
	);
} );

describe( 'repetidor dentro de contenido flexible', function () {
	beforeEach( function () {
		$this->sections = forja_test_field(
			array(
				'type'    => 'flexible_content',
				'name'    => 'secciones',
				'layouts' => array(
					'lista' => array(
						'label'      => 'Lista',
						'sub_fields' => array(
							array(
								'type'       => 'repeater',
								'name'       => 'items',
								'sub_fields' => array( array( 'type' => 'text', 'name' => 'item' ) ),
							),
						),
					),
					'texto' => array(
						'label'      => 'Texto',
						'sub_fields' => array( array( 'type' => 'text', 'name' => 'cuerpo' ) ),
					),
				),
			)
		);
	} );

	it( 'guarda con las claves de ACF y lo lee de vuelta', function () {
		( $this->write )(
			$this->sections,
			array( array( FlexibleContent::LAYOUT_KEY => 'lista', 'items' => array( array( 'item' => 'a' ) ) ) )
		);

		expect( $this->store )->toBe(
			array(
				'secciones_0_items_0_item' => 'a',
				'secciones_0_items'        => 1,
				'secciones'                => array( 'lista' ),
			)
		)->and( $this->sections->read_value( $this->get ) )->toBe(
			array( array( FlexibleContent::LAYOUT_KEY => 'lista', 'items' => array( array( 'item' => 'a' ) ) ) )
		);
	} );

	it( 'al cambiar la capa de una posición borra el repetidor de la anterior', function () {
		( $this->write )(
			$this->sections,
			array( array( FlexibleContent::LAYOUT_KEY => 'lista', 'items' => array( array( 'item' => 'a' ) ) ) )
		);

		( $this->write )(
			$this->sections,
			array( array( FlexibleContent::LAYOUT_KEY => 'texto', 'cuerpo' => 'Hola' ) )
		);

		expect( $this->store )->toBe(
			array(
				'secciones'          => array( 'texto' ),
				'secciones_0_cuerpo' => 'Hola',
			)
		);
	} );
} );
