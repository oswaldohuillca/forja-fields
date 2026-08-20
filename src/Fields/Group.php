<?php
/**
 * Grupo de subcampos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Registry\FieldRegistry;
use Forja\Render\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «group» de ACF.
 *
 * Es un repetidor de una sola fila: agrupa varios subcampos bajo un nombre
 * común, sin repetición. El formato de almacenamiento vuelve a ser el de ACF,
 * esta vez sin índice:
 *
 *     direccion_calle  => 'Av. Larco'
 *     direccion_ciudad => 'Lima'
 *
 * A diferencia del acordeón, que sólo agrupa visualmente, aquí el nombre del
 * grupo forma parte de la clave y del valor devuelto.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-group.php
 */
final class Group extends Field implements Composite {

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
		return 'group';
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
				'sub_fields' => array(),
				// Disposición de los subcampos: block (etiqueta arriba) o row
				// (etiqueta a la izquierda).
				'layout'     => 'block',
			)
		);
	}

	/**
	 * Subcampos del grupo.
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
	 * El valor ocupa una clave por subcampo, no una sola.
	 *
	 * @return bool Siempre false.
	 */
	public function stores_value(): bool {
		return false;
	}

	/**
	 * Clave de almacenamiento de un subcampo.
	 *
	 * @param string $sub_field Nombre del subcampo.
	 * @return string Clave de metadatos.
	 */
	public function sub_key( string $sub_field ): string {
		return $this->name() . '_' . $sub_field;
	}

	/**
	 * Lee los valores de todos los subcampos.
	 *
	 * @param callable $get Función que devuelve el valor de una clave.
	 * @return mixed Valores indexados por nombre de subcampo.
	 */
	public function read_value( callable $get ): mixed {
		$values = array();

		foreach ( $this->sub_fields as $sub_field ) {
			$values[ $sub_field->name() ] = $get( $this->sub_key( $sub_field->name() ) );
		}

		return $values;
	}

	/**
	 * Escribe los valores de los subcampos.
	 *
	 * @param mixed    $submitted Valor crudo enviado por el navegador.
	 * @param callable $get       Función que devuelve el valor de una clave.
	 * @param callable $set       Función que guarda el valor de una clave.
	 * @param callable $delete    Función que borra una clave.
	 * @return array<int, string> Mensajes de error; vacío si todo fue bien.
	 */
	public function write_value( mixed $submitted, callable $get, callable $set, callable $delete ): array {
		unset( $get, $delete );

		if ( ! is_array( $submitted ) ) {
			return array();
		}

		foreach ( $this->sub_fields as $sub_field ) {
			$name = $sub_field->name();

			if ( ! array_key_exists( $name, $submitted ) ) {
				continue;
			}

			$set( $this->sub_key( $name ), $sub_field->sanitize( $submitted[ $name ] ) );
		}

		return array();
	}

	/**
	 * Da forma a cada subcampo para la plantilla.
	 *
	 * @param mixed $value Valores leídos del almacenamiento.
	 * @return array<string, mixed> Valores formateados.
	 */
	public function format_value( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return array();
		}

		foreach ( $this->sub_fields as $sub_field ) {
			$name = $sub_field->name();

			if ( array_key_exists( $name, $value ) ) {
				$value[ $name ] = $sub_field->format_value( $value[ $name ] );
			}
		}

		return $value;
	}

	/**
	 * Pinta los subcampos dentro de su propio contenedor.
	 *
	 * @param mixed  $value      Valores actuales.
	 * @param string $input_name Atributo «name» base de los controles.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		if ( array() === $this->sub_fields ) {
			return;
		}

		$values   = is_array( $value ) ? $value : array();
		$renderer = new Renderer();

		// El contenedor lleva `-border` para que el grupo se distinga del
		// resto de campos, y la colocación de etiqueta que pida el layout.
		$placement = 'row' === $this->get( 'layout', 'block' ) ? '-left' : '-top';

		printf( '<div class="acf-fields %s -border">', esc_attr( $placement ) );

		$renderer->render_fields( $this->sub_fields, $values, $input_name );

		echo '</div>';
	}
}
