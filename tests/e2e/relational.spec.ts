import { test, expect, type Page } from '@playwright/test';

/**
 * Campos que apuntan a otros objetos.
 *
 * Lo que se comprueba aquí no lo puede ver Pest: que el desplegable se monte
 * con select2, que la búsqueda remota llegue al endpoint con su nonce, y que
 * los dos paneles del `relationship` se rellenen solos.
 */

const CATEGORY = process.env.FORJA_E2E_TERM ?? '1';

/**
 * Abre la edición de la categoría, donde el tema declara los campos.
 */
async function openTerm( page: Page ): Promise< void > {
	await page.goto(
		`/wp-admin/term.php?taxonomy=category&tag_ID=${ CATEGORY }`
	);
}

test( 'el desplegable relacional se monta con select2', async ( { page } ) => {
	await openTerm( page );

	const field = page.locator( '.acf-field[data-name="t_entrada"]' );

	await expect( field ).toBeVisible();

	// select2 esconde el `<select>` original y pinta el suyo al lado.
	await expect( field.locator( '.select2-container' ) ).toBeVisible();
} );

test( 'buscar en el desplegable consulta el endpoint y pinta resultados', async ( {
	page,
} ) => {
	await openTerm( page );

	const field = page.locator( '.acf-field[data-name="t_entrada"]' );

	await field.locator( '.select2-selection' ).click();

	const search = page.locator( '.select2-search__field' );

	await expect( search ).toBeVisible();

	await search.fill( 'a' );

	const options = page.locator( '.select2-results__option' );

	await expect
		.poll( () => options.count(), { timeout: 10_000 } )
		.toBeGreaterThan( 0 );

	// Si el nonce o la capacidad fallaran, select2 pintaría su mensaje de error
	// en lugar de resultados.
	await expect( options.first() ).not.toHaveText( /no results|sin resultados/i );
} );

test( 'elegir una entrada la deja seleccionada en el control', async ( {
	page,
} ) => {
	await openTerm( page );

	const field = page.locator( '.acf-field[data-name="t_entrada"]' );

	await field.locator( '.select2-selection' ).click();
	await page.locator( '.select2-search__field' ).fill( 'a' );

	const option = page.locator( '.select2-results__option' ).first();

	await expect( option ).toBeVisible( { timeout: 10_000 } );

	await option.click();

	/*
	 * El valor acaba en el `<select>`, que es lo que se envía al guardar. Se
	 * comprueba el valor y no el atributo `selected`: select2 marca la opción
	 * por propiedad del DOM, así que el atributo nunca aparece en el markup.
	 */
	const select = field.locator( 'select.forja-relational' );

	await expect( select ).not.toHaveValue( '' );

	// El texto se lee del propio `<select>` después de elegir: leerlo del
	// resultado antes de pulsar puede pillar el «Buscando…» de select2.
	const chosen = await select.evaluate(
		( element ) =>
			( element as HTMLSelectElement ).selectedOptions[ 0 ]
				?.textContent ?? ''
	);

	expect( chosen.trim() ).not.toBe( '' );

	await expect( field.locator( '.select2-selection' ) ).toContainText(
		chosen.trim()
	);
} );

test( 'el campo de dos paneles carga las entradas disponibles', async ( {
	page,
} ) => {
	await openTerm( page );

	const field = page.locator( '.acf-field[data-name="t_relacionadas"]' );

	await expect( field ).toBeVisible();

	// El panel izquierdo lo rellena el JavaScript nada más arrancar.
	await expect
		.poll(
			() => field.locator( '.choices-list .acf-rel-item' ).count(),
			{ timeout: 10_000 }
		)
		.toBeGreaterThan( 0 );
} );

test( 'pulsar una entrada disponible la pasa al panel de elegidas', async ( {
	page,
} ) => {
	await openTerm( page );

	const field = page.locator( '.acf-field[data-name="t_relacionadas"]' );
	const choices = field.locator( '.choices-list .acf-rel-item' );
	const values = field.locator( '.values-list .acf-rel-item' );

	await expect.poll( () => choices.count(), { timeout: 10_000 } ).toBeGreaterThan( 0 );

	const before = await values.count();
	const first = choices.first();
	const text = ( ( await first.textContent() ) ?? '' ).trim();

	await first.click();

	await expect( values ).toHaveCount( before + 1 );
	await expect( values.last() ).toContainText( text );

	// Y viaja un input oculto con su identificador, que es lo que se guarda.
	await expect(
		field.locator( '.values-list input[type="hidden"]' )
	).toHaveCount( before + 1 );
} );

test( 'lo ya elegido no se puede añadir dos veces', async ( { page } ) => {
	await openTerm( page );

	const field = page.locator( '.acf-field[data-name="t_relacionadas"]' );
	const choices = field.locator( '.choices-list .acf-rel-item' );

	await expect.poll( () => choices.count(), { timeout: 10_000 } ).toBeGreaterThan( 0 );

	await choices.first().click();

	// El mismo elemento queda marcado en el panel izquierdo.
	await expect( choices.first() ).toHaveClass( /(^|\s)disabled(\s|$)/ );
} );

test( 'la búsqueda del panel filtra lo disponible', async ( { page } ) => {
	await openTerm( page );

	const field = page.locator( '.acf-field[data-name="t_relacionadas"]' );
	const choices = field.locator( '.choices-list .acf-rel-item' );

	await expect.poll( () => choices.count(), { timeout: 10_000 } ).toBeGreaterThan( 0 );

	await field
		.locator( '[data-filter="s"]' )
		.fill( 'zzzzz-no-existe-nada-asi' );

	await expect.poll( () => choices.count(), { timeout: 10_000 } ).toBe( 0 );
} );
