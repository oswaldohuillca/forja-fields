import { test, expect } from '@playwright/test';

/**
 * Sitio de documentación.
 *
 * Que compile no significa que se lea. Estos casos comprueban lo que el build
 * no ve: que la navegación lleve a alguna parte, que el buscador encuentre y
 * que el contenido repartido desde el README siguiera entero.
 *
 * Necesita el sitio servido aparte:
 *
 *     bun run docs:build && bun run docs:preview
 */

const DOCS = process.env.FORJA_DOCS_URL ?? 'http://localhost:4173/forja/';

test.use( { baseURL: DOCS } );

test( 'la portada presenta el proyecto y deja entrar', async ( { page } ) => {
	await page.goto( './' );

	await expect( page.getByRole( 'heading', { level: 1 } ) ).toContainText(
		'Forja'
	);

	await page.getByRole( 'link', { name: 'Empezar' } ).click();

	await expect( page ).toHaveURL( /guia\/instalacion/ );
} );

test( 'la barra lateral cubre las cuatro secciones', async ( { page } ) => {
	await page.goto( './guia/instalacion' );

	const sidebar = page.locator( '.VPSidebar' );

	for ( const grupo of [ 'Guía', 'Campos', 'Referencia', 'Desarrollo' ] ) {
		await expect(
			sidebar.getByText( grupo, { exact: true } )
		).toBeVisible();
	}
} );

test( 'el buscador encuentra una opción concreta', async ( { page } ) => {
	await page.goto( './' );

	await page.locator( '.DocSearch-Button, #local-search button' ).first().click();

	const input = page.locator( 'input[type="search"], .DocSearch-Input' ).first();

	await input.fill( 'return_format' );

	// Es el término que más se busca y el que peor se encontraba con 1.140
	// líneas en un solo archivo.
	await expect
		.poll(
			() => page.locator( '#localsearch-list a, .VPLocalSearchBox a' ).count(),
			{ timeout: 10_000 }
		)
		.toBeGreaterThan( 0 );
} );

test( 'el contenido repartido llegó entero', async ( { page } ) => {
	// Una comprobación por sección, con algo que sólo aparece en ella.
	const muestras: Array< [ string, string ] > = [
		[ './campos/', 'flexible_content' ],
		[ './campos/compuestos', 'banner_0_titulo' ],
		[ './campos/clone', 'overrides' ],
		[ './campos/relacionales', 'save_terms' ],
		[ './referencia/valores', 'true_false' ],
		[ './desarrollo/arquitectura', 'ObjectAware' ],
	];

	for ( const [ ruta, texto ] of muestras ) {
		await page.goto( ruta );

		await expect( page.locator( '.vp-doc' ) ).toContainText( texto );
	}
} );
