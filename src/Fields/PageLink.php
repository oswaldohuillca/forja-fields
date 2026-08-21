<?php
/**
 * Enlace a una entrada del sitio.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «page_link» de ACF.
 *
 * Se elige igual que un `post_object` —de hecho **guarda el mismo dato**, el
 * identificador— pero al leerlo devuelve la dirección. Guardar el enlace ya
 * resuelto dejaría el sitio con URLs rotas en cuanto cambiara un slug.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-page_link.php
 */
final class PageLink extends PostObject {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'page_link';
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
				// Qué devuelve forja_get_field(): url o id.
				'return_format' => 'url',
			)
		);
	}

	/**
	 * Devuelve la dirección de la entrada elegida.
	 *
	 * @param mixed $value Identificadores almacenados.
	 * @return mixed Dirección, lista de direcciones, o el identificador si se
	 *               pidió así.
	 */
	public function format_value( mixed $value ): mixed {
		if ( 'url' !== $this->get( 'return_format', 'url' ) ) {
			return parent::format_value( $value );
		}

		$links = array();

		foreach ( $this->to_list( $value ) as $item ) {
			$post = $this->resolve( $item );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$permalink = get_permalink( $post );

			if ( is_string( $permalink ) ) {
				$links[] = $permalink;
			}
		}

		if ( $this->is_multiple() ) {
			return $links;
		}

		return $links[0] ?? null;
	}
}
