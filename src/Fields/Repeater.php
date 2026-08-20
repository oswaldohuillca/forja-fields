<?php
/**
 * Campo repetible.
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
 * Equivalente al campo «repeater» de ACF.
 *
 * Guarda una lista de filas, cada una con los mismos subcampos. El formato de
 * almacenamiento es el de ACF y no es negociable si se quiere leer lo que ya
 * hay en un sitio existente:
 *
 *     banner            => 2                (número de filas)
 *     banner_0_titulo   => 'Primera'
 *     banner_0_imagen   => 11
 *     banner_1_titulo   => 'Segunda'
 *
 * Es decir: una clave por subcampo y fila, con el índice en medio. No se
 * serializa un array, entre otras cosas porque así cada subcampo sigue siendo
 * consultable con `meta_query`.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-repeater.php
 */
final class Repeater extends Field implements Composite {

	/**
	 * Índice que ACF usa en la fila plantilla que clona el JavaScript.
	 */
	public const CLONE_INDEX = 'acfcloneindex';

	/**
	 * Subcampos ya instanciados.
	 *
	 * @var array<int, Field>
	 */
	private array $sub_fields = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args Configuración declarada por el desarrollador.
	 */
	public function __construct( array $args ) {
		parent::__construct( $args );

		$registry = new FieldRegistry();

		foreach ( (array) $this->get( 'sub_fields', array() ) as $definition ) {
			$this->sub_fields[] = $registry->make( $definition );
		}
	}

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'repeater';
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
				'sub_fields'   => array(),
				'min'          => 0,
				'max'          => 0,
				'button_label' => '',
			)
		);
	}

	/**
	 * Subcampos del repetidor.
	 *
	 * @return array<int, Field> Subcampos.
	 */
	public function sub_fields(): array {
		return $this->sub_fields;
	}

	/**
	 * La etiqueta encabeza el grupo, no un control concreto.
	 *
	 * @return bool Siempre false.
	 */
	public function label_targets_input(): bool {
		return false;
	}

	/**
	 * El valor no se lee ni se escribe con la clave a secas.
	 *
	 * Un repetidor ocupa muchas claves de metadatos, así que su lectura y su
	 * escritura pasan por `Composite`, no por la capa genérica.
	 *
	 * @return bool Siempre false.
	 */
	public function stores_value(): bool {
		return false;
	}

	/**
	 * Lee todas las filas almacenadas.
	 *
	 * @param callable $get Función que devuelve el valor de una clave.
	 * @return mixed Filas con sus valores por subcampo.
	 */
	public function read_value( callable $get ): mixed {
		$count = (int) $get( $this->name() );
		$rows  = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$row = array();

			foreach ( $this->sub_fields as $sub_field ) {
				$row[ $sub_field->name() ] = $get( $this->row_key( $i, $sub_field->name() ) );
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
	 * @return void
	 */
	public function write_value( mixed $submitted, callable $get, callable $set, callable $delete ): void {
		$previous = (int) $get( $this->name() );
		$rows     = is_array( $submitted ) ? $submitted : array();

		// La fila plantilla que clona el JavaScript nunca se guarda.
		unset( $rows[ self::CLONE_INDEX ] );

		$index = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			foreach ( $this->sub_fields as $sub_field ) {
				$name = $sub_field->name();

				if ( ! array_key_exists( $name, $row ) ) {
					continue;
				}

				$set( $this->row_key( $index, $name ), $sub_field->sanitize( $row[ $name ] ) );
			}

			++$index;
		}

		// Las filas que había de más se borran clave a clave; si no, quedarían
		// huérfanas en la base de datos y reaparecerían al crecer la lista.
		for ( $i = $index; $i < $previous; $i++ ) {
			foreach ( $this->sub_fields as $sub_field ) {
				$delete( $this->row_key( $i, $sub_field->name() ) );
			}
		}

		$set( $this->name(), $index );
	}

	/**
	 * Da forma a las filas para la plantilla.
	 *
	 * @param mixed $value Filas leídas del almacenamiento.
	 * @return array<int, array<string, mixed>> Filas con cada subcampo formateado.
	 */
	public function format_value( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map(
			function ( array $row ): array {
				foreach ( $this->sub_fields as $sub_field ) {
					$name = $sub_field->name();

					if ( array_key_exists( $name, $row ) ) {
						$row[ $name ] = $sub_field->format_value( $row[ $name ] );
					}
				}

				return $row;
			},
			$value
		);
	}

	/**
	 * Clave de almacenamiento de un subcampo en una fila.
	 *
	 * @param int    $index     Índice de la fila, empezando en cero.
	 * @param string $sub_field Nombre del subcampo.
	 * @return string Clave de metadatos.
	 */
	public function row_key( int $index, string $sub_field ): string {
		return $this->name() . '_' . $index . '_' . $sub_field;
	}

	/**
	 * Pinta la tabla de filas.
	 *
	 * @param mixed  $value      Filas actuales.
	 * @param string $input_name Atributo «name» base de los controles.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		if ( array() === $this->sub_fields ) {
			return;
		}

		$rows     = is_array( $value ) ? $value : array();
		$renderer = new Renderer();

		$attributes = array(
			'class'    => 'acf-repeater',
			'data-min' => (string) (int) $this->get( 'min', 0 ),
			'data-max' => (string) (int) $this->get( 'max', 0 ),
		);

		printf( '<div %s>', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		// Asegura que la clave viaje aunque no quede ninguna fila.
		printf(
			'<input type="hidden" name="%s" class="acf-repeater-hidden-input" />',
			esc_attr( $input_name )
		);

		echo '<table class="acf-table">';

		$this->render_head();

		echo '<tbody>';

		foreach ( $rows as $index => $row ) {
			$this->render_row( $renderer, (string) $index, (int) $index, is_array( $row ) ? $row : array(), $input_name );
		}

		// Fila plantilla: el JavaScript la clona para añadir. El CSS la
		// oculta con `.acf-clone`.
		$this->render_row( $renderer, self::CLONE_INDEX, 0, array(), $input_name );

		echo '</tbody>';
		echo '</table>';

		$label = (string) $this->get( 'button_label', '' );

		printf(
			'<div class="acf-actions"><a class="acf-button acf-repeater-add-row button button-primary" href="#" data-event="add-row">%s</a><div class="clear"></div></div>',
			esc_html( '' === $label ? __( 'Añadir fila', 'forja-fields' ) : $label )
		);

		echo '</div>';
	}

	/**
	 * Pinta la cabecera con una columna por subcampo.
	 *
	 * @return void
	 */
	private function render_head(): void {
		echo '<thead><tr><th class="acf-row-handle"></th>';

		foreach ( $this->sub_fields as $sub_field ) {
			$wrapper    = (array) $sub_field->get( 'wrapper', array() );
			$width      = (string) ( $wrapper['width'] ?? '' );
			$attributes = array(
				'class'     => 'acf-th',
				'data-name' => $sub_field->name(),
				'data-type' => $sub_field::type(),
			);

			if ( '' !== $width ) {
				$attributes['data-width'] = $width;
				$attributes['style']      = sprintf( 'width: %s%%;', $width );
			}

			printf( '<th %s>', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

			$label = (string) $sub_field->get( 'label', '' );

			if ( '' !== $label ) {
				$required = $sub_field->get( 'required', false )
					? ' <span class="acf-required">*</span>'
					: '';

				printf(
					'<label>%s%s</label>',
					esc_html( $label ),
					$required // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup fijo.
				);
			}

			$instructions = (string) $sub_field->get( 'instructions', '' );

			if ( '' !== $instructions ) {
				printf( '<p class="description">%s</p>', wp_kses_post( $instructions ) );
			}

			echo '</th>';
		}

		echo '<th class="acf-row-handle"></th></tr></thead>';
	}

	/**
	 * Pinta una fila.
	 *
	 * @param Renderer             $renderer   Renderizador de campos.
	 * @param string               $id         Identificador de la fila en el formulario.
	 * @param int                  $number     Número visible de la fila, empezando en cero.
	 * @param array<string, mixed> $row        Valores de la fila.
	 * @param string               $input_name Atributo «name» base.
	 * @return void
	 */
	private function render_row( Renderer $renderer, string $id, int $number, array $row, string $input_name ): void {
		$is_clone = self::CLONE_INDEX === $id;

		printf(
			'<tr class="acf-row%s" data-id="%s">',
			$is_clone ? ' acf-clone' : '',
			esc_attr( $id )
		);

		printf(
			'<td class="acf-row-handle order" title="%s"><span class="acf-row-number">%s</span></td>',
			esc_attr__( 'Arrastra para reordenar', 'forja-fields' ),
			esc_html( (string) ( $number + 1 ) )
		);

		foreach ( $this->sub_fields as $sub_field ) {
			$name  = $sub_field->name();
			$value = $row[ $name ] ?? $sub_field->default_value();

			$renderer->render_field_wrap(
				$sub_field,
				$value,
				$input_name . '[' . $id . ']',
				'label',
				array(),
				'td'
			);
		}

		printf(
			'<td class="acf-row-handle remove">'
			. '<a class="acf-icon -plus small" href="#" data-event="add-row" title="%1$s" aria-label="%1$s"></a>'
			. '<a class="acf-icon -minus small" href="#" data-event="remove-row" title="%2$s" aria-label="%2$s"></a>'
			. '</td>',
			esc_attr__( 'Añadir fila', 'forja-fields' ),
			esc_attr__( 'Quitar fila', 'forja-fields' )
		);

		echo '</tr>';
	}
}
