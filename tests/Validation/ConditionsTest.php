<?php
/**
 * Evaluación de la lógica condicional en el servidor.
 *
 * Tiene que dar lo mismo que `conditions.ts`: si discreparan, el navegador
 * dejaría enviar algo que el servidor rechaza, o al revés.
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Validation\Conditions;

describe( 'operadores', function () {
	it( 'compara igualdad y desigualdad contra todos los valores', function () {
		expect( Conditions::matches( array( 'a', 'b' ), '==', 'b' ) )->toBeTrue()
			->and( Conditions::matches( array( 'a' ), '==', 'b' ) )->toBeFalse()
			->and( Conditions::matches( array( 'a', 'b' ), '!=', 'b' ) )->toBeFalse()
			->and( Conditions::matches( array(), '!=', 'b' ) )->toBeTrue();
	} );

	it( 'busca texto contenido', function () {
		expect( Conditions::matches( array( 'video-largo' ), 'contains', 'video' ) )->toBeTrue()
			->and( Conditions::matches( array( 'audio' ), '!contains', 'video' ) )->toBeTrue();
	} );

	it( 'distingue vacío de relleno', function () {
		expect( Conditions::matches( array(), 'empty', '' ) )->toBeTrue()
			->and( Conditions::matches( array( 'x' ), '!empty', '' ) )->toBeTrue();
	} );

	it( 'compara números como parseFloat', function () {
		expect( Conditions::matches( array( '12px' ), '>', '10' ) )->toBeTrue()
			->and( Conditions::matches( array( '3.5' ), '<=', '3.5' ) )->toBeTrue()
			// NaN: ninguna comparación se cumple. Con (float) daría 0 y sí.
			->and( Conditions::matches( array( 'px12' ), '<', '10' ) )->toBeFalse()
			->and( Conditions::matches( array(), '<', '10' ) )->toBeFalse();
	} );
} );

describe( 'grupos y campos', function () {
	beforeEach( function () {
		$this->toggle = forja_test_field( array( 'type' => 'true_false', 'name' => 'activo' ) );
		$this->kind   = forja_test_field(
			array(
				'type'    => 'select',
				'name'    => 'tipo',
				'choices' => array( 'video' => 'Vídeo', 'audio' => 'Audio' ),
			)
		);
		$this->fields = array(
			'activo' => $this->toggle,
			'tipo'   => $this->kind,
		);
		$this->field  = fn ( array $logic ) => forja_test_field(
			array( 'type' => 'text', 'name' => 'dependiente', 'conditional_logic' => $logic )
		);
	} );

	it( 'un campo sin reglas siempre se ve', function () {
		expect( Conditions::is_visible( forja_test_field( array( 'type' => 'text' ) ), array(), array() ) )->toBeTrue();
	} );

	it( 'basta con un grupo, y dentro del grupo hacen falta todas', function () {
		$field = ( $this->field )(
			array(
				array(
					array( 'field' => 'activo', 'value' => '1' ),
					array( 'field' => 'tipo', 'value' => 'video' ),
				),
				array( array( 'field' => 'tipo', 'value' => 'audio' ) ),
			)
		);

		expect( Conditions::is_visible( $field, array( 'activo' => '1', 'tipo' => 'video' ), $this->fields ) )->toBeTrue()
			->and( Conditions::is_visible( $field, array( 'activo' => '0', 'tipo' => 'video' ), $this->fields ) )->toBeFalse()
			->and( Conditions::is_visible( $field, array( 'activo' => '0', 'tipo' => 'audio' ), $this->fields ) )->toBeTrue();
	} );

	it( 'lee el true_false como el navegador, con su oculto a cero', function () {
		// Apagado el navegador ve «0»; encendido, «0» y «1».
		$off = ( $this->field )( array( 'field' => 'activo', 'value' => '0' ) );

		expect( Conditions::is_visible( $off, array( 'activo' => '0' ), $this->fields ) )->toBeTrue()
			->and( Conditions::is_visible( $off, array( 'activo' => '1' ), $this->fields ) )->toBeTrue()
			->and( Conditions::is_visible( ( $this->field )( array( 'field' => 'activo', 'operator' => 'empty' ) ), array( 'activo' => '0' ), $this->fields ) )->toBeFalse();
	} );

	it( 'nunca cumple una regla sobre un campo que no se envió', function () {
		$field = ( $this->field )( array( 'field' => 'no-existe', 'operator' => '!=', 'value' => 'x' ) );

		expect( Conditions::is_visible( $field, array(), $this->fields ) )->toBeFalse();
	} );

	it( 'aplana los valores de un campo con varios controles', function () {
		$field = ( $this->field )( array( 'field' => 'enlace', 'operator' => 'contains', 'value' => 'oswa' ) );

		// Un link manda título, URL y destino; el navegador los lee todos.
		expect(
			Conditions::is_visible( $field, array( 'enlace' => array( 'title' => 'Web', 'url' => 'https://oswa.dev', 'target' => '' ) ), array() )
		)->toBeTrue();
	} );
} );
