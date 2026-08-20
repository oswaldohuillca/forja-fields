<?php
/**
 * Agrupado de la lista plana en pestañas y acordeones.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Render\Layout;

/**
 * Construye una lista de campos a partir de sus definiciones.
 *
 * @param array<int, array<string, mixed>> $definitions Definiciones de campo.
 * @return array<int, \Forja\Fields\Field> Campos instanciados.
 */
function forja_test_fields( array $definitions ): array {
	return array_map( 'forja_test_field', $definitions );
}

it( 'deja los campos sueltos sin pestaña', function () {
	$layout = Layout::parse(
		forja_test_fields(
			array(
				array( 'type' => 'text', 'name' => 'uno' ),
				array( 'type' => 'text', 'name' => 'dos' ),
			)
		)
	);

	expect( $layout['tabs'] )->toBe( array() )
		->and( $layout['nodes'] )->toHaveCount( 2 )
		->and( $layout['nodes'][0]['tab'] )->toBeNull();
} );

it( 'asigna a cada pestaña los campos que le siguen', function () {
	$layout = Layout::parse(
		forja_test_fields(
			array(
				array( 'type' => 'tab', 'name' => 'uno', 'label' => 'Uno' ),
				array( 'type' => 'text', 'name' => 'a' ),
				array( 'type' => 'text', 'name' => 'b' ),
				array( 'type' => 'tab', 'name' => 'dos', 'label' => 'Dos' ),
				array( 'type' => 'text', 'name' => 'c' ),
			)
		)
	);

	expect( $layout['tabs'] )->toHaveCount( 2 )
		->and( $layout['nodes'] )->toHaveCount( 3 )
		->and( array_column( $layout['nodes'], 'tab' ) )->toBe( array( 'uno', 'uno', 'dos' ) );
} );

it( 'cierra el grupo de pestañas con endpoint', function () {
	$layout = Layout::parse(
		forja_test_fields(
			array(
				array( 'type' => 'tab', 'name' => 'uno', 'label' => 'Uno' ),
				array( 'type' => 'text', 'name' => 'a' ),
				array( 'type' => 'tab', 'name' => 'fin', 'endpoint' => true ),
				array( 'type' => 'text', 'name' => 'b' ),
			)
		)
	);

	expect( $layout['tabs'] )->toHaveCount( 1 )
		->and( array_column( $layout['nodes'], 'tab' ) )->toBe( array( 'uno', null ) );
} );

it( 'anida en el acordeón los campos que le siguen', function () {
	$layout = Layout::parse(
		forja_test_fields(
			array(
				array( 'type' => 'accordion', 'name' => 'acc', 'label' => 'Acc' ),
				array( 'type' => 'text', 'name' => 'a' ),
				array( 'type' => 'text', 'name' => 'b' ),
			)
		)
	);

	expect( $layout['nodes'] )->toHaveCount( 1 )
		->and( $layout['nodes'][0]['type'] )->toBe( 'accordion' )
		->and( $layout['nodes'][0]['children'] )->toHaveCount( 2 );
} );

it( 'un acordeón nuevo cierra el anterior', function () {
	$layout = Layout::parse(
		forja_test_fields(
			array(
				array( 'type' => 'accordion', 'name' => 'uno', 'label' => 'Uno' ),
				array( 'type' => 'text', 'name' => 'a' ),
				array( 'type' => 'accordion', 'name' => 'dos', 'label' => 'Dos' ),
				array( 'type' => 'text', 'name' => 'b' ),
				array( 'type' => 'text', 'name' => 'c' ),
			)
		)
	);

	expect( $layout['nodes'] )->toHaveCount( 2 )
		->and( $layout['nodes'][0]['children'] )->toHaveCount( 1 )
		->and( $layout['nodes'][1]['children'] )->toHaveCount( 2 );
} );

it( 'un acordeón con endpoint cierra sin abrir otro', function () {
	$layout = Layout::parse(
		forja_test_fields(
			array(
				array( 'type' => 'accordion', 'name' => 'uno', 'label' => 'Uno' ),
				array( 'type' => 'text', 'name' => 'a' ),
				array( 'type' => 'accordion', 'name' => 'fin', 'endpoint' => true ),
				array( 'type' => 'text', 'name' => 'b' ),
			)
		)
	);

	expect( $layout['nodes'] )->toHaveCount( 2 )
		->and( $layout['nodes'][0]['type'] )->toBe( 'accordion' )
		->and( $layout['nodes'][1]['type'] )->toBe( 'field' );
} );

it( 'los campos de un acordeón heredan la pestaña en curso', function () {
	$layout = Layout::parse(
		forja_test_fields(
			array(
				array( 'type' => 'tab', 'name' => 'seo', 'label' => 'SEO' ),
				array( 'type' => 'accordion', 'name' => 'acc', 'label' => 'Acc' ),
				array( 'type' => 'text', 'name' => 'a' ),
			)
		)
	);

	expect( $layout['nodes'][0]['tab'] )->toBe( 'seo' );
} );
