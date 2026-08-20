/**
 * Campo repetible.
 *
 * Añadir, quitar y reordenar filas. La fila plantilla la emite el servidor con
 * `data-id="acfcloneindex"`; al añadir se clona y se le sustituye ese índice
 * por uno nuevo en los atributos `name` de sus controles.
 *
 * El reordenado usa la API de arrastrar y soltar del navegador, no jQuery UI:
 * es una dependencia menos y el comportamiento es equivalente.
 */

import { refreshConditions } from './conditions';

const CLONE_INDEX = 'acfcloneindex';

/**
 * Filas reales de la tabla, sin la plantilla.
 *
 * @param repeater Contenedor `.acf-repeater`.
 * @return Filas visibles.
 */
function realRows( repeater: HTMLElement ): HTMLTableRowElement[] {
	return Array.from(
		repeater.querySelectorAll< HTMLTableRowElement >(
			':scope > table > tbody > tr.acf-row:not(.acf-clone)'
		)
	);
}

/**
 * Renumera las filas y ajusta los índices de los controles.
 *
 * Los índices tienen que ser correlativos y empezar en cero: el servidor los
 * usa tal cual para componer las claves de metadatos.
 *
 * @param repeater Contenedor `.acf-repeater`.
 */
function renumber( repeater: HTMLElement ): void {
	realRows( repeater ).forEach( ( row, index ) => {
		const previous = row.dataset.id ?? '';

		row.dataset.id = String( index );

		const number = row.querySelector< HTMLElement >( '.acf-row-number' );

		if ( number ) {
			number.textContent = String( index + 1 );
		}

		if ( previous === String( index ) ) {
			return;
		}

		for ( const control of row.querySelectorAll< HTMLElement >(
			'[name]'
		) ) {
			const name = control.getAttribute( 'name' );

			if ( name ) {
				control.setAttribute(
					'name',
					name.replace( `[${ previous }]`, `[${ index }]` )
				);
			}
		}
	} );

	applyLimits( repeater );
}

/**
 * Activa o desactiva las acciones según los límites declarados.
 *
 * @param repeater Contenedor `.acf-repeater`.
 */
function applyLimits( repeater: HTMLElement ): void {
	const rows = realRows( repeater );
	const min = Number.parseInt( repeater.dataset.min ?? '0', 10 ) || 0;
	const max = Number.parseInt( repeater.dataset.max ?? '0', 10 ) || 0;

	const addButton = repeater.querySelector< HTMLElement >(
		'.acf-repeater-add-row'
	);

	if ( addButton ) {
		const full = max > 0 && rows.length >= max;

		addButton.classList.toggle( 'disabled', full );
		addButton.setAttribute( 'aria-disabled', full ? 'true' : 'false' );
	}

	// Por debajo del mínimo no se puede quitar más.
	const locked = rows.length <= min;

	for ( const row of rows ) {
		row.querySelector< HTMLElement >( '.acf-icon.-minus' )?.classList.toggle(
			'disabled',
			locked
		);
	}
}

/**
 * Inserta una fila nueva clonando la plantilla.
 *
 * @param repeater Contenedor `.acf-repeater`.
 * @param before   Fila delante de la cual insertar; al final si es null.
 */
function addRow( repeater: HTMLElement, before: HTMLTableRowElement | null ): void {
	const max = Number.parseInt( repeater.dataset.max ?? '0', 10 ) || 0;

	if ( max > 0 && realRows( repeater ).length >= max ) {
		return;
	}

	const template = repeater.querySelector< HTMLTableRowElement >(
		':scope > table > tbody > tr.acf-clone'
	);
	const body = repeater.querySelector< HTMLTableSectionElement >(
		':scope > table > tbody'
	);

	if ( ! template || ! body ) {
		return;
	}

	const row = template.cloneNode( true ) as HTMLTableRowElement;
	const index = realRows( repeater ).length;

	row.classList.remove( 'acf-clone' );
	row.dataset.id = String( index );

	for ( const control of row.querySelectorAll< HTMLElement >( '[name]' ) ) {
		const name = control.getAttribute( 'name' );

		if ( name ) {
			control.setAttribute(
				'name',
				name.replace( `[${ CLONE_INDEX }]`, `[${ index }]` )
			);
		}
	}

	// Los identificadores de la plantilla se duplicarían; se descartan porque
	// dentro de la tabla la etiqueta vive en la cabecera, no en la celda.
	for ( const control of row.querySelectorAll< HTMLElement >( '[id]' ) ) {
		control.removeAttribute( 'id' );
	}

	body.insertBefore( row, before );
	renumber( repeater );

	// La fila entra nueva al DOM: sus campos condicionales aún no se han
	// evaluado nunca.
	refreshConditions( row );

	row.querySelector< HTMLInputElement | HTMLTextAreaElement >(
		'input:not([type="hidden"]), textarea, select'
	)?.focus();
}

