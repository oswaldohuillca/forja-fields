<?php
/**
 * Campo de rango deslizante.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «range» de ACF.
 *
 * Se pintan dos controles: el deslizador, que es el que se envía, y un campo
 * numérico auxiliar sin `name` que sirve para ver y teclear el valor exacto.
 * El JavaScript los mantiene sincronizados.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-range.php
 */
final class Range extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'range';
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
				'min'     => 0,
				'max'     => 100,
				'step'    => 1,
				'prepend' => '',
				'append'  => '',
			)
		);
	}

	/**
	 * Pinta el deslizador y su campo numérico auxiliar.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$min  = (float) $this->get( 'min', 0 );
		$max  = (float) $this->get( 'max', 100 );
		$step = (float) $this->get( 'step', 1 );

		// ACF acota el valor al rango y trata como cero cualquier cosa que no
		// sea numérica, para que el deslizador nunca quede fuera de sus topes.
		$current = is_numeric( $value ) ? (float) $value : 0.0;
		$current = min( max( $current, $min ), $max );

		$prepend = (string) $this->get( 'prepend', '' );
		$append  = (string) $this->get( 'append', '' );

		echo '<div class="acf-range-wrap">';

		if ( '' !== $prepend ) {
			printf( '<div class="acf-prepend">%s</div>', esc_html( $prepend ) );
		}

		$slider = array(
			'type'  => 'range',
			'id'    => $this->input_id(),
			'name'  => $input_name,
			'value' => (string) $current,
			'min'   => (string) $min,
			'max'   => (string) $max,
			'step'  => (string) $step,
		);

		if ( $this->get( 'disabled', false ) ) {
			$slider['disabled'] = 'disabled';
		}

		printf( '<input %s />', Html::attributes( $slider ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		// El auxiliar no lleva «name» a propósito: no debe viajar en el envío.
		// Su ancho se calcula a partir del número de dígitos que puede mostrar.
		$digits = max( strlen( (string) $min ), strlen( (string) $max ) );

		$alt = array(
			'type'  => 'number',
			'id'    => $this->input_id() . '-alt',
			'class' => 'acf-range-alt',
			'value' => (string) $current,
			'step'  => (string) $step,
			'style' => sprintf( 'width: %sem;', 1.8 + $digits * 0.7 ),
		);

		printf( '<input %s />', Html::attributes( $alt ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		if ( '' !== $append ) {
			printf( '<div class="acf-append">%s</div>', esc_html( $append ) );
		}

		echo '</div>';
	}

	/**
	 * Sanea el valor acotándolo al rango declarado.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Número dentro del rango.
	 */
	public function sanitize( mixed $raw ): mixed {
		$min   = (float) $this->get( 'min', 0 );
		$max   = (float) $this->get( 'max', 100 );
		$value = is_numeric( $raw ) ? (float) $raw : $min;
		$value = min( max( $value, $min ), $max );

		return floor( $value ) === $value ? (int) $value : $value;
	}
}
