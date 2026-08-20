<?php
/**
 * Galería de imágenes.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «gallery» de ACF.
 *
 * Guarda una lista ordenada de identificadores de adjunto. A diferencia del
 * repetidor, cabe en una sola clave: el orden es el del array y no hay
 * subcampos que indexar.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-gallery.php
 */
final class Gallery extends MediaField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'gallery';
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
				'preview_size'  => 'thumbnail',
				'min'           => 0,
				'max'           => 0,
			)
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
	 * La etiqueta encabeza la galería, no un control concreto.
	 *
	 * @return bool Siempre false.
	 */
	public function label_targets_input(): bool {
		return false;
	}

	/**
	 * Sanea la lista, descartando lo que no sea una imagen de la mediateca.
	 *
	 * Cada identificador pasa por la misma comprobación que un campo de imagen
	 * suelto: que exista, que sea un adjunto y que su tipo encaje.
	 *
	 * @param mixed $raw Valores crudos enviados por el navegador.
	 * @return mixed Lista de identificadores válidos, reindexada.
	 */
	public function sanitize( mixed $raw ): mixed {
		$ids = array();

		foreach ( (array) $raw as $candidate ) {
			$id = parent::sanitize( $candidate );

			// Una imagen repetida en la lista no aporta nada.
			if ( '' !== $id && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Comprueba los límites declarados.
	 *
	 * @param mixed $value Lista ya saneada.
	 * @return string Mensaje de error, o cadena vacía.
	 */
	public function validate( mixed $value ): string {
		$count = is_array( $value ) ? count( $value ) : 0;
		$min   = (int) $this->get( 'min', 0 );
		$max   = (int) $this->get( 'max', 0 );
		$label = (string) $this->get( 'label', $this->name() );

		if ( $min > 0 && $count < $min ) {
			return sprintf(
				/* translators: 1: etiqueta del campo, 2: número mínimo de imágenes. */
				_n(
					'%1$s necesita al menos %2$d imagen.',
					'%1$s necesita al menos %2$d imágenes.',
					$min,
					'forja-fields'
				),
				$label,
				$min
			);
		}

		if ( $max > 0 && $count > $max ) {
			return sprintf(
				/* translators: 1: etiqueta del campo, 2: número máximo de imágenes. */
				_n(
					'%1$s admite como mucho %2$d imagen.',
					'%1$s admite como mucho %2$d imágenes.',
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
	 * Da forma a cada imagen según `return_format`.
	 *
	 * @param mixed $value Lista almacenada.
	 * @return array<int, mixed> Lista de identificadores, URLs o arrays.
	 */
	public function format_value( mixed $value ): mixed {
		$formatted = array();

		foreach ( (array) $value as $id ) {
			/*
			 * Borrar una imagen de la mediateca deja su identificador huérfano
			 * en la lista. Se comprueba que siga existiendo para que la
			 * plantilla no tenga que hacerlo en cada iteración.
			 *
			 * El coste es asumible: WordPress cachea los objetos de entrada, y
			 * una galería tiene unas pocas imágenes.
			 */
			if ( 'attachment' !== get_post_type( (int) $id ) ) {
				continue;
			}

			$formatted[] = parent::format_value( $id );
		}

		return $formatted;
	}

	/**
	 * Pinta la rejilla de imágenes.
	 *
	 * @param mixed  $value      Lista de identificadores.
	 * @param string $input_name Atributo «name» de los controles.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		wp_enqueue_media();

		$size = (string) $this->get( 'preview_size', 'thumbnail' );

		$attributes = $this->container_attributes( 'acf-gallery', false );

		$attributes['data-preview_size'] = $size;
		$attributes['data-min']          = (string) (int) $this->get( 'min', 0 );
		$attributes['data-max']          = (string) (int) $this->get( 'max', 0 );

		printf( '<div %s>', Html::attributes( $attributes, array( 'data-mime_types' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		// Sin este oculto, vaciar la galería no enviaría la clave y el guardado
		// no podría distinguirlo de «no se tocó el campo».
		printf( '<input type="hidden" name="%s" />', esc_attr( $input_name ) );

		echo '<div class="acf-gallery-main"><div class="acf-gallery-attachments">';

		foreach ( (array) $value as $id ) {
			$this->render_attachment( (int) $id, $size, $input_name );
		}

		echo '</div>';

		printf(
			'<div class="acf-gallery-toolbar"><a href="#" class="acf-button button button-primary acf-gallery-add">%s</a></div>',
			esc_html__( 'Añadir a la galería', 'forja-fields' )
		);

		echo '</div>';

		// Plantilla que clona el JavaScript al añadir imágenes.
		echo '<template class="acf-gallery-template">';
		$this->render_attachment( 0, $size, $input_name );
		echo '</template>';

		echo '</div>';
	}

	/**
	 * Pinta una miniatura de la rejilla.
	 *
	 * @param int    $id         Identificador del adjunto; cero para la plantilla.
	 * @param string $size       Tamaño de la vista previa.
	 * @param string $input_name Atributo «name» base.
	 * @return void
	 */
	private function render_attachment( int $id, string $size, string $input_name ): void {
		$image = $id > 0 ? wp_get_attachment_image_src( $id, $size ) : false;

		printf(
			'<div class="acf-gallery-attachment" data-id="%s" draggable="true">',
			esc_attr( (string) $id )
		);

		printf(
			'<input type="hidden" name="%s[]" value="%s" />',
			esc_attr( $input_name ),
			esc_attr( $id > 0 ? (string) $id : '' )
		);

		printf(
			'<div class="margin"><div class="thumbnail"><img src="%s" alt="%s" /></div><div class="filename">%s</div></div>',
			esc_url( $image ? (string) $image[0] : '' ),
			esc_attr( $id > 0 ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '' ),
			esc_html( $id > 0 ? (string) wp_basename( (string) get_attached_file( $id ) ) : '' )
		);

		printf(
			'<div class="actions"><a class="acf-icon -cancel dark acf-gallery-remove" href="#" data-name="remove" title="%1$s" aria-label="%1$s"></a></div>',
			esc_attr__( 'Quitar', 'forja-fields' )
		);

		echo '</div>';
	}
}
