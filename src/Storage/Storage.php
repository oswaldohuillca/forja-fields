<?php
/**
 * Contrato de la capa de almacenamiento.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Abstrae dónde viven los valores de un campo.
 *
 * Cada implementación despacha a la tabla de metadatos correspondiente
 * (postmeta, termmeta, usermeta) o a la tabla de opciones. Gracias a esto
 * un tipo de campo nunca sabe si lo están guardando en un post, un término
 * o una página de opciones.
 */
interface Storage {

	/**
	 * Lee el valor crudo de una clave.
	 *
	 * @param int|string $object_id Identificador del objeto contenedor.
	 * @param string     $key       Clave de almacenamiento.
	 * @return mixed Valor almacenado, o null si no existe.
	 */
	public function get( int|string $object_id, string $key ): mixed;

	/**
	 * Escribe el valor de una clave.
	 *
	 * @param int|string $object_id Identificador del objeto contenedor.
	 * @param string     $key       Clave de almacenamiento.
	 * @param mixed      $value     Valor a guardar.
	 * @return bool True si la escritura se realizó.
	 */
	public function update( int|string $object_id, string $key, mixed $value ): bool;

	/**
	 * Elimina una clave.
	 *
	 * @param int|string $object_id Identificador del objeto contenedor.
	 * @param string     $key       Clave de almacenamiento.
	 * @return bool True si la clave se eliminó.
	 */
	public function delete( int|string $object_id, string $key ): bool;
}
