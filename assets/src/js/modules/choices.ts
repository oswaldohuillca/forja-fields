/**
 * Campos con opciones: radio, casillas y grupo de botones.
 */

/**
 * Marca la etiqueta seleccionada dentro de un grupo de opciones.
 *
 * El CSS de ACF resalta la opción activa con la clase `selected` en la
 * etiqueta, no con un selector sobre el input, así que hay que mantenerla.
 *
 * @param group Contenedor del grupo.
 */
export function trackSelected( group: HTMLElement ): void {
	const paint = (): void => {
		for ( const label of group.querySelectorAll< HTMLElement >( 'label' ) ) {
			const input = label.querySelector< HTMLInputElement >( 'input' );

			label.classList.toggle( 'selected', Boolean( input?.checked ) );
		}
	};

	group.addEventListener( 'change', paint );
	paint();
}
