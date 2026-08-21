import { test, expect, type Page, type Locator } from '@playwright/test';

/**
 * Contenido flexible: elegir capa, añadir, quitar y reordenar.
 *
 * Es el campo con más JavaScript propio del paquete y hasta ahora sólo se había
 * comprobado el markup que emite el servidor.
 */

const CATEGORY = process.env.FORJA_E2E_TERM ?? '1';

/**
 * Abre la pantalla de la categoría y devuelve el campo flexible.
 */
async function openFlexible( page: Page ): Promise< Locator > {
	await page.goto(
		`/wp-admin/term.php?taxonomy=category&tag_ID=${ CATEGORY }`
	);

	const field = page.locator( '.acf-flexible-content' ).first();

	await expect( field ).toBeVisible();

	return field;
}

/**
 * Capas reales, sin contar las plantillas ocultas.
 */
function layouts( field: Locator ): Locator {
	return field.locator( ':scope > .values > .layout' );
}

/**
 * Abre el menú de capas y elige una.
 */
async function addLayout( field: Locator, name: string ): Promise< void > {
	await addButton( field ).click();

	await field
		.locator(
			`:scope > .acf-actions > .acf-fc-popup [data-layout="${ name }"]`
		)
		.click();
}

/**
 * Botón principal de añadir.
 *
 * Se acota al contenedor de acciones del campo: cada capa lleva su propio
 * `data-name="add-layout"`, y las plantillas ocultas también, así que un
 * selector suelto acaba pulsando algo que no se ve.
 */
function addButton( field: Locator ): Locator {
	return field.locator( ':scope > .acf-actions > [data-name="add-layout"]' );
}

test( 'el botón de añadir despliega el menú de capas', async ( { page } ) => {
	const field = await openFlexible( page );

	await addButton( field ).click();

	const popup = field.locator( ':scope > .acf-actions > .acf-fc-popup' );

	await expect( popup ).toBeVisible();

	// Las dos capas declaradas en el tema de prueba.
	await expect( popup.locator( '[data-layout="texto"]' ) ).toBeVisible();
	await expect( popup.locator( '[data-layout="imagen"]' ) ).toBeVisible();
} );

test( 'elegir una capa la añade con sus campos', async ( { page } ) => {
	const field = await openFlexible( page );

	const before = await layouts( field ).count();

	await addLayout( field, 'texto' );

	await expect( layouts( field ) ).toHaveCount( before + 1 );

	// La capa «texto» trae un campo de texto; la de imagen, no.
	await expect(
		layouts( field ).last().locator( 'input[type="text"]' ).first()
	).toBeVisible();
} );

test( 'cada capa añade los campos que le tocan', async ( { page } ) => {
	const field = await openFlexible( page );

	await addLayout( field, 'imagen' );

	// La capa de imagen trae un selector de medios, no un texto suelto.
	await expect(
		layouts( field ).last().locator( '.acf-image-uploader' )
	).toBeVisible();
} );

test( 'los campos de una capa nueva responden', async ( { page } ) => {
	const field = await openFlexible( page );

	await addLayout( field, 'imagen' );

	await layouts( field )
		.last()
		.locator( '.acf-image-uploader a[data-name="add"]' )
		.click();

	// Misma comprobación que en el repetidor: si nadie arrancó el campo, el
	// botón no hace nada.
	await expect( page.locator( '.media-modal' ) ).toBeVisible();
} );

test( 'quitar una capa la elimina', async ( { page } ) => {
	const field = await openFlexible( page );

	await addLayout( field, 'texto' );
	await addLayout( field, 'texto' );

	const before = await layouts( field ).count();

	await layouts( field )
		.last()
		.locator( ':scope > .acf-fc-layout-controls [data-name="remove-layout"]' )
		.first()
		.click();

	await expect( layouts( field ) ).toHaveCount( before - 1 );
} );

test( 'las capas se numeran en orden', async ( { page } ) => {
	const field = await openFlexible( page );

	await addLayout( field, 'texto' );
	await addLayout( field, 'imagen' );

	// Se leen sólo las capas reales: las plantillas ocultas llevan su propio
	// número y colarlas descuadraría la comprobación.
	const orders = await layouts( field )
		.locator( '.acf-fc-layout-order' )
		.evaluateAll( ( els ) =>
			els.map( ( e ) => ( e.textContent ?? '' ).trim() )
		);

	// El índice que se emite es la posición en la lista, que es lo que decide
	// las claves con las que se guarda.
	expect( orders ).toEqual( [ '1', '2' ] );
} );

test( 'los nombres de los campos llevan el índice de su capa', async ( {
	page,
} ) => {
	const field = await openFlexible( page );

	await addLayout( field, 'texto' );
	await addLayout( field, 'texto' );

	const names = await layouts( field )
		.locator( 'input[type="text"]' )
		.evaluateAll( ( els ) =>
			els.map( ( e ) => ( e as HTMLInputElement ).name )
		);

	// Sin reindexar, las dos capas escribirían en la misma clave y la segunda
	// pisaría a la primera al guardar.
	expect( new Set( names ).size ).toBe( names.length );
} );
