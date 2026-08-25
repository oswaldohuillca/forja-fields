# Instalación

## Requisitos

| | |
|---|---|
| PHP | 8.1 o superior |
| WordPress | 6.4 o superior |
| Composer | 2.x |
| Bun | 1.3 o superior (sólo para desarrollar la librería) |

## Instalación en un tema

```bash
cd wp-content/themes/mi-tema
composer require oswa/forja
```

Y en el `functions.php` del tema:

```php
require_once get_stylesheet_directory() . '/vendor/autoload.php';
```

Forja se arranca sola: el autoload de Composer incluye su `bootstrap.php`, que
la engancha a WordPress en `after_setup_theme`.

## Los assets los compila tu tema

Forja **no distribuye CSS ni JavaScript compilados**. En su lugar, importas sus
fuentes desde el bundle del tema. Así sale un único archivo, sin CSS duplicado y
con tu pipeline al mando.

En el `vite.config.ts` del tema, un atajo hacia los fuentes del paquete:

```ts
resolve: {
    alias: {
        'oswa-forja': resolve( import.meta.dirname, 'vendor/oswa/forja/assets/src' ),
    },
},
```

En la entrada de administración del tema:

```ts
// assets/src/admin.ts
import 'oswa-forja/js/forja-input';        // arrastra también el CSS de los campos
import 'oswa-forja/js/vendor/select2';     // opcional; ver más abajo

import './admin.css';                        // tus estilos propios, después
```

Importar **no basta**: eso decide qué entra en el bundle al compilar, pero
alguien tiene que decirle a WordPress que ese archivo existe. Un tema siempre
encola sus propios assets; el bundle no puede pedirse a sí mismo. Si te saltas
este paso, el CSS de Forja está dentro de `admin.css` y los campos salen en
crudo.

En el `functions.php`:

```php
use Forja\Assets;

// Tu bundle ya trae los fuentes de Forja: que el paquete no encole los suyos.
add_filter( 'forja/enqueue_assets', '__return_false' );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! Assets::is_field_screen( $hook ) ) {
		return;
	}

	$url = get_stylesheet_directory_uri();

	wp_enqueue_style( 'tema-admin', $url . '/assets/build/css/admin.css' );
	wp_enqueue_script( 'tema-admin', $url . '/assets/build/js/admin.js', array(), null, true );
} );
```

Es código normal de WordPress. Lo único que aporta Forja es
`Assets::is_field_screen()`, para no copiar la lista de pantallas donde pinta
campos: crece —entradas, taxonomías, perfiles, páginas de opciones— y una copia
se queda corta sin que nadie se entere.

## select2, si usas empaquetador

Los campos relacionales (`post_object`, `page_link`, `user`…) usan select2, que
WordPress no trae. Por defecto Forja lo encola como archivo suelto y no hay nada
que configurar.

Pero si ya tienes un empaquetador, un asset que entra por fuera es una excepción
molesta: no pasa por el build, no se minifica con el resto y no comparte la
caché. Puedes meterlo en tu bundle importándolo, y decirle a Forja que no lo
encole:

```php
add_filter( 'forja/enqueue_select2', '__return_false' );
```

Con una condición: **jQuery no se empaqueta**. Lo pone WordPress como global y
duplicarlo rompería los plugins que dependen de esa misma instancia. En el
`vite.config.ts`:

```ts
build: {
    rollupOptions: {
        external: [ 'jquery' ],
        output: { globals: { jquery: 'jQuery' } },
    },
},
```

Comprobado en el tema de referencia: el bundle crece lo que ocupa select2
—unos 70 kB— y jQuery no aparece dentro.

El tema `wp-content/themes/forja-test` de este repositorio está montado
exactamente así y sirve de referencia.

> Si tu tema no tiene bundler, ejecuta `bun run build` dentro del paquete: Forja
> detecta sus propios artefactos y los encola por su cuenta, sin necesidad del
> filtro ni de importar nada.
