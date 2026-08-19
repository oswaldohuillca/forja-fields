<?php
/**
 * Resolución del almacenamiento según el tipo de objeto.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Devuelve la implementación de almacenamiento adecuada para cada contexto.
 */
final class StorageFactory {

	/**
	 * Instancias ya construidas, indexadas por tipo de objeto.
	 *
	 * @var array<string, Storage>
	 */
	private array $instances = array();

	/**
	 * Resuelve el almacenamiento de un tipo de objeto.
	 *
	 * @param string $object_type Uno de: post, term, user, comment, option.
	 * @return Storage Implementación correspondiente.
	 * @throws \InvalidArgumentException Si el tipo de objeto no se reconoce.
	 */
	public function for( string $object_type ): Storage {
		if ( isset( $this->instances[ $object_type ] ) ) {
			return $this->instances[ $object_type ];
		}

		$storage = match ( $object_type ) {
			'post', 'term', 'user', 'comment' => new MetaStorage( $object_type ),
			'option' => new OptionStorage(),
			default  => null,
		};

		if ( null === $storage ) {
			throw new \InvalidArgumentException(
				sprintf( 'Forja: tipo de objeto desconocido «%s».', esc_html( $object_type ) )
			);
		}

		$this->instances[ $object_type ] = $storage;

		return $storage;
	}
}
