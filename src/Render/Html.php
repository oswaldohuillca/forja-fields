<?php
/**
 * Utilidades de escapado para el markup de campos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente a los helpers de `acf-input-functions.php`.
 */
final class Html {

	/**
	 * Convierte un array asociativo en atributos HTML escapados.
	 *
	 * Los atributos con valor cadena vacía se omiten, salvo los que
	 * legítimamente pueden ir vacíos (como «value»).
	 *
	 * @param array<string, string|int|bool> $attributes Pares atributo => valor.
	 * @return string Atributos listos para interpolar en una etiqueta.
	 */
	public static function attributes( array $attributes ): string {
		$always_render = array( 'value', 'placeholder' );
		$parts         = array();

		foreach ( $attributes as $name => $value ) {
			if ( false === $value || null === $value ) {
				continue;
			}

			if ( '' === (string) $value && ! in_array( $name, $always_render, true ) ) {
				continue;
			}

			$parts[] = sprintf( '%s="%s"', esc_attr( (string) $name ), esc_attr( (string) $value ) );
		}

		return implode( ' ', $parts );
	}
}
