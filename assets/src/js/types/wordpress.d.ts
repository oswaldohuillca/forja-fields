/**
 * Superficie de las APIs de WordPress que usa Forja.
 *
 * Vive en un único archivo a propósito: si cada módulo declarase su parte de
 * `window.wp`, TypeScript encontraría declaraciones incompatibles del mismo
 * global.
 *
 * Todo va dentro de `declare global` porque el `export {}` del final convierte
 * el archivo en un módulo, y lo que quedara fuera no sería visible.
 */

declare global {
	/** Adjunto tal como lo devuelve el modal de medios. */
	interface WpAttachment {
		id: number;
		url?: string;
		alt?: string;
		title?: string;
		filename?: string;
		filesizeHumanReadable?: string;
		icon?: string;
		sizes?: Record< string, { url: string } >;
	}

	/** Ventana del selector de medios. */
	interface WpMediaFrame {
		on( event: string, handler: () => void ): void;
		open(): void;
		state(): {
			get( key: string ): {
				first(): { toJSON(): WpAttachment } | undefined;
			};
		};
	}

	/** Ajustes con los que se arranca un editor. */
	interface WpEditorSettings {
		tinymce: boolean | Record< string, unknown >;
		quicktags: boolean;
		mediaButtons: boolean;
	}

	interface Window {
		wp?: {
			media?: ( args: Record< string, unknown > ) => WpMediaFrame;
			editor?: {
				initialize: ( id: string, settings: WpEditorSettings ) => void;
				remove: ( id: string ) => void;
				getDefaultSettings?: () => {
					tinymce?: Record< string, unknown >;
				};
			};
		};

		jQuery?: ( ( selector: HTMLElement ) => {
			wpColorPicker?: ( options: Record< string, unknown > ) => void;
			select2?: ( options: Record< string, unknown > ) => void;
			on?: (
				events: string,
				handler: ( event: unknown ) => void
			) => void;
		} ) & { fn?: Record< string, unknown > };

		/** Endpoint de admin-ajax; lo define WordPress en el escritorio. */
		ajaxurl?: string;
	}

	/** Resultado de una búsqueda remota. */
	interface ForjaSearchResult {
		id: string;
		text: string;
	}

	/** Respuesta del endpoint de búsqueda. */
	interface ForjaSearchResponse {
		success?: boolean;
		data?: {
			results?: ForjaSearchResult[];
			more?: boolean;
		};
	}
}

export {};
