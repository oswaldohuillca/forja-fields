<?php
/**
 * Ciclo completo de guardado en las pantallas del escritorio.
 *
 * Los demás tests comprueban las piezas por separado: un campo sanea, un
 * almacén escribe, un validador protesta. Aquí se recorre el camino entero —
 * envío, nonce, permisos, saneado, validación y escritura— porque es donde
 * viven los fallos que ninguna pieza ve por su cuenta.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Context\PostContext;
use Forja\Context\TermContext;
use Forja\Context\UserContext;
use Forja\Fields\Composite;
use Forja\Registry\BoxRegistry;
use Forja\Registry\FieldRegistry;
use Forja\Render\Renderer;
use Forja\Storage\StorageFactory;
use Forja\Validation\Validator;

/**
 * Construye un registro con una única caja.
 *
 * @param string               $id   Identificador de la caja.
 * @param array<string, mixed> $args Configuración de la caja.
 * @return BoxRegistry Registro con la caja declarada.
 */
function forja_test_registry( string $id, array $args ): BoxRegistry {
	$registry = new BoxRegistry( new FieldRegistry() );

	$registry->register( $id, $args );

	return $registry;
}

/**
 * Prepara el envío de una caja en `$_POST`, con su nonce.
 *
 * El contexto de términos usa otro nombre para el campo oculto, así que se
 * puede indicar. La acción del nonce sí es la misma en todas las pantallas.
 *
 * @param string               $box_id Identificador de la caja.
 * @param array<string, mixed> $values Valores enviados.
 * @param string               $field  Nombre del campo oculto del nonce.
 * @return void
 */
function forja_test_submit( string $box_id, array $values, string $field = 'forja_nonce_' ): void {
	$_POST                        = array();
	$_POST['forja']               = $values;
	$_POST[ $field . $box_id ]    = wp_create_nonce( 'forja_save_' . $box_id );
}

/**
 * Clave donde el contexto de entradas deja los errores de la última grabación.
 *
 * @param int $post_id Entrada.
 * @return string Nombre del transitorio.
 */
function forja_test_errors_key( int $post_id ): string {
	return 'forja_errors_' . get_current_user_id() . '_' . $post_id;
}

/**
 * Lee un campo con el formato que declara, usando un registro concreto.
 *
 * `forja_get_field()` consulta el registro global del plugin, y estos tests
 * trabajan con registros propios para no ensuciarlo. Esto hace lo mismo sobre
 * el registro que se le pase.
 *
 * @param BoxRegistry $registry  Registro donde buscar el campo.
 * @param string      $name      Nombre del campo.
 * @param int         $object_id Entrada contenedora.
 * @return mixed Valor formateado.
 */
function forja_test_read( BoxRegistry $registry, string $name, int $object_id ): mixed {
	$storage = ( new StorageFactory() )->for( 'post' );
	$field   = $registry->find_field( $name );

	if ( $field instanceof Composite ) {
		return $field->format_value(
			$field->read_value( static fn ( string $key ): mixed => $storage->get( $object_id, $key ) )
		);
	}

	return $field->format_value( $storage->get( $object_id, $name ) );
}

beforeEach( function () {
	$_POST = array();

	// El guardado comprueba `current_user_can()`, así que sin un usuario con
	// permisos estos tests no probarían nada: pasarían por la puerta falsa.
	$GLOBALS['forja_test_admin'] ??= wp_insert_user(
		array(
			'user_login' => 'forja_tester_' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		)
	);

	$this->user_id = (int) $GLOBALS['forja_test_admin'];

	wp_set_current_user( $this->user_id );

	$this->post_id = wp_insert_post(
		array(
			'post_title'  => 'Entrada de prueba',
			'post_type'   => 'post',
			'post_status' => 'publish',
		)
	);

	$this->post = get_post( $this->post_id );

	$this->save = function ( BoxRegistry $registry, ?int $post_id = null ) {
		$context = new PostContext( $registry, new Renderer(), new StorageFactory(), new Validator() );
		$post_id ??= $this->post_id;

		$context->save( $post_id, get_post( $post_id ) );
	};
} );

