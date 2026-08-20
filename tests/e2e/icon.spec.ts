import { test, expect, type Page, type Locator } from '@playwright/test';

/**
 * Buscador de iconos contra la API de Iconify.
 *
 * Estos tests salen a la red de verdad. Es deliberado: lo que se comprueba es
 * precisamente cómo se comporta el campo ante respuestas que llegan cuando
 * quieren, y simular la API dejaría fuera justo el fallo que motivó el test.
 */

const CATEGORY = process.env.FORJA_E2E_TERM ?? '1';

/**
 * Abre la pantalla, añade una fila y devuelve su selector de icono.
 *
 * El repetidor puede estar vacío, así que el único selector visible garantizado
 * es el de una fila recién añadida.
 */
async function openPicker( page: Page ): Promise< Locator > {
	await page.goto(
		`/wp-admin/term.php?taxonomy=category&tag_ID=${ CATEGORY }`
	);

	await page.locator( '.acf-repeater-add-row' ).first().click();

	const picker = page
		.locator( 'tr.acf-row:not(.acf-clone) .acf-icon-picker' )
		.first();

	await expect( picker ).toBeVisible();

	return picker;
}

/**
 * Nombres de los iconos pintados.
 */
function painted( picker: Locator ): Locator {
	return picker.locator( '.acf-icon-picker-result' );
}

test( 'buscar «home» devuelve iconos de casa', async ( { page } ) => {
	const picker = await openPicker( page );

	await picker.locator( '.acf-icon-picker-search' ).fill( 'home' );

	await expect
		.poll( () => painted( picker ).count() )
		.toBeGreaterThan( 50 );

	const names = await painted( picker ).evaluateAll( ( els ) =>
		els.map( ( e ) => ( e as HTMLElement ).dataset.icon ?? '' )
	);

	// Todo lo pintado tiene que venir de lo que se buscó.
	expect( names.every( ( n ) => n.includes( 'home' ) ) ).toBe( true );

	// Y los primeros deben ser el icono a secas, no una variante lejana.
	expect( names.some( ( n ) => n.endsWith( ':home' ) ) ).toBe( true );
} );

test( 'los resultados se pintan por páginas', async ( { page } ) => {
	const picker = await openPicker( page );

	await picker.locator( '.acf-icon-picker-search' ).fill( 'home' );

	await expect
		.poll( () => painted( picker ).count() )
		.toBeGreaterThan( 50 );

	// Se pide el máximo de la API pero se pinta una página, no los 999.
	const first = await painted( picker ).count();

	expect( first ).toBeLessThanOrEqual( 96 );

	const pages = picker.locator( '.acf-icon-picker-page' );

	// «home» da cientos de resultados, así que tiene que haber paginador.
	await expect( pages.first() ).toBeVisible();

	const names = async (): Promise< string[] > =>
		painted( picker ).evaluateAll( ( els ) =>
			els.map( ( e ) => ( e as HTMLElement ).dataset.icon ?? '' )
		);

	const before = await names();

	await picker.locator( '.acf-icon-picker-page:not(.-current)' ).first().click();

	// Cambiar de página muestra otros iconos, y sin volver a consultar la API.
	await expect.poll( async () => ( await names() )[ 0 ] ).not.toBe(
		before[ 0 ]
	);
} );

test( 'una búsqueda nueva cancela la anterior', async ( { page } ) => {
	/*
	 * El antirrebote reduce las consultas pero no impide que dos estén en vuelo
	 * si se escribe despacio, y entonces gana la que conteste la última, no la
	 * más reciente. Aquí se retiene la de «hom» para que siga viva cuando salga
	 * la de «home», y se comprueba que el campo la cancela.
	 *
	 * Se mira la cancelación y no los iconos pintados a propósito: pidiendo el
	 * máximo de resultados, «hom» y «home» devuelven casi lo mismo en las
	 * primeras posiciones, así que el síntoma ya no distingue una cosa de la
	 * otra. La cancelación sí.
	 */
	await page.route( /api\.iconify\.design\/search/, async ( route ) => {
		const query = new URL( route.request().url() ).searchParams.get(
			'query'
		);

		if ( 'hom' === query ) {
			await new Promise( ( resolve ) => setTimeout( resolve, 2500 ) );
		}

		await route.continue();
	} );

	const cancelled: string[] = [];

	page.on( 'requestfailed', ( request ) => {
		if ( request.url().includes( '/search' ) ) {
			cancelled.push( request.url() );
		}
	} );

	const picker = await openPicker( page );
	const input = picker.locator( '.acf-icon-picker-search' );

	await input.fill( 'hom' );

	// Lo justo para que la consulta salga, pero no para que conteste.
	await page.waitForTimeout( 600 );

	await input.fill( 'home' );

	await expect
		.poll( () => cancelled.some( ( url ) => url.includes( 'query=hom&' ) ), {
			timeout: 10_000,
		} )
		.toBe( true );

	// Y la búsqueda buena sigue en pie.
	await expect
		.poll( () => painted( picker ).count() )
		.toBeGreaterThan( 50 );
} );
