/**
 * Campo de rango deslizante.
 */

/**
 * Mantiene sincronizados el deslizador y su campo numérico auxiliar.
 *
 * Sólo el deslizador lleva `name`, así que es el que se envía; el auxiliar
 * existe para leer y teclear el valor exacto.
 *
 * @param wrap Contenedor `.acf-range-wrap`.
 */
export function syncRange( wrap: HTMLElement ): void {
	const slider = wrap.querySelector< HTMLInputElement >(
		'input[type="range"]'
	);
	const alt = wrap.querySelector< HTMLInputElement >( 'input.acf-range-alt' );

	if ( ! slider || ! alt ) {
		return;
	}

	slider.addEventListener( 'input', () => {
		alt.value = slider.value;
	} );

	alt.addEventListener( 'input', () => {
		slider.value = alt.value;
	} );

	// Al perder el foco, el auxiliar se corrige al valor que el deslizador
	// haya podido acotar; así nunca se queda mostrando algo fuera de rango.
	alt.addEventListener( 'change', () => {
		slider.value = alt.value;
		alt.value = slider.value;
	} );
}