afterEach( function () {
	$_POST = array();

	if ( ! empty( $this->post_id ) ) {
		wp_delete_post( $this->post_id, true );
	}

	if ( ! empty( $this->term_id ) ) {
		wp_delete_term( $this->term_id, 'category' );
	}
} );

it( 'guarda lo enviado y lo devuelve con su tipo nativo', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array( 'type' => 'text', 'name' => 'titular' ),
				array( 'type' => 'number', 'name' => 'orden' ),
				array( 'type' => 'true_false', 'name' => 'destacado' ),
			),
		)
	);

	forja_test_submit(
		'ficha',
		array(
			'titular'   => 'Hola mundo',
			'orden'     => '7',
			'destacado' => '1',
		)
	);

	( $this->save )( $registry );

	expect( get_post_meta( $this->post_id, 'titular', true ) )->toBe( 'Hola mundo' )
		->and( forja_test_read( $registry, 'orden', $this->post_id ) )->toBe( 7 )
		->and( forja_test_read( $registry, 'destacado', $this->post_id ) )->toBeTrue();
} );

it( 'sanea antes de escribir', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array( array( 'type' => 'text', 'name' => 'titular' ) ),
		)
	);

	forja_test_submit( 'ficha', array( 'titular' => '<script>alert(1)</script>Limpio' ) );

	( $this->save )( $registry );

	expect( get_post_meta( $this->post_id, 'titular', true ) )->toBe( 'Limpio' );
} );

it( 'no toca nada si el envío no trae el nonce de la caja', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array( array( 'type' => 'text', 'name' => 'titular' ) ),
		)
	);

	update_post_meta( $this->post_id, 'titular', 'Valor bueno' );

	// Es el caso de la edición rápida, la REST API o una importación: la caja
	// nunca se pintó, así que su ausencia no significa «bórralo».
	$_POST          = array();
	$_POST['forja'] = array( 'titular' => '' );

	( $this->save )( $registry );

	expect( get_post_meta( $this->post_id, 'titular', true ) )->toBe( 'Valor bueno' );
} );

it( 'no toca nada si el nonce es de otra caja', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array( array( 'type' => 'text', 'name' => 'titular' ) ),
		)
	);

	update_post_meta( $this->post_id, 'titular', 'Valor bueno' );

	$_POST                      = array();
	$_POST['forja']             = array( 'titular' => 'Intruso' );
	$_POST['forja_nonce_ficha'] = wp_create_nonce( 'forja_save_otra_caja' );

	( $this->save )( $registry );

	expect( get_post_meta( $this->post_id, 'titular', true ) )->toBe( 'Valor bueno' );
} );

it( 'conserva el valor anterior cuando el envío no valida', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array(
					'type'     => 'text',
					'name'     => 'titular',
					'label'    => 'Titular',
					'required' => true,
				),
			),
		)
	);

	update_post_meta( $this->post_id, 'titular', 'Valor bueno' );

	forja_test_submit( 'ficha', array( 'titular' => '   ' ) );

	( $this->save )( $registry );

	expect( get_post_meta( $this->post_id, 'titular', true ) )->toBe( 'Valor bueno' );
} );

it( 'deja el error a mano para pintarlo tras la redirección', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array(
					'type'     => 'text',
					'name'     => 'titular',
					'label'    => 'Titular',
					'required' => true,
				),
			),
		)
	);

	forja_test_submit( 'ficha', array( 'titular' => '' ) );

	( $this->save )( $registry );

	$errors = get_transient( forja_test_errors_key( $this->post_id ) );

	expect( $errors )->toBeArray()
		->and( $errors[0] )->toContain( 'Titular' );

	delete_transient( forja_test_errors_key( $this->post_id ) );
} );

it( 'limpia los errores de la grabación anterior cuando ya no los hay', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array(
					'type'     => 'text',
					'name'     => 'titular',
					'label'    => 'Titular',
					'required' => true,
				),
			),
		)
	);

	set_transient( forja_test_errors_key( $this->post_id ), array( 'Error viejo' ), MINUTE_IN_SECONDS );

	forja_test_submit( 'ficha', array( 'titular' => 'Ahora sí' ) );

	( $this->save )( $registry );

	expect( get_transient( forja_test_errors_key( $this->post_id ) ) )->toBeFalse();
} );

