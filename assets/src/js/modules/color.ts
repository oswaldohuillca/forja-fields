/**
 * Selector de color.
 *
 * Arranca Iris, el selector del núcleo de WordPress, sobre el campo de texto y
 * refleja el resultado en el oculto que es el que se envía.
 *
 * Iris es un plugin de jQuery y no tiene alternativa nativa, así que aquí sí se
 * depende de jQuery. WordPress ya lo carga en el escritorio.
 */

/**
 * Prepara un selector de color.
 *
 * @param field Contenedor `.acf-color-picker`.
 */
export function initColorPicker( field: HTMLElement ): void {
	const jq = window.jQuery;
	const text = field.querySelector< HTMLInputElement >(
		'input.forja-color-picker'
	);
	const hidden = field.querySelector< HTMLInputElement >(
		'input[type="hidden"]'
	);

	if ( ! jq || ! text || ! hidden || text.dataset.forjaReady === '1' ) {
		return;
	}

	// Iris envuelve el campo en su propio markup; sin esta marca, volver a
	// arrancarlo sobre una fila clonada lo duplicaría.
	text.dataset.forjaReady = '1';

	const palette = text.dataset.palette;

	const options: Record< string, unknown > = {
		defaultColor: false,
		palettes: palette ? palette.split( ',' ).map( ( c ) => c.trim() ) : true,
		change: ( _event: unknown, ui: { color: { toString(): string } } ) => {
			hidden.value = ui.color.toString();
			hidden.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		},
		clear: () => {
			hidden.value = '';
			hidden.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		},
	};

	jq( text ).wpColorPicker?.( options );
}
