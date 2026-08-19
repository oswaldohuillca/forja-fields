<?php
/**
 * Campo de correo electrónico.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «email» de ACF.
 */
final class Email extends TextInput {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'email';
	}

	/**
	 * Valor del atributo `type` del control.
	 *
	 * @return string Tipo de input HTML.
	 */
	protected function input_type(): string {
		return 'email';
	}

	/**
	 * Sanea la dirección, descartando las que no son válidas.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Dirección saneada, o cadena vacía si no es válida.
	 */
	public function sanitize( mixed $raw ): mixed {
		return sanitize_email( (string) $raw );
	}
}
