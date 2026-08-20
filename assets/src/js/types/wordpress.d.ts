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
			};
		};

		jQuery?: ( ( selector: HTMLElement ) => {
			wpColorPicker?: ( options: Record< string, unknown > ) => void;
		} ) & { fn?: Record< string, unknown > };
	}
}

export {};
