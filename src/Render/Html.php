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
	 * @param array<string, string|int|bool|null> $attributes Pares atributo => valor.
	 * @return string Atributos listos para interpolar en una etiqueta.
	 */
	public static function attributes( array $attributes ): string {
		$parts = array();

		foreach ( $attributes as $name => $value ) {
			if ( false === $value || null === $value || '' === (string) $value ) {
				continue;
			}

			$parts[] = sprintf( '%s="%s"', esc_attr( (string) $name ), esc_attr( (string) $value ) );
		}

		return implode( ' ', $parts );
	}
}