it( 'ignora las cajas que no aplican a ese tipo de contenido', function () {
	$registry = forja_test_registry(
		'solo_paginas',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'page' ),
			'fields'          => array( array( 'type' => 'text', 'name' => 'titular' ) ),
		)
	);

	forja_test_submit( 'solo_paginas', array( 'titular' => 'No debería guardarse' ) );

	( $this->save )( $registry );

	expect( metadata_exists( 'post', $this->post_id, 'titular' ) )->toBeFalse();
} );

it( 'no guarda los campos que son sólo presentación', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array(
					'type'    => 'message',
					'name'    => 'aviso',
					'message' => 'Hola',
				),
				array(
					'type' => 'separator',
					'name' => 'linea',
				),
				array(
					'type' => 'text',
					'name' => 'titular',
				),
			),
		)
	);

	forja_test_submit(
		'ficha',
		array(
			'aviso'   => 'intento',
			'linea'   => 'intento',
			'titular' => 'Bien',
		)
	);

	( $this->save )( $registry );

	expect( metadata_exists( 'post', $this->post_id, 'aviso' ) )->toBeFalse()
		->and( metadata_exists( 'post', $this->post_id, 'linea' ) )->toBeFalse()
		->and( get_post_meta( $this->post_id, 'titular', true ) )->toBe( 'Bien' );
} );

it( 'ignora los campos que no viajan en el envío, en vez de vaciarlos', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array( 'type' => 'text', 'name' => 'titular' ),
				array( 'type' => 'text', 'name' => 'subtitulo' ),
			),
		)
	);

	update_post_meta( $this->post_id, 'subtitulo', 'Intacto' );

	forja_test_submit( 'ficha', array( 'titular' => 'Nuevo' ) );

	( $this->save )( $registry );

	expect( get_post_meta( $this->post_id, 'subtitulo', true ) )->toBe( 'Intacto' );
} );

it( 'guarda un repetidor en el formato de ACF y lo recupera entero', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array(
					'type'       => 'repeater',
					'name'       => 'banners',
					'sub_fields' => array(
						array( 'type' => 'text', 'name' => 'titulo' ),
						array( 'type' => 'number', 'name' => 'peso' ),
					),
				),
			),
		)
	);

	forja_test_submit(
		'ficha',
		array(
			'banners' => array(
				array(
					'titulo' => 'Uno',
					'peso'   => '1',
				),
				array(
					'titulo' => 'Dos',
					'peso'   => '2',
				),
			),
		)
	);

	( $this->save )( $registry );

	// El formato de ACF: la clave del campo guarda el número de filas, y cada
	// subcampo va en su propia clave con el índice por medio.
	expect( get_post_meta( $this->post_id, 'banners', true ) )->toBe( '2' )
		->and( get_post_meta( $this->post_id, 'banners_0_titulo', true ) )->toBe( 'Uno' )
		->and( get_post_meta( $this->post_id, 'banners_1_titulo', true ) )->toBe( 'Dos' );

	$rows = forja_test_read( $registry, 'banners', $this->post_id );

	expect( $rows )->toHaveCount( 2 )
		->and( $rows[1]['titulo'] )->toBe( 'Dos' )
		->and( $rows[1]['peso'] )->toBe( 2 );
} );

it( 'borra las filas que sobran al acortar un repetidor', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array(
					'type'       => 'repeater',
					'name'       => 'banners',
					'sub_fields' => array( array( 'type' => 'text', 'name' => 'titulo' ) ),
				),
			),
		)
	);

	forja_test_submit(
		'ficha',
		array(
			'banners' => array(
				array( 'titulo' => 'Uno' ),
				array( 'titulo' => 'Dos' ),
			),
		)
	);

	( $this->save )( $registry );

	forja_test_submit( 'ficha', array( 'banners' => array( array( 'titulo' => 'Sólo una' ) ) ) );

	( $this->save )( $registry );

	expect( get_post_meta( $this->post_id, 'banners', true ) )->toBe( '1' )
		->and( metadata_exists( 'post', $this->post_id, 'banners_1_titulo' ) )->toBeFalse();
} );

