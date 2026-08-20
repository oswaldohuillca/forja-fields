<?php
/**
 * Campo booleano.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «true_false» de ACF.
 *
 * Con `ui => true` se pinta el interruptor deslizante de ACF, que no es más
 * que la casilla real oculta y un par de `<span>` estilizados.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-true_false.php
 */
final class TrueFalse extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'true_false';
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
				'default_value' => 0,
				// Texto que acompaña a la casilla.
				'message'       => '',
				// Pinta el interruptor en lugar de una casilla suelta.
				'ui'            => false,
				'ui_on_text'    => '',
				'ui_off_text'   => '',
			)
		);
	}

	/**
	 * Pinta la casilla o el interruptor.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$active = ! empty( $value );
		$ui     = (bool) $this->get( 'ui', false );

		// El orden de las claves define el orden de los atributos; se mantiene
		// el de ACF para que el markup sea comparable byte a byte.
		$attributes = array(
			'type'         => 'checkbox',
			'id'           => $this->input_id(),
			'name'         => $input_name,
			'value'        => '1',
			'class'        => '',
			'autocomplete' => 'off',
		);

		if ( $active ) {
			$attributes['checked'] = 'checked';
		}

		if ( $this->get( 'disabled', false ) ) {
			$attributes['disabled'] = 'disabled';
		}

		if ( $ui ) {
			// La casilla real se oculta por CSS y el interruptor la refleja.
			$attributes['class'] = 'acf-switch-input';
		}

		echo '<div class="acf-true-false">';

		// Una casilla sin marcar no se envía; este oculto garantiza el cero.
		printf( '<input type="hidden" name="%s" value="0" />', esc_attr( $input_name ) );

		echo '<label>';

		printf( '<input %s />', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		if ( $ui ) {
			$on  = (string) $this->get( 'ui_on_text', '' );
			$off = (string) $this->get( 'ui_off_text', '' );

			printf(
				'<div class="acf-switch%s"><span class="acf-switch-on">%s</span><span class="acf-switch-off">%s</span><div class="acf-switch-slider"></div></div>',
				$active ? ' -on' : '',
				esc_html( '' === $on ? __( 'Sí', 'forja-fields' ) : $on ),
				esc_html( '' === $off ? __( 'No', 'forja-fields' ) : $off )
			);
		}

		$message = (string) $this->get( 'message', '' );

		if ( '' !== $message ) {
			printf( '<span class="message">%s</span>', esc_html( $message ) );
		}

		echo '</label>';
		echo '</div>';
	}

	/**
	 * Devuelve el valor como booleano.
	 *
	 * Se almacena como 1 o 0, y WordPress lo entrega como cadena. Devolver
	 * «'0'» sería una trampa: en PHP es una cadena no vacía y más de una
	 * plantilla la trataría como verdadera.
	 *
	 * @param mixed $value Valor almacenado.
	 * @return bool Verdadero o falso.
	 */
	public function format_value( mixed $value ): mixed {
		return '1' === (string) $value;
	}

	/**
	 * Sanea el valor a un entero cero o uno.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed 1 o 0.
	 */
	public function sanitize( mixed $raw ): mixed {
		// Un valor sin marcar llega como "0" por el campo oculto; marcado
		// llega como "1". Cualquier otra cosa se trata como falso.
		return '1' === (string) $raw ? 1 : 0;
	}
}
