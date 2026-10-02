/**
 * Cliente del intermediario de iconos (`Ajax\Icons`).
 *
 * El navegador no habla con Iconify: pedir cada miniatura como una imagen
 * aparte eran 96 peticiones por página de resultados, y unas cuantas búsquedas
 * bastaban para que Cloudflare bloqueara la IP. Aquí se hace una petición por
 * búsqueda y otra por lote de miniaturas, y el servidor trae y cachea el resto.
 *
 * Los SVG se guardan en memoria por nombre, así que volver a una página ya vista
 * o elegir un icono de los resultados no pide nada.
 */

/** Lo que necesita una petición para identificarse. */
export interface IconsContext {
	nonce: string;
	searchAction: string;
	svgAction: string;
}

/** Forma de las respuestas de admin-ajax. */
interface AjaxResponse< T > {
	success?: boolean;
	data?: T;
}

/** SVG ya traídos. Una cadena vacía es un icono que no existe. */
const cache = new Map< string, string >();

/** Nombres pendientes del próximo lote. */
let queued = new Set< string >();

/** Lote en curso, compartido por todo lo que se pida en el mismo turno. */
let flush: Promise< void > | null = null;

/**
 * Cuántos nombres caben en una petición.
 *
 * Coincide con el tope del servidor (`Icons::MAX_ICONS`). Una página son 96.
 */
const BATCH = 200;

/**
 * Lee del markup los datos con los que se identifica una petición.
 *
 * Las acciones viajan en atributos y no escritas aquí a mano: si cambiaran en
 * PHP, una copia en el JavaScript seguiría pidiendo las viejas sin aviso.
 *
 * @param element Elemento que lleva los atributos `data-`.
 * @return Contexto de las peticiones.
 */
export function iconsContext( element: HTMLElement ): IconsContext {
	return {
		nonce: element.dataset.nonce ?? '',
		searchAction: element.dataset.searchAction ?? '',
		svgAction: element.dataset.svgAction ?? '',
	};
}

/**
 * Llama a admin-ajax.
 *
 * @param params Parámetros, incluidos la acción y el nonce.
 * @param signal Permite cancelar la petición.
 * @return Datos de la respuesta.
 */
async function post< T >(
	params: Record< string, string >,
	signal?: AbortSignal
): Promise< T > {
	const response = await fetch( window.ajaxurl ?? '/wp-admin/admin-ajax.php', {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
		},
		body: new URLSearchParams( params ),
		signal,
	} );

	if ( ! response.ok ) {
		throw new Error( String( response.status ) );
	}

	const data = ( await response.json() ) as AjaxResponse< T >;

	if ( ! data.success || ! data.data ) {
		throw new Error( 'ajax' );
	}

	return data.data;
}

/**
 * Busca iconos.
 *
 * @param context     Nonce y acciones.
 * @param query       Texto buscado.
 * @param collections Colecciones a las que se limita, separadas por comas.
 * @param signal      Permite cancelar la búsqueda si llega otra.
 * @return Nombres de icono.
 */
export async function searchIcons(
	context: IconsContext,
	query: string,
	collections: string,
	signal: AbortSignal
): Promise< string[] > {
	const data = await post< { icons?: string[] } >(
		{
			action: context.searchAction,
			nonce: context.nonce,
			query,
			prefixes: collections,
		},
		signal
	);

	return data.icons ?? [];
}

/**
 * Manda el lote acumulado.
 *
 * @param context Nonce y acciones.
 */
async function send( context: IconsContext ): Promise< void > {
	const names = Array.from( queued );

	// Lo que se pida mientras esto viaja va al lote siguiente.
	queued = new Set();
	flush = null;

	for ( let i = 0; i < names.length; i += BATCH ) {
		const chunk = names.slice( i, i + BATCH );

		try {
			const data = await post< { icons?: Record< string, string > } >( {
				action: context.svgAction,
				nonce: context.nonce,
				icons: chunk.join( ',' ),
			} );

			const icons = data.icons ?? {};

			// Lo que no viene en una respuesta buena no existe: se recuerda para
			// no volver a pedirlo.
			for ( const name of chunk ) {
				cache.set( name, icons[ name ] ?? '' );
			}
		} catch {
			// Un fallo no se recuerda: la próxima vez se vuelve a intentar.
		}
	}
}

/**
 * Trae los SVG de una lista de iconos.
 *
 * Todo lo que se pida en el mismo turno viaja en una sola petición: al cargar
 * la pantalla, las vistas previas de todos los campos salen juntas.
 *
 * @param context Nonce y acciones.
 * @param names   Nombres en formato `coleccion:icono`.
 */
export async function loadIcons(
	context: IconsContext,
	names: string[]
): Promise< void > {
	const missing = names.filter( ( name ) => ! cache.has( name ) );

	if ( missing.length === 0 ) {
		return;
	}

	for ( const name of missing ) {
		queued.add( name );
	}

	flush ??= new Promise< void >( ( resolve ) =>
		window.setTimeout( resolve, 0 )
	).then( () => send( context ) );

	await flush;
}

/**
 * Dirección de imagen de un icono ya traído.
 *
 * Se pinta como `<img>` con el SVG en una dirección `data:` y no incrustado: una
 * imagen no ejecuta nada de lo que traiga el SVG, aunque el saneado del servidor
 * fallara.
 *
 * @param name Nombre en formato `coleccion:icono`.
 * @return Dirección `data:`, o null si no se ha traído o no existe.
 */
export function iconSrc( name: string ): string | null {
	const svg = cache.get( name );

	return svg ? `data:image/svg+xml;charset=utf-8,${ encodeURIComponent( svg ) }` : null;
}
