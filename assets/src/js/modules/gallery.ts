/**
 * Galería de imágenes.
 *
 * Selección múltiple con `wp.media` y reordenado por arrastre. El orden de las
 * miniaturas es el orden del array que se guarda, así que basta con mover los
 * nodos: los ocultos viajan dentro de cada uno.
 */

import { refreshConditions } from './conditions';

/**
 * Miniaturas reales, sin la plantilla.
 *
 * @param field Contenedor `.acf-gallery`.
 * @return Miniaturas visibles.
 */
function items( field: HTMLElement ): HTMLElement[] {
	return Array.from(
		field.querySelectorAll< HTMLElement >(
			'.acf-gallery-attachments > .acf-gallery-attachment'
		)
	);
}

/**
 * Activa o desactiva el botón de añadir según el máximo declarado.
 *
 * @param field Contenedor `.acf-gallery`.
 */
function applyLimits( field: HTMLElement ): void {
	const max = Number.parseInt( field.dataset.max ?? '0', 10 ) || 0;
	const button = field.querySelector< HTMLElement >( '.acf-gallery-add' );

	if ( button ) {
		button.classList.toggle(
			'disabled',
			max > 0 && items( field ).length >= max
		);
	}

	field.classList.toggle( '-empty', items( field ).length === 0 );
}

/**
 * Añade una imagen a la rejilla.
 *
 * @param field      Contenedor `.acf-gallery`.
 * @param attachment Adjunto seleccionado.
 */
function add( field: HTMLElement, attachment: WpAttachment ): void {
	const grid = field.querySelector< HTMLElement >( '.acf-gallery-attachments' );
	const template = field.querySelector< HTMLTemplateElement >(
		'.acf-gallery-template'
	);

	if ( ! grid || ! template ) {
		return;
	}

	// Una imagen repetida no aporta nada; el servidor también la descartaría.
	if ( items( field ).some( ( item ) => item.dataset.id === String( attachment.id ) ) ) {
		return;
	}

	const node = template.content.firstElementChild?.cloneNode(
		true
	) as HTMLElement | undefined;

	if ( ! node ) {
		return;
	}

	node.dataset.id = String( attachment.id );

	const input = node.querySelector< HTMLInputElement >( 'input' );

	if ( input ) {
		input.value = String( attachment.id );
	}

	const image = node.querySelector< HTMLImageElement >( 'img' );

	if ( image ) {
		const size = field.dataset.preview_size ?? 'thumbnail';

		image.src = attachment.sizes?.[ size ]?.url ?? attachment.url ?? '';
		image.alt = attachment.alt ?? '';
	}

	const filename = node.querySelector< HTMLElement >( '.filename' );

	if ( filename ) {
		filename.textContent = attachment.filename ?? '';
	}

	grid.appendChild( node );
}

/**
 * Abre el modal de medios en modo selección múltiple.
 *
 * @param field Contenedor `.acf-gallery`.
 */
function openPicker( field: HTMLElement ): void {
	const media = window.wp?.media;

	if ( ! media ) {
		return;
	}

	const mimeTypes = field.dataset.mime_types ?? '';

	const frame = media( {
		multiple: 'add',
		library: {
			type: mimeTypes ? mimeTypes.split( ',' ) : 'image',
		},
	} );

	frame.on( 'select', () => {
		const selection = frame.state().get( 'selection' ) as unknown as {
			map: ( fn: ( model: { toJSON(): WpAttachment } ) => WpAttachment ) => WpAttachment[];
		};

		const max = Number.parseInt( field.dataset.max ?? '0', 10 ) || 0;

		for ( const attachment of selection.map( ( model ) => model.toJSON() ) ) {
			if ( max > 0 && items( field ).length >= max ) {
				break;
			}

			add( field, attachment );
		}

		applyLimits( field );
		refreshConditions( field );
	} );

	frame.open();
}

/**
 * Habilita el reordenado por arrastre.
 *
 * @param field Contenedor `.acf-gallery`.
 */
function enableDragging( field: HTMLElement ): void {
	let dragged: HTMLElement | null = null;

	field.addEventListener( 'dragstart', ( event: DragEvent ) => {
		dragged = ( event.target as HTMLElement ).closest< HTMLElement >(
			'.acf-gallery-attachment'
		);

		dragged?.classList.add( 'is-dragging' );
		event.dataTransfer?.setData( 'text/plain', '' );
	} );

	field.addEventListener( 'dragover', ( event: DragEvent ) => {
		if ( ! dragged ) {
			return;
		}

		event.preventDefault();

		const over = ( event.target as HTMLElement ).closest< HTMLElement >(
			'.acf-gallery-attachment'
		);

		if ( ! over || over === dragged ) {
			return;
		}

		const list = items( field );
		const isAfter = list.indexOf( over ) > list.indexOf( dragged );

		over.parentElement?.insertBefore(
			dragged,
			isAfter ? over.nextSibling : over
		);
	} );

	field.addEventListener( 'dragend', () => {
		dragged?.classList.remove( 'is-dragging' );
		dragged = null;
	} );
}

/**
 * Prepara una galería.
 *
 * @param field Contenedor `.acf-gallery`.
 */
export function initGallery( field: HTMLElement ): void {
	field.addEventListener( 'click', ( event: Event ) => {
		const target = event.target as HTMLElement;

		if ( target.closest( '.acf-gallery-add' ) ) {
			event.preventDefault();

			if ( ! target.closest( '.disabled' ) ) {
				openPicker( field );
			}

			return;
		}

		const remove = target.closest< HTMLElement >( '[data-name="remove"]' );

		if ( remove ) {
			event.preventDefault();
			remove.closest( '.acf-gallery-attachment' )?.remove();
			applyLimits( field );
		}
	} );

	enableDragging( field );
	applyLimits( field );
}
