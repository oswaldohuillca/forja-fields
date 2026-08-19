/**
 * Acordeón.
 *
 * El servidor emite la estructura ya anidada; aquí sólo se abre y se cierra.
 */

const ICON_OPEN =
	'<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="acf-accordion-icon" aria-hidden="true" focusable="false"><path d="M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"></path></svg>';

const ICON_CLOSED =
	'<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="acf-accordion-icon" aria-hidden="true" focusable="false"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>';

/**
 * Aplica el estado abierto o cerrado a un acordeón.
 *
 * @param field Campo `.acf-accordion`.
 * @param open  Si debe quedar abierto.
 */
function paint( field: HTMLElement, open: boolean ): void {
	const title = field.querySelector< HTMLElement >( '.acf-accordion-title' );
	const icon = title?.querySelector< HTMLElement >( '.acf-accordion-icon' );

	field.classList.toggle( '-open', open );
	title?.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

	if ( icon ) {
		icon.outerHTML = open ? ICON_OPEN : ICON_CLOSED;
	}
}

/**
 * Prepara un acordeón.
 *
 * Con `multi_expand` desactivado, abrir uno cierra a sus hermanos: es el
 * comportamiento por defecto en ACF.
 *
 * @param field Campo `.acf-accordion`.
 */
export function initAccordion( field: HTMLElement ): void {
	const title = field.querySelector< HTMLElement >( '.acf-accordion-title' );

	if ( ! title ) {
		return;
	}

	const toggle = (): void => {
		const open = ! field.classList.contains( '-open' );

		if ( open && field.dataset.multiExpand !== '1' ) {
			const container = field.parentElement;

			if ( container ) {
				for ( const sibling of container.querySelectorAll< HTMLElement >(
					':scope > .acf-accordion.-open'
				) ) {
					if ( sibling !== field ) {
						paint( sibling, false );
					}
				}
			}
		}

		paint( field, open );
	};

	title.addEventListener( 'click', toggle );

	title.addEventListener( 'keydown', ( event: KeyboardEvent ) => {
		if ( event.key === 'Enter' || event.key === ' ' ) {
			event.preventDefault();
			toggle();
		}
	} );

	paint( field, field.classList.contains( '-open' ) );
}
