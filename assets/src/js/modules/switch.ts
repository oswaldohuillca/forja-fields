/**
 * Interruptor del campo `true_false` con `ui`.
 */

/**
 * Refleja el estado de la casilla en el interruptor deslizante.
 *
 * La casilla real está oculta pero sigue siendo la que se envía; el
 * interruptor es sólo su representación visual.
 *
 * @param field Contenedor `.acf-true-false`.
 */
export function syncSwitch( field: HTMLElement ): void {
	const input = field.querySelector< HTMLInputElement >(
		'input.acf-switch-input'
	);
	const track = field.querySelector< HTMLElement >( '.acf-switch' );

	if ( ! input || ! track ) {
		return;
	}

	const paint = (): void => {
		track.classList.toggle( '-on', input.checked );
	};

	input.addEventListener( 'change', paint );
	input.addEventListener( 'focus', () => track.classList.add( '-focus' ) );
	input.addEventListener( 'blur', () => track.classList.remove( '-focus' ) );

	paint();
}
