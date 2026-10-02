/**
 * Reindexado de filas en repetidores y contenido flexible.
 *
 * Lo comparten los dos: los dos clonan plantillas y renumeran filas, y los dos
 * pueden llevar compuestos anidados dentro.
 */

/**
 * Cambia el índice de una fila en los `name` de sus controles.
 *
 * Se sustituye sólo el segmento que va justo detrás del nombre base del campo,
 * no la primera coincidencia del índice. Con un repetidor anidado
 * (`bloques[0][items][0][item]`), cambiar «la primera `[0]`» tocaba el índice
 * del nivel exterior en vez del propio.
 *
 * @param row  Fila cuyos controles se reindexan.
 * @param base Atributo `name` del campo, sin la fila.
 * @param from Índice anterior, o `acfcloneindex` si viene de la plantilla.
 * @param to   Índice nuevo.
 */
export function reindex( row: HTMLElement, base: string, from: string, to: string ): void {
	const old = `${ base }[${ from }]`;

	for ( const control of row.querySelectorAll< HTMLElement >( '[name]' ) ) {
		const name = control.getAttribute( 'name' );

		if ( name?.startsWith( old ) ) {
			control.setAttribute( 'name', `${ base }[${ to }]${ name.slice( old.length ) }` );
		}
	}
}
