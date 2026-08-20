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
	 * Devuelve el valor como número.
	 *
	 * WordPress entrega los metadatos como cadenas, así que sin esto la
	 * plantilla recibiría «1234» en lugar de 1234.
	 *
	 * Un campo sin rellenar devuelve null, no cero: el cero es un valor
	 * legítimo y confundirlos haría imposible distinguir «no lo tocaron» de
	 * «pusieron cero».
	 *
	 * @param mixed $value Valor almacenado.
	 * @return int|float|null Número, o null si está sin rellenar.
	 */
	public function format_value( mixed $value ): mixed {
		$raw = trim( (string) $value );

		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return null;
		}

		return str_contains( $raw, '.' ) ? (float) $raw : (int) $raw;
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
