<?php
/**
 * Editor enriquecido.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «wysiwyg» de ACF.
 *
 * A diferencia del resto de campos, aquí **no** se llama a `wp_editor()`.
 *
 * `wp_editor()` imprime el editor ya montado y registra su configuración en
 * `tinyMCEPreInit`, atada al identificador del control. Dentro de un repetidor
 * eso es un problema: la fila plantilla llevaría un editor inicializado con un
 * identificador que se duplicaría en cada fila nueva, y TinyMCE no arranca dos
 * veces sobre el mismo.
 *
 * En su lugar se emite un `<textarea>` pelado y es el JavaScript quien arranca
 * el editor con `wp.editor.initialize()`, que es la API que el propio núcleo
 * usa para los editores que aparecen después de cargar la página. Así una fila
 * recién añadida se comporta igual que una que venía guardada.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-wysiwyg.php
 */
final class Wysiwyg extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'wysiwyg';
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
				// Pestañas disponibles: all, visual o text.
				'tabs'         => 'all',
				// Barra de herramientas: full o basic.
				'toolbar'      => 'full',
				'rows'         => 8,
				// Botón de añadir objeto sobre el editor.
				'media_upload' => true,
			)
		);
	}

	/**
	 * Pinta el área de texto que el JavaScript convertirá en editor.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		// Carga TinyMCE, Quicktags y `wp.editor`. Sin esto el JavaScript no
		// tendría con qué arrancar.
		wp_enqueue_editor();

		$tabs = (string) $this->get( 'tabs', 'all' );

		$attributes = array(
			'id'             => $this->input_id(),
			'name'           => $input_name,
			'class'          => 'forja-editor',
			'rows'           => (string) $this->get( 'rows', 8 ),
			'data-tinymce'   => 'text' === $tabs ? '0' : '1',
			'data-quicktags' => 'visual' === $tabs ? '0' : '1',
			'data-toolbar'   => (string) $this->get( 'toolbar', 'full' ),
			'data-media'     => $this->get( 'media_upload', true ) ? '1' : '0',
		);

		echo '<div class="acf-editor-wrap">';

		printf(
			'<textarea %s>%s</textarea>',
			Html::attributes( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.
			esc_textarea( (string) $value )
		);

		echo '</div>';
	}

	/**
	 * Sanea el HTML según lo que pueda publicar quien edita.
	 *
	 * Se sigue el mismo criterio que WordPress aplica al contenido de una
	 * entrada: quien tiene permiso para publicar HTML sin filtrar lo conserva,
	 * y al resto se le limita a las etiquetas permitidas.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed HTML saneado.
	 */
	public function sanitize( mixed $raw ): mixed {
		$html = (string) $raw;

		return current_user_can( 'unfiltered_html' ) ? $html : wp_kses_post( $html );
	}

	/**
	 * Devuelve el HTML tal como se guardó.
	 *
	 * No se aplican `wpautop()` ni los filtros de `the_content` a propósito:
	 * eso es decisión de la plantilla, que puede querer el HTML crudo para
	 * meterlo en otro sitio.
	 *
	 * @param mixed $value Valor almacenado.
	 * @return string HTML almacenado.
	 */
	public function format_value( mixed $value ): mixed {
		return (string) $value;
	}
}
