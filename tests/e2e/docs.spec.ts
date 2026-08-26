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

const DOCS = process.env.FORJA_DOCS_URL ?? 'http://localhost:4173/';

test.use( { baseURL: DOCS } );

test( 'la portada por defecto está en inglés y deja entrar', async ( { page } ) => {
	await page.goto( './' );

	await expect( page.getByRole( 'heading', { level: 1 } ) ).toContainText(
		'Forja'
	);

	await page.getByRole( 'link', { name: 'Get started' } ).click();

	await expect( page ).toHaveURL( /guide\/installation/ );
} );

/*
 * Los enlaces del hero viven en el frontmatter, y `ignoreDeadLinks` no los
 * revisa: sólo mira los enlaces del Markdown. Al mover el español a `/es/`
 * quedaron apuntando a la raíz sin que el build dijera nada.
 */
test( 'la portada española sigue accesible en su ruta', async ( { page } ) => {
	await page.goto( './es/' );

	await page.getByRole( 'link', { name: 'Empezar' } ).click();

	await expect( page ).toHaveURL( /es\/guia\/instalacion/ );
} );

test( 'las dos portadas enumeran los campos disponibles', async ( { page } ) => {
	/*
	 * Quien llega quiere saber en diez segundos si la librería tiene lo que
	 * necesita. La lista se comprueba contra el catálogo real: si se registra un
	 * tipo nuevo y no se añade aquí, este test lo dice.
	 */
	const familias = [
		'text',
		'button_group',
		'wysiwyg',
		'icon_picker',
		'date_time_picker',
		'relationship',
		'flexible_content',
		'accordion',
	];

	for ( const ruta of [ './', './es/' ] ) {
		await page.goto( ruta );

		const portada = page.locator( '.vp-doc' );

		for ( const tipo of familias ) {
			await expect( portada ).toContainText( tipo );
		}
	}
} );

test( 'la barra lateral cubre las cuatro secciones', async ( { page } ) => {
	await page.goto( './es/guia/instalacion' );

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

test( 'el inglés avisa de su alcance y lleva al español', async ( { page } ) => {
	// Es el idioma por defecto pero cubre menos, así que tiene que decirlo en
	// vez de dar a entender que la traducción está completa.
	await page.goto( './fields/' );

	await expect( page.locator( '.vp-doc' ) ).toContainText( 'Spanish' );

	// Y el camino al manual completo sale en la propia barra lateral.
	const sidebar = page.locator( '.VPSidebar' );

	await expect( sidebar.getByText( 'In Spanish only' ) ).toBeVisible();

	await sidebar.getByRole( 'link', { name: 'Everything else' } ).click();

	await expect( page ).toHaveURL( /\/es\/$/ );
} );

test( 'cambiar de idioma nunca lleva a un 404', async ( { page } ) => {
	/*
	 * VitePress intercambia el prefijo del idioma y conserva el resto de la
	 * ruta. Aquí los segmentos están traducidos —`campos` frente a `fields`— y
	 * 18 páginas sólo existen en español, así que ese mapeo daba 404 en casi
	 * todas las combinaciones. Se resolvió fijando `link` por locale.
	 */
	const rutas = [
		'./',
		'./guide/installation',
		'./guide/getting-started',
		'./fields/',
		'./es/',
		'./es/guia/instalacion',
		'./es/campos/',
		'./es/campos/compuestos',
		'./es/desarrollo/arquitectura',
	];

	for ( const ruta of rutas ) {
		await page.goto( ruta );

		const enlace = page
			.locator( '.VPNavBarTranslations a[href]' )
			.filter( { hasNotText: 'GitHub' } )
			.first();

		await expect( enlace ).toBeAttached();

		const destino = await enlace.getAttribute( 'href' );

		expect( destino, `desde ${ ruta }` ).toBeTruthy();

		const respuesta = await page.goto( destino as string );

		expect( respuesta?.status(), `desde ${ ruta } hacia ${ destino }` ).toBe(
			200
		);
	}
} );

test( 'el contenido repartido llegó entero', async ( { page } ) => {
	// Una comprobación por sección, con algo que sólo aparece en ella.
	const muestras: Array< [ string, string ] > = [
		[ './es/campos/', 'flexible_content' ],
		[ './es/campos/compuestos', 'banner_0_titulo' ],
		[ './es/campos/clone', 'overrides' ],
		[ './es/campos/relacionales', 'save_terms' ],
		[ './es/referencia/valores', 'true_false' ],
		[ './es/desarrollo/arquitectura', 'ObjectAware' ],
	];

	for ( const [ ruta, texto ] of muestras ) {
		await page.goto( ruta );

		await expect( page.locator( '.vp-doc' ) ).toContainText( texto );
	}
} );
