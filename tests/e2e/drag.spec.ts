import { test, expect, type Page, type Locator } from '@playwright/test';

/**
 * Reordenar filas arrastrándolas.
 *
 * Es la parte que más se separa de ACF: allí lo hace jQuery UI y aquí la API
 * nativa del navegador, así que no basta con suponer que se comporta igual.
 */

const CATEGORY = process.env.FORJA_E2E_TERM ?? '1';

/**
 * Abre la pantalla y devuelve el repetidor, con dos filas rellenadas.
 */
async function openWithRows( page: Page ): Promise< Locator > {
	await page.goto(
		`/wp-admin/term.php?taxonomy=category&tag_ID=${ CATEGORY }`
	);

	const repeater = page.locator( '.acf-repeater' ).first();

	await expect( repeater ).toBeVisible();

	for ( const label of [ 'primera', 'segunda' ] ) {
		await repeater.locator( '.acf-repeater-add-row' ).first().click();

		await rows( repeater )
			.last()
			.locator( 'input[type="text"]' )
			.first()
			.fill( label );
	}

	return repeater;
}

/**
 * Filas reales, sin la plantilla oculta.
 */
function rows( repeater: Locator ): Locator {
	return repeater.locator( 'tr.acf-row:not(.acf-clone)' );
}

/**
 * Títulos de las filas, en el orden en que se ven.
 */
async function titles( repeater: Locator ): Promise< string[] > {
	return rows( repeater )
		.locator( 'input[type="text"]' )
		.evaluateAll( ( els ) =>
			els.map( ( e ) => ( e as HTMLInputElement ).value )
		);
}

test( 'arrastrar una fila por su número la mueve de sitio', async ( {
	page,
} ) => {
	const repeater = await openWithRows( page );

	expect( await titles( repeater ) ).toEqual( [ 'primera', 'segunda' ] );

	const handles = rows( repeater ).locator( '.acf-row-handle.order' );

	// El arrastre sólo se habilita desde la columna del número: así se puede
	// seleccionar texto dentro de los campos con normalidad.
	await handles.nth( 1 ).dragTo( handles.nth( 0 ) );

	await expect
		.poll( () => titles( repeater ) )
		.toEqual( [ 'segunda', 'primera' ] );
} );

test( 'reordenar renumera las filas', async ( { page } ) => {
	const repeater = await openWithRows( page );

	const handles = rows( repeater ).locator( '.acf-row-handle.order' );

	await handles.nth( 1 ).dragTo( handles.nth( 0 ) );

	const numbers = await handles.evaluateAll( ( els ) =>
		els.map( ( e ) => ( e.textContent ?? '' ).trim() )
	);

	expect( numbers ).toEqual( [ '1', '2' ] );
} );

test( 'reordenar reescribe los nombres, que es lo que decide las claves', async ( {
	page,
} ) => {
	const repeater = await openWithRows( page );

	const handles = rows( repeater ).locator( '.acf-row-handle.order' );

	await handles.nth( 1 ).dragTo( handles.nth( 0 ) );

	const first = await rows( repeater )
		.first()
		.locator( 'input[type="text"]' )
		.first()
		.evaluate( ( element ) => ( element as HTMLInputElement ).name );

	/*
	 * El valor que ahora está arriba tiene que llevar el índice 0. Si el nombre
	 * no se reescribiera, mover una fila cambiaría lo que se ve pero no lo que
	 * se guarda, que es el peor fallo posible: silencioso.
	 */
	expect( first ).toContain( '[0]' );

	const value = await rows( repeater )
		.first()
		.locator( 'input[type="text"]' )
		.first()
		.inputValue();

	expect( value ).toBe( 'segunda' );
} );

test( 'no se puede arrastrar desde dentro de un campo', async ( { page } ) => {
	const repeater = await openWithRows( page );

	const inputs = rows( repeater ).locator( 'input[type="text"]' );

	await inputs.nth( 1 ).dragTo( inputs.nth( 0 ) );

	// Arrastrar desde el control no reordena; si lo hiciera, seleccionar texto
	// con el ratón movería la fila.
	expect( await titles( repeater ) ).toEqual( [ 'primera', 'segunda' ] );
} );
