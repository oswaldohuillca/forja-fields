import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * Compuestos dentro de compuestos.
 *
 * El exterior guardaba al interior como un valor simple: PHP avisaba de «Array
 * to string conversion», el aviso salía antes de las cabeceras, la redirección
 * del guardado se rompía y el contenido interior se perdía. Por eso cada caso
 * guarda y recarga: el fallo sólo se veía al volver a la pantalla.
 *
 * Corre sobre `forja_sin_editor`, donde el tema declara la caja `banco_anidados`.
 */

/**
 * Pulsa «Publicar» o «Actualizar» y espera a que la entrada quede guardada.
 *
 * Se espera al siguiente `load`, escuchado antes del clic, y al aviso de
 * guardado: con el fallo, la página se quedaba en `post.php` sin aviso.
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

/**
 * Abre una entrada nueva con un título.
 */
async function openNew( page: Page ): Promise< void > {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_sin_editor' );
	await page.locator( '#title' ).fill( 'Test de compuestos anidados' );
}

/**
 * El repetidor propio de un campo, sin bajar a los que lleve dentro.
 */
function repeaterOf( field: Locator ): Locator {
	return field.locator( ':scope > .acf-input > .acf-repeater' );
}

/**
 * Filas reales de un repetidor, sin la plantilla ni las de repetidores internos.
 */
function rows( field: Locator ): Locator {
	return repeaterOf( field ).locator( ':scope > table > tbody > tr.acf-row:not(.acf-clone)' );
}

/**
 * Añade una fila al repetidor de un campo.
 */
async function addRow( field: Locator ): Promise< void > {
	await repeaterOf( field ).locator( ':scope > .acf-actions .acf-repeater-add-row' ).click();
}

/**
 * Quita una fila con su botón.
 *
 * El icono está oculto hasta pasar el ratón por la fila, como en ACF, así que
 * se pasa antes por la celda de acciones de esa misma fila, no la de una fila
 * de dentro.
 */
async function removeRow( row: Locator ): Promise< void > {
	const handle = row.locator( ':scope > td.acf-row-handle.remove' );

	await handle.hover();
	await handle.locator( '[data-event="remove-row"]' ).click();
}

/**
 * Un subcampo dentro de una fila.
 */
function sub( row: Locator, name: string ): Locator {
	return row.locator( `.acf-field[data-name="${ name }"]` ).first();
}

/**
 * El campo de primer nivel con ese nombre.
 */
function topField( page: Page, name: string ): Locator {
	return page.locator( `#forja-banco_anidados .acf-field[data-name="${ name }"]` ).first();
}

test( 'un repetidor dentro de otro se guarda, se recarga y pierde filas', async ( {
	page,
} ) => {
	await openNew( page );

	const sections = topField( page, 'an_secciones' );

	await addRow( sections );
	await sub( rows( sections ).nth( 0 ), 'an_titulo' ).locator( 'input' ).fill( 'Sección A' );

	const cardsA = sub( rows( sections ).nth( 0 ), 'an_tarjetas' );

	await addRow( cardsA );
	await addRow( cardsA );
	await sub( rows( cardsA ).nth( 0 ), 'an_texto' ).locator( 'input' ).fill( 'T1' );
	await sub( rows( cardsA ).nth( 1 ), 'an_texto' ).locator( 'input' ).fill( 'T2' );

	await addRow( sections );
	await sub( rows( sections ).nth( 1 ), 'an_titulo' ).locator( 'input' ).fill( 'Sección B' );

	const cardsB = sub( rows( sections ).nth( 1 ), 'an_tarjetas' );

	await addRow( cardsB );
	await sub( rows( cardsB ).nth( 0 ), 'an_texto' ).locator( 'input' ).fill( 'T3' );

	await save( page );

	// Lo que se ve ahora viene de la base de datos.
	const saved = topField( page, 'an_secciones' );

	await expect( rows( saved ) ).toHaveCount( 2 );
	await expect( sub( rows( saved ).nth( 1 ), 'an_titulo' ).locator( 'input' ) ).toHaveValue( 'Sección B' );

	const savedA = sub( rows( saved ).nth( 0 ), 'an_tarjetas' );

	await expect( rows( savedA ) ).toHaveCount( 2 );
	await expect( sub( rows( savedA ).nth( 1 ), 'an_texto' ).locator( 'input' ) ).toHaveValue( 'T2' );
	await expect(
		sub( rows( sub( rows( saved ).nth( 1 ), 'an_tarjetas' ) ).nth( 0 ), 'an_texto' ).locator( 'input' )
	).toHaveValue( 'T3' );

	// Quitar una tarjeta y guardar: tiene que desaparecer, no reaparecer.
	await removeRow( rows( savedA ).nth( 1 ) );
	await save( page );

	const after = sub( rows( topField( page, 'an_secciones' ) ).nth( 0 ), 'an_tarjetas' );

	await expect( rows( after ) ).toHaveCount( 1 );
	await expect( sub( rows( after ).nth( 0 ), 'an_texto' ).locator( 'input' ) ).toHaveValue( 'T1' );

	await trash( page );
} );

