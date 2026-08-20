<?php
/**
 * Base de los campos que apuntan a un adjunto de la mediateca.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Reúne lo común de `image` y `file`.
 *
 * Ambos guardan el identificador de un adjunto y se seleccionan con el modal
 * de medios del núcleo. Lo que cambia es la vista previa: una miniatura frente
 * a una ficha con icono, nombre y tamaño.
 */
abstract class MediaField extends Field {

	/**
	 * Valores por defecto comunes a los campos de medios.
	 *
	 * @return array<string, mixed> Configuración por defecto.
	 */
	protected function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'default_value' => '',
				// Restringe la selección: all o uploadedTo.
				'library'       => 'all',
				// Tipos MIME permitidos, separados por comas.
				'mime_types'    => '',
			)
		);
	}

	/**
	 * Tipo MIME general que acepta el campo, si lo restringe.
	 *
	 * @return string Tipo general —«image», por ejemplo— o cadena vacía.
	 */
	protected function accepted_type(): string {
		return '';
	}

	/**
	 * Atributos del contenedor que lee el JavaScript.
	 *
	 * @param string $extra_class Clase que identifica el tipo de campo.
	 * @param bool   $has_value   Si hay un adjunto válido seleccionado.
	 * @return array<string, string> Atributos del contenedor.
	 */
	protected function container_attributes( string $extra_class, bool $has_value ): array {
		return array(
			'class'           => $extra_class . ( $has_value ? ' has-value' : '' ),
			'data-library'    => (string) $this->get( 'library', 'all' ),
			// ACF lo emite siempre, aunque esté vacío, y su JavaScript lo lee.
			'data-mime_types' => (string) $this->get( 'mime_types', '' ),
			'data-uploader'   => 'wp',
		);
	}

	/**
	 * Pinta los botones de editar y quitar que aparecen al pasar por encima.
	 *
	 * @return void
	 */
	protected function render_actions(): void {
		printf(
			'<div class="acf-actions -hover">'
			. '<a class="acf-icon -pencil dark" data-name="edit" href="#" title="%1$s" aria-label="%1$s"></a>'
			. '<a class="acf-icon -cancel dark" data-name="remove" href="#" title="%2$s" aria-label="%2$s"></a>'
			. '</div>',
			esc_attr__( 'Editar', 'forja-fields' ),
			esc_attr__( 'Quitar', 'forja-fields' )
		);
	}

	/**
	 * Comprueba que el valor sea un adjunto existente del tipo esperado.
	 *
	 * Es la parte importante: el navegador envía un número cualquiera, así que
	 * hay que verificar que ese identificador corresponde de verdad a un
	 * adjunto, y que su tipo encaja con lo que el campo acepta. De lo
	 * contrario cualquiera podría apuntar el campo a un post arbitrario.
	 *
	 * @param mixed $raw Valor crudo enviado por el navegador.
	 * @return mixed Identificador del adjunto, o cadena vacía.
	 */
	public function sanitize( mixed $raw ): mixed {
		$id = (int) $raw;

		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
			return '';
		}

		$accepted = $this->accepted_type();

		if ( '' !== $accepted && ! str_starts_with( (string) get_post_mime_type( $id ), $accepted . '/' ) ) {
			return '';
		}

		$mime_types = array_filter( array_map( 'trim', explode( ',', (string) $this->get( 'mime_types', '' ) ) ) );

		if ( array() !== $mime_types && ! $this->matches_mime_types( $id, $mime_types ) ) {
			return '';
		}

		return $id;
	}

	/**
	 * Comprueba el adjunto contra la lista de tipos permitidos.
	 *
	 * Se acepta tanto un tipo MIME completo —«image/png»— como una extensión
	 * suelta —«png»—, que es como ACF permite escribirlos.
	 *
	 * @param int                $id         Identificador del adjunto.
	 * @param array<int, string> $mime_types Tipos o extensiones permitidos.
	 * @return bool True si el adjunto encaja.
	 */
	private function matches_mime_types( int $id, array $mime_types ): bool {
		$mime      = (string) get_post_mime_type( $id );
		$extension = strtolower( (string) pathinfo( (string) get_attached_file( $id ), PATHINFO_EXTENSION ) );

		foreach ( $mime_types as $allowed ) {
			$allowed = strtolower( ltrim( $allowed, '.' ) );

			if ( $allowed === $mime || $allowed === $extension ) {
				return true;
			}
		}

		return false;
	}
}
