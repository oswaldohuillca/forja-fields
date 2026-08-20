/**
 * Selector de icono.
 *
 * Busca contra la API de Iconify directamente desde el navegador, igual que
 * hace icones.js.org. Su CORS lo permite y las respuestas se sirven con caché
 * de una semana, así que no hace falta ni endpoint propio ni proceso de build.
 */

import { refreshConditions } from './conditions';

/** Respuesta del buscador de Iconify. */
interface SearchResponse {
	icons?: string[];
}

/**
 * Cuántos resultados se piden por búsqueda.
 *
 * 999 es el máximo de la API: pedir más devuelve un 400, y `start` más allá de
 * ahí también. Es el mismo tope con el que trabaja icon-sets.iconify.design,
 * que para «home» muestra once páginas.
 *
 * Se pide el máximo porque una búsqueda corta tiene cientos de variantes
 * repartidas entre colecciones, y quedarse en las primeras decenas da la
 * impresión de que el catálogo es pobre.
 */
const LIMIT = 999;

/**
 * Cuántos iconos se pintan a la vez.
 *
 * Pintar los 999 metería mil nodos en el DOM por cada campo, y una fila de
 * repetidor puede traer varios. Se pagina, como hace Iconify.
 */
const PAGE_SIZE = 96;

/** Cuánto se espera tras la última tecla antes de consultar. */
const DEBOUNCE_MS = 250;

/**
 * Escribe el icono elegido y refresca la vista previa.
 *
 * @param field Contenedor `.acf-icon-picker`.
 * @param name  Nombre en formato `coleccion:icono`, o vacío para quitarlo.
 */
function select( field: HTMLElement, name: string ): void {
	const type = field.querySelector< HTMLInputElement >( '.input-type' );
	const value = field.querySelector< HTMLInputElement >( '.input-value' );

	if ( ! type || ! value ) {
		return;
	}

	type.value = 'iconify';
	value.value = name;

	const preview = field.querySelector< HTMLImageElement >(
		'.acf-icon-picker-preview img'
	);
	const label = field.querySelector< HTMLElement >(
		'.acf-icon-picker-preview code'
	);

	if ( preview ) {
		preview.src = name === '' ? '' : iconUrl( field, name );
	}

	if ( label ) {
		label.textContent = name;
	}

	field.classList.toggle( '-value', name !== '' );

	// Un icono puede ser el campo observado por una regla condicional.
	value.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	refreshConditions();
}

/**
 * Dirección del SVG de un icono.
 *
 * @param field Contenedor `.acf-icon-picker`.
 * @param name  Nombre en formato `coleccion:icono`.
 * @return URL del SVG.
 */
function iconUrl( field: HTMLElement, name: string ): string {
	const api = field.dataset.api ?? 'https://api.iconify.design';

	return `${ api }/${ name.replace( ':', '/' ) }.svg`;
}

/**
 * Números de página a mostrar, con huecos donde se recortan.
 *
 * Se muestran siempre la primera y la última, y las vecinas de la actual. Un
 * `null` representa el «…» que separa los tramos.
 *
 * @param current Página actual, empezando en cero.
 * @param total   Número de páginas.
 * @return Lista de páginas y separadores.
 */
function pageNumbers( current: number, total: number ): Array< number | null > {
	const pages = new Set< number >( [ 0, total - 1 ] );

	for ( let i = current - 1; i <= current + 1; i++ ) {
		if ( i >= 0 && i < total ) {
			pages.add( i );
		}
	}

	const sorted = Array.from( pages ).sort( ( a, b ) => a - b );
	const output: Array< number | null > = [];
	let previous = -1;

	for ( const page of sorted ) {
		if ( previous !== -1 && page - previous > 1 ) {
			output.push( null );
		}

		output.push( page );
		previous = page;
	}

	return output;
}

/**
 * Crea un botón del paginador.
 *
 * @param label    Texto visible.
 * @param ariaText Texto para lectores de pantalla.
 * @param page     Página de destino.
 * @param current  Si es la página que se está viendo.
 * @return Botón listo para insertar.
 */
function pagerButton(
	label: string,
	ariaText: string,
	page: number,
	current: boolean
): HTMLButtonElement {
	const button = document.createElement( 'button' );

	button.type = 'button';
	button.className =
		'acf-icon-picker-page' + ( current ? ' -current' : '' );
	button.dataset.page = String( page );
	button.textContent = label;
	button.setAttribute( 'aria-label', ariaText );

	if ( current ) {
		button.setAttribute( 'aria-current', 'true' );
	}

	return button;
}

/**
 * Pinta el paginador de una búsqueda.
 *
 * @param field Contenedor `.acf-icon-picker`.
 * @param total Número de resultados.
 * @param page  Página actual, empezando en cero.
 */
function paintPager( field: HTMLElement, total: number, page: number ): void {
	const pager = field.querySelector< HTMLElement >(
		'.acf-icon-picker-pager'
	);

	if ( ! pager ) {
		return;
	}

	pager.textContent = '';

	const pages = Math.ceil( total / PAGE_SIZE );

	// Con una sola página el paginador sobra.
	if ( pages < 2 ) {
		return;
	}

	const pageLabel = pager.dataset.pageLabel ?? '%d';
	const label = ( n: number ): string =>
		pageLabel.replace( '%d', String( n + 1 ) );

	if ( page > 0 ) {
		pager.appendChild(
			pagerButton(
				'‹',
				pager.dataset.prevLabel ?? '',
				page - 1,
				false
			)
		);
	}

	for ( const entry of pageNumbers( page, pages ) ) {
		if ( null === entry ) {
			const gap = document.createElement( 'span' );

			gap.className = 'acf-icon-picker-gap';
			gap.textContent = '…';
			pager.appendChild( gap );

			continue;
		}

		pager.appendChild(
			pagerButton(
				String( entry + 1 ),
				label( entry ),
				entry,
				entry === page
			)
		);
	}

	if ( page < pages - 1 ) {
		pager.appendChild(
			pagerButton(
				'›',
				pager.dataset.nextLabel ?? '',
				page + 1,
				false
			)
		);
	}
}

