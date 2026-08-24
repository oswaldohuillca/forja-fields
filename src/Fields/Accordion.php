<?php
/**
 * Acordeón: agrupa los campos siguientes en un panel plegable.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «accordion» de ACF.
 *
 * Como la pestaña, es una instrucción de maquetado. La diferencia es que el
 * acordeón sí anida a sus campos dentro de su propio envoltorio.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-accordion.php
 */
final class Accordion extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'accordion';
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
				// Aparece desplegado al cargar.
				'open'         => false,
				// Permite tener varios abiertos a la vez.
				'multi_expand' => false,
				// Cierra el acordeón anterior sin abrir uno nuevo.
				'endpoint'     => false,
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
	 * El contenido lo pinta el renderer, que es quien conoce a los hijos.
	 *
	 * @param mixed  $value      Sin uso.
	 * @param string $input_name Sin uso.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		unset( $value, $input_name );
	}
}
