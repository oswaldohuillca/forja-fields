/**
 * Campo `relationship`: dos paneles, disponibles a la izquierda y elegidos a la
 * derecha.
 *
 * A diferencia de los demás relacionales no usa select2. Su interfaz es propia
 * porque el orden de lo elegido importa y hay que poder reordenarlo, cosa que
 * un desplegable no da.
 */

import { searchField, type SearchContext } from './search';
import { refreshConditions } from './conditions';

/** Cuánto se espera tras la última tecla antes de consultar. */
const DEBOUNCE_MS = 300;

/**
 * Identificadores ya elegidos.
 *
 * @param field Contenedor `.acf-relationship`.
 * @return Identificadores, en el orden en que se muestran.
 */
function chosenIds( field: HTMLElement ): string[] {
	return Array.from(
		field.querySelectorAll< HTMLElement >( '.values-list .acf-rel-item' )
	).map( ( item ) => item.dataset.id ?? '' );
}

/**
 * Avisa de que la selección cambió.
 *
 * El campo no tiene un control único que dispare `change`, así que se emite
 * sobre el contenedor para que la lógica condicional se entere.
 *
 * @param field Contenedor `.acf-relationship`.
 */
function notify( field: HTMLElement ): void {
	field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	refreshConditions();
}

/**
 * Marca en el panel izquierdo lo que ya está elegido.
 *
 * @param field Contenedor `.acf-relationship`.
 */
function paintDisabled( field: HTMLElement ): void {
	const chosen = new Set( chosenIds( field ) );

	for ( const item of field.querySelectorAll< HTMLElement >(
		'.choices-list .acf-rel-item'
	) ) {
		item.classList.toggle( 'disabled', chosen.has( item.dataset.id ?? '' ) );
	}
}

/**
 * Indica si se alcanzó el máximo de elementos.
 *
 * @param field Contenedor `.acf-relationship`.
 * @return True si no se puede añadir más.
 */
function isFull( field: HTMLElement ): boolean {
	const max = Number.parseInt( field.dataset.max ?? '0', 10 ) || 0;

	return max > 0 && chosenIds( field ).length >= max;
}

/**
 * Añade una entrada al panel de elegidas.
 *
 * @param field Contenedor `.acf-relationship`.
 * @param id    Identificador de la entrada.
 * @param text  Título con el que se muestra.
 */
function addItem( field: HTMLElement, id: string, text: string ): void {
	if ( '' === id || chosenIds( field ).includes( id ) || isFull( field ) ) {
		return;
	}

	const list = field.querySelector< HTMLElement >( '.values-list' );

	if ( ! list ) {
		return;
	}

	const name = field.dataset.name ?? '';
	const item = document.createElement( 'li' );

	const input = document.createElement( 'input' );
	input.type = 'hidden';
	input.name = `${ name }[]`;
	input.value = id;

	const span = document.createElement( 'span' );
	span.tabIndex = 0;
	span.dataset.id = id;
	span.className = 'acf-rel-item acf-rel-item-remove';
	span.textContent = text;

	const remove = document.createElement( 'a' );
	remove.href = '#';
	remove.className = 'acf-icon -minus small dark';
	remove.dataset.name = 'remove_item';

	span.appendChild( remove );
	item.appendChild( input );
	item.appendChild( span );
	list.appendChild( item );

	paintDisabled( field );
	notify( field );
}

/**
 * Pinta una página de resultados en el panel de disponibles.
 *
 * @param field   Contenedor `.acf-relationship`.
 * @param results Resultados devueltos.
 * @param append  Si se añaden al final en vez de reemplazar.
 */
function paintChoices(
	field: HTMLElement,
	results: ForjaSearchResult[],
	append: boolean
): void {
	const list = field.querySelector< HTMLElement >( '.choices-list' );

	if ( ! list ) {
		return;
	}

	if ( ! append ) {
		list.textContent = '';
	}

	for ( const result of results ) {
		const item = document.createElement( 'li' );
		const span = document.createElement( 'span' );

		span.tabIndex = 0;
		span.dataset.id = result.id;
		span.className = 'acf-rel-item';
		span.textContent = result.text;

		item.appendChild( span );
		list.appendChild( item );
	}

	paintDisabled( field );
}

/**
 * Arranca un campo de dos paneles.
 *
 * @param field Contenedor `.acf-relationship`.
 */
export function initRelationship( field: HTMLElement ): void {
	// Al clonar una fila de repetidor el contenedor viene con los resultados de
	// la plantilla; se vacían antes de volver a consultar.
	const choices = field.querySelector< HTMLElement >( '.choices-list' );

	if ( choices ) {
		choices.textContent = '';
	}

	const context: SearchContext = {
		field: field.dataset.field ?? '',
		nonce: field.dataset.nonce ?? '',
	};

	const search = field.querySelector< HTMLInputElement >( '[data-filter="s"]' );
	const postType = field.querySelector< HTMLSelectElement >(
		'[data-filter="post_type"]'
	);

	let page = 1;
	let more = true;
	let loading = false;
	let controller: AbortController | null = null;
	let timer = 0;

	const load = async ( reset: boolean ): Promise< void > => {
		if ( loading || ( ! reset && ! more ) ) {
			return;
		}

		loading = true;

		if ( reset ) {
			page = 1;
			more = true;
		}

		controller?.abort();
		controller = new AbortController();

		field.classList.add( '-loading' );

		// El tipo lo filtra el servidor, que lo cruza con los que declara el
		// campo. Filtrar aquí dejaría páginas medio vacías y descuadraría el
		// desplazamiento infinito.
		const found = await searchField(
			context,
			{
				s: search?.value.trim() ?? '',
				paged: page,
				post_type: postType?.value ?? '',
			},
			controller.signal
		);

		field.classList.remove( '-loading' );
		loading = false;

		if ( null === found ) {
			return;
		}

		paintChoices( field, found.results, ! reset );

		more = found.more;
		page += 1;
	};

	search?.addEventListener( 'input', () => {
		window.clearTimeout( timer );

		timer = window.setTimeout( () => void load( true ), DEBOUNCE_MS );
	} );

	postType?.addEventListener( 'change', () => void load( true ) );

	// Paginación al llegar al final del panel.
	field
		.querySelector< HTMLElement >( '.choices' )
		?.addEventListener( 'scroll', ( event ) => {
			const target = event.currentTarget as HTMLElement;

			if (
				target.scrollTop + target.clientHeight >=
				target.scrollHeight - 20
			) {
				void load( false );
			}
		} );

	field.addEventListener( 'click', ( event: Event ) => {
		const target = event.target as HTMLElement;

		if ( target.closest( '[data-name="remove_item"]' ) ) {
			event.preventDefault();
			target.closest( 'li' )?.remove();
			paintDisabled( field );
			notify( field );

			return;
		}

		const choice = target.closest< HTMLElement >(
			'.choices-list .acf-rel-item'
		);

		if ( choice && ! choice.classList.contains( 'disabled' ) ) {
			event.preventDefault();
			addItem(
				field,
				choice.dataset.id ?? '',
				choice.textContent ?? ''
			);
		}
	} );

	void load( true );
}