it( 'guarda un grupo con la clave compuesta', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array(
				array(
					'type'       => 'group',
					'name'       => 'direccion',
					'sub_fields' => array(
						array( 'type' => 'text', 'name' => 'calle' ),
						array( 'type' => 'text', 'name' => 'ciudad' ),
					),
				),
			),
		)
	);

	forja_test_submit(
		'ficha',
		array(
			'direccion' => array(
				'calle'  => 'Av. Larco',
				'ciudad' => 'Lima',
			),
		)
	);

	( $this->save )( $registry );

	expect( get_post_meta( $this->post_id, 'direccion_calle', true ) )->toBe( 'Av. Larco' )
		->and( forja_test_read( $registry, 'direccion', $this->post_id )['ciudad'] )->toBe( 'Lima' );
} );

it( 'guarda campos clonados bajo su propio nombre', function () {
	$registry = new BoxRegistry( new FieldRegistry() );

	$registry->sets()->register( 'medidas', array( array( 'type' => 'number', 'name' => 'ancho' ) ) );

	$registry->register(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array( array( 'type' => 'clone', 'clone' => 'medidas' ) ),
		)
	);

	forja_test_submit( 'ficha', array( 'ancho' => '120' ) );

	( $this->save )( $registry );

	expect( get_post_meta( $this->post_id, 'ancho', true ) )->toBe( '120' );
} );

it( 'no guarda sobre una revisión', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array( array( 'type' => 'text', 'name' => 'titular' ) ),
		)
	);

	$revision_id = wp_insert_post(
		array(
			'post_type'   => 'revision',
			'post_status' => 'inherit',
			'post_parent' => $this->post_id,
			'post_name'   => $this->post_id . '-revision-v1',
		)
	);

	forja_test_submit( 'ficha', array( 'titular' => 'De la revisión' ) );

	( $this->save )( $registry, $revision_id );

	expect( metadata_exists( 'post', $revision_id, 'titular' ) )->toBeFalse();

	wp_delete_post( $revision_id, true );
} );

it( 'no guarda si quien envía no puede editar la entrada', function () {
	$registry = forja_test_registry(
		'ficha',
		array(
			'object_type'     => 'post',
			'object_subtypes' => array( 'post' ),
			'fields'          => array( array( 'type' => 'text', 'name' => 'titular' ) ),
		)
	);

	forja_test_submit( 'ficha', array( 'titular' => 'Intruso' ) );

	wp_set_current_user( 0 );

	( $this->save )( $registry );

	expect( metadata_exists( 'post', $this->post_id, 'titular' ) )->toBeFalse();
} );

it( 'guarda en un término con la misma mecánica', function () {
	$registry = forja_test_registry(
		'taxonomia',
		array(
			'object_type'     => 'term',
			'object_subtypes' => array( 'category' ),
			'fields'          => array( array( 'type' => 'text', 'name' => 'color' ) ),
		)
	);

	$term = wp_insert_term( 'Forja ' . wp_generate_password( 6, false ), 'category' );

	// Se apunta para borrarlo en afterEach: si una aserción falla, un borrado
	// al final del test no llega a ejecutarse y el término queda huérfano.
	$this->term_id = (int) $term['term_id'];

	// El contexto de términos nombra su nonce de otra forma; enviarlo con el
	// nombre de las entradas haría que la caja se saltara en silencio.
	forja_test_submit( 'taxonomia', array( 'color' => 'Rojo' ), 'forja_term_nonce_' );

	$context = new TermContext( $registry, new Renderer(), new StorageFactory(), new Validator() );
	$context->save( $this->term_id, (int) $term['term_taxonomy_id'], 'category' );

	expect( get_term_meta( $this->term_id, 'color', true ) )->toBe( 'Rojo' );
} );

it( 'guarda en un usuario con la misma mecánica', function () {
	$registry = forja_test_registry(
		'perfil',
		array(
			'object_type' => 'user',
			'fields'      => array( array( 'type' => 'text', 'name' => 'cargo' ) ),
		)
	);

	forja_test_submit( 'perfil', array( 'cargo' => 'Editora' ) );

	$context = new UserContext( $registry, new Renderer(), new StorageFactory(), new Validator() );
	$context->save( $this->user_id );

	expect( get_user_meta( $this->user_id, 'cargo', true ) )->toBe( 'Editora' );

	delete_user_meta( $this->user_id, 'cargo' );
} );
