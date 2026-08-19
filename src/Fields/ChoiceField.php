<?php
/**
 * Base de los campos que ofrecen un juego de opciones.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Reúne lo común de `radio`, `checkbox`, `button_group` y `select`.
 *
 * Lo importante que aporta esta clase es la **validación contra las opciones
 * declaradas**: el navegador puede enviar cualquier cosa, así que un valor que
 * no esté en la lista se descarta en lugar de almacenarse.
 */
abstract class ChoiceField extends Field {

	/**
	 * Valores por defecto comunes a los campos con opciones.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'choices'    => array(),
				'allow_null' => false,
				// Disposición de las opciones: vertical u horizontal.
				'layout'     => 'vertical',
			)
		);
	}

	/**
	 * Opciones normalizadas como valor => etiqueta.
	 *
	 * Acepta dos formas. Un array asociativo se respeta tal cual:
	 *
	 *     array( 'rojo' => 'Rojo', 'azul' => 'Azul' )
	 *
	 * Y una lista simple usa cada elemento como valor y etiqueta a la vez:
	 *
	 *     array( 'rojo', 'azul' )
	 *
	 * @return array<string, string> Opciones normalizadas.
	 */
	protected function choices(): array {
		$raw = (array) $this->get( 'choices', array() );

		if ( array_is_list( $raw ) ) {
			$raw = array_combine( $raw, $raw );
		}

		$choices = array();

		foreach ( $raw as $value => $label ) {
			$choices[ (string) $value ] = (string) $label;
		}

		return $choices;
	}

	/**
	 * La etiqueta del grupo no apunta a un control concreto.
	 *
	 * Con varios controles no hay un único destino para el atributo `for`, así
	 * que la etiqueta se identifica y el grupo la referencia con
	 * `aria-labelledby`.
	 *
	 * @return bool Siempre false.
	 */
	public function label_targets_input(): bool {
		return false;
	}

	/**
	 * Identificador del control correspondiente a una opción.
	 *
	 * @param string $value Valor de la opción.
	 * @return string Atributo id.
	 */
	protected function option_id( string $value ): string {
		return sanitize_title( $this->input_id() . '-' . $value );
	}

	/**
	 * Clases de disposición que ACF aplica a las listas.
	 *
	 * @return string `acf-hl` para horizontal, `acf-bl` para vertical.
	 */
	protected function layout_class(): string {
		return 'horizontal' === $this->get( 'layout', 'vertical' ) ? 'acf-hl' : 'acf-bl';
	}

	/**
	 * Sanea el valor descartando lo que no sea una opción declarada.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Opción válida, o cadena vacía.
	 */
	public function sanitize( mixed $raw ): mixed {
		$value = (string) $raw;

		return array_key_exists( $value, $this->choices() ) ? $value : '';
	}
}
