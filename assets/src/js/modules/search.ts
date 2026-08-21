/**
 * Cliente de la búsqueda remota de los campos relacionales.
 *
 * Lo comparten el desplegable con select2 y los dos paneles del `relationship`,
 * que consultan el mismo endpoint con los mismos parámetros.
 */

/** Lo que necesita una consulta para identificarse. */
export interface SearchContext {
	field: string;
	nonce: string;
}

/** Filtros opcionales que acota el `relationship`. */
export interface SearchFilters {
	s?: string;
	paged?: number;
	post_type?: string;
}

/** Lo que devuelve una búsqueda. */
export interface SearchPage {
	results: ForjaSearchResult[];
	more: boolean;
}

/**
 * Consulta el endpoint de búsqueda.
 *
 * @param context Campo y nonce con los que se identifica la consulta.
 * @param filters Texto buscado y página.
 * @param signal  Permite cancelar la consulta si llega otra.
 * @return Resultados, o null si se canceló o falló.
 */
export async function searchField(
	context: SearchContext,
	filters: SearchFilters,
	signal?: AbortSignal
): Promise< SearchPage | null > {
	const url = window.ajaxurl ?? '/wp-admin/admin-ajax.php';

	const body = new URLSearchParams( {
		action: 'forja_search',
		field: context.field,
		nonce: context.nonce,
		s: filters.s ?? '',
		paged: String( filters.paged ?? 1 ),
		post_type: filters.post_type ?? '',
	} );

	try {
		const response = await fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body,
			signal,
		} );

		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}

		const data = ( await response.json() ) as ForjaSearchResponse;

		return {
			results: data.data?.results ?? [],
			more: Boolean( data.data?.more ),
		};
	} catch {
		// Una consulta cancelada no es un fallo: la sustituye otra más reciente,
		// y vaciar la lista haría parpadear los resultados sin motivo.
		return signal?.aborted ? null : { results: [], more: false };
	}
}
