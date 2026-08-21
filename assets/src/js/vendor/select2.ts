/**
 * select2, para los temas que prefieren compilarlo en su propio bundle.
 *
 * Por defecto Forja lo encola desde PHP como archivo suelto, porque así no hace
 * falta configurar nada. Pero si el tema ya usa un empaquetador, tener un asset
 * que entra por otro camino es una excepción molesta: no pasa por el build, no
 * se minifica con el resto y no comparte la caché.
 *
 * Importando este módulo, select2 entra por el bundle como todo lo demás:
 *
 *     import 'apros-forja/js/forja-input';
 *     import 'apros-forja/js/vendor/select2';
 *
 * A cambio hay que decirle al empaquetador que `jquery` no se empaqueta, que lo
 * pone WordPress como global. En Vite:
 *
 *     build: {
 *         rollupOptions: {
 *             external: [ 'jquery' ],
 *             output: { globals: { jquery: 'jQuery' } },
 *         },
 *     },
 *
 * Y en el `functions.php`, que Forja no lo encole por su cuenta:
 *
 *     add_filter( 'forja/enqueue_select2', '__return_false' );
 */

import '../../../vendor/select2/select2.min.css';
import '../../../vendor/select2/select2.min.js';
