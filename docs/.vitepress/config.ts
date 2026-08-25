import { defineConfig } from 'vitepress';

/**
 * Sitio de documentación de Forja.
 *
 * El contenido vive en Markdown junto al código, en `docs/`. Es deliberado:
 * documentación que se edita en el mismo commit que el cambio envejece mucho
 * menos que la que vive aparte.
 *
 * El inglés va en la raíz y el español en `/es/`. Ojo con la asimetría: el
 * proyecto se escribe en español —código, comentarios y la mayoría de estas
 * páginas—, así que **el español es la versión canónica**. Si las dos
 * discrepan, manda la española. El inglés está delante porque es lo que
 * encuentra quien busca una alternativa a ACF, pero cubre sólo la entrada.
 */
export default defineConfig( {
	title: 'Forja',

	base: '/forja/',

	// Un enlace roto no debe pasar de la revisión: el build falla.
	ignoreDeadLinks: false,

	cleanUrls: true,
	lastUpdated: true,

	locales: {
		root: {
			label: 'English',
			lang: 'en',
			description:
				'Code-defined custom fields for WordPress, with the ACF editing interface.',
			themeConfig: {
				nav: [
					{ text: 'Guide', link: '/guide/installation' },
					{ text: 'Fields', link: '/fields/' },
					{ text: 'Full docs (Spanish)', link: '/es/' },
				],
				sidebar: [
					{
						text: 'Guide',
						items: [
							{ text: 'Installation', link: '/guide/installation' },
							{ text: 'Getting started', link: '/guide/getting-started' },
						],
					},
					{
						text: 'Reference',
						items: [ { text: 'The fields', link: '/fields/' } ],
					},
					{
						// El grueso del manual está en español, y quien llega en
						// inglés tiene que verlo desde la propia barra lateral.
						text: 'In Spanish only',
						items: [
							{ text: 'Repeaters and flexible content', link: '/es/campos/compuestos' },
							{ text: 'Reusing fields with clone', link: '/es/campos/clone' },
							{ text: 'Relational fields', link: '/es/campos/relacionales' },
							{ text: 'Conditional logic', link: '/es/referencia/condicional' },
							{ text: 'Architecture notes', link: '/es/desarrollo/arquitectura' },
							{ text: 'Everything else', link: '/es/' },
						],
					},
				],
				outline: { level: [ 2, 3 ], label: 'On this page' },
				socialLinks: [
					{ icon: 'github', link: 'https://github.com/oswaldohuillca/forja' },
				],
				footer: {
					message: 'Released under the GPL-2.0-or-later licence.',
					copyright: 'Derived from Secure Custom Fields.',
				},
			},
		},

		es: {
			label: 'Español',
			lang: 'es-ES',
			description:
				'Campos personalizados para WordPress definidos por código, con la interfaz de ACF.',
			themeConfig: {
				nav: [
					{ text: 'Guía', link: '/es/guia/instalacion' },
					{ text: 'Campos', link: '/es/campos/' },
					{ text: 'Referencia', link: '/es/referencia/valores' },
					{ text: 'Desarrollo', link: '/es/desarrollo/' },
				],
				sidebar: [
					{
						text: 'Guía',
						items: [
							{ text: 'Instalación', link: '/es/guia/instalacion' },
							{ text: 'Primeros pasos', link: '/es/guia/primeros-pasos' },
							{ text: 'Dónde aparecen los campos', link: '/es/guia/destino' },
						],
					},
					{
						text: 'Campos',
						items: [
							{ text: 'Los campos', link: '/es/campos/' },
							{ text: 'Imágenes y archivos', link: '/es/campos/medios' },
							{ text: 'Editor enriquecido', link: '/es/campos/editor' },
							{ text: 'Fechas y horas', link: '/es/campos/fecha-y-hora' },
							{ text: 'Enlaces e incrustados', link: '/es/campos/enlaces' },
							{ text: 'Iconos', link: '/es/campos/iconos' },
							{ text: 'Relacionales', link: '/es/campos/relacionales' },
							{ text: 'Compuestos', link: '/es/campos/compuestos' },
							{ text: 'Reutilizar con clone', link: '/es/campos/clone' },
							{ text: 'Presentación', link: '/es/campos/presentacion' },
						],
					},
					{
						text: 'Referencia',
						items: [
							{ text: 'Qué devuelve cada campo', link: '/es/referencia/valores' },
							{ text: 'Lógica condicional', link: '/es/referencia/condicional' },
							{ text: 'Validación', link: '/es/referencia/validacion' },
							{ text: 'Términos, usuarios y opciones', link: '/es/referencia/contextos' },
							{ text: 'Añadir un tipo propio', link: '/es/referencia/extender' },
						],
					},
					{
						text: 'Desarrollo',
						items: [
							{ text: 'Trabajar en la librería', link: '/es/desarrollo/' },
							{ text: 'Arquitectura', link: '/es/desarrollo/arquitectura' },
							{ text: 'Publicar una versión', link: '/es/desarrollo/publicar' },
						],
					},
				],
				outline: { level: [ 2, 3 ], label: 'En esta página' },
				docFooter: { prev: 'Anterior', next: 'Siguiente' },
				darkModeSwitchLabel: 'Apariencia',
				returnToTopLabel: 'Volver arriba',
				lastUpdatedText: 'Actualizado el',
				socialLinks: [
					{ icon: 'github', link: 'https://github.com/oswaldohuillca/forja' },
				],
				footer: {
					message: 'Publicado con licencia GPL-2.0 o posterior.',
					copyright: 'Obra derivada de Secure Custom Fields.',
				},
			},
		},
	},

	themeConfig: {
		/*
		 * El selector de idioma lleva a la portada del otro, no a la misma
		 * página traducida.
		 *
		 * Con el enrutado por idioma activado, VitePress intercambia el prefijo
		 * y **conserva el resto de la ruta**. Aquí los segmentos están
		 * traducidos —`campos` frente a `fields`— y además 18 páginas sólo
		 * existen en español, así que ese mapeo daba 404 en casi todas las
		 * combinaciones: de siete rutas probadas, cinco.
		 *
		 * Se pierde el «esta misma página en el otro idioma», que con rutas
		 * distintas nunca llegó a funcionar, y a cambio no queda ninguna
		 * combinación rota.
		 */
		i18nRouting: false,

		// Buscador local, con un índice por idioma.
		search: {
			provider: 'local',
			options: {
				locales: {
					es: {
						translations: {
							button: { buttonText: 'Buscar', buttonAriaLabel: 'Buscar' },
							modal: {
								noResultsText: 'Sin resultados para',
								footer: { selectText: 'seleccionar', navigateText: 'navegar' },
							},
						},
					},
				},
			},
		},
	},
} );
