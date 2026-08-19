<?php
/**
 * Almacenamiento sobre la tabla de opciones.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Guarda los valores como opciones, para páginas de ajustes.
 *
 * El `$object_id` actúa como prefijo del nombre de la opción, de modo que
 * dos páginas de opciones distintas nunca se pisan las claves.
 */
final class OptionStorage implements Storage {

	/**
	 * Compone el nombre real de la opción.
	 *
	 * @param int|string $object_id Prefijo (slug de la página de opciones).
	 * @param string     $key       Clave de almacenamiento.
	 * @return string Nombre de la opción.
	 */
	private function option_name( int|string $object_id, string $key ): string {
		$prefix = '' === (string) $object_id ? 'options' : (string) $object_id;

		return $prefix . '_' . $key;
	}

	/**
	 * Lee el valor crudo de una clave.
	 *
	 * @param int|string $object_id Prefijo (slug de la página de opciones).
	 * @param string     $key       Clave de almacenamiento.
	 * @return mixed Valor almacenado, o null si no existe.
	 */
	public function get( int|string $object_id, string $key ): mixed {
		// El centinela distingue «opción ausente» de «opción con valor falsy».
		$sentinel = new \stdClass();
		$value    = get_option( $this->option_name( $object_id, $key ), $sentinel );

		return $value === $sentinel ? null : $value;
	}

	/**
	 * Escribe el valor de una clave.
	 *
	 * @param int|string $object_id Prefijo (slug de la página de opciones).
	 * @param string     $key       Clave de almacenamiento.
	 * @param mixed      $value     Valor a guardar.
	 * @return bool True si la escritura se realizó.
	 */
	public function update( int|string $object_id, string $key, mixed $value ): bool {
		$name = $this->option_name( $object_id, $key );

		// update_option() devuelve false si el valor no cambió; tratamos ese
		// caso como éxito para mantener el contrato de la interfaz.
		if ( get_option( $name, null ) === $value ) {
			return true;
		}

		return update_option( $name, $value );
	}

	/**
	 * Elimina una clave.
	 *
	 * @param int|string $object_id Prefijo (slug de la página de opciones).
	 * @param string     $key       Clave de almacenamiento.
	 * @return bool True si la clave se eliminó.
	 */
	public function delete( int|string $object_id, string $key ): bool {
		return delete_option( $this->option_name( $object_id, $key ) );
	}
}
