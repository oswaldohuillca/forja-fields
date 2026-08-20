<?php
/**
 * Pintado del envoltorio de los campos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Render;

use Forja\Fields\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Reproduce el markup de `acf_render_field_wrap()` de ACF/SCF.
 *
 * La estructura DOM se mantiene igual a la del original porque las líneas de
 * CSS portadas dependen de ella. Cualquier cambio aquí rompe la paridad
 * visual, que es el requisito central del paquete.
 *
 * @see secure-custom-fields/includes/acf-field-functions.php:645
 */
final class Renderer {

	/**
	 * Contador de grupos de pestañas dentro de la misma petición.
	 *
	 * Sirve para que dos cajas con pestañas no se interfieran.
	 *
	 * @var int
	 */
	private int $tab_group = 0;

	/**
	 * Pinta una colección de campos dentro de su contenedor.
	 *
	 * @param array<int, Field>    $fields       Campos a pintar.
	 * @param array<string, mixed> $values       Valores actuales, indexados por nombre de campo.
	 * @param string               $input_prefix Prefijo del atributo «name» de los controles.
	 * @param string               $instruction  Dónde colocar las instrucciones: label o field.
	 * @return void
	 */
	public function render_fields( array $fields, array $values, string $input_prefix, string $instruction = 'label' ): void {
		$layout = Layout::parse( $fields );
		$group  = '';

		if ( array() !== $layout['tabs'] ) {
			++$this->tab_group;
			$group = 'g' . $this->tab_group;

			$this->render_tab_bar( $layout['tabs'], $group );
		}

		foreach ( $layout['nodes'] as $node ) {
			$extra = $this->tab_attributes( $node['tab'] ?? null, $group );

			if ( 'accordion' === $node['type'] ) {
				$this->render_accordion( $node, $values, $input_prefix, $instruction, $extra );
				continue;
			}

			$field = $node['field'];
			$value = $values[ $field->name() ] ?? $field->default_value();

			$this->render_field_wrap( $field, $value, $input_prefix, $instruction, $extra );
		}
	}

