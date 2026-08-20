<?php
/**
 * Contrato de los campos que ocupan varias claves de almacenamiento.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Marca un campo cuyo valor no cabe en una sola clave.
 *
 * Un campo normal es una clave y un valor, y de eso se encarga la capa de
 * almacenamiento sin más. Un repetidor ocupa una clave por subcampo y fila,
 * así que necesita decidir por su cuenta qué leer y qué escribir.
 *
 * El contexto le pasa las tres operaciones sobre el almacén ya ligadas al
 * objeto en curso, de modo que el campo no necesita saber si está guardando en
 * un post, un término o una página de opciones.
 */
interface Composite {

	/**
	 * Lee el valor completo del campo.
	 *
	 * @param callable $get Devuelve el valor de una clave.
	 * @return mixed Valor reconstruido.
	 */
	public function read_value( callable $get ): mixed;

	/**
	 * Escribe el valor completo del campo.
	 *
	 * @param mixed    $submitted Valor crudo enviado por el navegador.
	 * @param callable $get       Devuelve el valor de una clave.
	 * @param callable $set       Guarda el valor de una clave.
	 * @param callable $delete    Borra una clave.
	 * @return void
	 */
	public function write_value( mixed $submitted, callable $get, callable $set, callable $delete ): void;
}