/**
 * Pinta una página de resultados.
 *
 * @param field Contenedor `.acf-icon-picker`.
 * @param icons Nombres devueltos por la API.
 * @param page  Página a mostrar, empezando en cero.
 */
function paint( field: HTMLElement, icons: string[], page = 0 ): void {
	const results = field.querySelector< HTMLElement >(
		'.acf-icon-picker-results'
	);

	if ( ! results ) {
		return;
	}

	results.textContent = '';

	paintPager( field, icons.length, page );

	const start = page * PAGE_SIZE;

	for ( const name of icons.slice( start, start + PAGE_SIZE ) ) {
		const button = document.createElement( 'button' );

		button.type = 'button';
		button.className = 'acf-icon-picker-result';
		button.dataset.icon = name;
		button.title = name;
		button.setAttribute( 'role', 'option' );

		const image = document.createElement( 'img' );

		// Cada icono son unos 150 bytes y la API los sirve como inmutables, así
		// que el navegador no vuelve a pedirlos.
		image.src = iconUrl( field, name );
		image.alt = '';
		image.loading = 'lazy';

		button.appendChild( image );
		results.appendChild( button );
	}
}

/**
 * Consulta la API de Iconify.
 *
 * Devuelve los nombres en vez de pintarlos: quien llama decide si la respuesta
 * sigue siendo la buena. Ver `initIconPicker()`.
 *
 * @param field  Contenedor `.acf-icon-picker`.
 * @param query  Texto buscado.
 * @param signal Permite cancelar la petición si llega otra búsqueda.
 * @return Nombres de icono, o null si la petición se canceló o falló.
 */
async function search(
	field: HTMLElement,
	query: string,
	signal: AbortSignal
): Promise< string[] | null > {
	if ( query.length < 2 ) {
		return [];
	}

	const api = field.dataset.api ?? 'https://api.iconify.design';
	const collections = field.dataset.collections ?? '';

	const url = new URL( 'search', `${ api }/` );
	url.searchParams.set( 'query', query );
	url.searchParams.set( 'limit', String( LIMIT ) );

	// Restringir las colecciones acota el catálogo a lo que el proyecto usa.
	if ( collections !== '' ) {
		url.searchParams.set( 'prefixes', collections );
	}

	field.classList.add( '-loading' );

	try {
		const response = await fetch( url, { signal } );

		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}

		const data = ( await response.json() ) as SearchResponse;

		return data.icons ?? [];
	} catch {
		// Una petición cancelada no es un fallo: la sustituye otra más reciente,
		// y vaciar los resultados haría parpadear la lista sin motivo.
		return signal.aborted ? null : [];
	} finally {
		if ( ! signal.aborted ) {
			field.classList.remove( '-loading' );
		}
	}
}

/**
 * Prepara un selector de icono.
 *
 * @param field Contenedor `.acf-icon-picker`.
 */
export function initIconPicker( field: HTMLElement ): void {
	const input = field.querySelector< HTMLInputElement >(
		'.acf-icon-picker-search'
	);

	if ( ! input ) {
		return;
	}

	let timer = 0;
	let controller: AbortController | null = null;
	let latest = 0;

	// Los resultados se guardan enteros y se pintan por páginas, así que cambiar
	// de página no vuelve a consultar la API.
	let icons: string[] = [];

	input.addEventListener( 'input', () => {
		// Sin esta espera, escribir «home» dispararía cuatro consultas.
		window.clearTimeout( timer );

		timer = window.setTimeout( () => {
			/*
			 * El antirrebote reduce las consultas, pero no impide que dos estén
			 * en vuelo a la vez si se escribe despacio. Y entonces gana la que
			 * conteste la última, no la más reciente: buscar «home» acababa
			 * mostrando los resultados de «hom» —jarras de cerveza y logotipos—
			 * porque su respuesta llegaba después.
			 *
			 * Se cancela la anterior y, por si acaso, se descarta cualquier
			 * respuesta que no sea la de la última búsqueda pedida.
			 */
			controller?.abort();
			controller = new AbortController();

			const token = ++latest;

			void search( field, input.value.trim(), controller.signal ).then(
				( found ) => {
					if ( null !== found && token === latest ) {
						icons = found;
						paint( field, icons );
					}
				}
			);
		}, DEBOUNCE_MS );
	} );

	field.addEventListener( 'click', ( event: Event ) => {
		const target = event.target as HTMLElement;

		const pageButton = target.closest< HTMLElement >(
			'.acf-icon-picker-page'
		);

		if ( pageButton ) {
			event.preventDefault();
			paint( field, icons, Number( pageButton.dataset.page ?? '0' ) );

			// La rejilla puede haber quedado desplazada de la página anterior.
			field
				.querySelector( '.acf-icon-picker-results' )
				?.scrollTo( { top: 0 } );

			return;
		}

		const result = target.closest< HTMLElement >( '.acf-icon-picker-result' );

		if ( result ) {
			event.preventDefault();
			select( field, result.dataset.icon ?? '' );
			input.value = '';
			icons = [];
			paint( field, icons );

			return;
		}

		if ( target.closest( '[data-name="remove"]' ) ) {
			event.preventDefault();
			select( field, '' );
		}
	} );
}
