<?php
/**
 * Validación de los valores enviados.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Validation;

use Forja\Fields\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Comprueba las reglas que el navegador no puede garantizar.
 *
 * El atributo `required` del HTML es una comodidad para quien rellena el
 * formulario, no una garantía: se salta desactivando JavaScript, desde una
 * petición hecha a mano o desde cualquier cliente que no sea un navegador.
 * Esta clase repite la comprobación en el servidor, que es donde cuenta.
 */
final class Validator {

	/**
	 * Comprueba un valor ya saneado contra las reglas de su campo.
	 *
	 * @param Field $field Campo al que pertenece el valor.
	 * @param mixed $value Valor saneado.
	 * @return string Mensaje de error, o cadena vacía si es válido.
	 */
	public function validate( Field $field, mixed $value ): string {
		$error = '';

		if ( $field->get( 'required', false ) && $this->is_empty( $value ) ) {
			$error = sprintf(
				/* translators: %s: etiqueta del campo. */
				__( '%s es obligatorio.', 'forja-fields' ),
				$field->get( 'label', $field->name() )
			);
		}

		// Reglas propias del tipo, si el `required` no ha fallado ya.
		if ( '' === $error ) {
			$error = $field->validate( $value );
		}

		/**
		 * Filtra el resultado de la validación de un campo.
		 *
		 * Devuelve una cadena no vacía para marcarlo como inválido.
		 *
		 * @param string $error Mensaje de error, o cadena vacía.
		 * @param mixed  $value Valor saneado.
		 * @param Field  $field Campo validado.
		 */
		return (string) apply_filters( 'forja/validate_field', $error, $value, $field );
	}

	/**
	 * Determina si un valor cuenta como vacío.
	 *
	 * No se usa `empty()` a secas porque un `0` o un `'0'` son valores
	 * legítimos: un número a cero o un booleano en falso están rellenos.
	 *
	 * @param mixed $value Valor saneado.
	 * @return bool True si el campo está sin rellenar.
	 */
	private function is_empty( mixed $value ): bool {
		if ( null === $value ) {
			return true;
		}

		if ( is_array( $value ) ) {
			return array() === $value;
		}

		return '' === trim( (string) $value );
	}
}
