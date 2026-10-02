<?php
/**
 * Lectura, escritura y borrado de los subcampos de un compuesto.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Lo que comparten el repetidor, el grupo y el contenido flexible al guardar.
 *
 * Un subcampo simple ocupa una clave: la de su posición (`banner_0_titulo`). Un
 * subcampo compuesto ocupa varias, y no se puede guardar con `sanitize()` como
 * un valor suelto: se le dan las operaciones del almacén con el prefijo de su
 * posición y él compone el resto. Un repetidor `items` dentro de la fila 0 de
 * `bloques` acaba en `bloques_0_items` y `bloques_0_items_0_item`, que es el
 * formato de ACF, sin que el repetidor interior sepa que está anidado.
 */
trait StoresSubFields {

	/**
	 * Envuelve una operación del almacén para que anteponga un prefijo a la clave.
	 *
	 * @param callable $operation Operación original: get, set o delete.
	 * @param string   $prefix    Prefijo de la posición del subcampo.
	 * @return callable Operación con las claves prefijadas.
	 */
	private static function scoped( callable $operation, string $prefix ): callable {
		return static fn ( string $key, mixed ...$rest ): mixed => $operation( $prefix . $key, ...$rest );
	}

	/**
	 * Lee un subcampo.
	 *
	 * @param Field    $sub_field Subcampo.
	 * @param string   $prefix    Prefijo de su posición, con el guion bajo final.
	 * @param callable $get       Devuelve el valor de una clave.
	 * @return mixed Valor almacenado.
	 */
	private function read_sub_field( Field $sub_field, string $prefix, callable $get ): mixed {
		if ( $sub_field instanceof Composite ) {
			return $sub_field->read_value( self::scoped( $get, $prefix ) );
		}

		return $get( $prefix . $sub_field->name() );
	}

	/**
	 * Escribe un subcampo.
	 *
	 * @param Field    $sub_field Subcampo.
	 * @param string   $prefix    Prefijo de su posición, con el guion bajo final.
	 * @param mixed    $raw       Valor crudo enviado.
	 * @param callable $get       Devuelve el valor de una clave.
	 * @param callable $set       Guarda el valor de una clave.
	 * @param callable $delete    Borra una clave.
	 * @return array<int, string> Mensajes de error del subcampo compuesto, si lo es.
	 */
	private function write_sub_field( Field $sub_field, string $prefix, mixed $raw, callable $get, callable $set, callable $delete ): array {
		if ( $sub_field instanceof Composite ) {
			return $sub_field->write_value(
				$raw,
				self::scoped( $get, $prefix ),
				self::scoped( $set, $prefix ),
				self::scoped( $delete, $prefix )
			);
		}

		$set( $prefix . $sub_field->name(), $sub_field->sanitize( $raw ) );

		return array();
	}

	/**
	 * Borra un subcampo, con todas sus claves si es compuesto.
	 *
	 * @param Field    $sub_field Subcampo.
	 * @param string   $prefix    Prefijo de su posición, con el guion bajo final.
	 * @param callable $get       Devuelve el valor de una clave.
	 * @param callable $delete    Borra una clave.
	 * @return void
	 */
	private function delete_sub_field( Field $sub_field, string $prefix, callable $get, callable $delete ): void {
		if ( $sub_field instanceof Composite ) {
			$sub_field->delete_value( self::scoped( $get, $prefix ), self::scoped( $delete, $prefix ) );

			return;
		}

		$delete( $prefix . $sub_field->name() );
	}
}
