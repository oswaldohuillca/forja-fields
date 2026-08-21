<?php
/**
 * Base de los campos que apuntan a otro objeto de WordPress.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Reúne lo que comparten los campos que guardan referencias a otros objetos.
 *
 * En ACF, `post_object`, `page_link` y `user` no tienen markup propio: se
 * convierten en un `select` con `ui` y `ajax` activados, y select2 se encarga
 * del resto. Aquí se hace lo mismo, así que esta clase pinta el desplegable y
 * cada campo concreto sólo dice **de dónde salen las opciones**.
 *
 * La diferencia importante con un `select` normal es que el catálogo es
 * enorme y cambiante: no se pintan todas las opciones, sólo las que ya están
 * elegidas. El resto llega buscando, por AJAX.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-post_object.php
 */
abstract class RelationalField extends Field {

	/**
	 * Cuántos resultados devuelve cada página de la búsqueda.
	 */
	public const PER_PAGE = 20;

	/**
	 * Valores por defecto comunes a los campos relacionales.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'default_value' => '',
				// Permite elegir más de un objeto; guarda un array.
				'multiple'      => false,
				// Añade la opción vacía cuando la selección es simple.
				'allow_null'    => false,
				// Límites de la selección múltiple; 0 desactiva.
				'min'           => 0,
				'max'           => 0,
				// Qué devuelve forja_get_field(): id u object.
				'return_format' => 'id',
			)
		);
	}

	/**
	 * Encola select2, que se sirve desde el propio paquete.
	 *
	 * WordPress no lo trae, así que hay que aportarlo. Va como asset estático y
	 * no como dependencia del bundler a propósito: select2 necesita jQuery, y
	 * meterlo por npm obligaría a cada tema que consuma la librería a replicar
	 * un alias de jQuery en su propia configuración de Vite. Como asset se
	 * encola con `jquery` por dependencia y no toca el build de nadie. Es el
	 * mismo camino que el plugin de tablas de TinyMCE.
	 *
	 * @return void
	 */
	protected static function enqueue_select2(): void {
		if ( wp_script_is( 'select2', 'enqueued' ) ) {
			return;
		}

		$paths = \forja()->paths();

		wp_enqueue_style( 'select2', $paths->url( 'assets/vendor/select2/select2.min.css' ), array(), '4.0.13' );
		wp_enqueue_script( 'select2', $paths->url( 'assets/vendor/select2/select2.min.js' ), array( 'jquery' ), '4.0.13', true );
	}

	/**
	 * Etiquetas de unos valores concretos.
	 *
	 * Se usa para pintar lo que ya está elegido sin traerse el catálogo entero.
	 *
	 * @param array<int, string> $values Valores almacenados.
	 * @return array<string, string> Etiquetas indexadas por valor.
	 */
	abstract protected function labels_for( array $values ): array;

	/**
	 * Busca opciones que encajen con un texto.
	 *
	 * Es lo que responde el endpoint de búsqueda mientras se escribe.
	 *
	 * @param string                $term    Texto buscado.
	 * @param int                   $page    Página de resultados, empezando en 1.
	 * @param array<string, string> $filters Filtros extra que admita el campo.
	 * @return array<int, array{id: string, text: string}> Resultados.
	 */
	abstract public function search( string $term, int $page, array $filters = array() ): array;

	/**
	 * Convierte un valor almacenado en el objeto al que apunta.
	 *
	 * @param string $value Valor almacenado.
	 * @return mixed Objeto, o null si ya no existe.
	 */
	abstract protected function resolve( string $value ): mixed;

	/**
	 * Nombre de la acción del nonce que protege la búsqueda.
	 *
	 * @return string Acción del nonce.
	 */
	public function search_action(): string {
		return 'forja_search_' . $this->name();
	}

	/**
	 * Indica si la selección admite varios objetos.
	 *
	 * @return bool True si es múltiple.
	 */
	protected function is_multiple(): bool {
		return (bool) $this->get( 'multiple', false );
	}

	/**
	 * Normaliza el valor almacenado a una lista de cadenas.
	 *
	 * Un campo simple guarda un escalar y uno múltiple un array, pero al leer
	 * conviene tratarlos igual.
	 *
	 * @param mixed $value Valor almacenado.
	 * @return array<int, string> Valores, sin vacíos.
	 */
	protected function to_list( mixed $value ): array {
		$list = is_array( $value ) ? $value : array( $value );

		$list = array_map(
			static fn ( $item ): string => is_scalar( $item ) ? (string) $item : '',
			$list
		);

		return array_values( array_filter( $list, static fn ( string $item ): bool => '' !== $item ) );
	}

