import { test, expect, type Page } from '@playwright/test';

/**
 * Opciones con claves numéricas.
 *
 * PHP convierte en entero toda clave que parezca un número, y el valor guardado
 * es texto. El `select` comparaba los dos sin convertir: al recargar no marcaba
 * la opción guardada, el navegador mostraba la primera, y volver a guardar sin
 * tocar nada la sobrescribía. Se recorre ese ciclo entero, porque el fallo sólo
 * se ve al segundo guardado.
 */

/**
 * Pulsa «Publicar» o «Actualizar» y espera a que la entrada quede guardada.
 *
 * Se espera al siguiente `load`, escuchado antes del clic: la URL de una
 * entrada nueva ya cambia sola con el borrador automático.
 */
async function save( page: Page ): Promise< void > {
	await Promise.all( [
		page.waitForEvent( 'load' ),
		page.locator( '#publish' ).click(),
	] );

	await expect( page.locator( '#message' ) ).toBeVisible();
}

/**
 * Manda la entrada a la papelera para no acumular una por pasada.
 */
async function trash( page: Page ): Promise< void > {
	await Promise.all( [
		page.waitForEvent( 'load' ),
		page.locator( '#delete-action a' ).click(),
	] );
}

test( 'un select con claves numéricas conserva lo elegido al volver a guardar', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_sin_editor' );
	await page.locator( '#title' ).fill( 'Test de opciones numéricas' );

	const numeric = page.locator( '.acf-field[data-name="on_select"] select' );
	const list = page.locator( '.acf-field[data-name="on_lista"] select' );

	// Ninguna es la primera opción: si se perdiera, saltaría a ella.
	await numeric.selectOption( '6' );
	await list.selectOption( '20' );

	await save( page );

	await expect( numeric ).toHaveValue( '6' );
	await expect( list ).toHaveValue( '20' );

	// El segundo guardado, sin tocar nada, es donde se perdía el valor.
	await save( page );
	await page.reload();

	await expect( numeric ).toHaveValue( '6' );
	await expect( list ).toHaveValue( '20' );

	await trash( page );
} );

test( 'un radio con claves numéricas se pinta y conserva lo elegido', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_clasico' );
	await page.locator( '#title' ).fill( 'Test de radio numérico' );

	const field = page.locator( '.acf-field[data-name="on_radio"]' );

	await field.locator( 'input[value="6"]' ).check();

	await save( page );
	await save( page );
	await page.reload();

	await expect( field.locator( 'input[value="6"]' ) ).toBeChecked();
	await expect( field.locator( 'input[value="1"]' ) ).not.toBeChecked();

	await trash( page );
} );
