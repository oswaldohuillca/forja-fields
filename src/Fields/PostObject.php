<?php
/**
 * Referencia a una o varias entradas.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «post_object» de ACF.
 *
 * Guarda identificadores de entrada. En ACF este campo no tiene markup propio:
 * se convierte en un `select` con búsqueda remota, y eso es lo que hace la
 * clase base.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-post_object.php
 */
class PostObject extends RelationalField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'post_object';
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
				// Tipos de contenido donde buscar; vacío significa todos los
				// que sean públicos.
				'post_type'   => array(),
				// Restringe a las entradas que tengan estos términos, en la
				// forma «taxonomia:slug».
				'taxonomy'    => array(),
				// Estados admitidos. Por defecto sólo lo publicado, que es lo
				// que tiene sentido enlazar desde la parte pública.
				'post_status' => array( 'publish' ),
			)
		);
	}

	/**
	 * Tipos de contenido donde buscar.
	 *
	 * @return array<int, string> Nombres de post type.
	 */
	protected function post_types(): array {
		$declared = array_filter( array_map( 'strval', (array) $this->get( 'post_type', array() ) ) );

		if ( array() !== $declared ) {
			return array_values( $declared );
		}

		return array_values( get_post_types( array( 'public' => true ) ) );
	}

	/**
	 * Argumentos comunes de las consultas del campo.
	 *
	 * @return array<string, mixed> Argumentos para WP_Query.
	 */
	protected function query_args(): array {
		$args = array(
			'post_type'              => $this->post_types(),
			'post_status'            => array_filter( array_map( 'strval', (array) $this->get( 'post_status', array( 'publish' ) ) ) ),
			'suppress_filters'       => false,
			'ignore_sticky_posts'    => true,
			// El buscador sólo necesita identificador y título; pedir los
			// términos y metadatos de cada resultado sería trabajo tirado.
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$taxonomy = array_filter( array_map( 'strval', (array) $this->get( 'taxonomy', array() ) ) );

		if ( array() !== $taxonomy ) {
			$args['tax_query'] = $this->tax_query( $taxonomy ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Es el filtro que declara el campo.
		}

		return $args;
	}

	/**
	 * Traduce la lista «taxonomia:slug» a una consulta de términos.
	 *
	 * @param array<int, string> $terms Términos declarados.
	 * @return array<int|string, mixed> Cláusula tax_query.
	 */
	private function tax_query( array $terms ): array {
		$grouped = array();

		foreach ( $terms as $term ) {
			if ( ! str_contains( $term, ':' ) ) {
				continue;
			}

			list( $taxonomy, $slug ) = explode( ':', $term, 2 );

			$grouped[ $taxonomy ][] = $slug;
		}

		$query = array( 'relation' => 'OR' );

		foreach ( $grouped as $taxonomy => $slugs ) {
			$query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
			);
		}

		return $query;
	}

	/**
	 * Título con el que se muestra una entrada.
	 *
	 * @param \WP_Post $post Entrada.
	 * @return string Título, nunca vacío.
	 */
	protected function result_text( \WP_Post $post ): string {
		$title = get_the_title( $post );

		if ( '' === trim( $title ) ) {
			/* translators: %d: identificador de la entrada. */
			$title = sprintf( __( '(sin título) #%d', 'forja-fields' ), $post->ID );
		}

		// Con varios tipos en juego, saber de cuál es cada resultado evita
		// elegir el equivocado cuando los títulos se parecen.
		if ( count( $this->post_types() ) > 1 ) {
			$object = get_post_type_object( (string) $post->post_type );

			if ( $object instanceof \WP_Post_Type ) {
				$title .= ' — ' . $object->labels->singular_name;
			}
		}

		return $title;
	}

	/**
	 * Etiquetas de unas entradas concretas.
	 *
	 * @param array<int, string> $values Identificadores almacenados.
	 * @return array<string, string> Etiquetas indexadas por identificador.
	 */
	protected function labels_for( array $values ): array {
		$ids = array_filter( array_map( 'absint', $values ) );

		if ( array() === $ids ) {
			return array();
		}

		$posts = get_posts(
			array_merge(
				$this->query_args(),
				array(
					'post__in'       => $ids,
					'orderby'        => 'post__in',
					'posts_per_page' => count( $ids ),
					// Lo ya elegido se muestra aunque su estado haya cambiado:
					// ocultarlo daría la impresión de que se perdió el dato.
					'post_status'    => 'any',
				)
			)
		);

		$labels = array();

		foreach ( $posts as $post ) {
			$labels[ (string) $post->ID ] = $this->result_text( $post );
		}

		return $labels;
	}

	/**
	 * Busca entradas por título.
	 *
	 * @param string                $term    Texto buscado.
	 * @param int                   $page    Página de resultados, empezando en 1.
	 * @param array<string, string> $filters Admite «post_type».
	 * @return array<int, array{id: string, text: string}> Resultados.
	 */
	public function search( string $term, int $page, array $filters = array() ): array {
		$args = array_merge(
			$this->query_args(),
			array(
				'posts_per_page' => self::PER_PAGE,
				'paged'          => max( 1, $page ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( '' !== $term ) {
			$args['s'] = $term;
		}

		/*
		 * El tipo pedido se cruza con los que declara el campo, nunca los
		 * sustituye: si no, bastaría con cambiar un parámetro para listar
		 * contenido que el formulario no expone.
		 */
		$requested = (string) ( $filters['post_type'] ?? '' );

		if ( '' !== $requested && in_array( $requested, $this->post_types(), true ) ) {
			$args['post_type'] = array( $requested );
		}

		$results = array();

		foreach ( get_posts( $args ) as $post ) {
			$results[] = array(
				'id'   => (string) $post->ID,
				'text' => $this->result_text( $post ),
			);
		}

		return $results;
	}

	/**
	 * Devuelve la entrada a la que apunta un valor.
	 *
	 * @param string $value Identificador almacenado.
	 * @return \WP_Post|null Entrada, o null si ya no existe.
	 */
	protected function resolve( string $value ): mixed {
		$post = get_post( absint( $value ) );

		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		// Se comprueba el tipo para que un identificador de otro sitio —una
		// imagen, por ejemplo— no pase por bueno.
		return in_array( $post->post_type, $this->post_types(), true ) ? $post : null;
	}
}
