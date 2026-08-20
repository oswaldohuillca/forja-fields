<?php
/**
 * Selector de color.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «color_picker» de ACF.
 *
 * Se apoya en el selector de color del núcleo de WordPress (Iris), que ya viene
 * registrado como `wp-color-picker`. No hace falta empaquetar nada.
 *
 * El markup replica el de ACF: un oculto que transporta el valor y un campo de
 * texto que Iris convierte en el selector. El oculto existe porque Iris deja el
 * campo de texto deshabilitado mientras el panel está cerrado en algunos
 * navegadores, y sin él el valor no viajaría.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-color_picker.php
 */
final class ColorPicker extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'color_picker';
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
				// Permite elegir transparencia, guardando el color como rgba().
				'enable_opacity' => false,
				// Colores sugeridos bajo el selector, separados por comas.
				'palette'        => '',
			)
		);
	}

	/**
	 * Pinta el selector.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		// Registrado por el núcleo; sólo hay que pedirlo.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		$current = (string) $value;

		echo '<div class="acf-color-picker">';

		printf(
			'<input type="hidden" name="%s" value="%s" />',
			esc_attr( $input_name ),
			esc_attr( $current )
		);

		$attributes = array(
			'type'  => 'text',
			'id'    => $this->input_id(),
			'class' => 'forja-color-picker',
			'value' => $current,
		);

		if ( $this->get( 'enable_opacity', false ) ) {
			$attributes['data-alpha-enabled'] = 'true';
		}

		$palette = (string) $this->get( 'palette', '' );

		if ( '' !== $palette ) {
			$attributes['data-palette'] = $palette;
		}

		printf( '<input %s />', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		echo '</div>';
	}

	/**
	 * Sanea el color.
	 *
	 * Se aceptan hexadecimales y, si el campo lo permite, `rgba()`. Cualquier
	 * otra cosa se descarta: es preferible guardar vacío a dejar que un valor
	 * arbitrario acabe interpolado en un atributo `style`.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Color válido, o cadena vacía.
	 */
	public function sanitize( mixed $raw ): mixed {
		$value = trim( (string) $raw );

		if ( '' === $value ) {
			return '';
		}

		$hex = sanitize_hex_color( $value );

		if ( null !== $hex && '' !== $hex ) {
			return $hex;
		}

		if ( $this->get( 'enable_opacity', false ) && $this->is_rgba( $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Comprueba si el valor es una expresión `rgba()` válida.
	 *
	 * @param string $value Valor a comprobar.
	 * @return bool True si encaja.
	 */
	private function is_rgba( string $value ): bool {
		return 1 === preg_match(
			'/^rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*(0|1|0?\.\d+)\s*\)$/i',
			$value
		);
	}
}
