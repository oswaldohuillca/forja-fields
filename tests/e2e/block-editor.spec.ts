import { test, expect, type Page } from '@playwright/test';

/**
 * Los metaboxes dentro del editor de bloques.
 *
 * Es el entorno donde de verdad se editan las entradas, y el que más se aparta
 * del escritorio clásico: React monta la pantalla, mueve el área de metaboxes y
 * la arranca plegada. Los demás tests corren sobre una taxonomía porque es
 * estable; éste existe para que ese atajo no esconda un fallo aquí.
 */

const FRONT_PAGE = process.env.FORJA_E2E_PAGE ?? '8';

/**
 * Abre la portada en el editor de bloques y despliega el cajón de metaboxes.
 *
 * El control es un `<button>` sin clase ni nombre accesible propio: lo único
 * estable es su texto, así que se busca por ahí.
 */
async function openDrawer( page: Page ): Promise< void > {
	await page.goto( `/wp-admin/post.php?post=${ FRONT_PAGE }&action=edit` );

	const drawer = page
		.locator( 'button' )
		.filter( { hasText: /^\s*(Cajas meta|Meta Boxes)\s*$/i } )
		.first();

	await expect( drawer ).toBeVisible( { timeout: 30_000 } );

	if ( 'true' !== ( await drawer.getAttribute( 'aria-expanded' ) ) ) {
		/*
		 * Se pulsa desde el propio documento en vez de con `click()`: el control
		 * es a la vez el tirador para redimensionar el panel, y Playwright lo ve
		 * tapado por la capa que gestiona el arrastre. Lo que interesa probar es
		 * lo que hay dentro del cajón, no cómo se abre.
		 */
		await drawer.evaluate( ( element ) =>
			( element as HTMLElement ).click()
		);
	}
}

test( 'los metaboxes se pintan en el editor de bloques', async ( { page } ) => {
	await openDrawer( page );

	const box = page.locator( '#forja-banco_clones' );

	await expect( box ).toBeVisible( { timeout: 30_000 } );

	// Los campos clonados llegan hasta aquí con su nombre propio.
	await expect(
		box.locator( '.acf-field[data-name="movil_ancho"]' )
	).toBeVisible();
} );

test( 'un campo relacional se monta con select2 aquí también', async ( {
	page,
} ) => {
	await openDrawer( page );

	const field = page.locator( '.acf-field[data-name="r_entrada"]' );

	await field.scrollIntoViewIfNeeded();

	await expect( field.locator( '.select2-container' ) ).toBeVisible( {
		timeout: 30_000,
	} );
} );

test( 'TinyMCE arranca dentro del cajón de metaboxes', async ( { page } ) => {
	await openDrawer( page );

	const textarea = page
		.locator( '#forja-banco_editor textarea.forja-editor' )
		.first();

	// No se le exige visibilidad: TinyMCE esconde el textarea al arrancar y lo
	// sustituye por su propio iframe, así que estar oculto es justo la señal de
	// que funcionó.
	const id = await textarea.getAttribute( 'id' );

	expect( id ).toBeTruthy();

	/*
	 * React mueve el área de metaboxes al montar la pantalla, y TinyMCE se
	 * rompe si lo reparentan después de arrancar. Es un dolor conocido de ACF,
	 * así que conviene tenerlo comprobado y no supuesto.
	 */
	await expect
		.poll(
			() =>
				page.evaluate(
					( editorId ) =>
						Boolean(
							(
								window as unknown as Record< string, any >
							 ).tinymce?.get( editorId )
						),
					id
				),
			{ timeout: 30_000 }
		)
		.toBe( true );
} );
