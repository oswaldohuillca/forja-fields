<?php
/**
 * Selector de icono.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Icons\Iconify;
use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «icon_picker» de ACF, con Iconify en lugar de dashicons.
 *
 * Se conserva la forma en que ACF guarda el valor —un array con `type` y
 * `value`— para poder leer lo que ya haya en un sitio existente. Los tipos
 * `dashicons`, `media_library` y `url` se siguen entendiendo al leer; el
 * selector, en cambio, busca en Iconify, que trae más de 200.000 iconos e
 * incluye la propia colección de dashicons.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-icon_picker.php
 */
final class IconPicker extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'icon_picker';
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
				'default_value' => array(),
				// Colecciones donde buscar; vacío significa todas.
				'collections'   => array(),
				// Qué recibe la plantilla: array, string o svg.
				'return_format' => 'array',
			)
		);
	}

	/**
	 * La etiqueta encabeza el conjunto, no un control concreto.
	 *
	 * @return bool Siempre false.
	 */
	public function label_targets_input(): bool {
		return false;
	}

	/**
	 * Normaliza el valor a sus dos claves.
	 *
	 * @param mixed $value Valor almacenado o enviado.
	 * @return array{type: string, value: string} Icono normalizado.
	 */
	private function normalize( mixed $value ): array {
		// Un valor guardado como cadena suelta se interpreta como un nombre de
		// Iconify: es la forma corta que puede escribir un desarrollador.
		if ( is_string( $value ) && '' !== $value ) {
			return array(
				'type'  => 'iconify',
				'value' => $value,
			);
		}

		$value = is_array( $value ) ? $value : array();
		$type  = (string) ( $value['type'] ?? '' );

		if ( ! in_array( $type, array( 'iconify', 'dashicons', 'media_library', 'url' ), true ) ) {
			$type = 'iconify';
		}

		return array(
			'type'  => $type,
			'value' => (string) ( $value['value'] ?? '' ),
		);
	}

	/**
	 * Pinta el buscador y la vista previa.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» de los controles.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		$icon        = $this->normalize( $value );
		$collections = array_filter( array_map( 'strval', (array) $this->get( 'collections', array() ) ) );

		$attributes = array(
			'class'            => 'acf-icon-picker' . ( '' !== $icon['value'] ? ' -value' : '' ),
			'data-api'         => Iconify::api_url(),
			'data-collections' => implode( ',', $collections ),
		);

		printf( '<div %s>', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		foreach ( $icon as $key => $current ) {
			printf(
				'<input type="hidden" class="input-%s" name="%s[%s]" value="%s" />',
				esc_attr( $key ),
				esc_attr( $input_name ),
				esc_attr( $key ),
				esc_attr( $current )
			);
		}

		// Vista previa. En el escritorio se pinta con la propia API como origen
		// de la imagen: son unos 150 bytes y el navegador la cachea una semana.
		printf(
			'<div class="acf-icon-picker-preview"><img src="%s" alt="" /><code>%s</code>'
			. '<a href="#" class="acf-icon -cancel dark" data-name="remove" title="%s"></a></div>',
			esc_url( $this->preview_url( $icon ) ),
			esc_html( $icon['value'] ),
			esc_attr__( 'Quitar', 'forja-fields' )
		);

		printf(
			'<input type="search" class="acf-icon-picker-search" placeholder="%s" aria-label="%s" />',
			esc_attr__( 'Buscar un icono…', 'forja-fields' ),
			esc_attr__( 'Buscar un icono', 'forja-fields' )
		);

		echo '<div class="acf-icon-picker-results" role="listbox"></div>';

		/*
		 * El paginador lo rellena el JavaScript, pero sus textos vienen de aquí:
		 * ninguna cadena visible vive en el TypeScript, para que todas las
		 * traducciones estén en un solo sitio y `tools/make-pot.php` las vea.
		 */
		printf(
			'<div class="acf-icon-picker-pager" data-page-label="%s" data-prev-label="%s" data-next-label="%s"></div>',
			/* translators: %d: número de página. */
			esc_attr__( 'Página %d', 'forja-fields' ),
			esc_attr__( 'Página anterior', 'forja-fields' ),
			esc_attr__( 'Página siguiente', 'forja-fields' )
		);

		echo '</div>';
	}

	/**
	 * Dirección de la vista previa de un icono.
	 *
	 * @param array{type: string, value: string} $icon Icono normalizado.
	 * @return string URL de la imagen, o cadena vacía.
	 */
	private function preview_url( array $icon ): string {
		if ( '' === $icon['value'] ) {
			return '';
		}

		return match ( $icon['type'] ) {
			'url'           => $icon['value'],
			'media_library' => (string) wp_get_attachment_image_url( (int) $icon['value'], 'thumbnail' ),
			// Los dashicons guardados por ACF se resuelven por su colección
			// homónima en Iconify, así que no hay que tratarlos aparte.
			'dashicons'     => Iconify::api_url() . '/dashicons/' . rawurlencode( $icon['value'] ) . '.svg',
			default         => str_contains( $icon['value'], ':' )
				? Iconify::api_url() . '/' . str_replace( ':', '/', $icon['value'] ) . '.svg'
				: '',
		};
	}

	/**
	 * Sanea el icono.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Icono saneado, o cadena vacía.
	 */
	public function sanitize( mixed $raw ): mixed {
		$icon = $this->normalize( $raw );

		if ( '' === $icon['value'] ) {
			return '';
		}

		$value = match ( $icon['type'] ) {
			'url'           => sanitize_url( $icon['value'] ),
			'media_library' => (string) (int) $icon['value'],
			'dashicons'     => sanitize_key( $icon['value'] ),
			// Un nombre que no encaje se descarta: acaba formando parte de una
			// URL contra la API.
			default         => Iconify::is_valid_name( $icon['value'] ) ? $icon['value'] : '',
		};

		if ( '' === $value || '0' === $value ) {
			return '';
		}

		return array(
			'type'  => $icon['type'],
			'value' => $value,
		);
	}

	/**
	 * Da forma al valor según `return_format`.
	 *
	 * @param mixed $value Valor almacenado.
	 * @return array{type: string, value: string}|string|null Icono, nombre o SVG.
	 */
	public function format_value( mixed $value ): mixed {
		$icon = $this->normalize( $value );

		if ( '' === $icon['value'] ) {
			return 'array' === $this->get( 'return_format', 'array' ) ? null : '';
		}

		return match ( (string) $this->get( 'return_format', 'array' ) ) {
			'string' => $icon['value'],
			'svg'    => forja_get_icon_svg( $icon ),
			default  => $icon,
		};
	}
}
