<?php
/**
 * Referencia a uno o varios usuarios.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Equivalente al campo «user» de ACF.
 *
 * Guarda identificadores de usuario y, como los demás relacionales, se pinta
 * como un desplegable con búsqueda remota.
 *
 * @see secure-custom-fields/includes/fields/class-acf-field-user.php
 */
final class User extends RelationalField {

	/**
	 * Identificador del tipo.
	 *
	 * @return string Nombre del tipo.
	 */
	public static function type(): string {
		return 'user';
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
				// Roles admitidos; vacío significa todos.
				'role' => array(),
			)
		);
	}

	/**
	 * Roles a los que se limita la búsqueda.
	 *
	 * @return array<int, string> Nombres de rol.
	 */
	private function roles(): array {
		return array_values( array_filter( array_map( 'strval', (array) $this->get( 'role', array() ) ) ) );
	}

	/**
	 * Argumentos comunes de las consultas del campo.
	 *
	 * @return array<string, mixed> Argumentos para WP_User_Query.
	 */
	private function query_args(): array {
		$args  = array( 'fields' => array( 'ID', 'display_name', 'user_login' ) );
		$roles = $this->roles();

		if ( array() !== $roles ) {
			$args['role__in'] = $roles;
		}

		return $args;
	}

	/**
	 * Texto con el que se muestra un usuario.
	 *
	 * @param object $user Usuario, con al menos display_name y user_login.
	 * @return string Texto del resultado.
	 */
	private function result_text( object $user ): string {
		$name  = trim( (string) ( $user->display_name ?? '' ) );
		$login = (string) ( $user->user_login ?? '' );

		if ( '' === $name ) {
			return $login;
		}

		// El nombre visible se repite con facilidad; el login desempata.
		return $name === $login ? $name : $name . ' (' . $login . ')';
	}

	/**
	 * Etiquetas de unos usuarios concretos.
	 *
	 * @param array<int, string> $values Identificadores almacenados.
	 * @return array<string, string> Etiquetas indexadas por identificador.
	 */
	protected function labels_for( array $values ): array {
		$ids = array_filter( array_map( 'absint', $values ) );

		if ( array() === $ids ) {
			return array();
		}

		// Aquí no se filtra por rol a propósito: si a alguien le cambian el rol
		// después de elegirlo, su nombre debe seguir viéndose.
		$users = get_users(
			array(
				'include' => $ids,
				'fields'  => array( 'ID', 'display_name', 'user_login' ),
			)
		);

		$labels = array();

		foreach ( $users as $user ) {
			$labels[ (string) $user->ID ] = $this->result_text( $user );
		}

		return $labels;
	}

	/**
	 * Busca usuarios por nombre o login.
	 *
	 * @param string                $term    Texto buscado.
	 * @param int                   $page    Página de resultados, empezando en 1.
	 * @param array<string, string> $filters Sin uso en este campo.
	 * @return array<int, array{id: string, text: string}> Resultados.
	 */
	public function search( string $term, int $page, array $filters = array() ): array {
		unset( $filters );

		$args = array_merge(
			$this->query_args(),
			array(
				'number'  => self::PER_PAGE,
				'paged'   => max( 1, $page ),
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);

		if ( '' !== $term ) {
			$args['search']         = '*' . $term . '*';
			$args['search_columns'] = array( 'user_login', 'user_nicename', 'user_email', 'display_name' );
		}

		$results = array();

		foreach ( get_users( $args ) as $user ) {
			$results[] = array(
				'id'   => (string) $user->ID,
				'text' => $this->result_text( $user ),
			);
		}

		return $results;
	}

	/**
	 * Devuelve el usuario al que apunta un valor.
	 *
	 * @param string $value Identificador almacenado.
	 * @return \WP_User|null Usuario, o null si ya no existe.
	 */
	protected function resolve( string $value ): mixed {
		$user = get_userdata( absint( $value ) );

		return $user instanceof \WP_User ? $user : null;
	}
}
