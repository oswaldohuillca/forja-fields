<?php
/**
 * Contenido flexible.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Registry\FieldRegistry;
use Forja\Render\Html;
use Forja\Render\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «flexible_content» de ACF.
 *
 * Es un repetidor en el que cada fila puede tener una forma distinta: se
 * declaran varias «capas», cada una con sus propios subcampos, y el editor
 * elige cuál añadir en cada posición.
 *
 * El almacenamiento vuelve a ser el de ACF. La clave del campo guarda la lista
 * ordenada de capas y los valores usan el mismo esquema que el repetidor:
 *
 *     secciones           => array( 'banner', 'texto' )
 *     secciones_0_titulo  => 'Hola'
 *     secciones_1_cuerpo  => 'Lorem ipsum'
 *
 * Nótese que el índice es la posición en la lista, no la posición dentro de su
 * capa: la fila 1 usa el prefijo `secciones_1_` aunque sea la primera de tipo
 * «texto».
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-flexible-content.php
 */
final class FlexibleContent extends Field implements Composite {

	/**
	 * Índice de la fila plantilla que clona el JavaScript.
	 */
	public const CLONE_INDEX = 'acfcloneindex';

	/**
	 * Clave con la que viaja el nombre de la capa en cada fila.
	 */
	public const LAYOUT_KEY = 'acf_fc_layout';

