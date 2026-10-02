<?php
/**
 * Opciones con claves numéricas en los campos de elección.
 *
 * PHP convierte en entero toda clave que parezca un número, aunque se escriba
 * `'6'`, y el valor guardado siempre es texto. Comparar los dos sin convertir
 * no coincidía nunca: el `select` y el `button_group` no marcaban la opción
 * guardada, y el `radio` y el `checkbox` lanzaban un `TypeError` al pintar.
 *
 * @package Forja
 */

declare( strict_types = 1 );

/**
 * Los cuatro tipos con sus dos formas de declarar las opciones.
 *
 * Cada caso lleva el valor guardado, una opción que no debe salir marcada y un
 * valor que no es una opción.
 */
dataset(
	'numeric_choices',
	function (): array {
		$cases = array();

		foreach ( array( 'select', 'radio', 'checkbox', 'button_group' ) as $type ) {
			$cases[ "$type con claves numéricas" ] = array( $type, array( '1' => 'Uno', '6' => 'Seis' ), '6', '1', '7' );
			$cases[ "$type con una lista de números" ] = array( $type, array( 10, 20, 30 ), '20', '10', '25' );
		}

		return $cases;
	}
);

/**
 * Si el control de una opción sale marcado en el markup.
 *
 * `Html::attributes()` emite `value` antes que `checked`; en el `<select>` el
 * `selected` va detrás del `value` de la opción.
 *
 * @param string $html  Markup del campo.
 * @param string $value Valor de la opción.
 * @return bool Si está marcada.
 */
function forja_test_is_marked( string $html, string $value ): bool {
	return 1 === preg_match(
		'/value="' . preg_quote( $value, '/' ) . '"[^>]*\b(checked="checked"|selected="selected")/',
		$html
	);
}

it(
	'marca la opción del valor guardado',
	function ( string $type, array $choices, string $saved, string $other ) {
		$field = forja_test_field( array( 'type' => $type, 'choices' => $choices ) );

		// El checkbox guarda una lista; los demás, un valor suelto.
		$html = forja_test_render( $field, 'checkbox' === $type ? array( $saved ) : $saved );

		expect( forja_test_is_marked( $html, $saved ) )->toBeTrue()
			->and( forja_test_is_marked( $html, $other ) )->toBeFalse();
	}
)->with( 'numeric_choices' );

it(
	'acepta la opción numérica y rechaza la que no existe',
	function ( string $type, array $choices, string $saved, string $other, string $missing ) {
		unset( $other );

		$field = forja_test_field( array( 'type' => $type, 'choices' => $choices ) );

		if ( 'checkbox' === $type ) {
			expect( $field->sanitize( array( $saved, $missing ) ) )->toBe( array( $saved ) );

			return;
		}

		expect( $field->sanitize( $saved ) )->toBe( $saved )
			->and( $field->sanitize( $missing ) )->toBe( '' );
	}
)->with( 'numeric_choices' );

it( 'no confunde una opción con su forma no canónica', function () {
	$field = forja_test_field( array( 'type' => 'select', 'choices' => array( '6' => 'Seis' ) ) );

	// La clave entera 6 sólo responde a «6»: «06» o « 6» no son esa opción.
	expect( $field->sanitize( '06' ) )->toBe( '' )
		->and( $field->sanitize( ' 6' ) )->toBe( '' );
} );

it( 'conserva los decimales de una lista de opciones', function () {
	$field = forja_test_field( array( 'type' => 'select', 'choices' => array( 1.5, 2 ) ) );

	$html = forja_test_render( $field, '1.5' );

	// Una clave decimal sí se queda como texto («1.5»): sólo los enteros
	// cambian de tipo. Que no se trunque a la opción 1.
	expect( forja_test_is_marked( $html, '1.5' ) )->toBeTrue()
		->and( $field->sanitize( '1.5' ) )->toBe( '1.5' )
		->and( $field->sanitize( '1' ) )->toBe( '' );
} );
