<?php
/**
 * Campo de texto multilínea.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «textarea» de ACF.
 */
final class Textarea extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'textarea';
	}

	/**
	 * Valores por defecto propios del tipo.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'rows'      => 4,
				'maxlength' => '',
			)
		);
	}

	/**
	 * Sanea el valor conservando los saltos de línea.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Valor saneado.
	 */
	public function sanitize( mixed $raw ): mixed {
		return sanitize_textarea_field( (string) $raw );
	}

	/**
	 * Pinta el control de entrada.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$attributes = array(
			'id'          => $this->input_id(),
			'name'        => $input_name,
			'rows'        => (string) $this->get( 'rows', 4 ),
			'placeholder' => (string) $this->get( 'placeholder', '' ),
		);

		if ( '' !== (string) $this->get( 'maxlength', '' ) ) {
			$attributes['maxlength'] = (string) $this->get( 'maxlength' );
		}

		if ( $this->get( 'required', false ) ) {
			$attributes['required'] = 'required';
		}

		printf(
			'<textarea %s>%s</textarea>',
			\Forja\Render\Html::attributes( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.
			esc_textarea( (string) $value )
		);
	}
}
