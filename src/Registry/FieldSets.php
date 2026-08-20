<?php
/**
 * Conjuntos de campos reutilizables.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Guarda listas de campos con nombre, para clonarlas desde varias cajas.
 *
 * A diferencia de una caja, un conjunto no se pinta en ninguna parte por sí
 * mismo: no tiene título ni ubicación. Existe sólo para que `clone` lo
 * referencie. Se guardan las definiciones en crudo, no instancias de `Field`,
 * porque cada clon puede renombrarlas o prefijarlas antes de construirlas.
 */
final class FieldSets {

	/**
	 * Definiciones indexadas por identificador.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $sets = array();

	/**
	 * Declara un conjunto reutilizable.
	 *
	 * @param string                           $id     Identificador único.
	 * @param array<int, array<string, mixed>> $fields Definiciones de campo.
	 * @return void
	 * @throws \InvalidArgumentException Si el identificador ya existe.
	 */
	public function register( string $id, array $fields ): void {
		if ( isset( $this->sets[ $id ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Forja: ya existe un conjunto de campos con el id «%s».', esc_html( $id ) )
			);
		}

		$this->sets[ $id ] = $fields;
	}

	/**
	 * Devuelve las definiciones de un conjunto.
	 *
	 * @param string $id Identificador.
	 * @return array<int, array<string, mixed>>|null Definiciones, o null si no existe.
	 */
	public function get( string $id ): ?array {
		return $this->sets[ $id ] ?? null;
	}

	/**
	 * Devuelve todos los conjuntos declarados.
	 *
	 * @return array<string, array<int, array<string, mixed>>> Conjuntos.
	 */
	public function all(): array {
		return $this->sets;
	}
}
