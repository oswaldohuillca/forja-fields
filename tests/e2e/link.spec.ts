import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * El campo `link` y el modal de enlaces del núcleo (`wpLink`).
 *
 * Corre en una entrada del tipo `forja_clasico`, que usa el editor clásico. Es
 * a propósito: sólo el editor clásico imprime el HTML del modal, y sin él no
 * hay nada que abrir. En la taxonomía de los demás tests el fallo que esto
 * vigila —los manejadores escuchando en un elemento al que el evento nunca
 * llega— ni siquiera se podría reproducir.
 */

/**
 * Abre una entrada nueva del editor clásico y devuelve el campo.
 */
async function openField( page: Page ): Promise< Locator > {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_clasico' );

	const field = page.locator( '.acf-field[data-name="lc_enlace"]' );

	await expect( field ).toBeVisible();

	return field;
}

/**
 * Lee los tres ocultos que viajan en el envío.
 */
async function hidden( field: Locator ): Promise< Record< string, string > > {
	const value = ( key: string ): Promise< string > =>
		field.locator( `input[name$="[${ key }]"]` ).inputValue();

	return {
		url: await value( 'url' ),
		title: await value( 'title' ),
		target: await value( 'target' ),
	};
}

/**
 * Abre el modal desde el campo y espera a que esté a la vista.
 */
async function openModal( field: Locator, action: 'add' | 'edit' ): Promise< void > {
	await field.locator( `[data-name="${ action }"]` ).click();

	await expect( field.page().locator( '#wp-link-wrap' ) ).toBeVisible();
}

test( 'aceptar el modal escribe el enlace en el campo', async ( { page } ) => {
	const field = await openField( page );

	await openModal( field, 'add' );

	await page.locator( '#wp-link-url' ).fill( 'https://example.org/forja' );
	await page.locator( '#wp-link-text' ).fill( 'Forja' );
	await page.locator( '#wp-link-target' ).check();
	await page.locator( '#wp-link-submit' ).click();

	await expect( page.locator( '#wp-link-wrap' ) ).toBeHidden();

	expect( await hidden( field ) ).toEqual( {
		url: 'https://example.org/forja',
		title: 'Forja',
		target: '_blank',
	} );

	// La vista también tiene que reflejarlo, no sólo los ocultos.
	await expect( field.locator( '.acf-link' ) ).toHaveClass( /-value/ );
	await expect( field.locator( '.link-title' ) ).toHaveText( 'Forja' );
} );

test( 'aceptar con Intro desde la URL también escribe el enlace', async ( {
	page,
} ) => {
	const field = await openField( page );

	await openModal( field, 'add' );

	await page.locator( '#wp-link-text' ).fill( 'Con teclado' );
	await page.locator( '#wp-link-url' ).fill( 'https://example.org/teclado' );
	await page.locator( '#wp-link-url' ).press( 'Enter' );

	await expect( page.locator( '#wp-link-wrap' ) ).toBeHidden();

	expect( await hidden( field ) ).toEqual( {
		url: 'https://example.org/teclado',
		title: 'Con teclado',
		target: '',
	} );
} );

test( 'editar un enlace guardado abre el modal precargado', async ( { page } ) => {
	const field = await openField( page );

	await openModal( field, 'add' );
	await page.locator( '#wp-link-url' ).fill( 'https://example.org/guardado' );
	await page.locator( '#wp-link-text' ).fill( 'Guardado' );
	await page.locator( '#wp-link-target' ).check();
	await page.locator( '#wp-link-submit' ).click();

	// Se guarda y se recarga: así el valor viene del servidor, no de los
	// ocultos que acaba de escribir el JavaScript.
	await page.locator( '#title' ).fill( 'Test de enlace' );
	// Se espera al siguiente `load`: la URL de una entrada nueva ya cambia sola
	// a `post.php?post=…` con el borrador automático, y `waitForURL()` se
	// resolvería antes de guardar.
	await Promise.all( [
		page.waitForEvent( 'load' ),
		page.locator( '#publish' ).click(),
	] );

	const saved = page.locator( '.acf-field[data-name="lc_enlace"]' );

	await expect( saved.locator( '.link-title' ) ).toHaveText( 'Guardado' );

	await openModal( saved, 'edit' );

	await expect( page.locator( '#wp-link-url' ) ).toHaveValue(
		'https://example.org/guardado'
	);
	await expect( page.locator( '#wp-link-text' ) ).toHaveValue( 'Guardado' );
	await expect( page.locator( '#wp-link-target' ) ).toBeChecked();

	// Deja la entrada en la papelera para no acumular una por pasada.
	await page.locator( '#wp-link-cancel button' ).click();
	await Promise.all( [
		page.waitForEvent( 'load' ),
		page.locator( '#delete-action a' ).click(),
	] );
} );

test( 'cancelar el modal no toca el valor', async ( { page } ) => {
	const field = await openField( page );

	await openModal( field, 'add' );
	await page.locator( '#wp-link-url' ).fill( 'https://example.org/original' );
	await page.locator( '#wp-link-text' ).fill( 'Original' );
	await page.locator( '#wp-link-submit' ).click();

	await expect( page.locator( '#wp-link-wrap' ) ).toBeHidden();

	const before = await hidden( field );

	// Sin el valor de partida, «no cambia» se cumpliría con el campo vacío.
	expect( before.url ).toBe( 'https://example.org/original' );

	await openModal( field, 'edit' );
	await page.locator( '#wp-link-url' ).fill( 'https://example.org/cambiado' );
	await page.locator( '#wp-link-cancel button' ).click();

	await expect( page.locator( '#wp-link-wrap' ) ).toBeHidden();

	expect( await hidden( field ) ).toEqual( before );
} );
