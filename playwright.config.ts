import { defineConfig, devices } from '@playwright/test';

/**
 * Configuración de los tests de navegador.
 *
 * Corren contra el WordPress de desarrollo y el tema `forja-test`, que declara
 * las cajas de prueba. No levantan nada: el contenedor tiene que estar en pie.
 *
 * Son la única parte de la suite que ejecuta el TypeScript del paquete. Pest
 * comprueba lo que emite el servidor; el comparador, que ese markup coincida
 * con el de ACF. Ninguno de los dos ve si un botón responde al pulsarlo.
 */
export default defineConfig( {
	testDir: './tests/e2e',
	// Sin paralelismo: todos los tests editan la misma entrada, y guardar desde
	// dos a la vez daría fallos que no son del código.
	workers: 1,
	fullyParallel: false,
	reporter: process.env.CI ? 'dot' : 'list',
	use: {
		baseURL: process.env.FORJA_E2E_URL ?? 'http://localhost:8080',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	/*
	 * El sitio de documentación se compila y se sirve solo. `reuseExistingServer`
	 * evita levantarlo otra vez si ya lo tienes abierto con `bun run docs`.
	 */
	webServer: {
		command: 'bun run docs:build && bun x vitepress preview docs --port 4173',
		url: 'http://localhost:4173/',
		reuseExistingServer: true,
		timeout: 120_000,
	},

	projects: [
		{
			name: 'login',
			testMatch: /auth\.setup\.ts/,
		},
		{
			// La documentación no necesita sesión: es un sitio estático.
			name: 'docs',
			testMatch: /docs\.spec\.ts/,
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'admin',
			testIgnore: /docs\.spec\.ts/,
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: 'tests/e2e/.auth/admin.json',
			},
			dependencies: [ 'login' ],
		},
	],
} );
