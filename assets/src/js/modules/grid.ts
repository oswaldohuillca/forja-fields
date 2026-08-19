/**
 * Modificadores de posición en la rejilla de campos.
 */

/**
 * Marca el primer campo de cada fila y de cada columna.
 *
 * ACF usa `-c0` para el primer campo de una fila y `-r0` para la primera fila,
 * de modo que el CSS pueda suprimir los bordes sobrantes de la rejilla.
 *
 * @param container Contenedor `.acf-fields`.
 */
export function markGridPositions( container: HTMLElement ): void {
	const fields = Array.from(
		container.querySelectorAll< HTMLElement >(
			':scope > .acf-field[data-width]'
		)
	);

	let rowWidth = 0;
	let rowIndex = 0;

	for ( const field of fields ) {
		const width = Number.parseFloat( field.dataset.width ?? '' ) || 0;

		field.classList.remove( '-c0', '-r0' );

		if ( rowWidth === 0 || rowWidth + width > 100 ) {
			rowWidth = 0;
			rowIndex += 1;
			field.classList.add( '-c0' );
		}

		if ( rowIndex === 1 ) {
			field.classList.add( '-r0' );
		}

		rowWidth += width;
	}
}
