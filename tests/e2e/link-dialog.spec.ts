import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * El modal de enlaces en pantallas sin editor de texto.
 *
 * El HTML de `wpLink` lo imprime el núcleo sólo cuando hay un editor en la
 * página. Sin él, el botón «Seleccionar enlace» no tenía nada que abrir. Aquí
 * se recorren las pantallas donde un campo `link` puede aparecer sin editor, y
 * las que sí lo tienen, para que el modal no salga duplicado.
 */

const FRONT_PAGE = process.env.FORJA_E2E_PAGE ?? '8';

/**
 * Abre el modal desde un campo y comprueba que aparece.
 */
async function opensModal( field: Locator ): Promise< void > {
	await field.locator( '[data-name="add"]' ).click();

	await expect( field.page().locator( '#wp-link-wrap' ) ).toBeVisible();
}

/**
 * Un campo concreto, sin contar las plantillas ocultas de filas y capas.
 */
function field( page: Page, name: string ): Locator {
	return page
		.locator( `.acf-field[data-name="${ name }"]` )
		.filter( { visible: true } )
		.first();
}

test( 'sin editor, «Seleccionar enlace» abre el modal', async ( { page } ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_sin_editor' );

	await opensModal( field( page, 'ls_enlace' ) );
} );

test( 'con editor, el modal está una sola vez', async ( { page } ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_clasico' );

	await expect( field( page, 'lc_enlace' ) ).toBeVisible();
	await expect( page.locator( '#wp-link-wrap' ) ).toHaveCount( 1 );
} );

test( 'en el editor de bloques, el modal está una sola vez', async ( { page } ) => {
	await page.goto( `/wp-admin/post.php?post=${ FRONT_PAGE }&action=edit` );

	// Los metaboxes van en el HTML del servidor, aunque el cajón esté plegado.
	await expect( page.locator( '.acf-field[data-name="l_enlace"]' ) ).toHaveCount( 1 );
	await expect( page.locator( '#wp-link-wrap' ) ).toHaveCount( 1 );
} );

test( 'una fila nueva de repetidor abre el modal', async ( { page } ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_sin_editor' );

	const repeater = field( page, 'ls_filas' );

	await repeater.locator( '.acf-repeater-add-row' ).first().click();

	await opensModal( field( page, 'ls_fila_enlace' ) );
} );

test( 'una capa nueva de contenido flexible abre el modal', async ( { page } ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_sin_editor' );

	const flexible = field( page, 'ls_secciones' ).locator( '.acf-flexible-content' ).first();

	// Con una sola capa no hay menú: el botón la añade directamente.
	await flexible.locator( ':scope > .acf-actions > [data-name="add-layout"]' ).click();

	await opensModal( field( page, 'ls_capa_enlace' ) );
} );

test( 'en un término abre el modal', async ( { page } ) => {
	/*
	 * Etiquetas y no categorías: en el tema de prueba las categorías tienen
	 * otras cajas con editor, y el núcleo ya imprimiría el modal por su cuenta.
	 * Se usa el formulario de alta, que no depende de que exista una etiqueta.
	 */
	await page.goto( '/wp-admin/edit-tags.php?taxonomy=post_tag' );

	await opensModal( field( page, 'lt_enlace' ) );
	await expect( page.locator( '#wp-link-wrap' ) ).toHaveCount( 1 );
} );

test( 'en el perfil abre el modal', async ( { page } ) => {
	await page.goto( '/wp-admin/profile.php' );

	await opensModal( field( page, 'lu_enlace' ) );
	await expect( page.locator( '#wp-link-wrap' ) ).toHaveCount( 1 );
} );

test( 'en una página de opciones abre el modal', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=forja-banco_enlace_opciones' );

	await opensModal( field( page, 'lo_enlace' ) );
	await expect( page.locator( '#wp-link-wrap' ) ).toHaveCount( 1 );
} );

test( 'sin editor: elegir, guardar y recargar conserva el enlace', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_sin_editor' );

	const link = field( page, 'ls_enlace' );

	await opensModal( link );

	/*
	 * Se elige de la lista de contenido reciente, no escribiendo la URL: esa
	 * lista la carga una petición AJAX que necesita el nonce del propio modal,
	 * así que también comprueba que el modal impreso funciona entero.
	 */
	const recent = page.locator( '#most-recent-results li' ).first();

	await expect( recent ).toBeVisible( { timeout: 10_000 } );
	await recent.click();

	const url = await page.locator( '#wp-link-url' ).inputValue();

	expect( url ).not.toBe( '' );

	await page.locator( '#wp-link-text' ).fill( 'Elegido de la lista' );
	await page.locator( '#wp-link-submit' ).click();

	await expect( page.locator( '#wp-link-wrap' ) ).toBeHidden();

	await page.locator( '#title' ).fill( 'Test de enlace sin editor' );
	// Se espera al siguiente `load`: la URL de una entrada nueva ya cambia sola
	// a `post.php?post=…` con el borrador automático, y `waitForURL()` se
	// resolvería antes de guardar.
	await Promise.all( [
		page.waitForEvent( 'load' ),
		page.locator( '#publish' ).click(),
	] );

	const saved = field( page, 'ls_enlace' );

	await expect( saved.locator( '.link-title' ) ).toHaveText( 'Elegido de la lista' );
	await expect( saved.locator( 'input[name$="[url]"]' ) ).toHaveValue( url );

	await saved.locator( '[data-name="edit"]' ).click();

	await expect( page.locator( '#wp-link-url' ) ).toHaveValue( url );
	await expect( page.locator( '#wp-link-text' ) ).toHaveValue( 'Elegido de la lista' );

	// Deja la entrada en la papelera para no acumular una por pasada.
	await page.locator( '#wp-link-cancel button' ).click();
	await Promise.all( [
		page.waitForEvent( 'load' ),
		page.locator( '#delete-action a' ).click(),
	] );
} );
