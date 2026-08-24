<?php
/**
 * Pestaña: marca el inicio de una sección de campos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «tab» de ACF.
 *
 * No es un campo, sino una instrucción de maquetado: todos los campos que le
 * siguen pertenecen a su sección, hasta la siguiente pestaña.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-tab.php
 */
final class Tab extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'tab';
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
				// Abre la sección activa al cargar.
				'selected' => false,
				// Cierra el grupo de pestañas en lugar de abrir una nueva.
				'endpoint' => false,
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
	 * No pinta nada: la barra de pestañas la emite el renderer.
	 *
	 * @param mixed  $value      Sin uso.
	 * @param string $input_name Sin uso.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		unset( $value, $input_name );
	}
}
