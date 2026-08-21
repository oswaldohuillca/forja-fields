<?php
/**
 * Catálogo de tipos de campo disponibles.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Registry;

use Forja\Fields\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Mapea el identificador de un tipo con la clase que lo implementa.
 *
 * Los tipos de terceros se añaden con `register()` desde el hook
 * `forja/register_field_types`, de modo que un plugin externo pueda
 * aportar sus propios controles sin tocar el núcleo.
 */
final class FieldRegistry {

	/**
	 * Clases indexadas por identificador de tipo.
	 *
	 * @var array<string, class-string<Field>>
	 */
	private array $types = array();

	/**
	 * Registra los tipos incluidos en el plugin.
	 */
	public function __construct() {
		$this->register( \Forja\Fields\Text::class );
		$this->register( \Forja\Fields\Textarea::class );
		$this->register( \Forja\Fields\Number::class );
		$this->register( \Forja\Fields\Range::class );
		$this->register( \Forja\Fields\Email::class );
		$this->register( \Forja\Fields\Url::class );
		$this->register( \Forja\Fields\Password::class );
		$this->register( \Forja\Fields\Select::class );
		$this->register( \Forja\Fields\Radio::class );
		$this->register( \Forja\Fields\Checkbox::class );
		$this->register( \Forja\Fields\ButtonGroup::class );
		$this->register( \Forja\Fields\TrueFalse::class );
		$this->register( \Forja\Fields\Wysiwyg::class );
		$this->register( \Forja\Fields\Link::class );
		$this->register( \Forja\Fields\Oembed::class );
		$this->register( \Forja\Fields\DatePicker::class );
		$this->register( \Forja\Fields\TimePicker::class );
		$this->register( \Forja\Fields\DateTimePicker::class );
		$this->register( \Forja\Fields\ColorPicker::class );
		$this->register( \Forja\Fields\Image::class );
		$this->register( \Forja\Fields\File::class );
		$this->register( \Forja\Fields\Gallery::class );
		$this->register( \Forja\Fields\IconPicker::class );
		$this->register( \Forja\Fields\PostObject::class );
		$this->register( \Forja\Fields\PageLink::class );
		$this->register( \Forja\Fields\Relationship::class );
		$this->register( \Forja\Fields\Taxonomy::class );
		$this->register( \Forja\Fields\User::class );
		$this->register( \Forja\Fields\Group::class );
		$this->register( \Forja\Fields\Repeater::class );
		$this->register( \Forja\Fields\FlexibleContent::class );
		$this->register( \Forja\Fields\Message::class );
		$this->register( \Forja\Fields\Separator::class );
		$this->register( \Forja\Fields\Tab::class );
		$this->register( \Forja\Fields\Accordion::class );
	}

	/**
	 * Añade un tipo de campo al catálogo.
	 *
	 * @param string $class_name Nombre de una clase que extiende Field.
	 * @return void
	 * @throws \InvalidArgumentException Si la clase no extiende Field.
	 */
	public function register( string $class_name ): void {
		if ( ! is_subclass_of( $class_name, Field::class ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Forja: «%s» no extiende Forja\Fields\Field.', esc_html( $class_name ) )
			);
		}

		$this->types[ $class_name::type() ] = $class_name;
	}

	/**
	 * Indica si un tipo está registrado.
	 *
	 * @param string $type Identificador del tipo.
	 * @return bool True si existe.
	 */
	public function has( string $type ): bool {
		return isset( $this->types[ $type ] );
	}

	/**
	 * Construye una instancia de campo a partir de su configuración.
	 *
	 * @param array<string, mixed> $args Configuración del campo; debe incluir «type» y «name».
	 * @return Field Instancia del tipo solicitado.
	 * @throws \InvalidArgumentException Si el tipo no está registrado o falta el nombre.
	 */
	public function make( array $args ): Field {
		$type = (string) ( $args['type'] ?? '' );

		if ( ! $this->has( $type ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Forja: tipo de campo desconocido «%s».', esc_html( $type ) )
			);
		}

		if ( empty( $args['name'] ) ) {
			throw new \InvalidArgumentException( 'Forja: todo campo necesita un «name».' );
		}

		$class_name = $this->types[ $type ];

		return new $class_name( $args );
	}

	/**
	 * Devuelve todos los tipos registrados.
	 *
	 * @return array<string, class-string<Field>> Catálogo completo.
	 */
	public function all(): array {
		return $this->types;
	}
}
