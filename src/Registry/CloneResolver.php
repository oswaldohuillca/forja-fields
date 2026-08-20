<?php
/**
 * Expansión de los campos de tipo «clone».
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Sustituye cada `clone` por los campos a los que apunta.
 *
 * En ACF el clon existe en tiempo de ejecución porque los campos viven en la
 * base de datos: no hay forma de que un grupo referencie a otro salvo por su
 * clave, y la sustitución tiene que ocurrir en cada petición. Aquí los campos
 * se declaran por código, así que el clon se puede resolver **una sola vez, al
 * registrar la caja**, antes de instanciar nada.
 *
 * La diferencia importa: al terminar esta clase no queda ningún campo de tipo
 * `clone` en el árbol. Ni el renderer, ni el guardado, ni la lectura saben que
 * existió. Todo el comportamiento raro del clon en ACF —claves compuestas,
 * nombres de respaldo en `__key`, filtros que restauran la clave original para
 * que la lógica condicional funcione— desaparece con él.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-clone.php
 */
final class CloneResolver {

	/**
	 * Profundidad máxima de clones anidados.
	 *
	 * Un clon puede apuntar a un conjunto que a su vez clona otro. El límite
	 * corta el caso patológico —un conjunto que se clona a sí mismo— con un
	 * error legible en lugar de agotar la memoria.
	 */
	private const MAX_DEPTH = 5;

	/**
	 * Resuelve una referencia por nombre a una lista de definiciones.
	 *
	 * @var callable(string): (array<int, array<string, mixed>>|null)
	 */
	private $resolve;

	/**
	 * Constructor.
	 *
	 * @param callable(string): (array<int, array<string, mixed>>|null) $resolve Traduce un identificador en definiciones.
	 */
	public function __construct( callable $resolve ) {
		$this->resolve = $resolve;
	}

	/**
	 * Expande los clones de una lista de definiciones.
	 *
	 * @param array<int, array<string, mixed>> $definitions Definiciones declaradas.
	 * @return array<int, array<string, mixed>> Definiciones sin ningún «clone».
	 */
	public function expand( array $definitions ): array {
		return $this->walk( $definitions, 0, array() );
	}

	/**
	 * Recorre una lista de definiciones expandiendo lo que encuentre.
	 *
	 * @param array<int, array<string, mixed>> $definitions Definiciones a recorrer.
	 * @param int                              $depth       Nivel de anidamiento de clones.
	 * @param array<int, string>               $trail       Referencias ya visitadas, para detectar ciclos.
	 * @return array<int, array<string, mixed>> Definiciones expandidas.
	 */
	private function walk( array $definitions, int $depth, array $trail ): array {
		$expanded = array();

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			if ( 'clone' === ( $definition['type'] ?? '' ) ) {
				// Un clon puede aportar varios campos, de ahí el array_push
				// con desempaquetado en lugar de una asignación.
				array_push( $expanded, ...$this->expand_clone( $definition, $depth, $trail ) );
				continue;
			}

			$expanded[] = $this->walk_children( $definition, $depth, $trail );
		}

