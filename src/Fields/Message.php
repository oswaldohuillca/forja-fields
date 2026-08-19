<?php
/**
 * Campo de aviso, sin valor.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «message» de ACF.
 *
 * Sirve para dar instrucciones o advertencias dentro del formulario. No guarda
 * nada.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-message.php
 */
final class Message extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'message';
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
				'message'   => '',
				// Escapar por defecto; se puede desactivar para permitir HTML.
				'esc_html'  => true,
				// Cómo tratar los saltos de línea: wpautop, br o vacío.
				'new_lines' => 'wpautop',
			)
		);
	}

	/**
	 * Este campo no almacena datos.
	 *
	 * @return bool Siempre false.
	 */
	public function stores_value(): bool {
		return false;
	}

	/**
	 * Pinta el mensaje.
	 *
	 * @param mixed  $value      Sin uso; el campo no tiene valor.
	 * @param string $input_name Sin uso; el campo no se envía.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		unset( $value, $input_name );

		$message = wptexturize( (string) $this->get( 'message', '' ) );

		if ( $this->get( 'esc_html', true ) ) {
			$message = esc_html( $message );
		}

		$new_lines = (string) $this->get( 'new_lines', 'wpautop' );

		if ( 'wpautop' === $new_lines ) {
			$message = wpautop( $message );
		} elseif ( 'br' === $new_lines ) {
			$message = nl2br( $message );
		}

		echo wp_kses_post( $message );
	}
}
