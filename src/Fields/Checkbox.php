<?php
/**
 * Campo de selección múltiple con casillas.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «checkbox» de ACF.
 *
 * Guarda un array de valores.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-checkbox.php
 */
final class Checkbox extends ChoiceField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'checkbox';
	}

	/**
	 * El valor por defecto de un campo múltiple es una lista vacía.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array( 'default_value' => array() )
		);
	}

	/**
	 * Pinta la lista de casillas.
	 *
	 * @param mixed  $value      Valores actuales del campo.
	 * @param string $input_name Atributo «name» de los controles.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$choices = $this->choices();

		if ( array() === $choices ) {
			return;
		}

		$current = array_map( 'strval', (array) $value );

		// Sin este oculto, desmarcar todas las casillas no enviaría la clave y
		// el guardado no podría distinguirlo de «no se tocó el campo».
		printf(
			'<input type="hidden" name="%s" />',
			esc_attr( $input_name )
		);

		printf(
			'<ul class="acf-checkbox-list %s" role="group" aria-labelledby="%s-label">',
			esc_attr( $this->layout_class() ),
			esc_attr( $this->input_id() )
		);

		foreach ( $choices as $choice_value => $choice_label ) {
			$attributes = array(
				'type'  => 'checkbox',
				'id'    => $this->option_id( $choice_value ),
				'name'  => $input_name . '[]',
				'value' => $choice_value,
			);

			$selected = in_array( $choice_value, $current, true );

			if ( $selected ) {
				$attributes['checked'] = 'checked';
			}

			if ( $this->get( 'disabled', false ) ) {
				$attributes['disabled'] = 'disabled';
			}

			printf(
				'<li><label%s><input %s /> %s</label></li>',
				$selected ? ' class="selected"' : '',
				Html::attributes( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.
				esc_html( $choice_label )
			);
		}

		echo '</ul>';
	}

	/**
	 * Sanea la selección, descartando lo que no sea una opción declarada.
	 *
	 * @param mixed $raw Valores crudos enviados por el navegador.
	 * @return mixed Lista de opciones válidas, reindexada.
	 */
	public function sanitize( mixed $raw ): mixed {
		$choices = $this->choices();
		$values  = array_map( 'strval', (array) $raw );

		$valid = array_filter(
			$values,
			static fn ( string $value ): bool => array_key_exists( $value, $choices )
		);

		return array_values( $valid );
	}
}
