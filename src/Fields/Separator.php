<?php
/**
 * Separador visual, sin valor.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «separator» de ACF.
 *
 * Divide el formulario en secciones. Su etiqueta actúa como título de la
 * sección y el área del control se oculta por CSS, así que aquí no se pinta
 * nada en absoluto.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-separator.php
 */
final class Separator extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'separator';
	}

	/**
	 * Este campo no almacena datos.
	 *
	 * @return bool Siempre false.
	 */
	public function stores_value(): bool {
		return false;
	}

	/**
	 * No pinta ningún control.
	 *
	 * @param mixed  $value      Sin uso.
	 * @param string $input_name Sin uso.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		unset( $value, $input_name );
	}
}
