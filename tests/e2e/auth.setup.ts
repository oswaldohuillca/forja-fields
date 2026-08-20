import { test as setup, expect } from '@playwright/test';

/**
 * Inicia sesión una vez y guarda las cookies para el resto de los tests.
 *
 * El usuario lo crea `tools/e2e-user.php`, que hay que ejecutar antes de la
 * primera pasada.
 */
const STATE = 'tests/e2e/.auth/admin.json';

setup( 'inicia sesión en el escritorio', async ( { page } ) => {
	const user = process.env.FORJA_E2E_USER ?? 'forja_e2e';
	const pass = process.env.FORJA_E2E_PASS ?? 'forja-e2e-pass';

	await page.goto( '/wp-login.php' );

	await page.locator( '#user_login' ).fill( user );
	await page.locator( '#user_pass' ).fill( pass );
	await page.locator( '#wp-submit' ).click();

	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

	await page.context().storageState( { path: STATE } );
} );
