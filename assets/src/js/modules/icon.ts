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

/** Cuántos resultados se piden por búsqueda. */
const LIMIT = 60;

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
 * Pinta los resultados de una búsqueda.
 *
 * @param field Contenedor `.acf-icon-picker`.
 * @param icons Nombres devueltos por la API.
 */
function paint( field: HTMLElement, icons: string[] ): void {
	const results = field.querySelector< HTMLElement >(
		'.acf-icon-picker-results'
	);

	if ( ! results ) {
		return;
	}

	results.textContent = '';

	for ( const name of icons ) {
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
 * @param field Contenedor `.acf-icon-picker`.
 * @param query Texto buscado.
 */
async function search( field: HTMLElement, query: string ): Promise< void > {
	if ( query.length < 2 ) {
		paint( field, [] );

		return;
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
		const response = await fetch( url );

		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}

		const data = ( await response.json() ) as SearchResponse;

		paint( field, data.icons ?? [] );
	} catch {
		paint( field, [] );
	} finally {
		field.classList.remove( '-loading' );
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

	input.addEventListener( 'input', () => {
		// Sin esta espera, escribir «home» dispararía cuatro consultas.
		window.clearTimeout( timer );

		timer = window.setTimeout( () => {
			void search( field, input.value.trim() );
		}, DEBOUNCE_MS );
	} );

	field.addEventListener( 'click', ( event: Event ) => {
		const target = event.target as HTMLElement;
		const result = target.closest< HTMLElement >( '.acf-icon-picker-result' );

		if ( result ) {
			event.preventDefault();
			select( field, result.dataset.icon ?? '' );
			input.value = '';
			paint( field, [] );

			return;
		}

		if ( target.closest( '[data-name="remove"]' ) ) {
			event.preventDefault();
			select( field, '' );
		}
	} );
}
