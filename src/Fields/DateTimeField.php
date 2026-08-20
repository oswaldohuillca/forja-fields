<?php
/**
 * Base de los campos de fecha y hora.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Reúne lo común de `date_picker`, `time_picker` y `date_time_picker`.
 *
 * ACF usa jQuery UI y un complemento propio de selección de hora. Aquí se usan
 * los controles nativos del navegador —`date`, `time` y `datetime-local`—, que
 * no añaden ninguna dependencia, funcionan bien en móvil y ya vienen
 * traducidos y accesibles.
 *
 * Lo que **no** cambia es el formato de almacenamiento: se conserva el de ACF
 * para poder leer lo que ya haya en un sitio existente. Como no coincide con
 * el que esperan los controles nativos, se convierte en ambos sentidos.
 */
abstract class DateTimeField extends TextInput {

	/**
	 * Formato con el que se guarda en la base de datos.
	 *
	 * @return string Formato de `DateTimeInterface::format()`.
	 */
	abstract protected function storage_format(): string;

	/**
	 * Formato que espera el control nativo en su atributo `value`.
	 *
	 * @return string Formato de `DateTimeInterface::format()`.
	 */
	abstract protected function input_format(): string;

	/**
	 * Valores por defecto comunes a los campos de fecha y hora.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				// Formato con el que se devuelve a la plantilla. Vacío significa
				// «tal como está guardado».
				'return_format' => '',
				'min'           => '',
				'max'           => '',
			)
		);
	}

	/**
	 * Límites de fecha u hora, si se declaran.
	 *
	 * @return array<string, string> Atributos adicionales.
	 */
	protected function extra_attributes(): array {
		$attributes = array();

		foreach ( array( 'min', 'max' ) as $key ) {
			$value = (string) $this->get( $key, '' );

			if ( '' !== $value ) {
				$attributes[ $key ] = $value;
			}
		}

		return $attributes;
	}

	/**
	 * Convierte el valor almacenado al formato del control nativo.
	 *
	 * @param mixed  $value      Valor almacenado.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$date = $this->parse( (string) $value, $this->storage_format() );

		parent::render_input(
			null === $date ? '' : $date->format( $this->input_format() ),
			$input_name
		);
	}

	/**
	 * Convierte lo que envía el control nativo al formato de almacenamiento.
	 *
	 * Un valor que no encaje se descarta: es preferible perder el envío a
	 * guardar una fecha inventada.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Valor en formato de almacenamiento, o cadena vacía.
	 */
	public function sanitize( mixed $raw ): mixed {
		$date = $this->parse( trim( (string) $raw ), $this->input_format() );

		return null === $date ? '' : $date->format( $this->storage_format() );
	}

	/**
	 * Da formato al valor para la plantilla.
	 *
	 * @param mixed $value Valor almacenado.
	 * @return string Valor con el formato declarado en `return_format`.
	 */
	public function format_value( mixed $value ): mixed {
		$stored = (string) $value;
		$format = (string) $this->get( 'return_format', '' );

		if ( '' === $format ) {
			return $stored;
		}

		$date = $this->parse( $stored, $this->storage_format() );

		return null === $date ? '' : $date->format( $format );
	}

	/**
	 * Interpreta una cadena con un formato concreto.
	 *
	 * Se usa `createFromFormat` en lugar de `strtotime` porque este último
	 * acepta casi cualquier cosa: «20231301» pasaría como una fecha válida
	 * desplazándose al mes siguiente.
	 *
	 * @param string $value  Cadena a interpretar.
	 * @param string $format Formato esperado.
	 * @return \DateTimeImmutable|null Fecha, o null si no encaja.
	 */
	protected function parse( string $value, string $format ): ?\DateTimeImmutable {
		if ( '' === $value ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( '!' . $format, $value, wp_timezone() );

		if ( false === $date ) {
			return null;
		}

		$errors = \DateTimeImmutable::getLastErrors();

		if ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) {
			return null;
		}

		return $date;
	}
}
