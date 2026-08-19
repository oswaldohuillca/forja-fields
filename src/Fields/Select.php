<?php
/**
 * Campo desplegable.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «select» de ACF, en su versión nativa.
 *
 * Sin select2: es un `<select>` del navegador. La versión con búsqueda y carga
 * remota llega con la Capa 2.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-select.php
 */
final class Select extends ChoiceField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'select';
	}

	/**
	 * Valores por defecto propios del tipo.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array( 'multiple' => false )
		);
	}

	/**
	 * Con un único control, la etiqueta sí puede apuntarlo.
	 *
	 * @return bool Siempre true.
	 */
	public function label_targets_input(): bool {
		return true;
	}

	/**
	 * Pinta el desplegable.
	 *
	 * @param mixed  $value      Valor o valores actuales.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$choices  = $this->choices();
		$multiple = (bool) $this->get( 'multiple', false );
		$current  = array_map( 'strval', (array) $value );

		$attributes = array(
			'id'   => $this->input_id(),
			'name' => $multiple ? $input_name . '[]' : $input_name,
		);

		if ( $multiple ) {
			$attributes['multiple'] = 'multiple';
			$attributes['size']     = (string) min( 8, max( 4, count( $choices ) ) );
		}

		if ( $this->get( 'required', false ) ) {
			$attributes['required'] = 'required';
		}

		if ( $this->get( 'disabled', false ) ) {
			$attributes['disabled'] = 'disabled';
		}

		if ( $multiple ) {
			// Igual que en el checkbox: sin esto, deseleccionar todo no
			// enviaría la clave.
			printf( '<input type="hidden" name="%s" />', esc_attr( $input_name ) );
		}

		printf( '<select %s>', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		if ( ! $multiple && $this->get( 'allow_null', false ) ) {
			printf( '<option value="">%s</option>', esc_html__( '- Selecciona -', 'forja-fields' ) );
		}

		foreach ( $choices as $choice_value => $choice_label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $choice_value ),
				in_array( $choice_value, $current, true ) ? ' selected="selected"' : '',
				esc_html( $choice_label )
			);
		}

		echo '</select>';
	}

	/**
	 * Sanea la selección según sea simple o múltiple.
	 *
	 * @param mixed $raw Valor o valores crudos.
	 * @return mixed Opción válida, o lista de opciones válidas.
	 */
	public function sanitize( mixed $raw ): mixed {
		$choices = $this->choices();

		if ( ! $this->get( 'multiple', false ) ) {
			return parent::sanitize( is_array( $raw ) ? reset( $raw ) : $raw );
		}

		$valid = array_filter(
			array_map( 'strval', (array) $raw ),
			static fn ( string $value ): bool => array_key_exists( $value, $choices )
		);

		return array_values( $valid );
	}
}