	/**
	 * Capas declaradas, con sus subcampos ya instanciados.
	 *
	 * @var array<string, array{label: string, sub_fields: array<int, Field>}>
	 */
	private array $layouts = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args Configuración declarada por el desarrollador.
	 */
	public function __construct( array $args ) {
		parent::__construct( $args );

		$registry = new FieldRegistry();

		foreach ( (array) $this->get( 'layouts', array() ) as $name => $layout ) {
			$sub_fields = array();

			foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $definition ) {
				$sub_fields[] = $registry->make( $definition );
			}

			$this->layouts[ (string) $name ] = array(
				'label'      => (string) ( $layout['label'] ?? $name ),
				'sub_fields' => $sub_fields,
			);
		}
	}

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'flexible_content';
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
				'layouts'      => array(),
				'min'          => 0,
				'max'          => 0,
				'button_label' => '',
			)
		);
	}

	/**
	 * Capas declaradas.
	 *
	 * @return array<string, array{label: string, sub_fields: array<int, Field>}> Capas.
	 */
	public function layouts(): array {
		return $this->layouts;
	}

	/**
	 * La etiqueta encabeza el campo, no un control concreto.
	 *
	 * @return bool Siempre false.
	 */
	public function label_targets_input(): bool {
		return false;
	}

	/**
	 * El valor ocupa muchas claves.
	 *
	 * @return bool Siempre false.
	 */
	public function stores_value(): bool {
		return false;
	}

	/**
	 * Clave de almacenamiento de un subcampo en una fila.
	 *
	 * @param int    $index     Posición de la fila, empezando en cero.
	 * @param string $sub_field Nombre del subcampo.
	 * @return string Clave de metadatos.
	 */
	public function row_key( int $index, string $sub_field ): string {
		return $this->name() . '_' . $index . '_' . $sub_field;
	}

	/**
	 * Lee todas las filas almacenadas.
	 *
	 * @param callable $get Función que devuelve el valor de una clave.
	 * @return mixed Filas, cada una con su capa y sus valores.
	 */
	public function read_value( callable $get ): mixed {
		$order = $get( $this->name() );

		if ( ! is_array( $order ) ) {
			return array();
		}

		$rows = array();

		foreach ( array_values( $order ) as $index => $layout_name ) {
			$layout_name = (string) $layout_name;

			// Una capa que ya no está declarada se descarta: sus datos siguen
			// en la base de datos, pero no hay con qué pintarlos.
			if ( ! isset( $this->layouts[ $layout_name ] ) ) {
				continue;
			}

			$row = array( self::LAYOUT_KEY => $layout_name );

			foreach ( $this->layouts[ $layout_name ]['sub_fields'] as $sub_field ) {
				$row[ $sub_field->name() ] = $get( $this->row_key( $index, $sub_field->name() ) );
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Escribe las filas enviadas y borra las que sobran.
	 *
	 * @param mixed    $submitted Valor crudo enviado por el navegador.
	 * @param callable $get       Función que devuelve el valor de una clave.
	 * @param callable $set       Función que guarda el valor de una clave.
	 * @param callable $delete    Función que borra una clave.
	 * @return array<int, string> Mensajes de error; vacío si todo fue bien.
	 */
	public function write_value( mixed $submitted, callable $get, callable $set, callable $delete ): array {
		$previous = $get( $this->name() );
		$previous = is_array( $previous ) ? array_values( $previous ) : array();
		$rows     = is_array( $submitted ) ? $submitted : array();

		unset( $rows[ self::CLONE_INDEX ] );

		// Sólo cuentan las filas que declaran una capa conocida.
		$rows = array_values(
			array_filter(
				$rows,
				fn ( $row ): bool => is_array( $row ) && isset( $this->layouts[ (string) ( $row[ self::LAYOUT_KEY ] ?? '' ) ] )
			)
		);

		$error = $this->check_limits( count( $rows ) );

		if ( '' !== $error ) {
			return array( $error );
		}

		$order = array();

		foreach ( $rows as $index => $row ) {
			$layout_name = (string) $row[ self::LAYOUT_KEY ];

			// Si la capa de esta posición cambió, los subcampos de la anterior
			// quedarían huérfanos bajo el mismo prefijo.
			if ( isset( $previous[ $index ] ) && $previous[ $index ] !== $layout_name ) {
				$this->delete_row( $index, (string) $previous[ $index ], $delete );
			}

			foreach ( $this->layouts[ $layout_name ]['sub_fields'] as $sub_field ) {
				$name = $sub_field->name();

				if ( ! array_key_exists( $name, $row ) ) {
					continue;
				}

				$set( $this->row_key( $index, $name ), $sub_field->sanitize( $row[ $name ] ) );
			}

			$order[] = $layout_name;
		}

		// Las filas que sobran se limpian con la capa que ocupaba cada
		// posición; si no, sus subcampos quedarían huérfanos.
		$kept           = count( $order );
		$previous_count = count( $previous );

		for ( $i = $kept; $i < $previous_count; $i++ ) {
			$this->delete_row( $i, (string) $previous[ $i ], $delete );
		}

		$set( $this->name(), $order );

		return array();
	}

	/**
	 * Comprueba los límites de filas.
	 *
	 * @param int $count Número de filas enviadas.
	 * @return string Mensaje de error, o cadena vacía.
	 */
	private function check_limits( int $count ): string {
		$min   = (int) $this->get( 'min', 0 );
		$max   = (int) $this->get( 'max', 0 );
		$label = (string) $this->get( 'label', $this->name() );

		if ( $min > 0 && $count < $min ) {
			return sprintf(
				/* translators: 1: etiqueta del campo, 2: número mínimo de filas. */
				_n(
					'%1$s necesita al menos %2$d fila.',
					'%1$s necesita al menos %2$d filas.',
					$min,
					'forja-fields'
				),
				$label,
				$min
			);
		}

		if ( $max > 0 && $count > $max ) {
			return sprintf(
				/* translators: 1: etiqueta del campo, 2: número máximo de filas. */
				_n(
					'%1$s admite como mucho %2$d fila.',
					'%1$s admite como mucho %2$d filas.',
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
	 * Borra las claves de una fila.
	 *
	 * @param int      $index       Posición de la fila.
	 * @param string   $layout_name Capa que ocupaba esa posición.
	 * @param callable $delete      Función que borra una clave.
	 * @return void
	 */
	private function delete_row( int $index, string $layout_name, callable $delete ): void {
		foreach ( $this->layouts[ $layout_name ]['sub_fields'] ?? array() as $sub_field ) {
			$delete( $this->row_key( $index, $sub_field->name() ) );
		}
	}

	/**
	 * Da forma a las filas para la plantilla.
	 *
	 * @param mixed $value Filas leídas del almacenamiento.
	 * @return array<int, array<string, mixed>> Filas formateadas.
	 */
	public function format_value( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$formatted = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$layout_name = (string) ( $row[ self::LAYOUT_KEY ] ?? '' );

			foreach ( $this->layouts[ $layout_name ]['sub_fields'] ?? array() as $sub_field ) {
				$name = $sub_field->name();

				if ( array_key_exists( $name, $row ) ) {
					$row[ $name ] = $sub_field->format_value( $row[ $name ] );
				}
			}

			$formatted[] = $row;
		}

		return $formatted;
	}

	/**
	 * Pinta las filas y el menú para añadir capas.
	 *
	 * @param mixed  $value      Filas actuales.
	 * @param string $input_name Atributo «name» base de los controles.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		if ( array() === $this->layouts ) {
			return;
		}

		$rows     = is_array( $value ) ? $value : array();
		$renderer = new Renderer();
		$label    = (string) $this->get( 'button_label', '' );
		$label    = '' === $label ? __( 'Añadir fila', 'forja-fields' ) : $label;

		$attributes = array(
			'class'    => 'acf-flexible-content' . ( array() === $rows ? ' -empty' : '' ),
			'data-min' => (string) (int) $this->get( 'min', 0 ),
			'data-max' => (string) (int) $this->get( 'max', 0 ),
		);

		printf( '<div %s>', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		printf( '<input type="hidden" name="%s" />', esc_attr( $input_name ) );

		printf(
			'<div class="no-value-message">%s</div>',
			esc_html(
				sprintf(
					/* translators: %s: etiqueta del botón para añadir. */
					__( 'Pulsa «%s» para empezar a construir el contenido.', 'forja-fields' ),
					$label
				)
			)
		);

		// Plantillas: una por capa, ocultas, que el JavaScript clona al añadir.
		echo '<div class="clones">';

		foreach ( $this->layouts as $name => $layout ) {
			$this->render_layout( $renderer, self::CLONE_INDEX, 0, (string) $name, $layout, array(), $input_name );
		}

		echo '</div>';

		echo '<div class="values">';

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name = (string) ( $row[ self::LAYOUT_KEY ] ?? '' );

			if ( ! isset( $this->layouts[ $name ] ) ) {
				continue;
			}

			$this->render_layout( $renderer, (string) $index, (int) $index, $name, $this->layouts[ $name ], $row, $input_name );
		}

		echo '</div>';

		$this->render_add_menu( $label );

		echo '</div>';
	}

	/**
	 * Pinta una fila con la capa que le corresponde.
	 *
	 * @param Renderer                                            $renderer   Renderizador de campos.
	 * @param string                                              $id         Identificador de la fila en el formulario.
	 * @param int                                                 $number     Posición visible, empezando en cero.
	 * @param string                                              $name       Nombre de la capa.
	 * @param array{label: string, sub_fields: array<int, Field>} $layout     Capa a pintar.
	 * @param array<string, mixed>                                $row        Valores de la fila.
	 * @param string                                              $input_name Atributo «name» base.
	 * @return void
	 */
	private function render_layout( Renderer $renderer, string $id, int $number, string $name, array $layout, array $row, string $input_name ): void {
		$attributes = array(
			'class'       => 'layout' . ( self::CLONE_INDEX === $id ? ' acf-clone' : '' ),
			'data-id'     => $id,
			'data-layout' => $name,
			'data-label'  => $layout['label'],
		);

		printf( '<div %s>', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		$prefix = $input_name . '[' . $id . ']';

		// Este oculto es lo que le dice al servidor qué capa es cada fila.
		printf(
			'<input type="hidden" name="%s[%s]" value="%s" />',
			esc_attr( $prefix ),
			esc_attr( self::LAYOUT_KEY ),
			esc_attr( $name )
		);

		printf(
			'<div class="acf-fc-layout-handle" title="%s" data-name="collapse-layout">'
			. '<span class="acf-fc-layout-order">%s</span>'
			. '<span class="acf-fc-layout-title">%s</span>'
			. '</div>',
			esc_attr__( 'Arrastra para reordenar', 'forja-fields' ),
			esc_html( (string) ( $number + 1 ) ),
			esc_html( $layout['label'] )
		);

		printf(
			'<div class="acf-fc-layout-controls">'
			. '<a class="acf-icon -plus small" href="#" data-name="add-layout" title="%1$s" aria-label="%1$s"></a>'
			. '<a class="acf-icon -minus small" href="#" data-name="remove-layout" title="%2$s" aria-label="%2$s"></a>'
			. '</div>',
			esc_attr__( 'Añadir', 'forja-fields' ),
			esc_attr__( 'Quitar', 'forja-fields' )
		);

		echo '<div class="acf-fields">';

		$renderer->render_fields( $layout['sub_fields'], $row, $prefix );

		echo '</div>';

		echo '</div>';
	}

	/**
	 * Pinta el botón de añadir y el menú de capas.
	 *
	 * Con una sola capa el menú sobra: el botón la añade directamente.
	 *
	 * @param string $label Etiqueta del botón.
	 * @return void
	 */
	private function render_add_menu( string $label ): void {
		echo '<div class="acf-actions">';

		printf(
			'<a class="acf-button button button-primary" href="#" data-name="add-layout">%s</a>',
			esc_html( $label )
		);

		if ( count( $this->layouts ) > 1 ) {
			echo '<div class="acf-fc-popup" hidden><ul>';

			foreach ( $this->layouts as $name => $layout ) {
				printf(
					'<li><a href="#" data-layout="%s">%s</a></li>',
					esc_attr( (string) $name ),
					esc_html( $layout['label'] )
				);
			}

			echo '</ul></div>';
		}

		echo '<div class="clear"></div>';
		echo '</div>';
	}
}
