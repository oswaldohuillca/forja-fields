<?php
/**
 * Pintado del envoltorio de los campos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Render;

use Forja\Fields\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Reproduce el markup de `acf_render_field_wrap()` de ACF/SCF.
 *
 * La estructura DOM se mantiene byte a byte respecto al original porque los
 * ~9.600 líneas de CSS portadas dependen de ella. Cualquier cambio aquí
 * rompe la paridad visual, que es el requisito central del plugin.
 *
 * @see secure-custom-fields/includes/acf-field-functions.php:645
 */
final class Renderer {

	/**
	 * Pinta una colección de campos dentro de su contenedor.
	 *
	 * @param array<int, Field>    $fields       Campos a pintar.
	 * @param array<string, mixed> $values       Valores actuales, indexados por nombre de campo.
	 * @param string               $input_prefix Prefijo del atributo «name» de los controles.
	 * @param string               $instruction  Dónde colocar las instrucciones: label o field.
	 * @return void
	 */
	public function render_fields( array $fields, array $values, string $input_prefix, string $instruction = 'label' ): void {
		foreach ( $fields as $field ) {
			$value = $values[ $field->name() ] ?? $field->default_value();

			$this->render_field_wrap( $field, $value, $input_prefix, $instruction );
		}
	}

	/**
	 * Pinta el envoltorio completo de un campo.
	 *
	 * @param Field  $field        Campo a pintar.
	 * @param mixed  $value        Valor actual.
	 * @param string $input_prefix Prefijo del atributo «name».
	 * @param string $instruction  Dónde colocar las instrucciones.
	 * @return void
	 */
	public function render_field_wrap( Field $field, mixed $value, string $input_prefix, string $instruction = 'label' ): void {
		$wrapper = $this->wrapper_attributes( $field );

		printf( '<div %s>', Html::attributes( $wrapper ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		echo '<div class="acf-label">';
		$this->render_label( $field );

		if ( 'label' === $instruction ) {
			$this->render_instructions( $field );
		}

		echo '</div>';

		echo '<div class="acf-input">';
		$field->render_input( $value, $input_prefix . '[' . $field->name() . ']' );

		if ( 'field' === $instruction ) {
			$this->render_instructions( $field );
		}

		echo '</div>';

		echo '</div>';
	}

	/**
	 * Calcula los atributos del div envolvente.
	 *
	 * @param Field $field Campo a pintar.
	 * @return array<string, string> Atributos del envoltorio.
	 */
	private function wrapper_attributes( Field $field ): array {
		$wrapper = $field->get( 'wrapper', array() );
		$classes = 'acf-field acf-field-' . $field::type() . ' acf-field-' . $field->name();

		if ( $field->get( 'required', false ) ) {
			$classes .= ' is-required';
		}

		if ( ! empty( $wrapper['class'] ) ) {
			$classes .= ' ' . $wrapper['class'];
		}

		// ACF normaliza los guiones bajos a guiones en las clases para que los
		// selectores CSS sean predecibles. Replicamos ese comportamiento.
		$classes = str_replace( '_', '-', $classes );

		$attributes = array(
			'id'        => (string) ( $wrapper['id'] ?? '' ),
			'class'     => $classes,
			'style'     => '',
			'data-name' => $field->name(),
			'data-type' => $field::type(),
			'data-key'  => $field->name(),
		);

		if ( $field->get( 'required', false ) ) {
			$attributes['data-required'] = '1';
		}

		$width = (string) ( $wrapper['width'] ?? '' );

		if ( '' !== $width ) {
			$width                     = (float) preg_replace( '/[^0-9.]/', '', $width );
			$attributes['data-width']  = (string) $width;
			$attributes['style']      .= sprintf( 'width:%s%%;', $width );
		}

		return $attributes;
	}

	/**
	 * Pinta la etiqueta del campo.
	 *
	 * @param Field $field Campo a pintar.
	 * @return void
	 */
	private function render_label( Field $field ): void {
		$label = (string) $field->get( 'label', '' );

		if ( '' === $label ) {
			return;
		}

		$html = esc_html( $label );

		if ( $field->get( 'required', false ) ) {
			$html .= ' <span class="acf-required">*</span>';
		}

		// Con un solo control la etiqueta lo señala con `for`; con varios se
		// identifica para que el grupo la referencie con `aria-labelledby`.
		$attribute = $field->label_targets_input()
			? sprintf( 'for="%s"', esc_attr( $field->input_id() ) )
			: sprintf( 'id="%s-label"', esc_attr( $field->input_id() ) );

		printf(
			'<label %s>%s</label>',
			$attribute, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba; el asterisco es markup nuestro.
		);
	}

	/**
	 * Pinta las instrucciones del campo.
	 *
	 * @param Field $field Campo a pintar.
	 * @return void
	 */
	private function render_instructions( Field $field ): void {
		$instructions = (string) $field->get( 'instructions', '' );

		if ( '' === $instructions ) {
			return;
		}

		printf( '<p class="description">%s</p>', wp_kses_post( $instructions ) );
	}
}
