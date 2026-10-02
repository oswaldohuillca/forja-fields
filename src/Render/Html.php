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
	 * Atributo que sustituye a `required` dentro de una plantilla.
	 *
	 * El JavaScript lo vuelve a convertir en `required` al clonar la plantilla.
	 */
	public const TEMPLATE_REQUIRED = 'data-forja-required';

	/**
	 * Cuántas plantillas se están pintando ahora mismo, una dentro de otra.
	 *
	 * @var int
	 */
	private static int $template_depth = 0;

	/**
	 * Pinta una plantilla de fila o de capa.
	 *
	 * Las plantillas van ocultas en el formulario, pero el navegador también
	 * valida sus controles: un `required` en una fila vacía que nadie ve
	 * cancela el envío sin decir nada. Mientras se pinta una plantilla,
	 * `attributes()` emite `required` como `data-forja-required`.
	 *
	 * Es un contador y no un booleano porque las plantillas se anidan: un
	 * repetidor dentro de otro pinta la suya dentro de la de fuera.
	 *
	 * @param callable(): void $render Función que pinta la plantilla.
	 * @return void
	 */
	public static function template( callable $render ): void {
		++self::$template_depth;

		try {
			$render();
		} finally {
			--self::$template_depth;
		}
	}

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

			if ( 'required' === $name && self::$template_depth > 0 ) {
				$name = self::TEMPLATE_REQUIRED;
			}

			$parts[] = sprintf( '%s="%s"', esc_attr( (string) $name ), esc_attr( (string) $value ) );
		}

		return implode( ' ', $parts );
	}
}
