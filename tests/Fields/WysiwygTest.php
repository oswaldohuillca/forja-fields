<?php
/**
 * Editor enriquecido.
 *
 * @package Forja
 */

declare( strict_types = 1 );

beforeEach( function () {
	$this->field = forja_test_field( array( 'type' => 'wysiwyg' ) );
} );

it( 'conserva el HTML permitido', function () {
	wp_set_current_user( 0 );

	expect( $this->field->sanitize( '<p>Hola <strong>mundo</strong></p>' ) )
		->toBe( '<p>Hola <strong>mundo</strong></p>' );
} );

it( 'filtra el HTML peligroso de quien no puede publicarlo sin filtrar', function () {
	// Se sigue el mismo criterio que WordPress aplica al contenido de una
	// entrada: sin `unfiltered_html`, pasa por wp_kses_post().
	wp_set_current_user( 0 );

	expect( $this->field->sanitize( '<p>Hola</p><script>alert(1)</script>' ) )
		->not->toContain( '<script' );
} );

it( 'devuelve el HTML tal como se guardó', function () {
	// Aplicar wpautop() o los filtros de the_content es decisión de la
	// plantilla, no del campo.
	expect( $this->field->format_value( "<p>Uno</p>\n<p>Dos</p>" ) )
		->toBe( "<p>Uno</p>\n<p>Dos</p>" );
} );

it( 'no arranca el editor desde el servidor', function () {
	// Se emite un textarea pelado: `wp_editor()` ataría la configuración al
	// identificador del control y dentro de un repetidor se duplicaría.
	$html = forja_test_render( $this->field, '<p>Contenido</p>' );

	expect( $html )->toContain( 'class="forja-editor"' )
		->and( $html )->toContain( '&lt;p&gt;Contenido&lt;/p&gt;' )
		->and( $html )->not->toContain( 'wp-editor-wrap' );
} );

it( 'traslada la configuración al markup para que la lea el JavaScript', function () {
	$field = forja_test_field(
		array(
			'type'         => 'wysiwyg',
			'tabs'         => 'text',
			'toolbar'      => 'basic',
			'media_upload' => false,
		)
	);

	$html = forja_test_render( $field, '' );

	expect( $html )->toContain( 'data-tinymce="0"' )
		->and( $html )->toContain( 'data-toolbar="basic"' )
		->and( $html )->toContain( 'data-media="0"' );
} );

describe( 'tablas', function () {
	it( 'no las ofrece si no se piden', function () {
		$html = forja_test_render( forja_test_field( array( 'type' => 'wysiwyg' ) ), '' );

		expect( $html )->toContain( 'data-table="0"' )
			->and( $html )->not->toContain( 'data-table-plugin' );
	} );

	it( 'pasa la URL del plugin cuando se piden', function () {
		// El filtro `mce_external_plugins` no sirve: sólo lo aplica
		// `wp_editor()`, y estos editores se arrancan desde JavaScript.
		$html = forja_test_render(
			forja_test_field( array( 'type' => 'wysiwyg', 'table' => true ) ),
			''
		);

		expect( $html )->toContain( 'data-table="1"' )
			->and( $html )->toContain( 'assets/vendor/tinymce/table/plugin.min.js' );
	} );

	it( 'conserva el HTML de una tabla al sanear', function () {
		wp_set_current_user( 0 );

		$field = forja_test_field( array( 'type' => 'wysiwyg', 'table' => true ) );
		$table = '<table><thead><tr><th>Uno</th></tr></thead><tbody><tr><td>Dos</td></tr></tbody></table>';

		// `wp_kses_post()` admite tablas con sus atributos habituales.
		expect( $field->sanitize( $table ) )->toBe( $table );
	} );
} );