test( 'quitar una fila interior renumera sólo su nivel', async ( { page } ) => {
	/*
	 * Al renumerar, el JavaScript cambiaba la primera aparición del índice
	 * anterior en cada `name`. En la sección 1, quitar la tarjeta 0 deja la
	 * tarjeta 1 en la posición 0, y «la primera `[1]`» de
	 * `an_secciones[1][an_tarjetas][1][an_texto]` es la de la sección: la
	 * tarjeta acababa guardada en la sección 0.
	 */
	await openNew( page );

	const sections = topField( page, 'an_secciones' );

	await addRow( sections );
	await sub( rows( sections ).nth( 0 ), 'an_titulo' ).locator( 'input' ).fill( 'Sección A' );

	await addRow( sections );
	await sub( rows( sections ).nth( 1 ), 'an_titulo' ).locator( 'input' ).fill( 'Sección B' );

	const cards = sub( rows( sections ).nth( 1 ), 'an_tarjetas' );

	await addRow( cards );
	await addRow( cards );
	await sub( rows( cards ).nth( 0 ), 'an_texto' ).locator( 'input' ).fill( 'Primera' );
	await sub( rows( cards ).nth( 1 ), 'an_texto' ).locator( 'input' ).fill( 'Segunda' );

	await removeRow( rows( cards ).nth( 0 ) );

	await expect(
		sub( rows( cards ).nth( 0 ), 'an_texto' ).locator( 'input' )
	).toHaveAttribute( 'name', /\[an_secciones\]\[1\]\[an_tarjetas\]\[0\]\[an_texto\]$/ );

	await save( page );

	const saved = topField( page, 'an_secciones' );

	// La sección A sigue sin tarjetas y la B tiene la que quedó.
	await expect( rows( sub( rows( saved ).nth( 0 ), 'an_tarjetas' ) ) ).toHaveCount( 0 );

	const savedCards = sub( rows( saved ).nth( 1 ), 'an_tarjetas' );

	await expect( rows( savedCards ) ).toHaveCount( 1 );
	await expect( sub( rows( savedCards ).nth( 0 ), 'an_texto' ).locator( 'input' ) ).toHaveValue( 'Segunda' );

	await trash( page );
} );

test( 'un repetidor dentro de contenido flexible se guarda', async ( { page } ) => {
	await openNew( page );

	const flexible = topField( page, 'an_capas' ).locator( '.acf-flexible-content' ).first();

	// Con una sola capa no hay menú: el botón la añade directamente.
	await flexible.locator( ':scope > .acf-actions > [data-name="add-layout"]' ).click();

	const items = flexible.locator( ':scope > .values > .layout' ).first().locator( '.acf-field[data-name="an_items"]' ).first();

	await addRow( items );
	await sub( rows( items ).nth( 0 ), 'an_item' ).locator( 'input' ).fill( 'Elemento 1' );

	await save( page );

	const saved = topField( page, 'an_capas' )
		.locator( '.acf-flexible-content > .values > .layout' )
		.first()
		.locator( '.acf-field[data-name="an_items"]' )
		.first();

	await expect( rows( saved ) ).toHaveCount( 1 );
	await expect( sub( rows( saved ).nth( 0 ), 'an_item' ).locator( 'input' ) ).toHaveValue( 'Elemento 1' );

	await trash( page );
} );

test( 'un grupo dentro de un repetidor se guarda', async ( { page } ) => {
	await openNew( page );

	const people = topField( page, 'an_personas' );

	await addRow( people );
	await sub( rows( people ).nth( 0 ), 'an_email' ).locator( 'input' ).fill( 'hola@oswa.dev' );

	await save( page );

	await expect(
		sub( rows( topField( page, 'an_personas' ) ).nth( 0 ), 'an_email' ).locator( 'input' )
	).toHaveValue( 'hola@oswa.dev' );

	await trash( page );
} );
