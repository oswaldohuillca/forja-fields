/**
 * Editor enriquecido.
 *
 * El servidor emite un `<textarea>` pelado y aquí se arranca TinyMCE con
 * `wp.editor.initialize()`, la misma API que usa el núcleo para los editores
 * que aparecen después de cargar la página. Es lo que hace que una fila recién
 * añadida a un repetidor tenga un editor funcionando.
 */

/** Botones de la barra reducida, los mismos que ofrece ACF. */
const BASIC_TOOLBAR =
	'bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,undo,redo';

/**
 * Arranca el editor sobre un área de texto.
 *
 * @param textarea Área de texto marcada con `forja-editor`.
 */
export function initEditor( textarea: HTMLTextAreaElement ): void {
	const editor = window.wp?.editor;

	if ( ! editor || ! textarea.id || textarea.dataset.forjaReady === '1' ) {
		return;
	}

	textarea.dataset.forjaReady = '1';

	const useTinymce = textarea.dataset.tinymce !== '0';
	const basic = textarea.dataset.toolbar === 'basic';

	editor.initialize( textarea.id, {
		tinymce: useTinymce
			? {
					wpautop: true,
					// TinyMCE necesita la lista explícita; sin ella la barra
					// reducida saldría igual que la completa.
					toolbar1: basic ? BASIC_TOOLBAR : undefined,
					toolbar2: basic ? '' : undefined,
			  }
			: false,
		quicktags: textarea.dataset.quicktags !== '0',
		mediaButtons: textarea.dataset.media !== '0',
	} );
}

/**
 * Arranca los editores que haya dentro de un contenedor.
 *
 * @param root Contenedor donde buscar.
 */
export function initEditors( root: ParentNode = document ): void {
	for ( const textarea of root.querySelectorAll< HTMLTextAreaElement >(
		'textarea.forja-editor'
	) ) {
		initEditor( textarea );
	}
}

/**
 * Prepara los editores de una fila recién clonada.
 *
 * Al clonar se descartan los identificadores, porque venían duplicados de la
 * plantilla. `wp.editor.initialize()` trabaja por identificador, así que hay
 * que darle uno nuevo, y se deriva del atributo `name`, que ya es único
 * después de reindexar la fila.
 *
 * @param row Fila recién insertada.
 */
export function prepareEditors( row: HTMLElement ): void {
	for ( const textarea of row.querySelectorAll< HTMLTextAreaElement >(
		'textarea.forja-editor'
	) ) {
		// Se descarta cualquier resto del editor de la plantilla.
		delete textarea.dataset.forjaReady;

		const name = textarea.getAttribute( 'name' ) ?? '';

		textarea.id = `forja-editor-${ name.replace( /[^a-z0-9]+/gi, '-' ) }`;

		initEditor( textarea );
	}
}
