/**
 * Lógica condicional entre campos.
 *
 * Un campo puede declarar reglas sobre el valor de otros. El servidor las emite
 * como JSON en `data-conditions`; aquí se evalúan y se muestra o se oculta.
 *
 * La estructura es una lista de grupos: basta con que **un grupo** se cumpla
 * entero para que el campo se vea. Dentro de un grupo, **todas** las reglas
 * deben cumplirse. Es la misma semántica que ACF.
 */

/** Una regla suelta. */
interface Rule {
	field: string;
	operator: string;
	value: string;
}

/** Contenedores que acotan la búsqueda de un campo por nombre. */
const SCOPES = '.acf-row, .layout, .acf-fields';

/**
 * Busca el campo al que apunta una regla.
 *
 * La búsqueda arranca en el contenedor más cercano y va subiendo. Importa
 * dentro de un repetidor: una regla escrita en un subcampo debe mirar a su
 * hermano de la misma fila, no al de la primera.
 *
 * @param from Campo que declara la regla.
 * @param name Nombre del campo observado.
 * @return Campo observado, o null si no aparece.
 */
function findTarget( from: HTMLElement, name: string ): HTMLElement | null {
	const selector = `.acf-field[data-name="${ CSS.escape( name ) }"]`;

	let scope: HTMLElement | null = from.parentElement?.closest( SCOPES ) ?? null;

	while ( scope ) {
		const found = scope.querySelector< HTMLElement >( selector );

		if ( found && found !== from ) {
			return found;
		}

		scope = scope.parentElement?.closest( SCOPES ) ?? null;
	}

	return document.querySelector< HTMLElement >( selector );
}

/**
 * Lee el valor actual de un campo.
 *
 * Devuelve siempre una lista, porque un checkbox o un desplegable múltiple
 * tienen varios valores a la vez y así las reglas se escriben igual para todos.
 *
 * @param field Campo del que leer.
 * @return Valores seleccionados.
 */
function readValue( field: HTMLElement ): string[] {
	const controls = Array.from(
		field.querySelectorAll< HTMLInputElement | HTMLSelectElement >(
			'input, select, textarea'
		)
	);

	const values: string[] = [];

	for ( const control of controls ) {
		if ( control instanceof HTMLSelectElement ) {
			values.push(
				...Array.from( control.selectedOptions ).map( ( o ) => o.value )
			);

			continue;
		}

		if ( control.type === 'checkbox' || control.type === 'radio' ) {
			if ( ( control as HTMLInputElement ).checked ) {
				values.push( control.value );
			}

			continue;
		}

		// Los ocultos que acompañan a radios y casillas llevan cadena vacía;
		// incluirlos falsearía las comprobaciones de «vacío».
		if ( control.type === 'hidden' && control.value === '' ) {
			continue;
		}

		values.push( control.value );
	}

	return values.filter( ( value ) => value !== '' );
}

/**
 * Evalúa una regla contra los valores actuales.
 *
 * @param values Valores del campo observado.
 * @param rule   Regla a evaluar.
 * @return Si la regla se cumple.
 */
function matches( values: string[], rule: Rule ): boolean {
	const first = values[ 0 ] ?? '';
	const target = rule.value;

	switch ( rule.operator ) {
		case '!=':
		case '!==':
			return ! values.includes( target );

		case 'contains':
		case '==contains':
			return values.some( ( value ) => value.includes( target ) );

		case '!contains':
		case '!=contains':
			return ! values.some( ( value ) => value.includes( target ) );

		case 'empty':
		case '==empty':
			return values.length === 0;

		case '!empty':
		case '!=empty':
			return values.length > 0;

		case '>':
			return Number.parseFloat( first ) > Number.parseFloat( target );

		case '<':
			return Number.parseFloat( first ) < Number.parseFloat( target );

		case '>=':
			return Number.parseFloat( first ) >= Number.parseFloat( target );

		case '<=':
			return Number.parseFloat( first ) <= Number.parseFloat( target );

		default:
			return values.includes( target );
	}
}

/**
 * Decide si un campo debe verse y lo aplica.
 *
 * @param field Campo con reglas.
 */
function evaluate( field: HTMLElement ): void {
	let groups: Rule[][];

	try {
		groups = JSON.parse( field.dataset.conditions ?? '[]' ) as Rule[][];
	} catch {
		return;
	}

	// Basta con que un grupo se cumpla entero.
	const visible = groups.some( ( group ) =>
		group.every( ( rule ) => {
			const target = findTarget( field, rule.field );

			// Una regla que apunta a un campo inexistente nunca se cumple; así
			// un nombre mal escrito se nota en lugar de pasar desapercibido.
			return target ? matches( readValue( target ), rule ) : false;
		} )
	);

	field.hidden = ! visible;
}

/**
 * Reevalúa todos los campos con reglas dentro de un contenedor.
 *
 * @param root Contenedor donde buscar.
 */
export function refreshConditions( root: ParentNode = document ): void {
	for ( const field of root.querySelectorAll< HTMLElement >(
		'.acf-field[data-conditions]'
	) ) {
		evaluate( field );
	}
}

/**
 * Arranca la lógica condicional.
 *
 * Se escucha en el documento para que las filas añadidas después —de un
 * repetidor o de un contenido flexible— queden cubiertas sin volver a
 * enganchar nada.
 */
export function initConditions(): void {
	for ( const event of [ 'change', 'input' ] ) {
		document.addEventListener( event, () => refreshConditions(), true );
	}

	refreshConditions();
}
