/**
 * Punto de entrada del JavaScript de administración.
 *
 * Sólo importa los módulos y los engancha a los elementos correspondientes.
 * Cada comportamiento vive en su propio archivo bajo `modules/`.
 */

import '../css/forja-input.css';

import { markGridPositions } from './modules/grid';
import { syncRange } from './modules/range';
import { syncSwitch } from './modules/switch';
import { trackSelected } from './modules/choices';
import { initLink } from './modules/link';
import { initMedia } from './modules/media';
import { initOembed } from './modules/oembed';
import { initRepeater } from './modules/repeater';
import { initColorPicker } from './modules/color';
import { initConditions } from './modules/conditions';
import { initEditors } from './modules/editor';
import { initFlexible } from './modules/flexible';
import { initTabs } from './modules/tabs';
import { initAccordion } from './modules/accordion';

/**
 * Aplica una función a todos los elementos que casen con un selector.
 *
 * @param selector Selector CSS.
 * @param apply    Función a aplicar sobre cada elemento.
 */
function forEach(
	selector: string,
	apply: ( element: HTMLElement ) => void
): void {
	for ( const element of document.querySelectorAll< HTMLElement >(
		selector
	) ) {
		apply( element );
	}
}

document.addEventListener( 'DOMContentLoaded', () => {
	forEach( '.acf-fields', markGridPositions );
	forEach( '.acf-range-wrap', syncRange );
	forEach( '.acf-true-false', syncSwitch );
	forEach(
		'.acf-radio-list, .acf-checkbox-list, .acf-button-group',
		trackSelected
	);
	forEach( '.acf-image-uploader, .acf-file-uploader', initMedia );
	forEach( '.acf-color-picker', initColorPicker );
	forEach( '.acf-link', initLink );
	forEach( '.acf-oembed', initOembed );
	forEach( '.acf-repeater', initRepeater );
	forEach( '.acf-flexible-content', initFlexible );
	forEach( '.acf-tab-wrap', initTabs );
	forEach( '.acf-accordion', initAccordion );

	initEditors();
	initConditions();
} );
