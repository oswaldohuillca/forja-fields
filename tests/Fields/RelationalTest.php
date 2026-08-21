<?php
/**
 * Campos que apuntan a otros objetos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

beforeEach( function () {
	$this->post_id = wp_insert_post(
		array(
			'post_title'  => 'Entrada relacionada',
			'post_type'   => 'post',
			'post_status' => 'publish',
		)
	);

	$this->other_id = wp_insert_post(
		array(
			'post_title'  => 'Otra entrada',
			'post_type'   => 'post',
			'post_status' => 'publish',
		)
	);
} );

afterEach( function () {
	foreach ( array( 'post_id', 'other_id' ) as $key ) {
		if ( ! empty( $this->{$key} ) ) {
			wp_delete_post( $this->{$key}, true );
		}
	}

	if ( ! empty( $this->term_id ) ) {
		wp_delete_term( $this->term_id, 'category' );
	}

	if ( ! empty( $this->user_id ) ) {
		// Vive en wp-admin, que no se carga al arrancar WordPress a secas.
		require_once ABSPATH . 'wp-admin/includes/user.php';

		wp_delete_user( $this->user_id );
	}
} );

it( 'guarda el identificador de la entrada elegida', function () {
	$field = forja_test_field(
		array(
			'type'      => 'post_object',
			'name'      => 'destacada',
			'post_type' => array( 'post' ),
		)
	);

	expect( $field->sanitize( (string) $this->post_id ) )->toBe( (string) $this->post_id );
} );

it( 'descarta un identificador que no existe', function () {
	$field = forja_test_field(
		array(
			'type'      => 'post_object',
			'name'      => 'destacada',
			'post_type' => array( 'post' ),
		)
	);

	expect( $field->sanitize( '99999999' ) )->toBe( '' );
} );

it( 'descarta una entrada de un tipo que el campo no admite', function () {
	$field = forja_test_field(
		array(
			'type'      => 'post_object',
			'name'      => 'destacada',
			// La entrada de prueba es de tipo «post», no «page».
			'post_type' => array( 'page' ),
		)
	);

	expect( $field->sanitize( (string) $this->post_id ) )->toBe( '' );
} );

it( 'devuelve un entero con el formato por defecto', function () {
	$field = forja_test_field(
		array(
			'type'      => 'post_object',
			'name'      => 'destacada',
			'post_type' => array( 'post' ),
		)
	);

	expect( $field->format_value( (string) $this->post_id ) )->toBe( $this->post_id );
} );

it( 'devuelve el objeto cuando se pide así', function () {
	$field = forja_test_field(
		array(
			'type'          => 'post_object',
			'name'          => 'destacada',
			'post_type'     => array( 'post' ),
			'return_format' => 'object',
		)
	);

	$value = $field->format_value( (string) $this->post_id );

	expect( $value )->toBeInstanceOf( WP_Post::class )
		->and( $value->post_title )->toBe( 'Entrada relacionada' );
} );

it( 'descarta al leer una entrada que ya se borró', function () {
	$field = forja_test_field(
		array(
			'type'      => 'post_object',
			'name'      => 'destacada',
			'post_type' => array( 'post' ),
			'multiple'  => true,
		)
	);

	$stored = array( (string) $this->post_id, '99999999' );

	expect( $field->format_value( $stored ) )->toBe( array( $this->post_id ) );
} );

it( 'guarda una lista cuando es múltiple', function () {
	$field = forja_test_field(
		array(
			'type'      => 'post_object',
			'name'      => 'destacadas',
			'post_type' => array( 'post' ),
			'multiple'  => true,
		)
	);

	$saneado = $field->sanitize( array( (string) $this->post_id, (string) $this->other_id ) );

	expect( $saneado )->toBe( array( (string) $this->post_id, (string) $this->other_id ) );
} );

it( 'respeta el máximo de una selección múltiple', function () {
	$field = forja_test_field(
		array(
			'type'      => 'post_object',
			'name'      => 'destacadas',
			'label'     => 'Destacadas',
			'post_type' => array( 'post' ),
			'multiple'  => true,
			'max'       => 1,
		)
	);

	expect( $field->validate( array( '1', '2' ) ) )->toContain( 'Destacadas' );
} );

it( 'encuentra entradas por su título', function () {
	$field = forja_test_field(
		array(
			'type'      => 'post_object',
			'name'      => 'destacada',
			'post_type' => array( 'post' ),
		)
	);

	$ids = array_column( $field->search( 'Entrada relacionada', 1 ), 'id' );

	expect( $ids )->toContain( (string) $this->post_id );
} );

it( 'no pinta el catálogo entero, sólo lo elegido', function () {
	$field = forja_test_field(
		array(
			'type'      => 'post_object',
			'name'      => 'destacada',
			'post_type' => array( 'post' ),
		)
	);

	$markup = forja_test_render( $field, (string) $this->post_id );

	// Una sola opción: la que ya está elegida. Lo demás llega buscando.
	expect( substr_count( $markup, '<option' ) )->toBe( 1 )
		->and( $markup )->toContain( 'Entrada relacionada' )
		->and( $markup )->not->toContain( 'Otra entrada' );
} );

it( 'el enlace devuelve la dirección, no el identificador', function () {
	$field = forja_test_field(
		array(
			'type'      => 'page_link',
			'name'      => 'enlace',
			'post_type' => array( 'post' ),
		)
	);

	expect( $field->format_value( (string) $this->post_id ) )
		->toBe( get_permalink( $this->post_id ) );
} );

it( 'el enlace guarda el identificador, no la dirección', function () {
	$field = forja_test_field(
		array(
			'type'      => 'page_link',
			'name'      => 'enlace',
			'post_type' => array( 'post' ),
		)
	);

	// Guardar la URL dejaría el sitio con enlaces rotos al cambiar un slug.
	expect( $field->sanitize( (string) $this->post_id ) )->toBe( (string) $this->post_id );
} );

it( 'el usuario guarda su identificador y devuelve el objeto', function () {
	$this->user_id = wp_insert_user(
		array(
			'user_login' => 'forja_rel_' . wp_generate_password( 6, false ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'editor',
		)
	);

	$field = forja_test_field(
		array(
			'type'          => 'user',
			'name'          => 'responsable',
			'return_format' => 'object',
		)
	);

	expect( $field->sanitize( (string) $this->user_id ) )->toBe( (string) $this->user_id )
		->and( $field->format_value( (string) $this->user_id ) )->toBeInstanceOf( WP_User::class );
} );

it( 'la taxonomía pinta casillas por defecto', function () {
	$term = wp_insert_term( 'Forja rel ' . wp_generate_password( 6, false ), 'category' );

	$this->term_id = (int) $term['term_id'];

	$field = forja_test_field(
		array(
			'type'     => 'taxonomy',
			'name'     => 'temas',
			'taxonomy' => 'category',
		)
	);

	$markup = forja_test_render( $field, array( (string) $this->term_id ) );

	expect( $markup )->toContain( 'acf-checkbox-list' )
		->and( $markup )->toContain( 'value="' . $this->term_id . '"' )
		->and( $markup )->toContain( 'checked' );
} );

it( 'la taxonomía con radio guarda un solo término', function () {
	$field = forja_test_field(
		array(
			'type'       => 'taxonomy',
			'name'       => 'tema',
			'taxonomy'   => 'category',
			'field_type' => 'radio',
		)
	);

	// Con radio la selección es simple, aunque lleguen varios valores.
	expect( $field->sanitize( array( '1', '2' ) ) )->toBeString();
} );

it( 'la relación guarda una lista y conserva el orden', function () {
	$field = forja_test_field(
		array(
			'type'      => 'relationship',
			'name'      => 'relacionadas',
			'post_type' => array( 'post' ),
		)
	);

	$enviado = array( (string) $this->other_id, (string) $this->post_id );

	// El orden es el dato: es lo que distingue este campo de un post_object
	// múltiple.
	expect( $field->sanitize( $enviado ) )->toBe( $enviado );
} );

it( 'la relación pinta lo elegido en el servidor', function () {
	$field = forja_test_field(
		array(
			'type'      => 'relationship',
			'name'      => 'relacionadas',
			'post_type' => array( 'post' ),
		)
	);

	$markup = forja_test_render( $field, array( (string) $this->post_id ) );

	// Aunque el panel de disponibles lo rellene el JavaScript, el valor tiene
	// que verse sin él.
	expect( $markup )->toContain( 'acf-relationship' )
		->and( $markup )->toContain( 'values-list' )
		->and( $markup )->toContain( 'Entrada relacionada' );
} );

it( 'cada campo protege su búsqueda con un nonce propio', function () {
	$uno = forja_test_field( array( 'type' => 'post_object', 'name' => 'uno' ) );
	$dos = forja_test_field( array( 'type' => 'post_object', 'name' => 'dos' ) );

	expect( $uno->search_action() )->not->toBe( $dos->search_action() );
} );
