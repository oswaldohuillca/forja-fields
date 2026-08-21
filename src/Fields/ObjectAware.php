<?php
/**
 * Contrato de los campos que además tocan el objeto, no sólo sus metadatos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Para los campos cuyo valor tiene un reflejo fuera de los metadatos.
 *
 * Casi todos los campos son autosuficientes: reciben un valor, lo sanean y lo
 * guardan bajo su clave. `taxonomy` puede no serlo. Con `save_terms` activado,
 * elegir un término no sólo guarda su identificador: **asigna de verdad el
 * término a la entrada**, que es lo que hace que salga en los archivos, en los
 * menús y en las consultas por taxonomía.
 *
 * Eso obliga a conocer el objeto que se está editando, y hasta ahora ningún
 * campo lo sabía: `sanitize()` recibe un valor y `Composite::write_value()`, un
 * almacén ya ligado al objeto. Antes que dar estado al campo —que se comparte
 * entre peticiones y objetos— el dato lo aporta quien sí lo tiene: el contexto
 * al guardar, y `forja_get_field()` al leer.
 *
 * Los campos que implementan esto siguen guardando su metadato con normalidad.
 * Esto es un añadido, no un sustituto.
 */
interface ObjectAware {

	/**
	 * Lee el valor del propio objeto en lugar de sus metadatos.
	 *
	 * @param int|string $object_id   Objeto contenedor.
	 * @param string     $object_type Tipo de objeto: post, term, user u option.
	 * @return mixed Valor leído, o null si en esta configuración no aplica y
	 *               debe usarse el metadato.
	 */
	public function read_from_object( int|string $object_id, string $object_type ): mixed;

	/**
	 * Refleja el valor en el objeto, además de guardarlo como metadato.
	 *
	 * @param int|string $object_id   Objeto contenedor.
	 * @param string     $object_type Tipo de objeto: post, term, user u option.
	 * @param mixed      $value       Valor ya saneado.
	 * @return void
	 */
	public function write_to_object( int|string $object_id, string $object_type, mixed $value ): void;
}
