<?php
/**
 * Grupo de campos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Registry;

use Forja\Fields\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Representa un grupo de campos y dónde debe aparecer.
 *
 * Sustituye al «field group» de ACF, pero sin reglas de ubicación
 * evaluadas en tiempo de ejecución: al declararse por código, el destino
 * se indica directamente y no hace falta motor de condiciones.
 */
final class Box {

	/**
	 * Identificador único de la caja.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Configuración normalizada.
	 *
	 * @var array<string, mixed>
	 */
	private array $args;

	/**
	 * Campos ya instanciados.
	 *
	 * @var array<int, Field>
	 */
	private array $fields;

	/**
	 * Constructor.
	 *
	 * @param string               $id     Identificador único.
	 * @param array<string, mixed> $args   Configuración de la caja.
	 * @param array<int, Field>    $fields Campos instanciados.
	 */
	public function __construct( string $id, array $args, array $fields ) {
		$this->id     = $id;
		$this->args   = array_merge( self::defaults(), $args );
		$this->fields = $fields;
	}

	/**
	 * Configuración por defecto de una caja.
	 *
	 * @return array<string, mixed> Valores por defecto.
	 */
	public static function defaults(): array {
		return array(
			'title'                 => '',
			// Dónde aparece: post, term, user, comment u option.
			'object_type'           => 'post',
			// Subtipos afectados: post types, taxonomías, etc. Vacío significa todos.
			'object_subtypes'       => array(),
			// Plantillas de página; usa 'default' para la plantilla por defecto.
			'templates'             => array(),
			// Identificadores concretos de objeto.
			'object_ids'            => array(),
			// Escape hatch: recibe el objeto y devuelve si la caja aplica.
			'condition'             => null,
			// Contexto y prioridad de add_meta_box().
			'context'               => 'normal',
			'priority'              => 'default',
			// Colocación de las instrucciones: label o field.
			'instruction_placement' => 'label',
			// Colocación de la etiqueta: top o left.
			'label_placement'       => 'top',
		);
	}

	/**
	 * Identificador de la caja.
	 *
	 * @return string Identificador.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Campos que contiene.
	 *
	 * @return array<int, Field> Campos.
	 */
	public function fields(): array {
		return $this->fields;
	}

	/**
	 * Devuelve una opción de configuración.
	 *
	 * @param string $key      Nombre de la opción.
	 * @param mixed  $fallback Valor por defecto.
	 * @return mixed Valor de la opción.
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->args[ $key ] ?? $fallback;
	}

	/**
	 * Indica si la caja aplica a un subtipo concreto.
	 *
	 * Una caja sin subtipos declarados aplica a todos.
	 *
	 * @param string $subtype Post type, taxonomía o rol.
	 * @return bool True si aplica.
	 */
	public function applies_to( string $subtype ): bool {
		$subtypes = (array) $this->get( 'object_subtypes', array() );

		return array() === $subtypes || in_array( $subtype, $subtypes, true );
	}

	/**
	 * Indica si la caja aplica al objeto concreto que se está editando.
	 *
	 * Complementa a `applies_to()`, que sólo mira el subtipo. Aquí entran los
	 * criterios que necesitan el objeto en la mano: plantilla, identificador y
	 * la condición libre.
	 *
	 * Todos los criterios declarados deben cumplirse. Los que se dejan vacíos
	 * no filtran.
	 *
	 * @param object $target Objeto en edición; normalmente un WP_Post.
	 * @return bool True si aplica.
	 */
	public function matches_object( object $target ): bool {
		$templates = (array) $this->get( 'templates', array() );

		if ( array() !== $templates ) {
			if ( ! $target instanceof \WP_Post ) {
				return false;
			}

			// `get_page_template_slug()` devuelve cadena vacía para la
			// plantilla por defecto; se normaliza para poder declararla.
			$current = get_page_template_slug( $target );
			$current = '' === $current ? 'default' : $current;

			if ( ! in_array( $current, $templates, true ) ) {
				return false;
			}
		}

		$object_ids = (array) $this->get( 'object_ids', array() );

		if ( array() !== $object_ids ) {
			$id = $target->ID ?? 0;

			if ( ! in_array( (int) $id, array_map( 'intval', $object_ids ), true ) ) {
				return false;
			}
		}

		$condition = $this->get( 'condition' );

		if ( is_callable( $condition ) && ! $condition( $target ) ) {
			return false;
		}

		return true;
	}
}
