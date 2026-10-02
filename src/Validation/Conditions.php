<?php
/**
 * Evaluación de la lógica condicional en el servidor.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Validation;

use Forja\Fields\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Decide si un campo estaba a la vista cuando se envió el formulario.
 *
 * Las condiciones se resuelven en el navegador, mientras se escribe. Pero el
 * servidor también lo necesita: un campo obligatorio que la condición oculta no
 * se puede exigir, o «obligatorio sólo si se muestra» rechazaría el guardado
 * por algo que nadie veía.
 *
 * Es una traducción de `conditions.ts`, regla por regla, y tiene que seguir
 * siéndolo: si las dos evaluaciones discrepan, el navegador deja enviar algo
 * que el servidor rechaza, o al revés. Por eso cada tipo de campo dice cómo
 * lee el navegador su valor (`Field::condition_values()`).
 */
final class Conditions {

	/**
	 * Si el campo se veía con los valores enviados.
	 *
	 * @param Field                $field     Campo con reglas.
	 * @param array<string, mixed> $submitted Valores crudos enviados, por nombre.
	 * @param array<string, Field> $fields    Campos de la caja, por nombre.
	 * @return bool True si no tiene reglas o si un grupo se cumple entero.
	 */
	public static function is_visible( Field $field, array $submitted, array $fields ): bool {
		$groups = $field->conditions();

		if ( array() === $groups ) {
			return true;
		}

		foreach ( $groups as $group ) {
			$met = true;

			foreach ( $group as $rule ) {
				if ( ! self::rule_met( $rule, $submitted, $fields ) ) {
					$met = false;
					break;
				}
			}

			// Basta con que un grupo se cumpla entero.
			if ( $met ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evalúa una regla.
	 *
	 * @param array{field: string, operator: string, value: string} $rule      Regla.
	 * @param array<string, mixed>                                  $submitted Valores crudos enviados.
	 * @param array<string, Field>                                  $fields    Campos de la caja.
	 * @return bool Si se cumple.
	 */
	private static function rule_met( array $rule, array $submitted, array $fields ): bool {
		// Una regla que apunta a un campo que no se envió nunca se cumple, igual
		// que en el navegador con un campo que no está en la pantalla.
		if ( ! array_key_exists( $rule['field'], $submitted ) ) {
			return false;
		}

		$raw    = $submitted[ $rule['field'] ];
		$target = $fields[ $rule['field'] ] ?? null;
		$values = $target ? $target->condition_values( $raw ) : Field::flatten_values( $raw );

		return self::matches( $values, $rule['operator'], $rule['value'] );
	}

	/**
	 * Compara los valores con la regla. Es `matches()` de `conditions.ts`.
	 *
	 * @param array<int, string> $values   Valores del campo observado.
	 * @param string             $operator Operador de la regla.
	 * @param string             $target   Valor de la regla.
	 * @return bool Si se cumple.
	 */
	public static function matches( array $values, string $operator, string $target ): bool {
		$first = $values[0] ?? '';

		switch ( $operator ) {
			case '!=':
			case '!==':
				return ! in_array( $target, $values, true );

			case 'contains':
			case '==contains':
				return self::any_contains( $values, $target );

			case '!contains':
			case '!=contains':
				return ! self::any_contains( $values, $target );

			case 'empty':
			case '==empty':
				return array() === $values;

			case '!empty':
			case '!=empty':
				return array() !== $values;

			case '>':
			case '<':
			case '>=':
			case '<=':
				return self::compare( $first, $operator, $target );

			default:
				return in_array( $target, $values, true );
		}
	}

	/**
	 * Si algún valor contiene el texto, como `String.prototype.includes()`.
	 *
	 * @param array<int, string> $values Valores.
	 * @param string             $needle Texto buscado.
	 * @return bool True si alguno lo contiene.
	 */
	private static function any_contains( array $values, string $needle ): bool {
		foreach ( $values as $value ) {
			if ( str_contains( $value, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compara dos números como lo hace el navegador.
	 *
	 * @param string $left     Valor del campo.
	 * @param string $operator `>`, `<`, `>=` o `<=`.
	 * @param string $right    Valor de la regla.
	 * @return bool Resultado; false si alguno no es un número.
	 */
	private static function compare( string $left, string $operator, string $right ): bool {
		$a = self::parse_float( $left );
		$b = self::parse_float( $right );

		// `parseFloat()` de algo que no empieza por un número da NaN, y toda
		// comparación con NaN es falsa. `(float)` daría 0 y la cumpliría.
		if ( null === $a || null === $b ) {
			return false;
		}

		return match ( $operator ) {
			'>'     => $a > $b,
			'<'     => $a < $b,
			'>='    => $a >= $b,
			default => $a <= $b,
		};
	}

	/**
	 * Lee el número del principio de un texto, como `parseFloat()`.
	 *
	 * «12px» es 12, igual que en el navegador; «px12», ninguno.
	 *
	 * @param string $value Texto.
	 * @return float|null Número, o null si no empieza por uno.
	 */
	private static function parse_float( string $value ): ?float {
		if ( 1 !== preg_match( '/^\s*[+-]?(\d+\.?\d*|\.\d+)(e[+-]?\d+)?/i', $value, $match ) ) {
			return null;
		}

		return (float) $match[0];
	}
}
