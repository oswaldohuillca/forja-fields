/**
 * Cliente de la búsqueda remota de los campos relacionales.
 *
 * Lo comparten el desplegable con select2 y los dos paneles del `relationship`,
 * que consultan el mismo endpoint con los mismos parámetros.
 */

/** Lo que necesita una consulta para identificarse. */
export interface SearchContext {
	action: string;
	field: string;
	nonce: string;
}

/**
 * Lee del markup los datos con los que se identifica una consulta.
 *
 * La acción viaja en un atributo y no escrita aquí a mano: si cambiara en PHP,
 * una copia en el JavaScript seguiría pidiendo la vieja y las búsquedas
 * dejarían de responder sin ningún aviso.
 *
 * @param element Elemento que lleva los atributos `data-`.
 * @return Contexto de búsqueda.
 */
export function searchContext( element: HTMLElement ): SearchContext {
	return {
		action: element.dataset.searchAction ?? '',
		field: element.dataset.field ?? '',
		nonce: element.dataset.nonce ?? '',
	};
}

/** Filtros opcionales que acota el `relationship`. */
interface SearchFilters {
	s?: string;
	paged?: number;
	post_type?: string;
}

/** Lo que devuelve una búsqueda. */
interface SearchPage {
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
		action: context.action,
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
