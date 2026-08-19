<?php
/**
 * Campo de selección única con aspecto de grupo de botones.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «button_group» de ACF.
 *
 * Por dentro son botones de radio; el CSS los oculta y estiliza sus etiquetas
 * como un grupo de botones segmentado.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-button-group.php
 */
final class ButtonGroup extends ChoiceField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'button_group';
	}

	/**
	 * Pinta el grupo de botones.
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

		if ( ! array_key_exists( $current, $choices ) ) {
			$current = $this->get( 'allow_null', false ) ? '' : (string) array_key_first( $choices );
		}

		printf(
			'<input type="hidden" name="%s" />',
			esc_attr( $input_name )
		);

		$classes = 'acf-button-group';

		if ( 'vertical' === $this->get( 'layout', 'horizontal' ) ) {
			$classes .= ' -vertical';
		}

		printf(
			'<div class="%s" role="radiogroup" aria-labelledby="%s-label">',
			esc_attr( $classes ),
			esc_attr( $this->input_id() )
		);

		foreach ( $choices as $choice_value => $choice_label ) {
			// A diferencia del radio, aquí el control no lleva `id`: el CSS lo
			// oculta y quien recibe el foco es la etiqueta.
			$attributes = array(
				'type'  => 'radio',
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

			// Tabulación móvil: sólo la opción activa entra en el orden de
			// tabulación; dentro del grupo se navega con las flechas.
			$label_attributes = array(
				'class'        => $selected ? 'selected' : '',
				'tabindex'     => $selected ? '0' : '-1',
				'role'         => 'radio',
				'aria-checked' => $selected ? 'true' : 'false',
			);

			printf(
				'<label %s><input %s /> %s</label>',
				Html::attributes( $label_attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.
				Html::attributes( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.
				esc_html( $choice_label )
			);
		}

		echo '</div>';
	}

	/**
	 * Por defecto los botones se disponen en horizontal.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array( 'layout' => 'horizontal' )
		);
	}
}