	/**
	 * Pinta el desplegable.
	 *
	 * Sólo se emiten las opciones ya elegidas. Las demás las trae el JavaScript
	 * al buscar: un sitio con miles de entradas no puede volcarlas en el HTML.
	 *
	 * @param mixed  $value      Valor o valores actuales.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		self::enqueue_select2();

		$current  = $this->to_list( $value );
		$labels   = array() === $current ? array() : $this->labels_for( $current );
		$multiple = $this->is_multiple();

		$attributes = array(
			'id'               => $this->input_id(),
			'name'             => $multiple ? $input_name . '[]' : $input_name,
			'class'            => 'forja-relational',
			'data-ui'          => '1',
			'data-ajax'        => '1',
			'data-field'       => $this->name(),
			'data-nonce'       => wp_create_nonce( $this->search_action() ),
			'data-placeholder' => (string) $this->get( 'placeholder', __( 'Selecciona', 'forja-fields' ) ),
			'data-allow_null'  => $this->get( 'allow_null', false ) ? '1' : '',
			'data-multiple'    => $multiple ? '1' : '',
		);

		if ( $multiple ) {
			$attributes['multiple'] = 'multiple';
			$attributes['size']     = '1';
		}

		if ( $this->get( 'disabled', false ) ) {
			$attributes['disabled'] = 'disabled';
		}

		if ( $multiple ) {
			// Sin esto, vaciar la selección no enviaría la clave y el valor
			// anterior se quedaría como estaba.
			printf( '<input type="hidden" name="%s" />', esc_attr( $input_name ) );
		}

		printf( '<select %s>', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		if ( ! $multiple && $this->get( 'allow_null', false ) ) {
			echo '<option value=""></option>';
		}

		foreach ( $current as $item ) {
			printf(
				'<option value="%s" selected="selected">%s</option>',
				esc_attr( $item ),
				esc_html( $labels[ $item ] ?? $item )
			);
		}

		echo '</select>';
	}

	/**
	 * Sanea la selección.
	 *
	 * @param mixed $raw Valor o valores crudos.
	 * @return mixed Valor saneado.
	 */
	public function sanitize( mixed $raw ): mixed {
		$list = array_values(
			array_filter(
				array_map(
					fn ( $item ): string => $this->sanitize_one( $item ),
					$this->to_list( $raw )
				),
				static fn ( string $item ): bool => '' !== $item
			)
		);

		if ( $this->is_multiple() ) {
			return $list;
		}

		return $list[0] ?? '';
	}

	/**
	 * Sanea un valor suelto.
	 *
	 * Por defecto se espera un identificador numérico; `page_link` guarda
	 * direcciones y lo reescribe.
	 *
	 * @param mixed $raw Valor crudo.
	 * @return string Valor saneado, o cadena vacía si no vale.
	 */
	protected function sanitize_one( mixed $raw ): string {
		$id = absint( is_scalar( $raw ) ? $raw : 0 );

		// Un identificador que no corresponde a nada existente se descarta en
		// lugar de guardarse: si no, la plantilla tendría que comprobarlo.
		return $id > 0 && null !== $this->resolve( (string) $id ) ? (string) $id : '';
	}

	/**
	 * Comprueba los límites de la selección múltiple.
	 *
	 * @param mixed $value Valor ya saneado.
	 * @return string Mensaje de error, o cadena vacía.
	 */
	public function validate( mixed $value ): string {
		if ( ! $this->is_multiple() ) {
			return '';
		}

		$count = is_array( $value ) ? count( $value ) : 0;
		$min   = (int) $this->get( 'min', 0 );
		$max   = (int) $this->get( 'max', 0 );
		$label = (string) $this->get( 'label', $this->name() );

		if ( $min > 0 && $count < $min ) {
			return sprintf(
				/* translators: 1: etiqueta del campo, 2: número mínimo de elementos. */
				_n(
					'%1$s necesita al menos %2$d elemento.',
					'%1$s necesita al menos %2$d elementos.',
					$min,
					'forja-fields'
				),
				$label,
				$min
			);
		}

		if ( $max > 0 && $count > $max ) {
			return sprintf(
				/* translators: 1: etiqueta del campo, 2: número máximo de elementos. */
				_n(
					'%1$s admite como mucho %2$d elemento.',
					'%1$s admite como mucho %2$d elementos.',
					$max,
					'forja-fields'
				),
				$label,
				$max
			);
		}

		return '';
	}

	/**
	 * Da forma al valor según `return_format`.
	 *
	 * @param mixed $value Valor almacenado.
	 * @return mixed Identificadores u objetos.
	 */
	public function format_value( mixed $value ): mixed {
		$list   = $this->to_list( $value );
		$object = 'object' === $this->get( 'return_format', 'id' );

		$formatted = array();

		foreach ( $list as $item ) {
			$resolved = $object ? $this->resolve( $item ) : $this->format_id( $item );

			// Un objeto borrado se descarta al leer, igual que en la galería:
			// así la plantilla no tiene que comprobarlo en cada vuelta.
			if ( null !== $resolved ) {
				$formatted[] = $resolved;
			}
		}

		if ( $this->is_multiple() ) {
			return $formatted;
		}

		return $formatted[0] ?? null;
	}

	/**
	 * Convierte un valor almacenado en el identificador que se devuelve.
	 *
	 * @param string $value Valor almacenado.
	 * @return mixed Identificador, o null si ya no existe.
	 */
	protected function format_id( string $value ): mixed {
		return null === $this->resolve( $value ) ? null : (int) $value;
	}
}
