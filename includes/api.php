<?php
/**
 * API pública del plugin.
 *
 * Estas son las únicas funciones que un proyecto debería usar. Todo lo demás
 * es interno y puede cambiar entre versiones.
 *
 * @package Forja
 */

declare( strict_types = 1 );

/*
 * Este archivo no lleva la guarda de `ABSPATH` a propósito: sólo declara
 * funciones, y `bootstrap.php` lo carga siempre para que el paquete sea
 * utilizable también desde un script de línea de comandos. Declararlas no
 * ejecuta nada; cada una usa WordPress sólo cuando se la llama.
 */

/**
 * Instancia única del plugin.
 *
 * @param string|null $dir Directorio raíz del paquete; sólo se usa en la primera llamada.
 * @return \Forja\Plugin Instancia compartida.
 */
function forja( ?string $dir = null ): \Forja\Plugin {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new \Forja\Plugin( $dir ?? ( defined( 'FORJA_DIR' ) ? FORJA_DIR : dirname( __DIR__ ) ) );
	}

	return $plugin;
}

/**
 * Declara un grupo de campos.
 *
 * Debe llamarse desde el hook `forja/register_boxes` o, como muy tarde,
 * antes de que se disparen los metaboxes de la pantalla de edición.
 *
 * Ejemplo:
 *
 *     add_action( 'forja/register_boxes', function () {
 *         forja_register_box( 'home', array(
 *             'title'           => 'Portada',
 *             'object_type'     => 'post',
 *             'object_subtypes' => array( 'page' ),
 *             'fields'          => array(
 *                 array( 'type' => 'text', 'name' => 'titular', 'label' => 'Titular' ),
 *             ),
 *         ) );
 *     } );
 *
 * @param string               $id   Identificador único del grupo.
 * @param array<string, mixed> $args Configuración del grupo, incluida la clave «fields».
 * @return \Forja\Registry\Box Grupo registrado.
 */
function forja_register_box( string $id, array $args ): \Forja\Registry\Box {
	return forja()->boxes()->register( $id, $args );
}

/**
 * Registra un tipo de campo adicional.
 *
 * @param string $class_name Clase que extiende `\Forja\Fields\Field`.
 * @return void
 */
function forja_register_field_type( string $class_name ): void {
	forja()->fields()->register( $class_name );
}

/**
 * Lee el valor de un campo.
 *
 * @param string          $name        Nombre del campo.
 * @param int|string|null $object_id   Objeto contenedor; por defecto, la entrada actual.
 * @param string          $object_type Tipo de objeto: post, term, user, comment u option.
 * @return mixed Valor almacenado, o null si no existe.
 */
function forja_get_field( string $name, int|string|null $object_id = null, string $object_type = 'post' ): mixed {
	if ( null === $object_id && 'post' === $object_type ) {
		// Dentro del loop basta con get_the_ID(). Fuera de él —una plantilla de
		// página que no llama a the_post()— hay que recurrir al objeto de la
		// consulta principal.
		$object_id = get_the_ID();

		if ( ! $object_id ) {
			$object_id = get_queried_object_id();
		}
	}

	if ( ! $object_id ) {
		return null;
	}

	$storage = forja()->storage()->for( $object_type );
	$field   = forja()->boxes()->find_field( $name );

	// Una clave que no corresponda a ningún campo declarado se devuelve tal
	// cual: puede ser un metadato de otro origen.
	if ( null === $field ) {
		return $storage->get( $object_id, $name );
	}

	// Un campo compuesto ocupa varias claves y sabe cómo reconstruirse.
	if ( $field instanceof \Forja\Fields\Composite ) {
		return $field->format_value(
			$field->read_value(
				static fn ( string $key ): mixed => $storage->get( $object_id, $key )
			)
		);
	}

	return $field->format_value( $storage->get( $object_id, $name ) );
}

/**
 * Lee el valor de un campo de una página de opciones.
 *
 * Atajo de `forja_get_field( $name, 'forja_' . $box_id, 'option' )`.
 *
 * @param string $name   Nombre del campo.
 * @param string $box_id Identificador de la página de opciones.
 * @return mixed Valor con el formato que declare el campo, o null si no existe.
 */
function forja_get_option( string $name, string $box_id ): mixed {
	return forja_get_field( $name, 'forja_' . $box_id, 'option' );
}

