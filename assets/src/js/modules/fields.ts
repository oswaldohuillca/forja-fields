/**
 * Arranque de los campos de un contenedor.
 *
 * Existe para que no haya dos listas de inicializaciones. La había: el punto de
 * entrada arrancaba dieciséis comportamientos al cargar la página, y las filas
 * que un repetidor o un contenido flexible añaden después arrancaban tres. El
 * resultado era que un `image` dentro de un repetidor funcionaba en la primera
 * fila —la que pinta el servidor— y no hacía nada en las que añadías tú.
 *
 * Ni los tests ni el comparador podían verlo: los dos miran el markup, y el
 * markup era correcto. Lo que faltaba era engancharle el comportamiento.
 *
 * Ahora la lista vive aquí y se usa en los tres sitios.
 */

import { markGridPositions } from './grid';
import { syncRange } from './range';
import { syncSwitch } from './switch';
import { trackSelected } from './choices';
import { initGallery } from './gallery';
import { initIconPicker } from './icon';
import { initLink } from './link';
import { initMedia } from './media';
import { initOembed } from './oembed';
import { initRepeater } from './repeater';
import { initColorPicker } from './color';
import { initFlexible } from './flexible';
import { initTabs } from './tabs';
import { initRelational } from './relational';
import { initRelationship } from './relationship';
import { initAccordion } from './accordion';
import { refreshConditions } from './conditions';
import { initEditors, prepareEditors } from './editor';

/**
 * Cada selector con la función que arranca el comportamiento del elemento.
 */
const BEHAVIOURS: Array< [ string, ( element: HTMLElement ) => void ] > = [
	[ '.acf-fields', markGridPositions ],
	[ '.acf-range-wrap', syncRange ],
	[ '.acf-true-false', syncSwitch ],
	[ '.acf-radio-list, .acf-checkbox-list, .acf-button-group', trackSelected ],
	[ '.acf-image-uploader, .acf-file-uploader', initMedia ],
	[ '.acf-color-picker', initColorPicker ],
	[ '.acf-gallery', initGallery ],
	[ '.acf-icon-picker', initIconPicker ],
	[ '.acf-link', initLink ],
	[ '.acf-oembed', initOembed ],
	[ '.acf-repeater', initRepeater ],
	[ '.acf-flexible-content', initFlexible ],
	[ 'select.forja-relational', initRelational ],
	[ '.acf-relationship', initRelationship ],
	[ '.acf-tab-wrap', initTabs ],
	[ '.acf-accordion', initAccordion ],
];

/**
 * Arranca todos los comportamientos dentro de un contenedor.
 *
 * No toca los editores ni la lógica condicional: esos dos se arrancan distinto
 * según el contenedor venga del servidor o de clonar una plantilla.
 *
 * @param root Contenedor donde buscar.
 */
function initBehaviours( root: ParentNode ): void {
	for ( const [ selector, apply ] of BEHAVIOURS ) {
		for ( const element of root.querySelectorAll< HTMLElement >(
			selector
		) ) {
			apply( element );
		}
	}
}

/**
 * Arranca los campos que ha pintado el servidor.
 *
 * @param root Contenedor donde buscar.
 */
export function initFields( root: ParentNode = document ): void {
	initBehaviours( root );
	initEditors( root );
}

/**
 * Arranca los campos de una fila recién clonada.
 *
 * Se diferencia de `initFields()` en dos cosas. Los editores pasan por
 * `prepareEditors()`, que les asigna un identificador nuevo porque el de la
 * plantilla venía duplicado y se descartó al clonar. Y las condiciones se
 * reevalúan, porque una fila recién insertada no ha pasado nunca por el
 * escuchador del documento.
 *
 * @param row Fila recién insertada.
 */
export function initClonedRow( row: HTMLElement ): void {
	initBehaviours( row );
	prepareEditors( row );
	refreshConditions( row );
}
