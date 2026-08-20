<?php
/**
 * Registro global de grupos de campos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Registry;

use Forja\Fields\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Guarda las cajas declaradas y permite consultarlas por contexto.
 */
final class BoxRegistry {

	/**
	 * Cajas registradas, indexadas por identificador.
	 *
	 * @var array<string, Box>
	 */
	private array $boxes = array();

	/**
	 * Catálogo de tipos de campo.
	 *
	 * @var FieldRegistry
	 */
	private FieldRegistry $fields;

	/**
	 * Conjuntos de campos reutilizables.
	 *
	 * @var FieldSets
	 */
	private FieldSets $sets;

	/**
	 * Definiciones ya expandidas de cada caja, indexadas por identificador.
	 *
	 * Se conservan además de los campos instanciados porque un `clone` puede
	 * apuntar a una caja anterior, y clonar necesita las definiciones en crudo:
	 * cada copia puede renombrarse o prefijarse antes de construirse.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $definitions = array();

	/**
	 * Índice de campos por nombre, construido bajo demanda.
	 *
	 * @var array<string, Field>|null
	 */
	private ?array $field_index = null;

	/**
	 * Constructor.
	 *
	 * @param FieldRegistry  $fields Catálogo de tipos de campo.
	 * @param FieldSets|null $sets   Conjuntos reutilizables; se crea uno vacío si no se pasa.
	 */
	public function __construct( FieldRegistry $fields, ?FieldSets $sets = null ) {
		$this->fields = $fields;
		$this->sets   = $sets ?? new FieldSets();
	}

	/**
	 * Conjuntos de campos reutilizables.
	 *
	 * @return FieldSets Registro de conjuntos.
	 */
	public function sets(): FieldSets {
		return $this->sets;
	}

	/**
	 * Expansor de clones, con las dos fuentes que se pueden referenciar.
	 *
	 * Un `clone` por nombre busca primero entre los conjuntos reutilizables y
	 * después entre las cajas ya registradas. El orden importa poco en la
	 * práctica —los identificadores no suelen chocar— pero se documenta para que
	 * sea predecible.
	 *
	 * Las definiciones de una caja se devuelven ya expandidas, así que clonar
	 * una caja que a su vez clonaba otra no vuelve a resolver nada.
	 *
	 * @return CloneResolver Expansor listo para usar.
	 */
	private function resolver(): CloneResolver {
		return new CloneResolver(
			fn ( string $reference ): ?array => $this->sets->get( $reference ) ?? ( $this->definitions[ $reference ] ?? null )
		);
	}

	/**
	 * Declara una caja a partir de su configuración.
	 *
	 * @param string               $id   Identificador único.
	 * @param array<string, mixed> $args Configuración; la clave «fields» contiene los campos.
	 * @return Box Caja registrada.
	 * @throws \InvalidArgumentException Si el identificador ya existe o la configuración es inválida.
	 */
	public function register( string $id, array $args ): Box {
		if ( isset( $this->boxes[ $id ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Forja: ya existe una caja con el id «%s».', esc_html( $id ) )
			);
		}

		$definitions = (array) ( $args['fields'] ?? array() );
		unset( $args['fields'] );

		// Los clones se resuelven aquí, antes de instanciar nada: a partir de
		// esta línea el resto del paquete trabaja con una lista de campos
		// normales y no sabe que existía un `clone`.
		$definitions = $this->resolver()->expand( $definitions );

		$fields = array();

		foreach ( $definitions as $definition ) {
			$fields[] = $this->fields->make( $definition );
		}

		$box = new Box( $id, $args, $fields );

		$this->boxes[ $id ]       = $box;
		$this->definitions[ $id ] = $definitions;

		// El índice se reconstruye a la próxima consulta.
		$this->field_index = null;

		return $box;
	}

	/**
	 * Devuelve todas las cajas registradas.
	 *
	 * @return array<string, Box> Cajas.
	 */
	public function all(): array {
		return $this->boxes;
	}

	/**
	 * Devuelve una caja por su identificador.
	 *
	 * @param string $id Identificador.
	 * @return Box|null Caja, o null si no existe.
	 */
	public function get( string $id ): ?Box {
		return $this->boxes[ $id ] ?? null;
	}

	/**
	 * Busca un campo declarado por su nombre.
	 *
	 * Lo usa la API de lectura para saber cómo dar forma al valor almacenado.
	 * Se construye un índice perezoso porque `forja_get_field()` se llama
	 * muchas veces por página y recorrer todas las cajas cada vez sería
	 * innecesario.
	 *
	 * Si dos cajas declaran un campo con el mismo nombre gana la primera
	 * registrada, que es también la que escribe en esa clave de metadatos.
	 *
	 * @param string $name Nombre del campo.
	 * @return Field|null Campo declarado, o null si no existe.
	 */
	public function find_field( string $name ): ?Field {
		if ( null === $this->field_index ) {
			$this->field_index = array();

			foreach ( $this->boxes as $box ) {
				foreach ( $box->fields() as $field ) {
					if ( ! isset( $this->field_index[ $field->name() ] ) ) {
						$this->field_index[ $field->name() ] = $field;
					}
				}
			}
		}

		return $this->field_index[ $name ] ?? null;
	}

	/**
	 * Devuelve todas las cajas de un tipo de objeto.
	 *
	 * A diferencia de `for_subtype()`, no mira los subtipos. Lo usan los
	 * contextos que necesitan filtrar por su cuenta: el de usuarios, por
	 * ejemplo, compara los subtipos contra los roles de la persona, y un
	 * subtipo vacío no significa lo mismo que «sin filtro».
	 *
	 * @param string $object_type Tipo de objeto: post, term, user, comment u option.
	 * @return array<string, Box> Cajas del tipo indicado.
	 */
	public function for_object_type( string $object_type ): array {
		return array_filter(
			$this->boxes,
			static fn ( Box $box ): bool => $box->get( 'object_type' ) === $object_type
		);
	}

	/**
	 * Filtra las cajas que aplican a un tipo y subtipo de objeto.
	 *
	 * Deliberadamente no evalúa plantilla, identificador ni condición: eso
	 * necesita el objeto en la mano y se hace con `Box::matches_object()`.
	 * Separarlo permite que el guardado use este filtro más amplio y se apoye
	 * en el nonce, evitando depender de un estado (la plantilla elegida) que
	 * puede estar cambiando en la misma petición.
	 *
	 * @param string $object_type Tipo de objeto: post, term, user, comment u option.
	 * @param string $subtype     Subtipo concreto: post type, taxonomía, etc.
	 * @return array<string, Box> Cajas que aplican.
	 */
	public function for_subtype( string $object_type, string $subtype ): array {
		return array_filter(
			$this->boxes,
			static fn ( Box $box ): bool => $box->get( 'object_type' ) === $object_type && $box->applies_to( $subtype )
		);
	}
}