/**
 * Lee el valor de un campo sin darle formato.
 *
 * Devuelve exactamente lo que hay en la base de datos, sin aplicar
 * `return_format`. Útil cuando necesitas el identificador crudo aunque el
 * campo esté declarado para devolver otra cosa.
 *
 * @param string          $name        Nombre del campo.
 * @param int|string|null $object_id   Objeto contenedor.
 * @param string          $object_type Tipo de objeto.
 * @return mixed Valor almacenado, o null si no existe.
 */
function forja_get_field_raw( string $name, int|string|null $object_id = null, string $object_type = 'post' ): mixed {
	if ( null === $object_id && 'post' === $object_type ) {
		$object_id = get_the_ID();

		if ( ! $object_id ) {
			$object_id = get_queried_object_id();
		}
	}

	if ( ! $object_id ) {
		return null;
	}

	return forja()->storage()->for( $object_type )->get( $object_id, $name );
}

/**
 * Devuelve el markup de un icono, listo para incrustar.
 *
 * Cada tipo se pinta como corresponde:
 *
 * - `iconify` y `dashicons` se resuelven a un SVG en línea, cacheado. No añade
 *   JavaScript en la parte pública ni provoca salto de maquetado.
 * - `media_library` y `url` se pintan como una imagen.
 *
 * @param mixed  $icon    Valor de un campo `icon_picker`, o un nombre de Iconify.
 * @param string $classes Clases CSS para el elemento resultante.
 * @return string Markup del icono, o cadena vacía.
 */
function forja_get_icon_svg( mixed $icon, string $classes = '' ): string {
	// Un nombre suelto es la forma corta: forja_get_icon_svg( 'mdi:home' ).
	if ( is_string( $icon ) ) {
		$icon = array(
			'type'  => 'iconify',
			'value' => $icon,
		);
	}

	if ( ! is_array( $icon ) || empty( $icon['value'] ) ) {
		return '';
	}

	$type  = (string) ( $icon['type'] ?? 'iconify' );
	$value = (string) $icon['value'];

	if ( 'media_library' === $type ) {
		return wp_get_attachment_image( (int) $value, 'full', false, array( 'class' => $classes ) );
	}

	if ( 'url' === $type ) {
		return sprintf(
			'<img src="%s" alt="" class="%s" />',
			esc_url( $value ),
			esc_attr( $classes )
		);
	}

	// Los dashicons de ACF se resuelven por la colección homónima de Iconify.
	$name = 'dashicons' === $type ? 'dashicons:' . $value : $value;
	$svg  = \Forja\Icons\Iconify::svg( $name );

	if ( '' === $svg || '' === $classes ) {
		return $svg;
	}

	// El SVG viene sin clase; se le añade la pedida sin volver a parsearlo.
	return (string) preg_replace(
		'/^<svg /',
		sprintf( '<svg class="%s" ', esc_attr( $classes ) ),
		$svg,
		1
	);
}

/**
 * Imprime el icono de un campo.
 *
 * @param string          $name      Nombre del campo.
 * @param string          $classes   Clases CSS para el elemento resultante.
 * @param int|string|null $object_id Objeto contenedor.
 * @return void
 */
function forja_the_icon( string $name, string $classes = '', int|string|null $object_id = null ): void {
	$icon = forja_get_field( $name, $object_id );

	// El SVG ya viene saneado por Iconify::svg(); wp_kses_post no admite
	// etiquetas SVG, así que se imprime tal cual.
	echo forja_get_icon_svg( $icon, $classes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Saneado en Iconify::svg().
}

/**
 * Imprime el valor de un campo, escapado como texto.
 *
 * @param string          $name        Nombre del campo.
 * @param int|string|null $object_id   Objeto contenedor.
 * @param string          $object_type Tipo de objeto.
 * @return void
 */
function forja_the_field( string $name, int|string|null $object_id = null, string $object_type = 'post' ): void {
	$value = forja_get_field( $name, $object_id, $object_type );

	// Un campo declarado con `return_format => array` no se puede imprimir
	// como texto; en ese caso no se pinta nada en lugar de emitir «Array».
	if ( ! is_scalar( $value ) ) {
		return;
	}

	echo esc_html( (string) $value );
}
