import { test, expect, type Page, type Locator } from '@playwright/test';

/**
 * Arranque de los campos en las filas que se añaden desde el navegador.
 *
 * Es la regresión que motivó estos tests: la fila que pinta el servidor
 * funcionaba y las añadidas después no, porque sólo se les arrancaban tres de
 * los dieciséis comportamientos. Ni Pest ni el comparador podían verlo: los dos
 * miran el markup, y el markup era correcto.
 */

/**
 * Categoría sobre la que se prueba.
 *
 * Se usa una pantalla de taxonomía, no la de entradas, porque es clásica: el
 * editor de bloques esconde los metaboxes en un cajón plegable cuyo control
 * cambia de nombre y de estructura entre versiones. El código que se prueba
 * —clonar una fila y arrancarle los campos— es exactamente el mismo.
 */
const CATEGORY = process.env.FORJA_E2E_TERM ?? '1';

/**
 * Abre la edición de la categoría y devuelve el repetidor de la caja pedida.
 */
async function openRepeater( page: Page ): Promise< Locator > {
	await page.goto(
		`/wp-admin/term.php?taxonomy=category&tag_ID=${ CATEGORY }`
	);

	const repeater = page.locator( '.acf-repeater' ).first();

	await expect( repeater ).toBeVisible( { timeout: 30_000 } );

	return repeater;
}

/**
 * Pulsa el control de añadir fila.
 *
 * Es un `<a data-event="add-row">`, igual que en ACF, así que no tiene rol de
 * botón y hay que ir por su clase.
 */
async function addRow( repeater: Locator ): Promise< void > {
	await repeater.locator( '.acf-repeater-add-row' ).first().click();
}

/**
 * Filas reales de un repetidor, sin contar la plantilla oculta.
 */
function rows( repeater: Locator ): Locator {
	return repeater.locator( 'tr.acf-row:not(.acf-clone)' );
}

test.describe( 'filas añadidas desde el navegador', () => {
	test( 'la fila nueva aparece y se numera', async ( { page } ) => {
		const repeater = await openRepeater( page );

		const before = await rows( repeater ).count();

		await addRow( repeater );

		await expect( rows( repeater ) ).toHaveCount( before + 1 );
	} );

	test( 'el rango de una fila nueva sincroniza con su número', async ( {
		page,
	} ) => {
		const repeater = await openRepeater( page );

		await addRow( repeater );

		const row = rows( repeater ).last();
		const range = row.locator( 'input[type="range"]' );
		const number = row.locator( 'input[type="number"]' );

		await range.fill( '73' );

		// syncRange() es lo que ata los dos controles. Sin arrancarlo, el número
		// se queda en su valor inicial.
		await expect( number ).toHaveValue( '73' );
	} );

	test( 'el interruptor de una fila nueva cambia el valor enviado', async ( {
		page,
	} ) => {
		const repeater = await openRepeater( page );

		await addRow( repeater );

		const row = rows( repeater ).last();
		const field = row.locator( '.acf-true-false' );
		const input = field.locator( 'input[type="checkbox"]' );
		const track = field.locator( '.acf-switch' );

		await expect( input ).not.toBeChecked();

		await track.click();

		await expect( input ).toBeChecked();

		// La casilla cambia sola, por el `label` que la envuelve. Lo que aporta
		// `syncSwitch()` es mover el interruptor, así que sin comprobar la clase
		// este test pasaría aunque nadie lo hubiera arrancado.
		await expect( track ).toHaveClass( /(^|\s)-on(\s|$)/ );
	} );

	test( 'el botón de imagen de una fila nueva abre la mediateca', async ( {
		page,
	} ) => {
		const repeater = await openRepeater( page );

		await addRow( repeater );

		const row = rows( repeater ).last();

		await row.locator( '.acf-image-uploader a[data-name="add"]' ).click();

		// wp.media sólo se monta si initMedia() enganchó el botón.
		await expect( page.locator( '.media-modal' ) ).toBeVisible();
	} );

	test( 'la primera fila y la añadida se comportan igual', async ( {
		page,
	} ) => {
		const repeater = await openRepeater( page );

		await addRow( repeater );

		// La que pinta el servidor siempre funcionó; la comparación es lo que
		// convierte este test en una red de seguridad y no en un caso suelto.
		for ( const row of await rows( repeater ).all() ) {
			await row.locator( 'input[type="range"]' ).fill( '42' );

			await expect( row.locator( 'input[type="number"]' ) ).toHaveValue(
				'42'
			);
		}
	} );
} );

test.describe( 'editores dentro de un repetidor', () => {
	test( 'la fila nueva arranca su propio TinyMCE', async ( { page } ) => {
		const repeater = await openRepeater( page );

		await addRow( repeater );

		const textarea = rows( repeater )
			.last()
			.locator( 'textarea.forja-editor' );

		// El identificador se lo asigna `prepareEditors()` a partir del `name`,
		// porque el de la plantilla venía duplicado y se descarta al clonar.
		const id = await textarea.getAttribute( 'id' );

		expect( id ).toBeTruthy();

		// Contar iframes no vale: TinyMCE monta más de uno. La pregunta real es
		// si la instancia existe, y eso lo responde su propio registro.
		await expect
			.poll( () =>
				page.evaluate(
					( editorId ) =>
						Boolean(
							(
								window as unknown as Record< string, any >
							 ).tinymce?.get( editorId )
						),
					id
				)
			)
			.toBe( true );
	} );
} );
