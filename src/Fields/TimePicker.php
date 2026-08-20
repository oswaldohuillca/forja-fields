<?php
/**
 * Selector de hora.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «time_picker» de ACF.
 *
 * Se almacena como `H:i:s`, igual que ACF, para poder leer los datos de un
 * sitio existente sin migrarlos.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-time_picker.php
 */
final class TimePicker extends DateTimeField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'time_picker';
	}

	/**
	 * Valor del atributo `type` del control.
	 *
	 * @return string Tipo de input HTML.
	 */
	protected function input_type(): string {
		return 'time';
	}

	/**
	 * Formato con el que se guarda en la base de datos.
	 *
	 * @return string Formato de fecha.
	 */
	protected function storage_format(): string {
		return 'H:i:s';
	}

	/**
	 * Formato que espera el control nativo.
	 *
	 * @return string Formato de fecha.
	 */
	protected function input_format(): string {
		return 'H:i';
	}
}
