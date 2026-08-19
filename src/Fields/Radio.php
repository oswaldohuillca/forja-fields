<?php
/**
 * Campo de selección única con botones de radio.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «radio» de ACF.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-radio.php
 */
final class Radio extends ChoiceField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'radio';
	}

	/**
	 * Pinta la lista de opciones.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» de los controles.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$choices = $this->choices();

		if ( array() === $choices ) {
			return;
		}

		$current = (string) $value;

		// Si el valor guardado no es una opción válida, ACF marca la primera
		// salvo que se permita explícitamente dejarlo vacío.
		if ( ! array_key_exists( $current, $choices ) ) {
			$current = $this->get( 'allow_null', false ) ? '' : (string) array_key_first( $choices );
		}

		// El campo oculto asegura que la clave viaje siempre, incluso si el
		// navegador no envía ningún radio marcado.
		printf(
			'<input type="hidden" name="%s" value="" />',
			esc_attr( $input_name )
		);

		printf(
			'<ul class="acf-radio-list %s" role="radiogroup" aria-labelledby="%s-label">',
			esc_attr( $this->layout_class() ),
			esc_attr( $this->input_id() )
		);

		foreach ( $choices as $choice_value => $choice_label ) {
			$attributes = array(
				'type'  => 'radio',
				'id'    => $this->option_id( $choice_value ),
				'name'  => $input_name,
				'value' => $choice_value,
			);

			$selected = $choice_value === $current;

			if ( $selected ) {
				$attributes['checked'] = 'checked';
			}

			if ( $this->get( 'disabled', false ) ) {
				$attributes['disabled'] = 'disabled';
			}

			printf(
				'<li><label%s><input %s />%s</label></li>',
				$selected ? ' class="selected"' : '',
				Html::attributes( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.
				esc_html( $choice_label )
			);
		}

		echo '</ul>';
	}
}
