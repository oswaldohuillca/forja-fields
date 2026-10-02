import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * Subcampos obligatorios dentro de repetidores y contenido flexible.
 *
 * Las filas y capas nuevas se clonan de plantillas ocultas que van dentro del
 * formulario. El navegador valida también los controles ocultos, así que un
 * `required` en una plantilla vacía cancelaba el envío sin ningún mensaje:
 * «Actualizar» dejaba de hacer nada. Ni Pest ni el comparador pueden verlo,
 * porque el markup de cada fila es correcto por separado.
 *
 * Corre sobre `forja_requeridos`, un tipo de contenido del tema de prueba que
 * sólo tiene esta caja: sus `required` no bloquean los guardados de otros tests.
 */

/**
 * Un campo por nombre, sin contar las plantillas ocultas.
 */
function field( page: Page, name: string ): Locator {
	return page
		.locator( `.acf-field[data-name="${ name }"]` )
		.filter( { visible: true } );
}

/**
 * Filas reales de un repetidor, sin la plantilla.
 */
function rows( repeater: Locator ): Locator {
	return repeater.locator( ':scope tr.acf-row:not(.acf-clone)' );
}

/**
 * Abre una entrada nueva con un título, que es lo único que pide WordPress.
 */
async function openNew( page: Page ): Promise< void > {
	await page.goto( '/wp-admin/post-new.php?post_type=forja_requeridos' );
	await page.locator( '#title' ).fill( 'Test de obligatorios' );
}

/**
 * Pulsa «Publicar» o «Actualizar» y espera a que la entrada quede guardada.
 *
 * Se espera al siguiente `load`, escuchado antes del clic. `waitForURL()` no
 * vale: tras el primer guardado la URL ya coincide y se resolvería al momento,
 * con la navegación todavía en curso.
 */
async function save( page: Page ): Promise< void > {
	await Promise.all( [
		page.waitForEvent( 'load' ),
		page.locator( '#publish' ).click(),
	] );

	// El aviso y no la URL: WordPress quita `message=` de la dirección con
	// `replaceState` en cuanto carga la página.
	await expect( page ).toHaveURL( /post\.php\?post=\d+&action=edit/ );
	await expect( page.locator( '#message' ) ).toBeVisible();
}

/**
 * Pulsa «Publicar» y comprueba que el navegador no deja enviar.
 *
 * No basta con mirar la URL: en una entrada nueva WordPress la cambia por su
 * cuenta con `history.replaceState` al guardar el borrador automático. Se deja
 * una marca en `window`, que sólo desaparece si la página se recarga. Y se
 * comprueba que el control vacío es el motivo, y no otra cosa.
 */
async function expectBlocked( page: Page, control: Locator ): Promise< void > {
	await page.evaluate( () => {
		( window as unknown as { forjaSinRecarga: boolean } ).forjaSinRecarga = true;
	} );

	await page.locator( '#publish' ).click();

	await expect
		.poll( () =>
			control.evaluate(
				( element ) => ( element as HTMLInputElement ).validity.valueMissing
			)
		)
		.toBe( true );

	// Un margen para que una navegación, si la hubiera, llegue a producirse.
	await page.waitForTimeout( 1_000 );

	expect(
		await page.evaluate(
			() => ( window as unknown as { forjaSinRecarga?: boolean } ).forjaSinRecarga
		)
	).toBe( true );
}

/**
 * Si el navegador daría por bueno el formulario de la entrada.
 *
 * Es la misma comprobación que hace antes de enviar, y la que fallaba por una
 * plantilla oculta con `required`.
 */
function formIsValid( page: Page ): Promise< boolean > {
	return page.locator( '#post' ).evaluate( ( form ) =>
		( form as HTMLFormElement ).checkValidity()
	);
}

/**
 * Manda la entrada a la papelera para no acumular una por pasada.
 */
