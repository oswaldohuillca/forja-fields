/**
 * Punto de entrada del JavaScript de administración.
 *
 * La lista de comportamientos vive en `modules/fields.ts`, no aquí, porque la
 * comparten la carga inicial y las filas que se añaden después. Tenerla en dos
 * sitios ya provocó que un campo de imagen funcionara en la primera fila de un
 * repetidor y no en las siguientes.
 */

import '../css/forja-input.css';

import { initFields } from './modules/fields';
import { initConditions } from './modules/conditions';

document.addEventListener( 'DOMContentLoaded', () => {
	initFields( document );

	// Escucha en el documento, así que se engancha una sola vez y cubre también
	// lo que se añada más tarde.
	initConditions();
} );
