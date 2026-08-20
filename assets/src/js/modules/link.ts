/**
 * Selector de enlace.
 *
 * Reutiliza `wpLink`, el modal del núcleo. Está pensado para trabajar sobre un
 * editor, así que hay que darle uno: se crea un `<textarea>` oculto de usar y
 * tirar y se leen los campos del modal al cerrarse.
 */

/** Enlace tal como se guarda. */
interface LinkValue {
	title: string;
	url: string;
	target: string;
}

declare global {
	interface Window {
		wpLink?: {
			open: (
				editorId: string,
				url?: string,
				text?: string,
				node?: unknown
			) => void;
			close: () => void;
		};
	}
}

/** Identificador del área de texto de usar y tirar. */
const PROXY_ID = 'forja-link-proxy';

/**
 * Lee el enlace de los ocultos del campo.
 *
 * @param field Contenedor `.acf-link`.
 * @return Enlace actual.
 */
function read( field: HTMLElement ): LinkValue {
	const value = ( key: string ): string =>
		field.querySelector< HTMLInputElement >( `.input-${ key }` )?.value ?? '';

	return { title: value( 'title' ), url: value( 'url' ), target: value( 'target' ) };
}

/**
 * Escribe el enlace en los ocultos y refresca la vista.
 *
 * @param field Contenedor `.acf-link`.
 * @param link  Enlace a guardar.
 */
function write( field: HTMLElement, link: LinkValue ): void {
	for ( const [ key, value ] of Object.entries( link ) ) {
		const input = field.querySelector< HTMLInputElement >( `.input-${ key }` );

		if ( input ) {
			input.value = value;
		}
	}

	const node = field.querySelector< HTMLAnchorElement >( '.link-node' );

	if ( node ) {
		node.textContent = link.title;
		node.href = link.url;
		node.target = link.target;
	}

	const title = field.querySelector< HTMLElement >( '.link-title' );
	const url = field.querySelector< HTMLAnchorElement >( '.link-url' );

	if ( title ) {
		title.textContent = link.title;
	}

	if ( url ) {
		url.textContent = link.url;
		url.href = link.url;
	}

	field.classList.toggle( '-value', link.url !== '' );
	field.classList.toggle( '-external', link.target === '_blank' );

	field
		.querySelector< HTMLInputElement >( '.input-url' )
		?.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

/**
 * Abre el modal del núcleo sobre un campo.
 *
 * @param field Contenedor `.acf-link`.
 */
function openModal( field: HTMLElement ): void {
	const jq = window.jQuery;
	const wpLink = window.wpLink;

	if ( ! jq || ! wpLink ) {
		return;
	}

	const current = read( field );

	const proxy = document.createElement( 'textarea' );
	proxy.id = PROXY_ID;
	proxy.style.display = 'none';
	document.body.appendChild( proxy );

	const $document = jq( document.documentElement as HTMLElement ) as unknown as {
		on: ( event: string, handler: () => void ) => void;
		off: ( event: string ) => void;
	};

	const onOpen = (): void => {
		// WordPress oculta el campo de texto si el enlace venía vacío; aquí
		// siempre interesa poder escribirlo.
		document.getElementById( 'wp-link-wrap' )?.classList.add( 'has-text-field' );

		const text = document.getElementById( 'wp-link-text' ) as HTMLInputElement | null;
		const url = document.getElementById( 'wp-link-url' ) as HTMLInputElement | null;
		const target = document.getElementById( 'wp-link-target' ) as HTMLInputElement | null;

		if ( text ) {
			text.value = current.title;
		}

		if ( url ) {
			url.value = current.url;
		}

		if ( target ) {
			target.checked = current.target === '_blank';
		}
	};

	const onClose = (): void => {
		const submit = document.getElementById( 'wp-link-submit' );

		/*
		 * `wpLink` no distingue entre aceptar y cancelar: emite el mismo evento
		 * al cerrarse. Se mira si el puntero o el foco están sobre el botón de
		 * aceptar, que es el mismo truco que usa ACF.
		 */
		const accepted = Boolean(
			submit && ( submit.matches( ':hover' ) || submit.matches( ':focus' ) )
		);

		if ( accepted ) {
			write( field, {
				title:
					( document.getElementById( 'wp-link-text' ) as HTMLInputElement | null )
						?.value ?? '',
				url:
					( document.getElementById( 'wp-link-url' ) as HTMLInputElement | null )
						?.value ?? '',
				target: ( document.getElementById( 'wp-link-target' ) as HTMLInputElement | null )
					?.checked
					? '_blank'
					: '',
			} );
		}

		$document.off( 'wplink-open' );
		$document.off( 'wplink-close' );
		proxy.remove();
	};

	$document.on( 'wplink-open', onOpen );
	$document.on( 'wplink-close', onClose );

	wpLink.open( PROXY_ID, current.url, current.title );
}

/**
 * Prepara un campo de enlace.
 *
 * @param field Contenedor `.acf-link`.
 */
export function initLink( field: HTMLElement ): void {
	field.addEventListener( 'click', ( event: Event ) => {
		const action = ( event.target as HTMLElement ).closest< HTMLElement >(
			'[data-name]'
		);

		switch ( action?.dataset.name ) {
			case 'add':
			case 'edit':
				event.preventDefault();
				openModal( field );
				break;

			case 'remove':
				event.preventDefault();
				write( field, { title: '', url: '', target: '' } );
				break;
		}
	} );
}
