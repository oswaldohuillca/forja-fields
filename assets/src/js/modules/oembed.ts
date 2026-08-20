/**
 * Contenido incrustado.
 *
 * La vista previa se pide al endpoint `oembed/1.0/proxy` de la API REST del
 * núcleo, que ya resuelve proveedores y caché.
 */

declare global {
	interface Window {
		wpApiSettings?: {
			root: string;
			nonce: string;
		};
	}
}

/**
 * Pide la vista previa de una dirección y la pinta.
 *
 * @param field Contenedor `.acf-oembed`.
 * @param url   Dirección a incrustar.
 */
async function preview( field: HTMLElement, url: string ): Promise< void > {
	const preview = field.querySelector< HTMLElement >( '.acf-oembed-preview' );
	const api = window.wpApiSettings;

	if ( ! preview ) {
		return;
	}

	if ( url === '' ) {
		preview.innerHTML = '';
		field.classList.remove( '-value' );

		return;
	}

	if ( ! api ) {
		return;
	}

	field.classList.add( '-loading' );

	try {
		const endpoint = new URL( 'oembed/1.0/proxy', api.root );
		endpoint.searchParams.set( 'url', url );
		endpoint.searchParams.set( 'maxwidth', field.dataset.width ?? '640' );

		const response = await fetch( endpoint, {
			headers: { 'X-WP-Nonce': api.nonce },
			credentials: 'same-origin',
		} );

		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}

		const data = ( await response.json() ) as { html?: string };

		// El HTML viene del proveedor a través del núcleo, que ya lo filtra.
		preview.innerHTML = data.html ?? '';
		field.classList.add( '-value' );
	} catch {
		preview.textContent = '';
		field.classList.remove( '-value' );
	} finally {
		field.classList.remove( '-loading' );
	}
}

/**
 * Prepara un campo de contenido incrustado.
 *
 * @param field Contenedor `.acf-oembed`.
 */
export function initOembed( field: HTMLElement ): void {
	const input = field.querySelector< HTMLInputElement >( '.acf-oembed-search' );

	if ( ! input ) {
		return;
	}

	// Se refresca al perder el foco, no mientras se escribe: cada pulsación
	// sería una petición al proveedor.
	input.addEventListener( 'change', () => {
		void preview( field, input.value.trim() );
	} );

	field.addEventListener( 'click', ( event: Event ) => {
		const action = ( event.target as HTMLElement ).closest< HTMLElement >(
			'[data-name="remove"]'
		);

		if ( action ) {
			event.preventDefault();
			input.value = '';
			void preview( field, '' );
		}
	} );
}
