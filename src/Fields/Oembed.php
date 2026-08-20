<?php
/**
 * Contenido incrustado.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

use Forja\Render\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «oembed» de ACF.
 *
 * Guarda la URL, no el HTML incrustado. Es lo correcto: el HTML de un proveedor
 * cambia con el tiempo y guardarlo dejaría el sitio con vídeos rotos.
 *
 * La vista previa se pide al endpoint `oembed/1.0/proxy` de la API REST del
 * núcleo, que ya existe y ya resuelve caché y proveedores. No hace falta
 * registrar un endpoint propio.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-oembed.php
 */
final class Oembed extends Field {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'oembed';
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
				'width'         => 640,
				'height'        => 390,
				// Qué recibe la plantilla: html o url.
				'return_format' => 'html',
			)
		);
	}

	/**
	 * Pinta el campo y, si hay valor, su vista previa.
	 *
	 * @param mixed  $value      Valor actual del campo.
	 * @param string $input_name Atributo «name» del control.
	 * @return void
	 */
	public function render_input( mixed $value, string $input_name ): void {
		// `wpApiSettings`: la raíz de la API REST y el nonce que necesita el
		// JavaScript para pedir la vista previa.
		wp_enqueue_script( 'wp-api-request' );

		$url = (string) $value;

		printf(
			'<div class="acf-oembed%s" data-width="%s" data-height="%s">',
			'' === $url ? '' : ' -value',
			esc_attr( (string) (int) $this->get( 'width', 640 ) ),
			esc_attr( (string) (int) $this->get( 'height', 390 ) )
		);

		$attributes = array(
			'type'        => 'url',
			'id'          => $this->input_id(),
			'class'       => 'acf-oembed-search',
			'name'        => $input_name,
			'value'       => $url,
			'placeholder' => __( 'Pega la dirección del contenido a incrustar', 'forja-fields' ),
		);

		printf( '<div class="acf-oembed-search-wrap"><input %s /></div>', Html::attributes( $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::attributes() escapa cada atributo.

		echo '<div class="acf-oembed-preview">';

		// La primera vista previa se pinta en el servidor para que no parpadee
		// al cargar; las siguientes las pide el JavaScript.
		if ( '' !== $url ) {
			$embed = wp_oembed_get( $url, array( 'width' => (int) $this->get( 'width', 640 ) ) );

			if ( is_string( $embed ) ) {
				echo wp_kses_post( $embed );
			} else {
				printf(
					'<p class="acf-oembed-error">%s</p>',
					esc_html__( 'No se pudo incrustar esta dirección.', 'forja-fields' )
				);
			}
		}

		echo '</div>';

		printf(
			'<a href="#" class="acf-icon -cancel dark acf-oembed-remove" data-name="remove" title="%1$s" aria-label="%1$s"></a>',
			esc_attr__( 'Quitar', 'forja-fields' )
		);

		echo '</div>';
	}

	/**
	 * Sanea la dirección.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed URL saneada, o cadena vacía.
	 */
	public function sanitize( mixed $raw ): mixed {
		$url = trim( (string) $raw );

		return '' === $url ? '' : sanitize_url( $url );
	}

	/**
	 * Devuelve el HTML incrustado o la dirección.
	 *
	 * @param mixed $value Valor almacenado.
	 * @return string HTML incrustado, la URL, o cadena vacía.
	 */
	public function format_value( mixed $value ): mixed {
		$url = (string) $value;

		if ( '' === $url || 'url' === $this->get( 'return_format', 'html' ) ) {
			return $url;
		}

		$embed = wp_oembed_get( $url, array( 'width' => (int) $this->get( 'width', 640 ) ) );

		return is_string( $embed ) ? $embed : '';
	}
}
