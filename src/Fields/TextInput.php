<?php
/**
 * Base de los campos que son un control de texto de una línea.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Reúne el markup común de `text`, `email`, `url`, `password` y `number`.
 *
 * Los cinco comparten envoltorio y la mecánica de prefijo y sufijo; sólo
 * cambian el atributo `type` y algún atributo suelto. Centralizarlo evita que
 * uno de ellos se desvíe y rompa la paridad visual.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-text.php
 */
abstract class TextInput extends Field {

	/**
	 * Valor del atributo `type` del control.
	 *
	 * @return string Tipo de input HTML.
	 */
	abstract protected function input_type(): string;

	/**
	 * Valores por defecto comunes a los campos de texto.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'maxlength' => '',
				'prepend'   => '',
				'append'    => '',
				'readonly'  => false,
				'disabled'  => false,
			)
		);
	}

	/**
	 * Atributos propios del tipo, más allá de los comunes.
	 *
	 * @return array<string, string> Atributos adicionales.
	 */
	protected function extra_attributes(): array {
		return array();
	}

	/**
	 * Pinta el control con su prefijo y sufijo opcionales.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$prepend = (string) $this->get( 'prepend', '' );
		$append  = (string) $this->get( 'append', '' );
		$classes = array();

		// ACF pinta el prefijo y el sufijo ANTES del envoltorio del control y
		// los coloca con float; el input recibe una clase que redondea sólo las
		// esquinas que quedan libres.
		if ( '' !== $prepend ) {
			$classes[] = 'acf-is-prepended';
			printf( '<div class="acf-input-prepend">%s</div>', esc_html( $prepend ) );
		}

		if ( '' !== $append ) {
			$classes[] = 'acf-is-appended';
			printf( '<div class="acf-input-append">%s</div>', esc_html( $append ) );
		}

		$attributes = array_merge(
			array(
				'type'        => $this->input_type(),
				'id'          => $this->input_id(),
				'class'       => implode( ' ', $classes ),
				'name'        => $input_name,
				'value'       => (string) $value,
				'placeholder' => (string) $this->get( 'placeholder', '' ),
			),
			$this->extra_attributes()
		);

		if ( '' !== (string) $this->get( 'maxlength', '' ) ) {
			$attributes['maxlength'] = (string) $this->get( 'maxlength' );
		}

		foreach ( array( 'readonly', 'disabled', 'required' ) as $flag ) {
			if ( $this->get( $flag, false ) ) {
				$attributes[ $flag ] = $flag;
			}
		}

		printf(
			'<div class="acf-input-wrap"><input %s /></div>',
			Html::attributes( $attributes ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.
		);
	}
}
