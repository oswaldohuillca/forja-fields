<?php
/**
 * Campo de URL.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «url» de ACF.
 */
final class Url extends TextInput {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'url';
	}

	/**
	 * Valor del atributo `type` del control.
	 *
	 * @return string Tipo de input HTML.
	 */
	protected function input_type(): string {
		return 'url';
	}

	/**
	 * Sanea la URL para almacenarla.
	 *
	 * Se usa la variante «raw» porque el valor va a la base de datos, no a un
	 * atributo HTML; el escapado para imprimir es responsabilidad de la
	 * plantilla, con `esc_url()`.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed URL saneada.
	 */
	public function sanitize( mixed $raw ): mixed {
		$value = trim( (string) $raw );

		return '' === $value ? '' : sanitize_url( $value );
	}
}