	/**
	 * Pinta el envoltorio completo de un campo.
	 *
	 * @param Field                 $field        Campo a pintar.
	 * @param mixed                 $value        Valor actual.
	 * @param string                $input_prefix Prefijo del atributo «name».
	 * @param string                $instruction  Dónde colocar las instrucciones.
	 * @param array<string, string> $extra        Atributos adicionales del envoltorio.
	 * @param string                $element      Etiqueta del envoltorio: div o td.
	 * @return void
	 */
	public function render_field_wrap( Field $field, mixed $value, string $input_prefix, string $instruction = 'label', array $extra = array(), string $element = 'div' ): void {
		$wrapper = array_merge( $this->wrapper_attributes( $field ), $extra );

		// Dentro de una tabla el envoltorio es una celda. Su etiqueta ya vive
		// en la cabecera de la columna, así que aquí se omite: es lo que hace
		// ACF y de ello depende el ancho de la columna.
		$element  = 'td' === $element ? 'td' : 'div';
		$in_table = 'td' === $element;

		printf( '<%s %s>', esc_attr( $element ), Html::attributes( $wrapper ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		if ( ! $in_table ) {
			echo '<div class="acf-label">';
			$this->render_label( $field );

			if ( 'label' === $instruction ) {
				$this->render_instructions( $field );
			}

			echo '</div>';
		}

		echo '<div class="acf-input">';
		$field->render_input( $value, $input_prefix . '[' . $field->name() . ']' );

		if ( ! $in_table && 'field' === $instruction ) {
			$this->render_instructions( $field );
		}

		echo '</div>';

		printf( '</%s>', esc_attr( $element ) );
	}

	/**
	 * Pinta la barra de pestañas.
	 *
	 * @param array<int, array{key: string, label: string, selected: bool}> $tabs  Pestañas declaradas.
	 * @param string                                                        $group Identificador del grupo.
	 * @return void
	 */
	private function render_tab_bar( array $tabs, string $group ): void {
		printf(
			'<div class="acf-tab-wrap -top" data-forja-tab-group="%s">',
			esc_attr( $group )
		);

		echo '<ul class="acf-hl acf-tab-group" role="tablist">';

		foreach ( $tabs as $tab ) {
			printf(
				'<li><a class="acf-tab-button" href="#" role="tab" data-key="%s" data-selected="%s">%s</a></li>',
				esc_attr( $tab['key'] ),
				$tab['selected'] ? '1' : '0',
				esc_html( $tab['label'] )
			);
		}

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Pinta un acordeón con sus campos dentro.
	 *
	 * @param array<string, mixed>  $node         Nodo de tipo acordeón.
	 * @param array<string, mixed>  $values       Valores actuales.
	 * @param string                $input_prefix Prefijo del atributo «name».
	 * @param string                $instruction  Dónde colocar las instrucciones.
	 * @param array<string, string> $extra        Atributos adicionales del envoltorio.
	 * @return void
	 */
	private function render_accordion( array $node, array $values, string $input_prefix, string $instruction, array $extra ): void {
		$field = $node['field'];
		$open  = (bool) $field->get( 'open', false );

		$wrapper           = array_merge( $this->wrapper_attributes( $field ), $extra );
		$wrapper['class'] .= ' acf-accordion' . ( $open ? ' -open' : '' );

		if ( $field->get( 'multi_expand', false ) ) {
			$wrapper['data-multi-expand'] = '1';
		}

		printf( '<div %s>', Html::attributes( $wrapper ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		printf(
			'<div class="acf-label acf-accordion-title" tabindex="0" role="button" aria-expanded="%s">',
			$open ? 'true' : 'false'
		);

		// El icono lo repinta el JavaScript al abrir y cerrar; aquí se emite
		// ya en el estado correcto para que no parpadee al cargar.
		printf(
			'<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="acf-accordion-icon" aria-hidden="true" focusable="false"><path d="%s"></path></svg>',
			$open
				? 'M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z'
				: 'M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z'
		);

		$this->render_label( $field );
		$this->render_instructions( $field );

		echo '</div>';

		echo '<div class="acf-input acf-accordion-content">';
		echo '<div class="acf-fields">';

		foreach ( $node['children'] as $child ) {
			$value = $values[ $child->name() ] ?? $child->default_value();

			$this->render_field_wrap( $child, $value, $input_prefix, $instruction );
		}

		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Atributos que asocian un campo con su pestaña.
	 *
	 * @param string|null $tab   Clave de la pestaña, si el campo pertenece a una.
	 * @param string      $group Identificador del grupo de pestañas.
	 * @return array<string, string> Atributos adicionales.
	 */
	private function tab_attributes( ?string $tab, string $group ): array {
		if ( null === $tab || '' === $group ) {
			return array();
		}

		return array(
			'data-forja-tab'       => $tab,
			'data-forja-tab-group' => $group,
		);
	}

	/**
	 * Calcula los atributos del div envolvente.
	 *
	 * @param Field $field Campo a pintar.
	 * @return array<string, string> Atributos del envoltorio.
	 */
	private function wrapper_attributes( Field $field ): array {
		$wrapper = $field->get( 'wrapper', array() );
		$classes = 'acf-field acf-field-' . $field::type() . ' acf-field-' . $field->name();

		if ( $field->get( 'required', false ) ) {
			$classes .= ' is-required';
		}

		if ( ! empty( $wrapper['class'] ) ) {
			$classes .= ' ' . $wrapper['class'];
		}

		// ACF normaliza los guiones bajos a guiones en las clases para que los
		// selectores CSS sean predecibles. Replicamos ese comportamiento.
		$classes = str_replace( '_', '-', $classes );

		$attributes = array(
			'id'        => (string) ( $wrapper['id'] ?? '' ),
			'class'     => $classes,
			'style'     => '',
			'data-name' => $field->name(),
			'data-type' => $field::type(),
			'data-key'  => $field->name(),
		);

		if ( $field->get( 'required', false ) ) {
			$attributes['data-required'] = '1';
		}

		$width = (string) ( $wrapper['width'] ?? '' );

		if ( '' !== $width ) {
			$width                    = (float) preg_replace( '/[^0-9.]/', '', $width );
			$attributes['data-width'] = (string) $width;
			$attributes['style']     .= sprintf( 'width:%s%%;', $width );
		}

		return $attributes;
	}

	/**
	 * Pinta la etiqueta del campo.
	 *
	 * @param Field $field Campo a pintar.
	 * @return void
	 */
	private function render_label( Field $field ): void {
		$label = (string) $field->get( 'label', '' );

		if ( '' === $label ) {
			return;
		}

		$html = esc_html( $label );

		if ( $field->get( 'required', false ) ) {
			$html .= ' <span class="acf-required">*</span>';
		}

		// Con un solo control la etiqueta lo señala con `for`; con varios se
		// identifica para que el grupo la referencie con `aria-labelledby`.
		$attribute = $field->label_targets_input()
			? sprintf( 'for="%s"', esc_attr( $field->input_id() ) )
			: sprintf( 'id="%s-label"', esc_attr( $field->input_id() ) );

		printf(
			'<label %s>%s</label>',
			$attribute, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba; el asterisco es markup nuestro.
		);
	}

	/**
	 * Pinta las instrucciones del campo.
	 *
	 * @param Field $field Campo a pintar.
	 * @return void
	 */
	private function render_instructions( Field $field ): void {
		$instructions = (string) $field->get( 'instructions', '' );

		if ( '' === $instructions ) {
			return;
		}

		printf( '<p class="description">%s</p>', wp_kses_post( $instructions ) );
	}
}