/**
 * Quita una fila, respetando el mínimo declarado.
 *
 * @param repeater Contenedor `.acf-repeater`.
 * @param row      Fila a quitar.
 */
function removeRow( repeater: HTMLElement, row: HTMLTableRowElement ): void {
	const min = Number.parseInt( repeater.dataset.min ?? '0', 10 ) || 0;

	if ( realRows( repeater ).length <= min ) {
		return;
	}

	row.remove();
	renumber( repeater );
}

/**
 * Habilita el reordenado por arrastre desde la columna del número.
 *
 * @param repeater Contenedor `.acf-repeater`.
 */
function enableDragging( repeater: HTMLElement ): void {
	let dragged: HTMLTableRowElement | null = null;

	repeater.addEventListener( 'mousedown', ( event: MouseEvent ) => {
		const handle = ( event.target as HTMLElement ).closest(
			'.acf-row-handle.order'
		);
		const row = ( event.target as HTMLElement ).closest< HTMLTableRowElement >(
			'tr.acf-row'
		);

		// Sólo la columna del número arrastra; así se puede seleccionar texto
		// dentro de los campos con normalidad.
		if ( handle && row ) {
			row.draggable = true;
		}
	} );

	repeater.addEventListener( 'dragstart', ( event: DragEvent ) => {
		dragged = ( event.target as HTMLElement ).closest< HTMLTableRowElement >(
			'tr.acf-row'
		);

		dragged?.classList.add( 'is-dragging' );
		event.dataTransfer?.setData( 'text/plain', '' );
	} );

	repeater.addEventListener( 'dragover', ( event: DragEvent ) => {
		if ( ! dragged ) {
			return;
		}

		event.preventDefault();

		const over = ( event.target as HTMLElement ).closest< HTMLTableRowElement >(
			'tr.acf-row:not(.acf-clone)'
		);

		if ( ! over || over === dragged ) {
			return;
		}

		const rows = realRows( repeater );
		const isAfter = rows.indexOf( over ) > rows.indexOf( dragged );

		over.parentElement?.insertBefore(
			dragged,
			isAfter ? over.nextSibling : over
		);
	} );

	repeater.addEventListener( 'dragend', () => {
		dragged?.classList.remove( 'is-dragging' );

		if ( dragged ) {
			dragged.draggable = false;
		}

		dragged = null;
		renumber( repeater );
	} );
}

/**
 * Prepara un repetidor.
 *
 * @param repeater Contenedor `.acf-repeater`.
 */
export function initRepeater( repeater: HTMLElement ): void {
	repeater.addEventListener( 'click', ( event: Event ) => {
		const action = ( event.target as HTMLElement ).closest< HTMLElement >(
			'[data-event]'
		);

		if ( ! action || action.classList.contains( 'disabled' ) ) {
			return;
		}

		const row = action.closest< HTMLTableRowElement >(
			'tr.acf-row:not(.acf-clone)'
		);

		switch ( action.dataset.event ) {
			case 'add-row':
				event.preventDefault();
				// Desde el botón de una fila se inserta justo después de ella;
				// desde el botón del pie, al final.
				addRow( repeater, row ? ( row.nextSibling as HTMLTableRowElement ) : null );
				break;

			case 'remove-row':
				event.preventDefault();

				if ( row ) {
					removeRow( repeater, row );
				}

				break;
		}
	} );

	enableDragging( repeater );
	applyLimits( repeater );
}
