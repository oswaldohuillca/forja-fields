<?php
/**
 * Selector de enlace.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «link» de ACF.
 *
 * Reutiliza el modal de enlaces del núcleo, el mismo que sale al pulsar el
 * botón de enlace en el editor. Guarda un array con el texto, la URL y si se
 * abre en otra pestaña.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-link.php
 */
final class Link extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'link';
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
				// Qué recibe la plantilla: array o url.
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
	 * Normaliza el valor a sus tres claves.
	 *
	 * @param mixed $value Valor almacenado o enviado.
	 * @return array{title: string, url: string, target: string} Enlace normalizado.
	 */
	private function normalize( mixed $value ): array {
		$value = is_array( $value ) ? $value : array();

		return array(
			'title'  => (string) ( $value['title'] ?? '' ),
			'url'    => (string) ( $value['url'] ?? '' ),
			'target' => '_blank' === ( $value['target'] ?? '' ) ? '_blank' : '',
		);
	}

	/**
	 * Pinta el enlace y el botón que abre el modal.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» de los controles.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		// Registra el modal de enlaces del núcleo.
		wp_enqueue_script( 'wplink' );
		wp_enqueue_style( 'editor-buttons' );

		$link    = $this->normalize( $value );
		$classes = 'acf-link' . ( '' !== $link['url'] ? ' -value' : '' );

		printf( '<div class="%s">', esc_attr( $classes ) );

		/*
		 * El ancla oculta es la que entiende el modal del núcleo: `wpLink`
		 * trabaja sobre un enlace, no sobre campos sueltos. Los ocultos que la
		 * acompañan son los que viajan en el envío.
		 */
		printf(
			'<div class="acf-hidden"><a class="link-node" href="%s" target="%s">%s</a>',
			esc_url( $link['url'] ),
			esc_attr( $link['target'] ),
			esc_html( $link['title'] )
		);

		foreach ( $link as $key => $current ) {
			printf(
				'<input type="hidden" class="input-%s" name="%s[%s]" value="%s" />',
				esc_attr( $key ),
				esc_attr( $input_name ),
				esc_attr( $key ),
				esc_attr( $current )
			);
		}

		echo '</div>';

		printf(
			'<a href="#" class="button" data-name="add">%s</a>',
			esc_html__( 'Seleccionar enlace', 'forja-fields' )
		);

		printf(
			'<div class="link-wrap">'
			. '<span class="link-title">%1$s</span>'
			. '<a class="link-url" href="%2$s" target="_blank" rel="noopener">%2$s</a>'
			. '<i class="link-target">%3$s</i>'
			. '<a href="#" class="acf-icon -pencil dark" data-name="edit" title="%4$s" aria-label="%4$s"></a>'
			. '<a href="#" class="acf-icon -cancel dark" data-name="remove" title="%5$s" aria-label="%5$s"></a>'
			. '</div>',
			esc_html( $link['title'] ),
			esc_url( $link['url'] ),
			esc_html__( 'Se abre en una pestaña nueva', 'forja-fields' ),
			esc_attr__( 'Editar', 'forja-fields' ),
			esc_attr__( 'Quitar', 'forja-fields' )
		);

		echo '</div>';
	}

	/**
	 * Sanea las tres claves del enlace.
	 *
	 * Un enlace sin URL no es un enlace: se guarda vacío para que la plantilla
	 * pueda comprobarlo con un simple `if`.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Enlace saneado, o cadena vacía.
	 */
	public function sanitize( mixed $raw ): mixed {
		$link = $this->normalize( $raw );
		$url  = '' === $link['url'] ? '' : sanitize_url( $link['url'] );

		if ( '' === $url ) {
			return '';
		}

		return array(
			'title'  => sanitize_text_field( $link['title'] ),
			'url'    => $url,
			'target' => $link['target'],
		);
	}

	/**
	 * Da forma al valor según `return_format`.
	 *
	 * @param mixed $value Valor almacenado.
	 * @return array{title: string, url: string, target: string}|string|null Enlace o URL.
	 */
	public function format_value( mixed $value ): mixed {
		$link = $this->normalize( $value );

		if ( 'url' === $this->get( 'return_format', 'array' ) ) {
			return $link['url'];
		}

		return '' === $link['url'] ? null : $link;
	}
}
