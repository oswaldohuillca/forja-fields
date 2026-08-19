<?php
/**
 * Campo numérico.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «number» de ACF.
 */
final class Number extends TextInput {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'number';
	}

	/**
	 * Valor del atributo `type` del control.
	 *
	 * @return string Tipo de input HTML.
	 */
	protected function input_type(): string {
		return 'number';
	}

	/**
	 * Valores por defecto propios del tipo.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'min'  => '',
				'max'  => '',
				// ACF emite «any» por defecto, para no impedir decimales.
				'step' => 'any',
			)
		);
	}

	/**
	 * Atributos de rango y precisión.
	 *
	 * @return array<string, string> Atributos adicionales.
	 */
	protected function extra_attributes(): array {
		$attributes = array();

		foreach ( array( 'min', 'max', 'step' ) as $key ) {
			$value = $this->get( $key, '' );

			if ( '' !== (string) $value ) {
				$attributes[ $key ] = (string) $value;
			}
		}

		return $attributes;
	}

	/**
	 * Sanea el valor conservando su naturaleza numérica.
	 *
	 * Un campo vacío se guarda como cadena vacía y no como cero, para poder
	 * distinguir «sin rellenar» de «cero».
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Número, o cadena vacía.
	 */
	public function sanitize( mixed $raw ): mixed {
		$value = trim( (string) $raw );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}

		return str_contains( $value, '.' ) ? (float) $value : (int) $value;
	}
}
