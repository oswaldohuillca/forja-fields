# Desarrollar la librería

## Desarrollo de la librería

```bash
composer install   # herramientas de análisis de código
bun install
bun run build
```

| Comando | Qué hace |
|---|---|
| `bun run build` | Compila los assets a `assets/build/` |
| `bun run watch` | Recompila al guardar |
| `bun run typecheck` | Comprueba los tipos sin emitir nada |
| `composer lint` | Revisa los estándares de código de WordPress |
| `composer test` | Ejecuta la suite de Pest |

Para probar los cambios contra un tema real sin publicar en Packagist, usa un
repositorio de tipo `path` en el `composer.json` del tema:

```json
{
    "repositories": [
        { "type": "path", "url": "../../packages/forja", "options": { "symlink": true } }
    ],
    "require": { "oswa/forja": "@dev" }
}
```

El `wp-content/themes/forja-test` de este repositorio es exactamente eso.

## Tests

La suite usa [Pest](https://pestphp.com) y son tests de **integración**: cargan
un WordPress real en lugar de simularlo. El código se apoya en una docena de
funciones del núcleo, y simularlas costaría más que ejecutarlas, además de
probar los dobles en vez del comportamiento.

```bash
docker exec -w /var/www/html/wp-content/packages/forja acf-wordpress-1 \
    php vendor/bin/pest
```

Si tu WordPress no está en `/var/www/html`, indícalo con la variable de entorno
`FORJA_WP_LOAD`.

Cubren desde el saneado de cada tipo hasta el ciclo de guardado completo —envío,
nonce, permisos, validación y escritura— en entradas, términos y usuarios. Ese
último bloque es el que comprueba las garantías que ninguna pieza suelta puede
demostrar por sí misma: que un envío sin nonce no borra nada, que un valor
inválido conserva el anterior, y que un repetidor más corto limpia las filas que
sobran.

## Tests de navegador

Pest y el comparador miran lo que emite el servidor. Ninguno ejecuta el
TypeScript del paquete, y ahí es donde vive la mitad del comportamiento: si un
botón deja de responder, el markup sigue siendo correcto y nadie se entera.

Eso lo cubre Playwright, contra el WordPress de desarrollo y el tema
`forja-test`:

```bash
## Una vez: crea el usuario con el que entran los tests
docker exec -w /var/www/html acf-wordpress-1 \
    php wp-content/packages/forja/tools/e2e-user.php

bun run test:e2e        # o test:e2e:ui para verlos correr
```

Se configuran con variables de entorno: `FORJA_E2E_URL` (por defecto
`http://localhost:8080`), `FORJA_E2E_USER`, `FORJA_E2E_PASS` y `FORJA_E2E_TERM`.

Prueban sobre la pantalla de una **categoría**, no sobre la de entradas. El
editor de bloques esconde los metaboxes en un cajón plegable cuyo control cambia
de estructura entre versiones; la de taxonomías es clásica y el código que
interesa —clonar una fila y arrancarle los campos— es exactamente el mismo.

## Traducciones

Las cadenas usan el dominio `forja-fields`. La plantilla se regenera con:

```bash
docker exec -w /var/www/html/wp-content/packages/forja acf-wordpress-1 \
    composer make-pot
```

Escribe `languages/forja-fields.pot` y **sale con error** si encuentra una
cadena con otro dominio o construida dinámicamente: en ambos casos esa cadena no
se traduciría nunca. Un test lo ejecuta en cada pasada, así que la plantilla no
se queda atrás sin que nadie lo note.

Una advertencia: los `msgid` están **en español**, no en inglés. Los textos
visibles reproducen los de ACF para que el markup sea idéntico, y el WordPress
de referencia está en español. Traducir es, por tanto, español → idioma destino.
Es válido en gettext, aunque no sea la convención de wordpress.org.

Para traducir, coloca el `.mo` en `languages/forja-fields-{locale}.mo`.

## Comprobar la paridad con ACF

Con Secure Custom Fields presente en la instalación, esta herramienta pinta los
mismos campos con las dos implementaciones y compara el markup resultante:

```bash
docker exec -w /var/www/html acf-wordpress-1 \
    php wp-content/packages/forja/tools/compare-with-scf.php
```

Sirve como test de regresión: si un cambio se desvía del original, sale ahí.
