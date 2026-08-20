<?php
/**
 * Clase base de todos los tipos de campo.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Define el contrato y el comportamiento común de un tipo de campo.
 *
 * Un campo sólo se ocupa de tres cosas: pintar su control de entrada,
 * saneárselo al recibirlo y devolver su valor por defecto. Todo lo que
 * envuelve al control (label, instrucciones, ancho, clases) lo resuelve
 * el renderer, de modo que el markup exterior sea idéntico en todos los tipos.
 */
abstract class Field {

	/**
	 * Configuración del campo ya normalizada.
	 *
	 * @var array<string, mixed>
	 */
	protected array $args;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args Configuración declarada por el desarrollador.
	 */
	public function __construct( array $args ) {
		$this->args = array_merge( $this->defaults(), $args );
	}

	/**
	 * Identificador del tipo, tal como se declara en el array de campos.
	 *
	 * @return string Nombre del tipo.
	 */
	abstract public static function type(): string;

	/**
	 * Pinta el control de entrada, sin el envoltorio.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» que debe usar el control.
	 * @return void
	 */
	abstract public function render_input( mixed $value, string $input_name ): void;

	/**
	 * Valores por defecto comunes a todos los campos.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array(
			'name'          => '',
			'label'         => '',
			'instructions'  => '',
			'required'      => false,
			'default_value' => '',
			'placeholder'   => '',
			'wrapper'       => array(
				'width' => '',
				'class' => '',
				'id'    => '',
			),
		);
	}

	/**
	 * Indica si el campo organiza a los que le siguen.
	 *
	 * Las pestañas y los acordeones no son campos: son instrucciones de
	 * maquetado que el renderer interpreta para agrupar la lista plana antes
	 * de pintarla.
	 *
	 * @return bool True si el campo agrupa.
	 */
	public function is_layout(): bool {
		return false;
	}

	/**
	 * Indica si la etiqueta debe apuntar al control con el atributo `for`.
	 *
	 * Los campos con varios controles —un grupo de radios, por ejemplo— no
	 * tienen un destino único, así que en su lugar la etiqueta se identifica y
	 * el grupo la referencia con `aria-labelledby`. Es lo que hace ACF.
	 *
	 * @return bool True si la etiqueta usa `for`.
	 */
	public function label_targets_input(): bool {
		return true;
	}

	/**
	 * Indica si el campo tiene un valor que leer y guardar.
	 *
	 * Algunos tipos son puramente de presentación: `message` muestra un aviso y
	 * `separator` divide el formulario. No leen ni escriben metadatos, así que
	 * el contexto los salta al guardar en lugar de almacenar cadenas vacías.
	 *
	 * @return bool True si el campo almacena datos.
	 */
	public function stores_value(): bool {
		return true;
	}

	/**
	 * Transforma el valor almacenado antes de entregarlo a la plantilla.
	 *
	 * Es la pieza simétrica de `sanitize()`: uno normaliza lo que entra y el
	 * otro da forma a lo que sale. Lo que se guarda no cambia nunca; esto sólo
	 * afecta a lo que devuelve `forja_get_field()`.
	 *
	 * @param mixed $value Valor tal como está almacenado.
	 * @return mixed Valor listo para usar en la plantilla.
	 */
	public function format_value( mixed $value ): mixed {
		return $value;
	}

	/**
	 * Sanea el valor recibido del formulario.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Valor saneado, listo para almacenar.
	 */
	public function sanitize( mixed $raw ): mixed {
		return sanitize_text_field( (string) $raw );
	}

	/**
	 * Devuelve una opción de configuración del campo.
	 *
	 * @param string $key      Nombre de la opción.
	 * @param mixed  $fallback Valor a devolver si la opción no existe.
	 * @return mixed Valor de la opción.
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->args[ $key ] ?? $fallback;
	}

	/**
	 * Nombre del campo, usado como clave de almacenamiento.
	 *
	 * @return string Nombre del campo.
	 */
	public function name(): string {
		return (string) $this->args['name'];
	}

	/**
	 * Valor por defecto cuando el campo aún no se ha guardado.
	 *
	 * @return mixed Valor por defecto.
	 */
	public function default_value(): mixed {
		return $this->args['default_value'];
	}

	/**
	 * Identificador HTML del control, para enlazarlo con su etiqueta.
	 *
	 * @return string Atributo id.
	 */
	public function input_id(): string {
		return 'forja-' . str_replace( '_', '-', $this->name() );
	}

	/**
	 * Configuración completa del campo.
	 *
	 * @return array<string, mixed> Configuración.
	 */
	public function args(): array {
		return $this->args;
	}
}
