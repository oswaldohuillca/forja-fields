/**
 * Pestañas.
 *
 * A diferencia de ACF, aquí el servidor ya emite la barra de pestañas y marca
 * cada campo con `data-forja-tab`. El JavaScript sólo alterna la visibilidad,
 * sin reestructurar el DOM. Los campos siguen siendo hijos directos de
 * `.acf-fields`, que es de lo que dependen los bordes y el espaciado.
 */

/**
 * Activa una pestaña y oculta los campos del resto.
 *
 * @param wrap Barra de pestañas `.acf-tab-wrap`.
 * @param key  Clave de la pestaña a mostrar.
 */
function activate( wrap: HTMLElement, key: string ): void {
	const container = wrap.parentElement;

	if ( ! container ) {
		return;
	}

	for ( const item of wrap.querySelectorAll< HTMLElement >( 'li' ) ) {
		const button = item.querySelector< HTMLElement >( '.acf-tab-button' );
		const active = button?.dataset.key === key;

		item.classList.toggle( 'active', active );
		button?.setAttribute( 'aria-selected', active ? 'true' : 'false' );
	}

	const group = wrap.dataset.forjaTabGroup;

	for ( const field of container.querySelectorAll< HTMLElement >(
		':scope > .acf-field[data-forja-tab]'
	) ) {
		// Un mismo contenedor puede tener varias barras si el desarrollador
		// declara grupos separados; sólo se toca la que corresponde.
		if ( field.dataset.forjaTabGroup !== group ) {
			continue;
		}

		field.hidden = field.dataset.forjaTab !== key;
	}
}

/**
 * Prepara una barra de pestañas.
 *
 * @param wrap Barra de pestañas `.acf-tab-wrap`.
 */
export function initTabs( wrap: HTMLElement ): void {
	const buttons = Array.from(
		wrap.querySelectorAll< HTMLElement >( '.acf-tab-button' )
	);

	if ( buttons.length === 0 ) {
		return;
	}

	for ( const button of buttons ) {
		button.addEventListener( 'click', ( event: Event ) => {
			event.preventDefault();

			const key = button.dataset.key;

			if ( key ) {
				activate( wrap, key );
			}
		} );
	}

	const initial =
		buttons.find( ( button ) => button.dataset.selected === '1' ) ??
		buttons[ 0 ];

	if ( initial?.dataset.key ) {
		activate( wrap, initial.dataset.key );
	}
}
