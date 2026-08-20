<?php
/**
 * Selector de fecha y hora.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «date_time_picker» de ACF.
 *
 * Se almacena como `Y-m-d H:i:s`, igual que ACF, para poder leer los datos de un
 * sitio existente sin migrarlos.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-date_time_picker.php
 */
final class DateTimePicker extends DateTimeField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'date_time_picker';
	}

	/**
	 * Valor del atributo `type` del control.
	 *
	 * @return string Tipo de input HTML.
	 */
	protected function input_type(): string {
		return 'datetime-local';
	}

	/**
	 * Formato con el que se guarda en la base de datos.
	 *
	 * @return string Formato de fecha.
	 */
	protected function storage_format(): string {
		return 'Y-m-d H:i:s';
	}

	/**
	 * Formato que espera el control nativo.
	 *
	 * @return string Formato de fecha.
	 */
	protected function input_format(): string {
		return 'Y-m-d\\TH:i';
	}
}