async function trash( page: Page ): Promise< void > {
	await Promise.all( [
		page.waitForEvent( 'load' ),
		page.locator( '#delete-action a' ).click(),
	] );

	await expect( page ).toHaveURL( /edit\.php/ );
}

test( 'con filas rellenas, guardar y actualizar funciona', async ( { page } ) => {
	await openNew( page );

	const repeater = field( page, 'rq_filas' );

	await repeater.locator( '.acf-repeater-add-row' ).first().click();
	await rows( repeater ).last().locator( 'input[type="text"]' ).fill( 'Fila rellena' );

	await save( page );

	// Ya guardada, la pantalla trae la fila real y otra vez la plantilla: es
	// exactamente el caso de «Actualizar» del reporte.
	const saved = field( page, 'rq_filas' );

	await expect( rows( saved ) ).toHaveCount( 1 );
	await expect( rows( saved ).first().locator( 'input[type="text"]' ) ).toHaveValue(
		'Fila rellena'
	);

	await save( page );

	await trash( page );
} );

test( 'una fila nueva vacía no deja guardar', async ( { page } ) => {
	await openNew( page );

	const repeater = field( page, 'rq_filas' );

	await repeater.locator( '.acf-repeater-add-row' ).first().click();

	// El `required` tiene que volver en la fila real, o se guardaría vacía.
	await expectBlocked( page, rows( repeater ).last().locator( 'input[type="text"]' ) );
} );

/*
 * Los dos casos anidados comprueban la validación del navegador y no un
 * guardado completo: guardar un repetidor dentro de otro falla en el servidor
 * por otro motivo (está en el ROADMAP), y aquí se prueba sólo lo que el
 * navegador deja enviar.
 */
test( 'en un repetidor anidado, la plantilla interior no bloquea', async ( {
	page,
} ) => {
	await openNew( page );

	const outer = field( page, 'rq_bloques' );

	// La fila exterior nueva trae dentro la plantilla del repetidor interior,
	// que tiene que seguir sin `required`.
	await outer.locator( ':scope > .acf-input > .acf-repeater > .acf-actions .acf-repeater-add-row' ).click();

	await expect( field( page, 'rq_items' ) ).toHaveCount( 1 );
	expect( await formIsValid( page ) ).toBe( true );
} );

test( 'en un repetidor anidado, una fila interior vacía no deja guardar', async ( {
	page,
} ) => {
	await openNew( page );

	const outer = field( page, 'rq_bloques' );

	await outer.locator( ':scope > .acf-input > .acf-repeater > .acf-actions .acf-repeater-add-row' ).click();

	const inner = field( page, 'rq_items' ).first();

	await inner.locator( '.acf-repeater-add-row' ).first().click();

	const control = rows( inner ).last().locator( 'input[type="text"]' );

	expect( await formIsValid( page ) ).toBe( false );
	expect(
		await control.evaluate( ( element ) => ( element as HTMLInputElement ).validity.valueMissing )
	).toBe( true );
} );

test( 'con una capa rellena, el contenido flexible guarda', async ( { page } ) => {
	await openNew( page );

	const flexible = field( page, 'rq_secciones' ).locator( '.acf-flexible-content' ).first();

	// Con una sola capa no hay menú: el botón la añade directamente.
	await flexible.locator( ':scope > .acf-actions > [data-name="add-layout"]' ).click();
	await field( page, 'rq_texto' ).locator( 'input[type="text"]' ).fill( 'Capa rellena' );

	await save( page );

	await expect( field( page, 'rq_texto' ).locator( 'input[type="text"]' ) ).toHaveValue(
		'Capa rellena'
	);

	await trash( page );
} );

test( 'una capa nueva vacía no deja guardar', async ( { page } ) => {
	await openNew( page );

	const flexible = field( page, 'rq_secciones' ).locator( '.acf-flexible-content' ).first();

	await flexible.locator( ':scope > .acf-actions > [data-name="add-layout"]' ).click();

	await expectBlocked( page, field( page, 'rq_texto' ).locator( 'input[type="text"]' ) );
} );
