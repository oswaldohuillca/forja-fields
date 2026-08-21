<?php
/**
 * Referencia a uno o varios términos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «taxonomy» de ACF.
 *
 * Guarda identificadores de término. A diferencia del resto de relacionales, no
 * siempre es un desplegable: `field_type` decide entre casillas, opciones
 * excluyentes o `select`, porque una taxonomía suele tener pocos términos y
 * verlos todos a la vez es más cómodo que buscarlos.
 *
 * Limitación conocida: `save_terms` y `load_terms` de ACF —asignar de verdad
 * los términos al objeto, además de guardarlos como metadato— todavía no están.
 * El campo guarda en metadatos, que es el comportamiento por defecto de ACF.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-taxonomy.php
 */
final class Taxonomy extends RelationalField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'taxonomy';
	}

	/**
	 * Valores por defecto propios del tipo.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'taxonomy'   => 'category',
				// Control con el que se elige: checkbox, radio, select o
				// multi_select.
				'field_type' => 'checkbox',
				// Incluir los términos que no tienen contenido asignado.
				'hide_empty' => false,
			)
		);
	}

	/**
	 * Taxonomía sobre la que trabaja el campo.
	 *
	 * @return string Nombre de la taxonomía.
	 */
	private function taxonomy(): string {
		return (string) $this->get( 'taxonomy', 'category' );
	}

	/**
	 * Control con el que se elige.
	 *
	 * @return string checkbox, radio, select o multi_select.
	 */
	private function field_type(): string {
		$type = (string) $this->get( 'field_type', 'checkbox' );

		return in_array( $type, array( 'checkbox', 'radio', 'select', 'multi_select' ), true )
			? $type
			: 'checkbox';
	}

	/**
	 * La selección es múltiple según el control elegido, no por una opción
	 * aparte.
	 *
	 * @return bool True si admite varios términos.
	 */
	protected function is_multiple(): bool {
		return in_array( $this->field_type(), array( 'checkbox', 'multi_select' ), true );
	}

	/**
	 * Con casillas o radios hay varios controles, así que la etiqueta no puede
	 * apuntar a uno concreto.
	 *
	 * @return bool True sólo en las variantes de desplegable.
	 */
	public function label_targets_input(): bool {
		return in_array( $this->field_type(), array( 'select', 'multi_select' ), true );
	}

	/**
	 * Términos de la taxonomía, ordenados por jerarquía.
	 *
	 * @return array<int, \WP_Term> Términos.
	 */
	private function terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $this->taxonomy(),
				'hide_empty' => (bool) $this->get( 'hide_empty', false ),
			)
		);

		return is_array( $terms ) ? array_filter( $terms, static fn ( $term ): bool => $term instanceof \WP_Term ) : array();
	}

	/**
	 * Etiquetas de unos términos concretos.
	 *
	 * @param array<int, string> $values Identificadores almacenados.
	 * @return array<string, string> Etiquetas indexadas por identificador.
	 */
	protected function labels_for( array $values ): array {
		$labels = array();

		foreach ( $values as $value ) {
			$term = $this->resolve( $value );

			if ( $term instanceof \WP_Term ) {
				$labels[ $value ] = $term->name;
			}
		}

		return $labels;
	}

	/**
	 * Busca términos por nombre.
	 *
	 * @param string                $term    Texto buscado.
	 * @param int                   $page    Página de resultados, empezando en 1.
	 * @param array<string, string> $filters Sin uso en este campo.
	 * @return array<int, array{id: string, text: string}> Resultados.
	 */
	public function search( string $term, int $page, array $filters = array() ): array {
		unset( $filters );

		$args = array(
			'taxonomy'   => $this->taxonomy(),
			'hide_empty' => (bool) $this->get( 'hide_empty', false ),
			'number'     => self::PER_PAGE,
			'offset'     => ( max( 1, $page ) - 1 ) * self::PER_PAGE,
		);

		if ( '' !== $term ) {
			$args['search'] = $term;
		}

		$found = get_terms( $args );

		if ( ! is_array( $found ) ) {
			return array();
		}

		$results = array();

		foreach ( $found as $item ) {
			if ( $item instanceof \WP_Term ) {
				$results[] = array(
					'id'   => (string) $item->term_id,
					'text' => $item->name,
				);
			}
		}

		return $results;
	}

	/**
	 * Devuelve el término al que apunta un valor.
	 *
	 * @param string $value Identificador almacenado.
	 * @return \WP_Term|null Término, o null si ya no existe.
	 */
	protected function resolve( string $value ): mixed {
		$term = get_term( absint( $value ), $this->taxonomy() );

		return $term instanceof \WP_Term ? $term : null;
	}

	/**
	 * Pinta el control que corresponda.
	 *
	 * @param mixed  $value      Valor o valores actuales.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		if ( in_array( $this->field_type(), array( 'select', 'multi_select' ), true ) ) {
			parent::render_input( $value, $input_name );

			return;
		}

		$this->render_list( $this->to_list( $value ), $input_name );
	}

	/**
	 * Pinta la lista de casillas u opciones excluyentes.
	 *
	 * Se reutiliza el markup de `checkbox` y `radio`, que ya está portado y
	 * tiene su CSS.
	 *
	 * @param array<int, string> $current    Identificadores elegidos.
	 * @param string             $input_name Atributo «name» del control.
	 * @return void
	 */
	private function render_list( array $current, string $input_name ): void {
		$multiple = $this->is_multiple();
		$type     = $multiple ? 'checkbox' : 'radio';

		// Sin esto, desmarcarlo todo no enviaría la clave y se conservaría la
		// selección anterior.
		printf( '<input type="hidden" name="%s" />', esc_attr( $input_name ) );

		printf(
			'<ul class="acf-%s-list acf-bl" role="%s" aria-labelledby="%s">',
			esc_attr( $type ),
			$multiple ? 'group' : 'radiogroup',
			esc_attr( $this->input_id() . '-label' )
		);

		$terms = $this->terms();

		if ( array() === $terms ) {
			printf(
				'<li class="acf-notice -info"><p>%s</p></li>',
				esc_html__( 'No hay términos que elegir.', 'forja-fields' )
			);
		}

		foreach ( $terms as $term ) {
			$id = (string) $term->term_id;

			$attributes = array(
				'type'  => $type,
				'name'  => $multiple ? $input_name . '[]' : $input_name,
				'value' => $id,
				'id'    => $this->input_id() . '-' . $id,
			);

			if ( in_array( $id, $current, true ) ) {
				$attributes['checked'] = 'checked';
			}

			if ( $this->get( 'disabled', false ) ) {
				$attributes['disabled'] = 'disabled';
			}

			printf(
				'<li><label><input %1$s /> %2$s</label></li>',
				Html::attributes( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.
				esc_html( $this->indent( $term ) . $term->name )
			);
		}

		echo '</ul>';
	}

	/**
	 * Sangrado que refleja la profundidad de un término jerárquico.
	 *
	 * @param \WP_Term $term Término.
	 * @return string Prefijo de sangrado.
	 */
	private function indent( \WP_Term $term ): string {
		if ( ! is_taxonomy_hierarchical( $this->taxonomy() ) ) {
			return '';
		}

		$ancestors = get_ancestors( $term->term_id, $this->taxonomy(), 'taxonomy' );

		return str_repeat( '— ', count( $ancestors ) );
	}
}
