<?php
/**
 * Almacenamiento sobre las tablas de metadatos de WordPress.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Implementación común para post, term, user y comment.
 *
 * Las cuatro tablas de metadatos comparten la misma API (`get_metadata()`,
 * `update_metadata()`, `delete_metadata()`), así que basta con parametrizar
 * el tipo de objeto en lugar de escribir cuatro clases idénticas.
 */
final class MetaStorage implements Storage {

	/**
	 * Tipo de meta de WordPress: post, term, user o comment.
	 *
	 * @var string
	 */
	private string $meta_type;

	/**
	 * Constructor.
	 *
	 * @param string $meta_type Tipo de meta de WordPress.
	 */
	public function __construct( string $meta_type ) {
		$this->meta_type = $meta_type;
	}

	/**
	 * Lee el valor crudo de una clave.
	 *
	 * @param int|string $object_id Identificador del objeto contenedor.
	 * @param string     $key       Clave de almacenamiento.
	 * @return mixed Valor almacenado, o null si no existe.
	 */
	public function get( int|string $object_id, string $key ): mixed {
		$object_id = (int) $object_id;

		if ( ! metadata_exists( $this->meta_type, $object_id, $key ) ) {
			return null;
		}

		return get_metadata( $this->meta_type, $object_id, $key, true );
	}

	/**
	 * Escribe el valor de una clave.
	 *
	 * @param int|string $object_id Identificador del objeto contenedor.
	 * @param string     $key       Clave de almacenamiento.
	 * @param mixed      $value     Valor a guardar.
	 * @return bool True si lo almacenado coincide con el valor pedido.
	 */
	public function update( int|string $object_id, string $key, mixed $value ): bool {
		$object_id = (int) $object_id;

		if ( false !== update_metadata( $this->meta_type, $object_id, $key, $value ) ) {
			return true;
		}

		/*
		 * `update_metadata()` devuelve false por dos motivos distintos: porque
		 * la escritura falló, o porque el valor ya era ese. Sólo el primero es
		 * un error, así que se distingue leyendo lo almacenado.
		 *
		 * La comparación va sobre la forma serializada porque WordPress guarda
		 * todo como cadena: un entero 11 vuelve como «11», y compararlos en
		 * estricto daría un falso negativo.
		 */
		if ( ! metadata_exists( $this->meta_type, $object_id, $key ) ) {
			return false;
		}

		$stored = get_metadata( $this->meta_type, $object_id, $key, true );

		return (string) maybe_serialize( $stored ) === (string) maybe_serialize( $value );
	}

	/**
	 * Elimina una clave.
	 *
	 * @param int|string $object_id Identificador del objeto contenedor.
	 * @param string     $key       Clave de almacenamiento.
	 * @return bool True si la clave se eliminó.
	 */
	public function delete( int|string $object_id, string $key ): bool {
		return delete_metadata( $this->meta_type, (int) $object_id, $key );
	}
}
