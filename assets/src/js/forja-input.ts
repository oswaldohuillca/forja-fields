/**
 * Comportamiento de los campos en la pantalla de edición.
 *
 * De momento sólo calcula los modificadores de posición que ACF asigna a los
 * campos con ancho porcentual. La lógica condicional llegará en su fase.
 */

import '../css/forja-input.css';

/**
 * Marca el primer campo de cada fila y de cada columna.
 *
 * ACF usa `-c0` para el primer campo de una fila y `-r0` para la primera fila,
 * de modo que el CSS pueda suprimir los bordes sobrantes de la rejilla.
 *
 * @param container Contenedor `.acf-fields`.
 */
function markGridPositions( container: HTMLElement ): void {
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

/**
 * Mantiene sincronizados el deslizador y su campo numérico auxiliar.
 *
 * Sólo el deslizador lleva `name`, así que es el que se envía; el auxiliar
 * existe para leer y teclear el valor exacto.
 *
 * @param wrap Contenedor `.acf-range-wrap`.
 */
function syncRange( wrap: HTMLElement ): void {
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

/**
 * Refleja el estado de la casilla en el interruptor deslizante.
 *
 * La casilla real está oculta pero sigue siendo la que se envía; el
 * interruptor es sólo su representación visual.
 *
 * @param field Contenedor `.acf-true-false`.
 */
function syncSwitch( field: HTMLElement ): void {
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

/**
 * Marca la etiqueta seleccionada dentro de un grupo de opciones.
 *
 * El CSS de ACF resalta la opción activa con la clase `selected` en la
 * etiqueta, no con un selector sobre el input, así que hay que mantenerla.
 *
 * @param group Contenedor del grupo.
 */
function trackSelected( group: HTMLElement ): void {
	const paint = (): void => {
		for ( const label of group.querySelectorAll< HTMLElement >( 'label' ) ) {
			const input = label.querySelector< HTMLInputElement >( 'input' );

			label.classList.toggle( 'selected', Boolean( input?.checked ) );
		}
	};

	group.addEventListener( 'change', paint );
	paint();
}

document.addEventListener( 'DOMContentLoaded', () => {
	for ( const container of document.querySelectorAll< HTMLElement >(
		'.acf-fields'
	) ) {
		markGridPositions( container );
	}

	for ( const wrap of document.querySelectorAll< HTMLElement >(
		'.acf-range-wrap'
	) ) {
		syncRange( wrap );
	}

	for ( const field of document.querySelectorAll< HTMLElement >(
		'.acf-true-false'
	) ) {
		syncSwitch( field );
	}

	for ( const group of document.querySelectorAll< HTMLElement >(
		'.acf-radio-list, .acf-checkbox-list, .acf-button-group'
	) ) {
		trackSelected( group );
	}
} );
