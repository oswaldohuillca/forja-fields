<?php
/**
 * Campo de imagen.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «image» de ACF.
 *
 * Guarda el identificador del adjunto.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-image.php
 */
final class Image extends MediaField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'image';
	}

	/**
	 * Valores por defecto propios del tipo.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array( 'preview_size' => 'medium' )
		);
	}

	/**
	 * Sólo acepta imágenes.
	 *
	 * @return string Tipo MIME general.
	 */
	protected function accepted_type(): string {
		return 'image';
	}

	/**
	 * Pinta la vista previa y el selector.
	 *
	 * @param mixed  $value      Identificador del adjunto.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		// Necesario para que el modal de medios esté disponible en la pantalla.
		wp_enqueue_media();

		$size       = (string) $this->get( 'preview_size', 'medium' );
		$id         = (int) $value;
		$image      = $id > 0 ? wp_get_attachment_image_src( $id, $size ) : false;
		$stored     = $image ? (string) $id : '';
		$dimensions = $this->preview_dimensions( $size );

		// El orden de las claves marca el orden de los atributos; se sigue el
		// de ACF para que el markup sea comparable.
		$container = array_merge(
			array( 'class' => '' ),
			array( 'data-preview_size' => $size ),
			$this->container_attributes( 'acf-image-uploader', (bool) $image )
		);

		printf( '<div %s>', Html::attributes( $container, array( 'data-mime_types' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		printf(
			'<input type="hidden" name="%s" value="%s" />',
			esc_attr( $input_name ),
			esc_attr( $stored )
		);

		printf(
			'<div class="show-if-value image-wrap" style="max-width: %s" tabindex="0" role="button" aria-label="%s">',
			esc_attr( $dimensions['width'] ),
			esc_attr__( 'Imagen seleccionada. Pulsa tabulador para acceder a sus opciones.', 'forja-fields' )
		);

		printf(
			'<img src="%s" alt="%s" data-name="image" style="max-height: %s;" />',
			esc_url( $image ? (string) $image[0] : '' ),
			esc_attr( $id > 0 ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '' ),
			esc_attr( $dimensions['height'] )
		);

		$this->render_actions();

		echo '</div>';

		printf(
			'<div class="hide-if-value"><p>%s <a data-name="add" class="acf-button button" href="#">%s</a></p></div>',
			esc_html__( 'No hay ninguna imagen seleccionada', 'forja-fields' ),
			esc_html__( 'Añadir imagen', 'forja-fields' )
		);

		echo '</div>';
	}

	/**
	 * Dimensiones máximas de la vista previa.
	 *
	 * Un tamaño sin límite declarado se muestra al 100 %, como hace ACF.
	 *
	 * @param string $size Nombre del tamaño de imagen.
	 * @return array{width: string, height: string} Dimensiones en CSS.
	 */
	private function preview_dimensions( string $size ): array {
		$sizes = wp_get_registered_image_subsizes();

		if ( ! isset( $sizes[ $size ] ) ) {
			return array(
				'width'  => '100%',
				'height' => '100%',
			);
		}

		$width  = (int) ( $sizes[ $size ]['width'] ?? 0 );
		$height = (int) ( $sizes[ $size ]['height'] ?? 0 );

		return array(
			'width'  => $width > 0 ? $width . 'px' : '100%',
			'height' => $height > 0 ? $height . 'px' : '100%',
		);
	}
}
