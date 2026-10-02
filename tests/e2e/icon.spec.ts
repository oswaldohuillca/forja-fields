import { test, expect, type Page, type Locator, type Request } from '@playwright/test';

/**
 * Buscador de iconos, a través del intermediario del paquete (`Ajax\Icons`).
 *
 * Estos tests salen a la red de verdad: el servidor consulta Iconify. Es
 * deliberado: lo que se comprueba es precisamente cómo se comporta el campo
 * ante respuestas que llegan cuando quieren, y simular la API dejaría fuera
 * justo el fallo que motivó el test.
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
 * Si una petición es la de una acción concreta de admin-ajax.
 */
function isAction( request: Request, action: string ): boolean {
	return (
		request.url().includes( 'admin-ajax.php' ) &&
		new URLSearchParams( request.postData() ?? '' ).get( 'action' ) === action
	);
}

/**
 * Texto buscado en una petición de búsqueda.
 */
function queryOf( request: Request ): string | null {
	return new URLSearchParams( request.postData() ?? '' ).get( 'query' );
}

/**
 * Miniaturas que el navegador ha llegado a pintar de verdad.
 *
 * Una imagen rota también es un `<img>`: lo que cuenta es que haya cargado y
 * tenga tamaño. Así se veía el fallo, con miniaturas rotas por un 429.
 */
function paintedImages( picker: Locator ): Promise< number > {
	return picker.evaluate(
		( element ) =>
			Array.from(
				element.querySelectorAll< HTMLImageElement >( '.acf-icon-picker-result img' )
			).filter( ( image ) => image.complete && image.naturalWidth > 0 ).length
	);
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
	await page.route( /admin-ajax\.php/, async ( route ) => {
		const request = route.request();

		if ( isAction( request, 'forja_icons_search' ) && 'hom' === queryOf( request ) ) {
			await new Promise( ( resolve ) => setTimeout( resolve, 2500 ) );
		}

		await route.continue();
	} );

	const cancelled: string[] = [];

	page.on( 'requestfailed', ( request ) => {
		if ( isAction( request, 'forja_icons_search' ) ) {
			cancelled.push( queryOf( request ) ?? '' );
		}
	} );

	const picker = await openPicker( page );
	const input = picker.locator( '.acf-icon-picker-search' );

	await input.fill( 'hom' );

	// Lo justo para que la consulta salga, pero no para que conteste.
	await page.waitForTimeout( 600 );

	await input.fill( 'home' );

	await expect
		.poll( () => cancelled.includes( 'hom' ), {
			timeout: 10_000,
		} )
		.toBe( true );

	// Y la búsqueda buena sigue en pie.
	await expect
		.poll( () => painted( picker ).count() )
		.toBeGreaterThan( 50 );
} );

test( 'buscar y paginar no pide nada a Iconify desde el navegador', async ( {
	page,
} ) => {
	/*
	 * El fallo: cada miniatura era una imagen aparte pedida a la API pública,
	 * 96 por página, y Cloudflare acababa bloqueando la IP (HTTP 429). Ahora el
	 * navegador sólo habla con admin-ajax: una petición por búsqueda y otra por
	 * página de miniaturas.
	 */
	const iconify: string[] = [];
	const batches: Request[] = [];

	page.on( 'request', ( request ) => {
		if ( new URL( request.url() ).hostname.endsWith( 'iconify.design' ) ) {
			iconify.push( request.url() );
		}

		if ( isAction( request, 'forja_icons_svg' ) ) {
			batches.push( request );
		}
	} );

	const picker = await openPicker( page );

	await picker.locator( '.acf-icon-picker-search' ).fill( 'home' );

	await expect
		.poll( () => painted( picker ).count(), { timeout: 30_000 } )
		.toBeGreaterThan( 90 );

	// Se mira antes que las miniaturas: es lo que el test vigila, y con el
	// fallo las miniaturas pueden tardar o no llegar nunca.
	expect( iconify ).toEqual( [] );

	// Pintadas de verdad, no sólo insertadas: una miniatura rota no cuenta.
	await expect
		.poll( () => paintedImages( picker ), { timeout: 30_000 } )
		.toBeGreaterThan( 90 );

	await picker.locator( '.acf-icon-picker-page:not(.-current)' ).first().click();

	await expect
		.poll( () => paintedImages( picker ), { timeout: 30_000 } )
		.toBeGreaterThan( 90 );

	expect( iconify ).toEqual( [] );

	// Una por página. La de la vista previa no cuenta: la fila es nueva y su
	// selector no tiene icono todavía.
	expect( batches ).toHaveLength( 2 );
} );

test( 'elegir un icono lo pinta en la vista previa', async ( { page } ) => {
	const picker = await openPicker( page );

	await picker.locator( '.acf-icon-picker-search' ).fill( 'home' );

	const first = painted( picker ).first();

	await expect( first ).toBeVisible( { timeout: 30_000 } );

	const name = ( await first.getAttribute( 'data-icon' ) ) ?? '';

	await first.click();

	const preview = picker.locator( '.acf-icon-picker-preview img' );

	await expect( picker.locator( '.acf-icon-picker-preview code' ) ).toHaveText( name );
	await expect( preview ).toHaveAttribute( 'src', /^data:image\/svg\+xml/ );
	await expect
		.poll( () => preview.evaluate( ( image ) => ( image as HTMLImageElement ).naturalWidth ) )
		.toBeGreaterThan( 0 );
} );

test( 'no ofrece las colecciones animadas', async ( { page } ) => {
	/*
	 * line-md y svg-spinners dibujan con `<animate>`, que el saneado quita al
	 * incrustar el icono en la parte pública: se verían como una raya. «loading»
	 * es de lo que más tienen las dos.
	 */
	const picker = await openPicker( page );

	await picker.locator( '.acf-icon-picker-search' ).fill( 'loading' );

	await expect
		.poll( () => painted( picker ).count(), { timeout: 30_000 } )
		.toBeGreaterThan( 10 );

	const names = await painted( picker ).evaluateAll( ( els ) =>
		els.map( ( e ) => ( e as HTMLElement ).dataset.icon ?? '' )
	);

	expect(
		names.filter( ( n ) => n.startsWith( 'line-md:' ) || n.startsWith( 'svg-spinners:' ) )
	).toEqual( [] );
} );
