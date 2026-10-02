import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * Un campo obligatorio que la lógica condicional oculta.
 *
 * «Obligatorio sólo si se muestra» es lo que se espera al escribir las dos
 * opciones juntas. Pero el control oculto conservaba su `required`, y el
 * navegador valida también los controles ocultos: «Actualizar» no hacía nada y
 * no decía por qué. Y aunque el envío pasara, el servidor exigía el campo sin
 * mirar las condiciones.
 *
 * Corre sobre `forja_requeridos`, donde el tema declara la caja
 * `banco_condicional_requerido`.
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
 * Pulsa «Publicar» y comprueba que el navegador no deja enviar.
 *
 * Se deja una marca en `window`, que sólo desaparece si la página se recarga.
 */
async function expectBlocked( page: Page, control: Locator ): Promise< void > {
	await page.evaluate( () => {
		( window as unknown as { forjaSinRecarga: boolean } ).forjaSinRecarga = true;
	} );

	await page.locator( '#publish' ).click();

	await expect
		.poll( () =>
			control.evaluate( ( element ) => ( element as HTMLInputElement ).validity.valueMissing )
		)
		.toBe( true );

	await page.waitForTimeout( 1_000 );

	expect(
		await page.evaluate(
			() => ( window as unknown as { forjaSinRecarga?: boolean } ).forjaSinRecarga
		)
	).toBe( true );
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
	await page.goto( '/wp-admin/post-new.php?post_type=forja_requeridos' );
	await page.locator( '#title' ).fill( 'Test de obligatorio condicional' );
}

/**
 * El campo de primer nivel con ese nombre.
 */
function field( page: Page, name: string ): Locator {
	return page.locator( `#forja-banco_condicional_requerido .acf-field[data-name="${ name }"]` ).first();
}

/**
 * Enciende un interruptor de `true_false`.
 *
 * La casilla real está oculta por CSS y se pulsa el interruptor que la refleja.
 */
async function switchOn( toggle: Locator ): Promise< void > {
	await toggle.locator( '.acf-switch' ).click();
	await expect( toggle.locator( 'input[type="checkbox"]' ) ).toBeChecked();
}

/**
 * Avisos de error del guardado, si el servidor rechazó algún campo.
 */
function errors( page: Page ): Locator {
	return page.locator( '.notice-error' ).filter( { hasText: 'Teléfono' } );
}

test( 'oculto y vacío, no impide guardar', async ( { page } ) => {
	await openNew( page );

	await expect( field( page, 'cr_telefono' ) ).toBeHidden();

	await save( page );

	// Tampoco el servidor lo rechaza: no puede exigir algo que no se veía.
	await expect( errors( page ) ).toHaveCount( 0 );

	await trash( page );
} );

test( 'visible y vacío, sigue siendo obligatorio', async ( { page } ) => {
	await openNew( page );

	await switchOn( field( page, 'cr_mostrar' ) );
	await expect( field( page, 'cr_telefono' ) ).toBeVisible();

	await expectBlocked( page, field( page, 'cr_telefono' ).locator( 'input' ) );
} );

test( 'visible y relleno, se guarda', async ( { page } ) => {
	await openNew( page );

	await switchOn( field( page, 'cr_mostrar' ) );
	await field( page, 'cr_telefono' ).locator( 'input' ).fill( '600 000 000' );

	await save( page );

	await expect( field( page, 'cr_telefono' ).locator( 'input' ) ).toHaveValue( '600 000 000' );
	await expect( errors( page ) ).toHaveCount( 0 );

	await trash( page );
} );

test( 'mostrar y volver a ocultar deja guardar otra vez', async ( { page } ) => {
	await openNew( page );

	const toggle = field( page, 'cr_mostrar' );

	await switchOn( toggle );
	await toggle.locator( '.acf-switch' ).click();
	await expect( field( page, 'cr_telefono' ) ).toBeHidden();

	await save( page );
	await expect( errors( page ) ).toHaveCount( 0 );

	await trash( page );
} );

test( 'en una fila de repetidor, la condición de su fila decide', async ( { page } ) => {
	await openNew( page );

	const repeater = field( page, 'cr_filas' );

	await repeater.locator( ':scope > .acf-input > .acf-repeater > .acf-actions .acf-repeater-add-row' ).click();

	const row = repeater.locator( ':scope > .acf-input > .acf-repeater > table > tbody > tr.acf-row:not(.acf-clone)' ).first();
	const data = row.locator( '.acf-field[data-name="cr_dato"]' );

	// La fila nueva viene con el interruptor apagado: el dato, oculto.
	await expect( data ).toBeHidden();

	// Encendido y vacío, la fila sí bloquea.
	await switchOn( row.locator( '.acf-field[data-name="cr_activa"]' ) );
	await expectBlocked( page, data.locator( 'input' ) );

	// Apagado otra vez, ya no.
	await row.locator( '.acf-field[data-name="cr_activa"] .acf-switch' ).click();
	await expect( data ).toBeHidden();

	await save( page );
	await trash( page );
} );
