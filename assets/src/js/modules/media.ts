/**
 * Campos de imagen y archivo.
 *
 * Usan el modal de medios del núcleo, `wp.media`. El campo guarda sólo el
 * identificador del adjunto; todo lo demás —miniatura, nombre, tamaño— es
 * presentación que se repinta al seleccionar.
 */

/**
 * Abre el modal de medios y devuelve el adjunto elegido.
 *
 * @param field  Contenedor del campo.
 * @param onPick Se invoca con el adjunto seleccionado.
 */
function openPicker(
	field: HTMLElement,
	onPick: ( attachment: WpAttachment ) => void
): void {
	const media = window.wp?.media;

	if ( ! media ) {
		return;
	}

	const mimeTypes = field.dataset.mime_types ?? '';

	const frame = media( {
		multiple: false,
		library: mimeTypes ? { type: mimeTypes.split( ',' ) } : {},
	} );

	frame.on( 'select', () => {
		const selected = frame.state().get( 'selection' ).first();

		if ( selected ) {
			onPick( selected.toJSON() );
		}
	} );

	frame.open();
}

/**
 * Devuelve la URL de la vista previa en el tamaño pedido.
 *
 * El modal no siempre entrega todos los tamaños; si falta el solicitado se
 * cae a la URL original.
 *
 * @param attachment Adjunto seleccionado.
 * @param size       Nombre del tamaño.
 * @return URL de la imagen.
 */
function previewUrl( attachment: WpAttachment, size: string ): string {
	return attachment.sizes?.[ size ]?.url ?? attachment.url ?? '';
}

/**
 * Conecta un campo de medios con el modal.
 *
 * @param field Contenedor `.acf-image-uploader` o `.acf-file-uploader`.
 */
export function initMedia( field: HTMLElement ): void {
	/*
	 * El campo de archivo marca su input oculto con `data-name="id"` y el de
	 * imagen no, porque el markup reproduce el de ACF y allí tampoco lo lleva.
	 * Exigir el atributo dejaba a las imágenes sin ningún escuchador: el botón
	 * «Añadir imagen» no hacía nada, en ninguna fila ni pantalla.
	 *
	 * Se busca primero el marcado y se cae al primer oculto del contenedor, que
	 * es lo que hace ACF.
	 */
	const input =
		field.querySelector< HTMLInputElement >(
			'input[type="hidden"][data-name="id"]'
		) ??
		field.querySelector< HTMLInputElement >( 'input[type="hidden"]' );

	if ( ! input ) {
		return;
	}

	const isImage = field.classList.contains( 'acf-image-uploader' );

	const paint = ( attachment: WpAttachment | null ): void => {
		if ( ! attachment ) {
			input.value = '';
			field.classList.remove( 'has-value' );

			return;
		}

		input.value = String( attachment.id );
		field.classList.add( 'has-value' );

		if ( isImage ) {
			const img = field.querySelector< HTMLImageElement >(
				'img[data-name="image"]'
			);

			if ( img ) {
				img.src = previewUrl(
					attachment,
					field.dataset.preview_size ?? 'medium'
				);
				img.alt = attachment.alt ?? '';
			}

			return;
		}

		const set = ( name: string, text: string ): void => {
			const node = field.querySelector< HTMLElement >(
				`[data-name="${ name }"]`
			);

			if ( node ) {
				node.textContent = text;
			}
		};

		set( 'title', attachment.title ?? '' );
		set( 'filename', attachment.filename ?? '' );
		set( 'filesize', attachment.filesizeHumanReadable ?? '' );

		const icon = field.querySelector< HTMLImageElement >(
			'img[data-name="icon"]'
		);

		if ( icon && attachment.icon ) {
			icon.src = attachment.icon;
		}

		const link = field.querySelector< HTMLAnchorElement >(
			'a[data-name="filename"]'
		);

		if ( link && attachment.url ) {
			link.href = attachment.url;
		}
	};

	field.addEventListener( 'click', ( event: Event ) => {
		const target = ( event.target as HTMLElement ).closest< HTMLElement >(
			'[data-name]'
		);

		switch ( target?.dataset.name ) {
			case 'add':
			case 'edit':
				event.preventDefault();
				openPicker( field, paint );
				break;

			case 'remove':
				event.preventDefault();
				paint( null );
				break;
		}
	} );
}
