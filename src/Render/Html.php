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
	 * Los atributos vacíos se omiten, igual que hace `acf_clean_atts()`. Un
	 * `value=""` o un `placeholder=""` sobrante no cambia nada visualmente,
	 * pero ensucia el markup y rompe la comparación con el original.
	 *
	 * Algunos atributos son ganchos que el JavaScript lee siempre y deben
	 * emitirse aunque estén vacíos; para esos está `$keep_empty`.
	 *
	 * @param array<string, string|int|bool|null> $attributes Pares atributo => valor.
	 * @param array<int, string>                  $keep_empty Claves que se emiten aunque vayan vacías.
	 * @return string Atributos listos para interpolar en una etiqueta.
	 */
	public static function attributes( array $attributes, array $keep_empty = array() ): string {
		$parts = array();

		foreach ( $attributes as $name => $value ) {
			if ( false === $value || null === $value ) {
				continue;
			}

			if ( '' === (string) $value && ! in_array( $name, $keep_empty, true ) ) {
				continue;
			}

			$parts[] = sprintf( '%s="%s"', esc_attr( (string) $name ), esc_attr( (string) $value ) );
		}

		return implode( ' ', $parts );
	}
}
