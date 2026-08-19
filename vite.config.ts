import { defineConfig } from 'vite';
import { resolve } from 'node:path';

/**
 * Build de assets para el admin de WordPress.
 *
 * Dos decisiones a tener presentes:
 *
 * 1. Formato IIFE. Los scripts se encolan con `wp_enqueue_script()` como
 *    scripts clásicos, no como módulos ES. Rollup no admite IIFE con varias
 *    entradas (eso sería un build con code-splitting), así que cada bundle
 *    tiene su propia entrada y arrastra su CSS mediante un import.
 *
 * 2. Modo librería. Es lo que hace que Vite extraiga el CSS a su propio
 *    archivo en lugar de inyectarlo desde el JS; necesitamos el `.css` suelto
 *    para poder encolarlo con `wp_enqueue_style()`.
 */
export default defineConfig( {
	build: {
		outDir: 'assets/build',
		emptyOutDir: true,
		// El admin de WordPress no necesita navegadores antiguos; esto mantiene
		// el anidamiento CSS nativo sin transpilar de más.
		target: 'es2022',
		cssTarget: 'chrome112',
		minify: 'esbuild',
		lib: {
			entry: resolve( import.meta.dirname, 'assets/src/js/forja-input.ts' ),
			name: 'ForjaInput',
			formats: [ 'iife' ],
			fileName: () => 'js/forja-input.js',
			cssFileName: 'forja-input',
		},
		rollupOptions: {
			output: {
				assetFileNames: 'css/[name][extname]',
			},
		},
	},
} );
