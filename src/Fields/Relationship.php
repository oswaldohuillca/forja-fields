<?php
/**
 * Selector de entradas con dos paneles.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «relationship» de ACF.
 *
 * Guarda lo mismo que `post_object` —una lista de identificadores— pero se
 * elige de otra forma: un panel con lo disponible y otro con lo elegido, en el
 * orden que decida quien edita. Ese orden se conserva, y es lo que distingue a
 * este campo de un `post_object` múltiple.
 *
 * No usa select2: su interfaz es propia, igual que en ACF.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-relationship.php
 */
final class Relationship extends PostObject {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'relationship';
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
				// Filtros del panel izquierdo: search y post_type.
				'filters' => array( 'search', 'post_type' ),
			)
		);
	}

	/**
	 * Siempre guarda una lista, aunque el máximo sea uno.
	 *
	 * @return bool Siempre true.
	 */
	protected function is_multiple(): bool {
		return true;
	}

	/**
	 * La etiqueta encabeza los dos paneles, no un control concreto.
	 *
	 * @return bool Siempre false.
	 */
	public function label_targets_input(): bool {
		return false;
	}

	/**
	 * Filtros activos del panel de disponibles.
	 *
	 * @return array<int, string> Nombres de filtro.
	 */
	private function filters(): array {
		return array_values(
			array_filter(
				array_map( 'strval', (array) $this->get( 'filters', array() ) ),
				static fn ( string $filter ): bool => in_array( $filter, array( 'search', 'post_type' ), true )
			)
		);
	}

	/**
	 * Pinta los dos paneles.
	 *
	 * El panel de disponibles lo rellena el JavaScript con el mismo endpoint de
	 * búsqueda que usan los demás relacionales. El de elegidos sí se pinta en el
	 * servidor, para que el campo muestre su valor aunque el JavaScript falle.
	 *
	 * @param mixed  $value      Identificadores actuales.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$current = $this->to_list( $value );
		$filters = $this->filters();

		$attributes = array(
			'class'      => 'acf-relationship',
			'data-min'   => (string) (int) $this->get( 'min', 0 ),
			'data-max'   => (string) (int) $this->get( 'max', 0 ),
			'data-field' => $this->name(),
			'data-nonce' => wp_create_nonce( $this->search_action() ),
			'data-name'  => $input_name,
		);

		printf( '<div %s>', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		// Sin esto, quitarlo todo no enviaría la clave y se conservaría la
		// selección anterior.
		printf( '<input type="hidden" name="%s" />', esc_attr( $input_name ) );

		if ( array() !== $filters ) {
			$this->render_filters( $filters );
		}

		echo '<div class="selection">';
		echo '<div class="choices"><ul class="acf-bl list choices-list"></ul></div>';
		echo '<div class="values"><ul class="acf-bl list values-list">';

		foreach ( $current as $id ) {
			$post = $this->resolve( $id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$this->render_value( $post, $input_name );
		}

		echo '</ul></div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Pinta la barra de filtros del panel de disponibles.
	 *
	 * @param array<int, string> $filters Filtros activos.
	 * @return void
	 */
	private function render_filters( array $filters ): void {
		printf( '<div class="filters -f%d">', count( $filters ) );

		if ( in_array( 'search', $filters, true ) ) {
			printf(
				'<div class="filter -search"><input type="text" data-filter="s" placeholder="%s" aria-label="%s" /></div>',
				esc_attr__( 'Buscar…', 'forja-fields' ),
				esc_attr__( 'Buscar entradas', 'forja-fields' )
			);
		}

		if ( in_array( 'post_type', $filters, true ) ) {
			$types = $this->post_types();

			// Con un solo tipo el filtro no ofrece ninguna elección.
			if ( count( $types ) > 1 ) {
				printf(
					'<div class="filter -post_type"><select data-filter="post_type" aria-label="%s"><option value="">%s</option>',
					esc_attr__( 'Filtrar por tipo de contenido', 'forja-fields' ),
					esc_html__( 'Todos los tipos', 'forja-fields' )
				);

				foreach ( $types as $type ) {
					$object = get_post_type_object( $type );

					printf(
						'<option value="%s">%s</option>',
						esc_attr( $type ),
						esc_html( $object instanceof \WP_Post_Type ? $object->labels->singular_name : $type )
					);
				}

				echo '</select></div>';
			}
		}

		echo '</div>';
	}

	/**
	 * Pinta una entrada del panel de elegidas.
	 *
	 * @param \WP_Post $post       Entrada elegida.
	 * @param string   $input_name Atributo «name» del control.
	 * @return void
	 */
	private function render_value( \WP_Post $post, string $input_name ): void {
		printf(
			'<li><input type="hidden" name="%1$s[]" value="%2$d" />'
			. '<span tabindex="0" data-id="%2$d" class="acf-rel-item acf-rel-item-remove">%3$s'
			. '<a href="#" class="acf-icon -minus small dark" data-name="remove_item" title="%4$s" aria-label="%4$s"></a>'
			. '</span></li>',
			esc_attr( $input_name ),
			(int) $post->ID,
			esc_html( $this->result_text( $post ) ),
			esc_attr__( 'Quitar', 'forja-fields' )
		);
	}
}
