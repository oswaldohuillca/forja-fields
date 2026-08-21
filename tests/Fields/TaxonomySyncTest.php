<?php
/**
 * `save_terms` y `load_terms`: cuando el campo toca el objeto, no sólo su
 * metadato.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Context\PostContext;
use Forja\Fields\ObjectAware;
use Forja\Registry\BoxRegistry;
use Forja\Registry\FieldRegistry;
use Forja\Render\Renderer;
use Forja\Storage\StorageFactory;
use Forja\Validation\Validator;

beforeEach( function () {
	$this->post_id = wp_insert_post(
		array(
			'post_title'  => 'Entrada con términos',
			'post_type'   => 'post',
			'post_status' => 'publish',
		)
	);

	// WordPress asigna la categoría por defecto a toda entrada nueva. Se quita
	// para partir de un estado conocido; si no, el primer test vería un término
	// que no puso nadie.
	wp_set_object_terms( $this->post_id, array(), 'category' );

	$this->terms = array();

	foreach ( array( 'uno', 'dos' ) as $slug ) {
		$term = wp_insert_term( 'Forja ' . $slug . ' ' . wp_generate_password( 6, false ), 'category' );

		$this->terms[ $slug ] = (int) $term['term_id'];
	}

	$this->field = function ( array $args ) {
		return forja_test_field(
			array_merge(
				array(
					'type'     => 'taxonomy',
					'name'     => 'temas',
					'taxonomy' => 'category',
				),
				$args
			)
		);
	};
} );

afterEach( function () {
	if ( ! empty( $this->post_id ) ) {
		wp_delete_post( $this->post_id, true );
	}

	foreach ( $this->terms ?? array() as $term_id ) {
		wp_delete_term( $term_id, 'category' );
	}
} );

it( 'no toca los términos de la entrada si no se pide', function () {
	$field = ( $this->field )( array() );

	$field->write_to_object( $this->post_id, 'post', array( (string) $this->terms['uno'] ) );

	// Sin `save_terms`, el campo es un metadato y nada más.
	expect( wp_get_object_terms( $this->post_id, 'category', array( 'fields' => 'ids' ) ) )
		->toBe( array() );
} );

it( 'asigna los términos a la entrada con save_terms', function () {
	$field = ( $this->field )( array( 'save_terms' => true ) );

	$field->write_to_object(
		$this->post_id,
		'post',
		array( (string) $this->terms['uno'], (string) $this->terms['dos'] )
	);

	$assigned = wp_get_object_terms( $this->post_id, 'category', array( 'fields' => 'ids' ) );

	sort( $assigned );

	$expected = array( $this->terms['uno'], $this->terms['dos'] );

	sort( $expected );

	expect( $assigned )->toBe( $expected );
} );

it( 'reemplaza los términos en vez de añadirlos', function () {
	$field = ( $this->field )( array( 'save_terms' => true ) );

	wp_set_object_terms( $this->post_id, array( $this->terms['uno'] ), 'category' );

	$field->write_to_object( $this->post_id, 'post', array( (string) $this->terms['dos'] ) );

	// El campo representa el estado completo de esa taxonomía: quitar un
	// término del formulario tiene que quitarlo de verdad.
	expect( wp_get_object_terms( $this->post_id, 'category', array( 'fields' => 'ids' ) ) )
		->toBe( array( $this->terms['dos'] ) );
} );

it( 'vaciar la selección borra los términos asignados', function () {
	$field = ( $this->field )( array( 'save_terms' => true ) );

	wp_set_object_terms( $this->post_id, array( $this->terms['uno'] ), 'category' );

	$field->write_to_object( $this->post_id, 'post', array() );

	expect( wp_get_object_terms( $this->post_id, 'category', array( 'fields' => 'ids' ) ) )
		->toBe( array() );
} );

it( 'no hace nada fuera de una entrada', function () {
	$field = ( $this->field )( array( 'save_terms' => true ) );

	// Un término, un usuario o una página de opciones no tienen taxonomías;
	// la llamada tiene que ser inofensiva, no fatal.
	$field->write_to_object( 1, 'user', array( (string) $this->terms['uno'] ) );
	$field->write_to_object( 'forja_ajustes', 'option', array( (string) $this->terms['uno'] ) );

	expect( true )->toBeTrue();
} );

it( 'lee del metadato si no se pide load_terms', function () {
	$field = ( $this->field )( array() );

	expect( $field->read_from_object( $this->post_id, 'post' ) )->toBeNull();
} );

it( 'lee los términos de la entrada con load_terms', function () {
	$field = ( $this->field )( array( 'load_terms' => true ) );

	wp_set_object_terms( $this->post_id, array( $this->terms['uno'] ), 'category' );

	expect( $field->read_from_object( $this->post_id, 'post' ) )
		->toBe( array( (string) $this->terms['uno'] ) );
} );

it( 'con load_terms y control simple devuelve un solo valor', function () {
	$field = ( $this->field )(
		array(
			'load_terms' => true,
			'field_type' => 'radio',
		)
	);

	wp_set_object_terms( $this->post_id, array( $this->terms['uno'] ), 'category' );

	expect( $field->read_from_object( $this->post_id, 'post' ) )
		->toBe( (string) $this->terms['uno'] );
} );

it( 'declara el contrato para que el contexto lo reconozca', function () {
	expect( ( $this->field )( array() ) )->toBeInstanceOf( ObjectAware::class );
} );

it( 'guardar desde la pantalla asigna los términos de verdad', function () {
	$registry = new BoxRegistry( new FieldRegistry() );

	$registry->register(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array(
					'type'       => 'taxonomy',
					'name'       => 'temas',
					'taxonomy'   => 'category',
					'save_terms' => true,
				),
			),
		)
	);

	$GLOBALS['forja_test_admin'] ??= wp_insert_user(
		array(
			'user_login' => 'forja_tester_' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		)
	);

	wp_set_current_user( (int) $GLOBALS['forja_test_admin'] );

	$_POST                      = array();
	$_POST['forja']             = array( 'temas' => array( (string) $this->terms['uno'] ) );
	$_POST['forja_nonce_ficha'] = wp_create_nonce( 'forja_save_ficha' );

	$context = new PostContext( $registry, new Renderer(), new StorageFactory(), new Validator() );
	$context->save( $this->post_id, get_post( $this->post_id ) );

	$_POST = array();

	// El ciclo entero: el metadato se guarda y el término queda asignado.
	expect( get_post_meta( $this->post_id, 'temas', true ) )->toBe( array( (string) $this->terms['uno'] ) )
		->and( wp_get_object_terms( $this->post_id, 'category', array( 'fields' => 'ids' ) ) )
		->toBe( array( $this->terms['uno'] ) );
} );
