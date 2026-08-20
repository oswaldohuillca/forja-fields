<?php
/**
 * Campo de archivo.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «file» de ACF.
 *
 * Guarda el identificador del adjunto y muestra una ficha con su icono,
 * nombre y tamaño.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-file.php
 */
final class File extends MediaField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'file';
	}

	/**
	 * Pinta la ficha del archivo y el selector.
	 *
	 * @param mixed  $value      Identificador del adjunto.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		wp_enqueue_media();

		$id   = (int) $value;
		$info = $this->attachment_info( $id );

		printf(
			'<div %s>',
			Html::attributes( $this->container_attributes( 'acf-file-uploader', null !== $info ), array( 'data-mime_types' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.
		);

		printf(
			'<input type="hidden" name="%s" value="%s" data-name="id" />',
			esc_attr( $input_name ),
			esc_attr( null !== $info ? (string) $id : '' )
		);

		printf(
			'<div class="show-if-value file-wrap" tabindex="0" role="button" aria-label="%s">',
			esc_attr__( 'Archivo seleccionado. Pulsa tabulador para acceder a sus opciones.', 'forja-fields' )
		);

		printf(
			'<div class="file-icon"><img data-name="icon" src="%s" alt="" /></div>',
			esc_url( $info['icon'] ?? '' )
		);

		printf(
			'<div class="file-info">'
			. '<p><strong data-name="title">%1$s</strong></p>'
			. '<p><strong>%2$s:</strong> <a data-name="filename" href="%3$s" target="_blank" rel="noopener">%4$s</a></p>'
			. '<p><strong>%5$s:</strong> <span data-name="filesize">%6$s</span></p>'
			. '</div>',
			esc_html( $info['title'] ?? '' ),
			esc_html__( 'Nombre del archivo', 'forja-fields' ),
			esc_url( $info['url'] ?? '' ),
			esc_html( $info['filename'] ?? '' ),
			esc_html__( 'Tamaño del archivo', 'forja-fields' ),
			esc_html( $info['filesize'] ?? '' )
		);

		$this->render_actions();

		echo '</div>';

		printf(
			'<div class="hide-if-value"><p>%s <a data-name="add" class="acf-button button" href="#">%s</a></p></div>',
			esc_html__( 'Ningún archivo seleccionado', 'forja-fields' ),
			esc_html__( 'Añadir archivo', 'forja-fields' )
		);

		echo '</div>';
	}

	/**
	 * Reúne los datos del adjunto que se muestran en la ficha.
	 *
	 * @param int $id Identificador del adjunto.
	 * @return array<string, string>|null Datos del adjunto, o null si no existe.
	 */
	private function attachment_info( int $id ): ?array {
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
			return null;
		}

		$path = (string) get_attached_file( $id );
		$size = is_readable( $path ) ? (int) filesize( $path ) : 0;

		return array(
			'icon'     => (string) wp_mime_type_icon( $id ),
			'title'    => (string) get_the_title( $id ),
			'url'      => (string) wp_get_attachment_url( $id ),
			'filename' => (string) wp_basename( $path ),
			'filesize' => $size > 0 ? size_format( $size ) : '',
		);
	}
}
