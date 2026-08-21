/**
 * Desplegables con búsqueda remota: `post_object`, `page_link`, `user` y las
 * variantes de `taxonomy` que usan un `select`.
 *
 * El control es select2, que el paquete sirve como asset porque WordPress no lo
 * trae. Si por lo que sea no está cargado, el `<select>` sigue funcionando: no
 * tendrá búsqueda, pero muestra y guarda lo que ya estuviera elegido.
 */

/**
 * Deja el `<select>` como estaba antes de que select2 lo tocara.
 *
 * Hace falta al clonar una fila de repetidor: la plantilla puede traer un
 * select2 ya montado, y el clon se lleva su markup pero no su estado, así que
 * hay que retirar los restos antes de volver a arrancarlo.
 *
 * @param select Control a limpiar.
 */
function clearSelect2( select: HTMLSelectElement ): void {
	select.classList.remove( 'select2-hidden-accessible' );
	select.removeAttribute( 'data-select2-id' );
	select.removeAttribute( 'aria-hidden' );
	select.removeAttribute( 'tabindex' );

	for ( const option of select.options ) {
		option.removeAttribute( 'data-select2-id' );
	}

	const container = select.parentElement?.querySelector(
		':scope > .select2-container'
	);

	container?.remove();
}

/**
 * Conecta un desplegable relacional con select2.
 *
 * @param select Control `.forja-relational`.
 */
export function initRelational( select: HTMLElement ): void {
	if ( ! ( select instanceof HTMLSelectElement ) ) {
		return;
	}

	const jquery = window.jQuery;

	if ( ! jquery?.fn?.select2 ) {
		return;
	}

	clearSelect2( select );

	const field = select.dataset.field ?? '';
	const nonce = select.dataset.nonce ?? '';

	jquery( select ).select2?.( {
		width: '100%',
		placeholder: select.dataset.placeholder ?? '',
		allowClear: '1' === select.dataset.allow_null,
		// El desplegable se cuelga del propio campo y no del final del
		// documento, para que dentro de un metabox no quede por debajo.
		dropdownParent: jquery( select.parentElement ?? select ),
		ajax: {
			url: window.ajaxurl ?? '/wp-admin/admin-ajax.php',
			dataType: 'json',
			type: 'POST',
			delay: 250,
			data: ( params: { term?: string; page?: number } ) => ( {
				action: 'forja_search',
				field,
				nonce,
				s: params.term ?? '',
				paged: params.page ?? 1,
			} ),
			processResults: ( data: ForjaSearchResponse ) => ( {
				results: data.data?.results ?? [],
				pagination: { more: Boolean( data.data?.more ) },
			} ),
		},
	} );

	/*
	 * select2 avisa de los cambios con eventos de jQuery, que no despiertan a
	 * los escuchadores registrados con addEventListener. La lógica condicional
	 * es uno de ellos, así que se traduce a un evento nativo.
	 */
	jquery( select ).on?.(
		'select2:select select2:unselect select2:clear',
		() => {
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	);
}
