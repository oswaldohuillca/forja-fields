<?php
/**
 * Campo de contraseña.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «password» de ACF.
 *
 * Oculta lo que se escribe, pero el valor se almacena en claro en los
 * metadatos, igual que en ACF. No lo uses para credenciales reales.
 */
final class Password extends TextInput {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'password';
	}

	/**
	 * Valor del atributo `type` del control.
	 *
	 * @return string Tipo de input HTML.
	 */
	protected function input_type(): string {
		return 'password';
	}
}
