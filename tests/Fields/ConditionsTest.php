<?php
/**
 * Normalización de las reglas de visibilidad.
 *
 * @package Forja
 */

declare( strict_types = 1 );

it( 'no declara reglas cuando no se configuran', function () {
	expect( forja_test_field( array( 'type' => 'text' ) )->conditions() )->toBe( array() );
} );

it( 'acepta una regla suelta', function () {
	$field = forja_test_field(
		array(
			'type'              => 'text',
			'conditional_logic' => array( 'field' => 'tipo', 'value' => 'video' ),
		)
	);

	expect( $field->conditions() )->toBe(
		array(
			array(
				array( 'field' => 'tipo', 'operator' => '==', 'value' => 'video' ),
			),
		)
	);
} );

it( 'envuelve una lista de reglas como un único grupo en AND', function () {
	$field = forja_test_field(
		array(
			'type'              => 'text',
			'conditional_logic' => array(
				array( 'field' => 'tipo', 'value' => 'video' ),
				array( 'field' => 'activo', 'value' => '1' ),
			),
		)
	);

	$groups = $field->conditions();

	expect( $groups )->toHaveCount( 1 )
		->and( $groups[0] )->toHaveCount( 2 );
} );

it( 'respeta los grupos alternativos en OR', function () {
	$field = forja_test_field(
		array(
			'type'              => 'text',
			'conditional_logic' => array(
				array( array( 'field' => 'tipo', 'value' => 'video' ) ),
				array( array( 'field' => 'tipo', 'value' => 'audio' ) ),
			),
		)
	);

	expect( $field->conditions() )->toHaveCount( 2 );
} );

it( 'usa la igualdad cuando no se indica operador', function () {
	$field = forja_test_field(
		array(
			'type'              => 'text',
			'conditional_logic' => array( 'field' => 'tipo', 'value' => 'video' ),
		)
	);

	expect( $field->conditions()[0][0]['operator'] )->toBe( '==' );
} );

it( 'conserva el operador declarado', function () {
	$field = forja_test_field(
		array(
			'type'              => 'text',
			'conditional_logic' => array( 'field' => 'tipo', 'operator' => '!=', 'value' => 'mapa' ),
		)
	);

	expect( $field->conditions()[0][0]['operator'] )->toBe( '!=' );
} );

it( 'descarta las reglas sin campo observado', function () {
	$field = forja_test_field(
		array(
			'type'              => 'text',
			'conditional_logic' => array(
				array( 'field' => 'tipo', 'value' => 'video' ),
				array( 'value' => 'sin campo' ),
			),
		)
	);

	expect( $field->conditions()[0] )->toHaveCount( 1 );
} );
