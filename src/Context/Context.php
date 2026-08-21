<?php
/**
 * Base de los contextos donde se pintan y guardan campos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Context;

use Forja\Fields\Composite;
use Forja\Fields\ObjectAware;
use Forja\Registry\Box;
use Forja\Registry\BoxRegistry;
use Forja\Render\Renderer;
use Forja\Storage\Storage;
use Forja\Storage\StorageFactory;
use Forja\Validation\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Reúne lo que comparten todas las pantallas donde aparecen campos.
 *
 * Cada contexto —entradas, términos, usuarios, páginas de opciones— se engancha
 * a hooks distintos y pinta en un sitio distinto, pero leer, sanear, validar y
 * escribir se hace igual en todas partes. Eso vive aquí.
 */
abstract class Context {

	/**
	 * Prefijo del atributo «name» de todos los controles.
	 */
	protected const INPUT_PREFIX = 'forja';

	/**
	 * Registro de cajas.
	 *
	 * @var BoxRegistry
	 */
	protected BoxRegistry $boxes;

	/**
	 * Renderizador de campos.
	 *
	 * @var Renderer
	 */
	protected Renderer $renderer;

	/**
	 * Fábrica de almacenamiento.
	 *
	 * @var StorageFactory
	 */
	protected StorageFactory $storage;

	/**
	 * Validador de los valores enviados.
	 *
	 * @var Validator
	 */
	protected Validator $validator;

	/**
	 * Constructor.
	 *
	 * @param BoxRegistry    $boxes     Registro de cajas.
	 * @param Renderer       $renderer  Renderizador de campos.
	 * @param StorageFactory $storage   Fábrica de almacenamiento.
	 * @param Validator      $validator Validador de los valores enviados.
	 */
	public function __construct( BoxRegistry $boxes, Renderer $renderer, StorageFactory $storage, Validator $validator ) {
		$this->boxes     = $boxes;
		$this->renderer  = $renderer;
		$this->storage   = $storage;
		$this->validator = $validator;
	}

	/**
	 * Engancha el contexto a WordPress.
	 *
	 * @return void
	 */
	abstract public function register_hooks(): void;

	/**
	 * Tipo de objeto sobre el que trabaja esta pantalla.
	 *
	 * Lo necesitan el almacén y los campos que además tocan el objeto, como
	 * `taxonomy` con `save_terms`.
	 *
	 * @return string post, term, user u option.
	 */
	abstract protected function object_type(): string;

	/**
	 * Lee los valores de una caja.
	 *
	 * @param Box        $box       Caja a leer.
	 * @param Storage    $storage   Almacén donde buscar.
	 * @param int|string $object_id Objeto contenedor.
	 * @return array<string, mixed> Valores indexados por nombre de campo.
	 */
	protected function read( Box $box, Storage $storage, int|string $object_id ): array {
		$values = array();
		$get    = static fn ( string $key ): mixed => $storage->get( $object_id, $key );

		foreach ( $box->fields() as $field ) {
			// Un campo compuesto ocupa varias claves y sabe reconstruirse.
			if ( $field instanceof Composite ) {
				$values[ $field->name() ] = $field->read_value( $get );
				continue;
			}

			if ( ! $field->stores_value() ) {
				continue;
			}

			// Un campo que lee del propio objeto manda sobre el metadato: es lo
			// que hace que el formulario muestre los términos reales de la
			// entrada aunque alguien los cambiara desde otro sitio.
			if ( $field instanceof ObjectAware ) {
				$own = $field->read_from_object( $object_id, $this->object_type() );

				if ( null !== $own ) {
					$values[ $field->name() ] = $own;
					continue;
				}
			}

			$stored = $get( $field->name() );

			if ( null !== $stored ) {
				$values[ $field->name() ] = $stored;
			}
		}

		return $values;
	}

	/**
	 * Sanea, valida y guarda los valores de una caja.
	 *
	 * Un valor que no valida **no se escribe**: se conserva lo que hubiera. Así
	 * un envío manipulado no puede borrar un dato bueno.
	 *
	 * @param Box                  $box       Caja a guardar.
	 * @param Storage              $storage   Almacén donde escribir.
	 * @param int|string           $object_id Objeto contenedor.
	 * @param array<string, mixed> $submitted Valores crudos enviados.
	 * @return array<int, string> Mensajes de error; vacío si todo fue bien.
	 */
	protected function write( Box $box, Storage $storage, int|string $object_id, array $submitted ): array {
		$errors = array();

		foreach ( $box->fields() as $field ) {
			$name = $field->name();

			if ( $field instanceof Composite ) {
				if ( array_key_exists( $name, $submitted ) ) {
					$errors = array_merge(
						$errors,
						$field->write_value(
							$submitted[ $name ],
							static fn ( string $key ): mixed => $storage->get( $object_id, $key ),
							static fn ( string $key, mixed $value ): bool => $storage->update( $object_id, $key, $value ),
							static fn ( string $key ): bool => $storage->delete( $object_id, $key )
						)
					);
				}

				continue;
			}

			if ( ! $field->stores_value() || ! array_key_exists( $name, $submitted ) ) {
				continue;
			}

			$value = $field->sanitize( $submitted[ $name ] );
			$error = $this->validator->validate( $field, $value );

			if ( '' !== $error ) {
				$errors[] = $error;
				continue;
			}

			$storage->update( $object_id, $name, $value );

			// El metadato ya está guardado; esto es el reflejo en el objeto.
			if ( $field instanceof ObjectAware ) {
				$field->write_to_object( $object_id, $this->object_type(), $value );
			}
		}

		return $errors;
	}

	/**
	 * Valores crudos enviados para los campos de Forja.
	 *
	 * @return array<string, mixed> Valores enviados; vacío si no hay envío.
	 */
	protected function submitted(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cada contexto valida su nonce antes de llamar aquí.
		$submitted = wp_unslash( $_POST[ static::INPUT_PREFIX ] ?? array() );

		return is_array( $submitted ) ? $submitted : array();
	}

	/**
	 * Comprueba el nonce de una caja.
	 *
	 * @param Box $box Caja cuyo envío se comprueba.
	 * @return bool True si el envío es legítimo.
	 */
	protected function verify_nonce( Box $box ): bool {
		$name = $this->nonce_name( $box );

		if ( empty( $_POST[ $name ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ $name ] ) );

		return (bool) wp_verify_nonce( $nonce, $this->nonce_action( $box ) );
	}

	/**
	 * Nombre del campo oculto que transporta el nonce de una caja.
	 *
	 * @param Box $box Caja.
	 * @return string Nombre del campo.
	 */
	protected function nonce_name( Box $box ): string {
		return 'forja_nonce_' . $box->id();
	}

	/**
	 * Acción del nonce de una caja.
	 *
	 * @param Box $box Caja.
	 * @return string Acción del nonce.
	 */
	protected function nonce_action( Box $box ): string {
		return 'forja_save_' . $box->id();
	}
}
