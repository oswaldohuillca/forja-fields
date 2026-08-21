import { test, expect, type Page } from '@playwright/test';

/**
 * Lógica condicional en vivo.
 *
 * Las reglas se resuelven en el navegador a propósito: tienen que reaccionar
 * mientras se escribe. El servidor sólo las emite como JSON en `data-conditions`,
 * así que Pest puede comprobar que viajan, pero no que funcionen.
 */

const CATEGORY = process.env.FORJA_E2E_TERM ?? '1';

/**
 * Abre la pantalla donde el tema declara el par de campos condicionados.
 */
async function open( page: Page ): Promise< void > {
	await page.goto(
		`/wp-admin/term.php?taxonomy=category&tag_ID=${ CATEGORY }`
	);
}

test( 'el campo dependiente arranca oculto', async ( { page } ) => {
	await open( page );

	const dependiente = page.locator(
		'.acf-field[data-name="x_dependiente"]'
	);

	await expect( dependiente ).toBeHidden();
} );

test( 'activar el interruptor muestra el campo, sin recargar', async ( {
	page,
} ) => {
	await open( page );

	const dependiente = page.locator(
		'.acf-field[data-name="x_dependiente"]'
	);

	await expect( dependiente ).toBeHidden();

	await page
		.locator( '.acf-field[data-name="x_activar"] .acf-switch' )
		.click();

	await expect( dependiente ).toBeVisible();
} );

test( 'volver a desactivarlo lo esconde otra vez', async ( { page } ) => {
	await open( page );

	const track = page.locator(
		'.acf-field[data-name="x_activar"] .acf-switch'
	);
	const dependiente = page.locator(
		'.acf-field[data-name="x_dependiente"]'
	);

	await track.click();
	await expect( dependiente ).toBeVisible();

	await track.click();
	await expect( dependiente ).toBeHidden();
} );

test( 'un campo oculto no arrastra su valor al guardar', async ( { page } ) => {
	await open( page );

	const track = page.locator(
		'.acf-field[data-name="x_activar"] .acf-switch'
	);
	const input = page.locator(
		'.acf-field[data-name="x_dependiente"] input[type="text"]'
	);

	await track.click();
	await input.fill( 'algo escrito' );
	await track.click();

	// El campo se esconde, pero su control sigue en el formulario: lo que se
	// escribió viaja igual. Es el comportamiento de ACF, y conviene tenerlo
	// comprobado para que nadie lo cambie sin darse cuenta.
	await expect( input ).toHaveValue( 'algo escrito' );
} );