		return $expanded;
	}

	/**
	 * Expande los clones que haya dentro de un campo contenedor.
	 *
	 * Los grupos y repetidores guardan sus hijos en «sub_fields»; el contenido
	 * flexible, en «layouts». Se recorren aquí para que un conjunto reutilizable
	 * se pueda clonar también dentro de una fila.
	 *
	 * @param array<string, mixed> $definition Definición de un campo.
	 * @param int                  $depth      Nivel de anidamiento de clones.
	 * @param array<int, string>   $trail      Referencias ya visitadas.
	 * @return array<string, mixed> Definición con los hijos expandidos.
	 */
	private function walk_children( array $definition, int $depth, array $trail ): array {
		if ( isset( $definition['sub_fields'] ) && is_array( $definition['sub_fields'] ) ) {
			$definition['sub_fields'] = $this->walk( $definition['sub_fields'], $depth, $trail );
		}

		if ( isset( $definition['layouts'] ) && is_array( $definition['layouts'] ) ) {
			foreach ( $definition['layouts'] as $name => $layout ) {
				if ( is_array( $layout ) && isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					$definition['layouts'][ $name ]['sub_fields'] = $this->walk( $layout['sub_fields'], $depth, $trail );
				}
			}
		}

		return $definition;
	}

	/**
	 * Convierte un `clone` en los campos que aporta.
	 *
	 * @param array<string, mixed> $spec  Definición del clon.
	 * @param int                  $depth Nivel de anidamiento.
	 * @param array<int, string>   $trail Referencias ya visitadas.
	 * @return array<int, array<string, mixed>> Campos resultantes.
	 * @throws \InvalidArgumentException Si la referencia no existe, se repite o se anida demasiado.
	 */
	private function expand_clone( array $spec, int $depth, array $trail ): array {
		if ( $depth >= self::MAX_DEPTH ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Forja: los clones se anidan más de %d niveles (%s). Probablemente un conjunto se clona a sí mismo.',
					absint( self::MAX_DEPTH ),
					esc_html( implode( ' → ', $trail ) )
				)
			);
		}

		$source = $spec['clone'] ?? array();

		// Una referencia por nombre: un conjunto reutilizable o una caja ya
		// registrada. Se resuelve fuera de esta clase para no acoplarla a
		// ninguno de los dos registros.
		if ( is_string( $source ) ) {
			if ( in_array( $source, $trail, true ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Forja: ciclo de clones en «%s».',
						esc_html( implode( ' → ', array_merge( $trail, array( $source ) ) ) )
					)
				);
			}

			$trail[]  = $source;
			$resolved = ( $this->resolve )( $source );

			if ( null === $resolved ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Forja: el clon apunta a «%s», que no es ningún conjunto ni ninguna caja. Comprueba el identificador y que se declare en «forja/register_boxes»; el orden entre ambos da igual.',
						esc_html( $source )
					)
				);
			}

			$source = $resolved;
		}

		if ( ! is_array( $source ) || array() === $source ) {
			throw new \InvalidArgumentException( 'Forja: un campo «clone» necesita la clave «clone» con un conjunto de campos.' );
		}

		$fields = $this->override( $this->walk( $source, $depth + 1, $trail ), $spec );

		// En modo «group» el clon sí deja rastro: se convierte en un grupo, que
		// ya prefija las claves de sus hijos por sí mismo. No hace falta tocar
		// los nombres, y por eso `prefix_name` sólo aplica al modo seamless.
		if ( 'group' === ( $spec['display'] ?? 'seamless' ) ) {
			if ( ! empty( $spec['prefix_name'] ) ) {
				throw new \InvalidArgumentException( 'Forja: «prefix_name» no tiene efecto con «display» a group, porque el grupo ya antepone su nombre a las claves. Quita una de las dos.' );
			}

			return array( $this->as_group( $spec, $fields ) );
		}

		return array_map(
			fn ( array $field ): array => $this->apply( $field, $spec ),
			$fields
		);
	}

	/**
	 * Aplica los ajustes que el clon declare sobre campos concretos.
	 *
	 * Es lo que separa a `clone` de compartir una variable de PHP, y lo que ACF
	 * no puede ofrecer: allí los campos viven en la base de datos y una copia no
	 * se puede retocar sin duplicar el grupo entero. Aquí basta con nombrar el
	 * campo y las claves que cambian:
	 *
	 *     'overrides' => array(
	 *         'ancho' => array( 'label' => 'Anchura', 'required' => true ),
	 *     )
	 *
	 * Los nombres son los del conjunto de origen, antes de cualquier prefijo.
	 *
	 * @param array<int, array<string, mixed>> $fields Campos clonados.
	 * @param array<string, mixed>             $spec   Definición del clon.
	 * @return array<int, array<string, mixed>> Campos ajustados.
	 * @throws \InvalidArgumentException Si un ajuste nombra un campo que el conjunto no trae.
	 */
	private function override( array $fields, array $spec ): array {
		$overrides = $spec['overrides'] ?? array();

		if ( ! is_array( $overrides ) || array() === $overrides ) {
			return $fields;
		}

		$known = array_column( $fields, 'name' );

		// Un nombre que no existe casi siempre es una errata, y en silencio se
		// traduciría en «el ajuste no se aplicó» sin ninguna pista de por qué.
		$unknown = array_diff( array_keys( $overrides ), $known );

		if ( array() !== $unknown ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Forja: «overrides» nombra campos que el conjunto clonado no trae: %1$s. Disponibles: %2$s.',
					esc_html( implode( ', ', $unknown ) ),
					esc_html( implode( ', ', $known ) )
				)
			);
		}

		return array_map(
			static function ( array $field ) use ( $overrides ): array {
				$changes = $overrides[ $field['name'] ?? '' ] ?? null;

				return is_array( $changes ) ? array_merge( $field, $changes ) : $field;
			},
			$fields
		);
	}

	/**
	 * Envuelve los campos clonados en un grupo.
	 *
	 * @param array<string, mixed>             $spec  Definición del clon.
	 * @param array<int, array<string, mixed>> $fields Campos clonados.
	 * @return array<string, mixed> Definición de un campo «group».
	 * @throws \InvalidArgumentException Si el clon no tiene nombre.
	 */
	private function as_group( array $spec, array $fields ): array {
		if ( empty( $spec['name'] ) ) {
			throw new \InvalidArgumentException( 'Forja: un clon con «display» a group necesita un «name», porque pasa a formar parte de la clave.' );
		}

		$group = $spec;

		unset( $group['clone'], $group['display'], $group['prefix_name'], $group['prefix_label'], $group['overrides'] );

		$group['type']       = 'group';
		$group['sub_fields'] = $fields;

		return $group;
	}

	/**
	 * Adapta un campo clonado a lo que pide el clon.
	 *
	 * @param array<string, mixed> $field Campo clonado.
	 * @param array<string, mixed> $spec  Definición del clon.
	 * @return array<string, mixed> Campo adaptado.
	 * @throws \InvalidArgumentException Si se pide prefijo y el clon no tiene nombre.
	 */
	private function apply( array $field, array $spec ): array {
		if ( ! empty( $spec['prefix_name'] ) ) {
			if ( empty( $spec['name'] ) ) {
				throw new \InvalidArgumentException( 'Forja: un clon con «prefix_name» necesita un «name» del que sacar el prefijo.' );
			}

			$field['name'] = $spec['name'] . '_' . ( $field['name'] ?? '' );
		}

		if ( ! empty( $spec['prefix_label'] ) && ! empty( $field['label'] ) ) {
			$field['label'] = trim( (string) ( $spec['label'] ?? '' ) . ' ' . $field['label'] );
		}

		// Un clon obligatorio vuelve obligatorio todo lo que trae, igual que en
		// ACF. Al revés no: un clon opcional no relaja lo que el conjunto haya
		// declarado como obligatorio.
		if ( ! empty( $spec['required'] ) ) {
			$field['required'] = true;
		}

		/*
		 * En modo seamless el clon desaparece, y con él sus reglas de
		 * visibilidad. En ACF eso significa perderlas; aquí se heredan a los
		 * campos que no tengan las suyas, que es lo que se espera al escribir
		 * «muestra este bloque de campos sólo si...».
		 */
		if ( ! empty( $spec['conditional_logic'] ) && empty( $field['conditional_logic'] ) ) {
			$field['conditional_logic'] = $spec['conditional_logic'];
		}

		return $field;
	}
}
