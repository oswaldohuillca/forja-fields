/**
 * Editor enriquecido.
 *
 * El servidor emite un `<textarea>` pelado y aquí se arranca TinyMCE con
 * `wp.editor.initialize()`, la misma API que usa el núcleo para los editores
 * que aparecen después de cargar la página. Es lo que hace que una fila recién
 * añadida a un repetidor tenga un editor funcionando.
 */

/*
 * Barras de herramientas.
 *
 * Hay que declararlas enteras. Los ajustes que WordPress expone en
 * `wp.editor.getDefaultSettings()` vienen de `print_default_editor_scripts()`,
 * pensados para el bloque clásico, y traen una barra mínima —negrita, cursiva,
 * listas y enlace— que no es la del editor de entradas.
 */

/** Las dos filas del editor de entradas de WordPress. */
const FULL_TOOLBAR_1 =
	'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,wp_more,spellchecker,wp_adv';

const FULL_TOOLBAR_2 =
	'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help';

/** Barra reducida, la misma que ofrece ACF. */
const BASIC_TOOLBAR =
	'bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,undo,redo';

/**
 * Barra que aporta el plugin de tablas.
 *
 * `table` abre el menú completo —insertar, filas, columnas, propiedades—; los
 * otros dos son los atajos que más se usan.
 */
const TABLE_TOOLBAR = 'table,tablerowprops,tablecellprops';

/**
 * Compone los ajustes de TinyMCE de un editor.
 *
 * @param textarea Área de texto con la configuración en sus atributos.
 * @return Ajustes para TinyMCE.
 */
function tinymceSettings( textarea: HTMLTextAreaElement ): Record< string, unknown > {
	const basic = textarea.dataset.toolbar === 'basic';
	const tables = textarea.dataset.table === '1';
	const plugin = textarea.dataset.tablePlugin;

	const first = basic ? BASIC_TOOLBAR : FULL_TOOLBAR_1;
	const second = basic ? '' : FULL_TOOLBAR_2;

	const settings: Record< string, unknown > = {
		wpautop: true,
		toolbar1: tables && basic ? `${ first },${ TABLE_TOOLBAR }` : first,
		toolbar2: tables && ! basic ? `${ second },${ TABLE_TOOLBAR }` : second,
	};

	// El plugin de tablas no lo trae WordPress; se sirve desde el paquete y se
	// registra aquí porque los ajustes por defecto no lo contemplan.
	if ( tables && plugin ) {
		settings.external_plugins = { table: plugin };
	}

	return settings;
}

/**
 * Arranca el editor sobre un área de texto.
 *
 * @param textarea Área de texto marcada con `forja-editor`.
 */
function initEditor( textarea: HTMLTextAreaElement ): void {
	const editor = window.wp?.editor;

	if ( ! editor || ! textarea.id || textarea.dataset.forjaReady === '1' ) {
		return;
	}

	textarea.dataset.forjaReady = '1';

	const useTinymce = textarea.dataset.tinymce !== '0';

	editor.initialize( textarea.id, {
		tinymce: useTinymce ? tinymceSettings( textarea ) : false,
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
