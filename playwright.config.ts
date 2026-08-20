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
	projects: [
		{
			name: 'login',
			testMatch: /auth\.setup\.ts/,
		},
		{
			name: 'admin',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: 'tests/e2e/.auth/admin.json',
			},
			dependencies: [ 'login' ],
		},
	],
} );
