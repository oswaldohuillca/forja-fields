import { defineConfig } from 'vitepress';

/**
 * Sitio de documentación de Forja.
 *
 * El contenido vive en Markdown junto al código, en `docs/`. Es deliberado:
 * documentación que se edita en el mismo commit que el cambio envejece mucho
 * menos que la que vive aparte.
 */
export default defineConfig( {
	title: 'Forja',
	description:
		'Campos personalizados para WordPress definidos por código, con la interfaz de ACF.',

	// Se publica en GitHub Pages bajo el nombre del repositorio.
	base: '/forja/',

	// Un enlace roto no debe pasar de la revisión: el build falla.
	ignoreDeadLinks: false,

	cleanUrls: true,
	lastUpdated: true,

	/*
	 * El español va en la raíz y el inglés cuelga de `/en/`.
	 *
	 * El proyecto se escribe en español —código, comentarios y estas páginas—,
	 * así que **el español es la versión canónica**: si las dos discrepan, manda
	 * la española. El inglés cubre la entrada (instalar, empezar y la referencia
	 * de campos), que es lo que decide si alguien adopta la librería; lo demás
	 * se traduce cuando haga falta.
	 */
	locales: {
		root: {
			label: 'Español',
			lang: 'es-ES',
			themeConfig: {
				nav: [
					{ text: 'Guía', link: '/guia/instalacion' },
					{ text: 'Campos', link: '/campos/' },
					{ text: 'Referencia', link: '/referencia/valores' },
					{ text: 'Desarrollo', link: '/desarrollo/' },
				],

				sidebar: [
					{
						text: 'Guía',
						items: [
							{ text: 'Instalación', link: '/guia/instalacion' },
							{ text: 'Primeros pasos', link: '/guia/primeros-pasos' },
							{ text: 'Dónde aparecen los campos', link: '/guia/destino' },
						],
					},
					{
						text: 'Campos',
						items: [
							{ text: 'Los campos', link: '/campos/' },
							{ text: 'Imágenes y archivos', link: '/campos/medios' },
							{ text: 'Editor enriquecido', link: '/campos/editor' },
							{ text: 'Fechas y horas', link: '/campos/fecha-y-hora' },
							{ text: 'Enlaces e incrustados', link: '/campos/enlaces' },
							{ text: 'Iconos', link: '/campos/iconos' },
							{ text: 'Relacionales', link: '/campos/relacionales' },
							{ text: 'Compuestos', link: '/campos/compuestos' },
							{ text: 'Reutilizar con clone', link: '/campos/clone' },
							{ text: 'Presentación', link: '/campos/presentacion' },
						],
					},
					{
						text: 'Referencia',
						items: [
							{ text: 'Qué devuelve cada campo', link: '/referencia/valores' },
							{ text: 'Lógica condicional', link: '/referencia/condicional' },
							{ text: 'Validación', link: '/referencia/validacion' },
							{ text: 'Términos, usuarios y opciones', link: '/referencia/contextos' },
							{ text: 'Añadir un tipo propio', link: '/referencia/extender' },
						],
					},
					{
						text: 'Desarrollo',
						items: [
							{ text: 'Trabajar en la librería', link: '/desarrollo/' },
							{ text: 'Arquitectura', link: '/desarrollo/arquitectura' },
							{ text: 'Publicar una versión', link: '/desarrollo/publicar' },
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

		en: {
			label: 'English',
			lang: 'en',
			description:
				'Code-defined custom fields for WordPress, with the ACF editing interface.',
			themeConfig: {
				nav: [
					{ text: 'Guide', link: '/en/guide/installation' },
					{ text: 'Fields', link: '/en/fields/' },
					{ text: 'Full docs (Spanish)', link: '/' },
				],
				sidebar: [
					{
						text: 'Guide',
						items: [
							{ text: 'Installation', link: '/en/guide/installation' },
							{ text: 'Getting started', link: '/en/guide/getting-started' },
						],
					},
					{
						text: 'Reference',
						items: [ { text: 'The fields', link: '/en/fields/' } ],
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
	},

	themeConfig: {
		// Buscador local, con un índice por idioma.
		search: {
			provider: 'local',
			options: {
				locales: {
					en: {
						translations: {
							button: { buttonText: 'Search', buttonAriaLabel: 'Search' },
						},
					},
				},
			},
		},
	},
} );
