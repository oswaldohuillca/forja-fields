/**
 * Contenido flexible.
 *
 * Misma mecánica que el repetidor —clonar una plantilla y reindexar— pero con
 * una plantilla por capa: al añadir hay que elegir cuál.
 */

const CLONE_INDEX = 'acfcloneindex';

/**
 * Filas reales, sin las plantillas.
 *
 * @param field Contenedor `.acf-flexible-content`.
 * @return Filas visibles.
 */
function rows( field: HTMLElement ): HTMLElement[] {
	return Array.from(
		field.querySelectorAll< HTMLElement >( ':scope > .values > .layout' )
	);
}

/**
 * Renumera las filas y reindexa los controles.
 *
 * @param field Contenedor `.acf-flexible-content`.
 */
function renumber( field: HTMLElement ): void {
	const list = rows( field );

	field.classList.toggle( '-empty', list.length === 0 );

	list.forEach( ( row, index ) => {
		const previous = row.dataset.id ?? '';

		row.dataset.id = String( index );

		const order = row.querySelector< HTMLElement >( '.acf-fc-layout-order' );

		if ( order ) {
			order.textContent = String( index + 1 );
		}

		if ( previous === String( index ) ) {
			return;
		}

		for ( const control of row.querySelectorAll< HTMLElement >( '[name]' ) ) {
			const name = control.getAttribute( 'name' );

			if ( name ) {
				control.setAttribute(
					'name',
					name.replace( `[${ previous }]`, `[${ index }]` )
				);
			}
		}
	} );

	applyLimits( field, list.length );
}

/**
 * Activa o desactiva las acciones según los límites declarados.
 *
 * @param field Contenedor `.acf-flexible-content`.
 * @param count Número de filas.
 */
function applyLimits( field: HTMLElement, count: number ): void {
	const min = Number.parseInt( field.dataset.min ?? '0', 10 ) || 0;
	const max = Number.parseInt( field.dataset.max ?? '0', 10 ) || 0;

	const add = field.querySelector< HTMLElement >(
		':scope > .acf-actions > [data-name="add-layout"]'
	);

	if ( add ) {
		add.classList.toggle( 'disabled', max > 0 && count >= max );
	}

	for ( const row of rows( field ) ) {
		row.querySelector< HTMLElement >(
			'[data-name="remove-layout"]'
		)?.classList.toggle( 'disabled', count <= min );
	}
}

/**
 * Inserta una fila de la capa indicada.
 *
 * @param field  Contenedor `.acf-flexible-content`.
 * @param layout Nombre de la capa.
 * @param before Fila delante de la cual insertar; al final si es null.
 */
function addLayout(
	field: HTMLElement,
	layout: string,
	before: HTMLElement | null
): void {
	const max = Number.parseInt( field.dataset.max ?? '0', 10 ) || 0;

	if ( max > 0 && rows( field ).length >= max ) {
		return;
	}

	const template = field.querySelector< HTMLElement >(
		`:scope > .clones > .layout[data-layout="${ CSS.escape( layout ) }"]`
	);
	const values = field.querySelector< HTMLElement >( ':scope > .values' );

	if ( ! template || ! values ) {
		return;
	}

	const row = template.cloneNode( true ) as HTMLElement;
	const index = rows( field ).length;

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

	// Los identificadores vienen duplicados de la plantilla.
	for ( const control of row.querySelectorAll< HTMLElement >( '[id]' ) ) {
		control.removeAttribute( 'id' );
	}

	values.insertBefore( row, before );
	renumber( field );
}

/**
 * Muestra u oculta el menú de capas.
 *
 * @param field Contenedor `.acf-flexible-content`.
 * @param open  Si debe quedar visible.
 */
function togglePopup( field: HTMLElement, open: boolean ): void {
	const popup = field.querySelector< HTMLElement >( '.acf-fc-popup' );

	if ( popup ) {
		popup.hidden = ! open;
	}
}

/**
 * Prepara un campo de contenido flexible.
 *
 * @param field Contenedor `.acf-flexible-content`.
 */
export function initFlexible( field: HTMLElement ): void {
	// Con una sola capa no hay menú: el botón la añade directamente.
	const onlyLayout = field.querySelector< HTMLElement >(
		':scope > .clones > .layout'
	)?.dataset.layout;

	const hasMenu = Boolean(
		field.querySelector< HTMLElement >( '.acf-fc-popup' )
	);

	field.addEventListener( 'click', ( event: Event ) => {
		const target = event.target as HTMLElement;
		const choice = target.closest< HTMLElement >( '.acf-fc-popup [data-layout]' );

		if ( choice ) {
			event.preventDefault();
			togglePopup( field, false );
			addLayout( field, choice.dataset.layout ?? '', null );

			return;
		}

		const action = target.closest< HTMLElement >( '[data-name]' );

		if ( ! action || action.classList.contains( 'disabled' ) ) {
			return;
		}

		const row = action.closest< HTMLElement >( '.values > .layout' );

		switch ( action.dataset.name ) {
			case 'add-layout':
				event.preventDefault();

				if ( hasMenu ) {
					togglePopup( field, true );
				} else if ( onlyLayout ) {
					addLayout(
						field,
						onlyLayout,
						row ? ( row.nextSibling as HTMLElement ) : null
					);
				}

				break;

			case 'remove-layout': {
				event.preventDefault();

				const min = Number.parseInt( field.dataset.min ?? '0', 10 ) || 0;

				if ( row && rows( field ).length > min ) {
					row.remove();
					renumber( field );
				}

				break;
			}

			case 'collapse-layout':
				event.preventDefault();
				row?.classList.toggle( '-collapsed' );
				break;
		}
	} );

	// Un clic fuera cierra el menú.
	document.addEventListener( 'click', ( event: Event ) => {
		if ( ! field.contains( event.target as Node ) ) {
			togglePopup( field, false );
		}
	} );

	applyLimits( field, rows( field ).length );
}
