<?php
/**
 * Contexto de perfiles de usuario.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Monta las cajas declaradas con `object_type => 'user'` en el perfil.
 *
 * Los campos van en una `form-table` del escritorio, así que cada uno se pinta
 * como una fila.
 *
 * `object_subtypes` filtra aquí por **rol**: una caja con
 * `array( 'editor' )` sólo aparece en los perfiles que tengan ese rol.
 *
 * @see secure-custom-fields/includes/forms/form-user.php
 */
final class UserContext extends Context {

	/**
	 * Engancha el contexto a WordPress.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// El propio perfil y el de otra persona son pantallas distintas.
		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );

		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
	}

	/**
	 * Tipo de objeto de esta pantalla.
	 *
	 * @return string Siempre «user».
	 */
	protected function object_type(): string {
		return 'user';
	}

	/**
	 * Pinta los campos en el perfil.
	 *
	 * @param \WP_User $user Usuario en edición.
	 * @return void
	 */
	public function render( \WP_User $user ): void {
		$storage = $this->storage->for( $this->object_type() );

		foreach ( $this->boxes_for( $user ) as $box ) {
			printf( '<h2>%s</h2>', esc_html( (string) $box->get( 'title', $box->id() ) ) );

			wp_nonce_field( $this->nonce_action( $box ), $this->nonce_name( $box ) );

			echo '<table class="form-table" role="presentation"><tbody>';

			$this->renderer->render_fields(
				$box->fields(),
				$this->read( $box, $storage, $user->ID ),
				self::INPUT_PREFIX,
				'field',
				'tr'
			);

			echo '</tbody></table>';
		}
	}

	/**
	 * Guarda los valores enviados.
	 *
	 * @param int $user_id Identificador del usuario.
	 * @return void
	 */
	public function save( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$storage   = $this->storage->for( $this->object_type() );
		$submitted = $this->submitted();

		foreach ( $this->boxes_for( $user ) as $box ) {
			if ( ! $this->verify_nonce( $box ) ) {
				continue;
			}

			$this->write( $box, $storage, $user_id, $submitted );
		}
	}

	/**
	 * Cajas que aplican a un usuario.
	 *
	 * Un usuario puede tener varios roles, así que basta con que uno de ellos
	 * encaje. Una caja sin roles declarados aparece en todos los perfiles.
	 *
	 * @param \WP_User $user Usuario a comprobar.
	 * @return array<string, \Forja\Registry\Box> Cajas aplicables.
	 */
	private function boxes_for( \WP_User $user ): array {
		$boxes = array();

		foreach ( $this->boxes->for_object_type( 'user' ) as $id => $box ) {
			$subtypes = (array) $box->get( 'object_subtypes', array() );

			if ( array() !== $subtypes && array() === array_intersect( $subtypes, $user->roles ) ) {
				continue;
			}

			if ( ! $box->matches_object( $user ) ) {
				continue;
			}

			$boxes[ $id ] = $box;
		}

		return $boxes;
	}
}
