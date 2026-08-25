# Installation

| | |
|---|---|
| PHP | 8.1 or newer |
| WordPress | 6.4 or newer |
| Composer | 2.x |
| Bun | 1.3 or newer (only to work on the library itself) |

```bash
cd wp-content/themes/my-theme
composer require oswa/forja
```

And in your theme's `functions.php`:

```php
require_once get_stylesheet_directory() . '/vendor/autoload.php';
```

Forja boots itself: Composer's autoloader includes its `bootstrap.php`, which
hooks into WordPress on `after_setup_theme`.

## Your theme compiles the assets

Forja **does not ship compiled CSS or JavaScript**. Instead, you import its
sources into your theme's bundle. That way you get a single file, no duplicated
CSS, and your own pipeline stays in charge.

In your theme's `vite.config.ts`, an alias to the package sources:

```ts
resolve: {
    alias: {
        'oswa-forja': resolve( import.meta.dirname, 'vendor/oswa/forja/assets/src' ),
    },
},
```

In your admin entry point:

```ts
// assets/src/admin.ts
import 'oswa-forja/js/forja-input';        // pulls the field CSS along with it

import './admin.css';                       // your own styles, afterwards
```

Importing **is not enough**: that decides what goes *into* the bundle at build
time, but something has to tell WordPress the file exists. A theme always
enqueues its own assets — the bundle cannot request itself. Skip this step and
Forja's CSS sits inside `admin.css` while the fields render unstyled.

In `functions.php`:

```php
use Forja\Assets;

// Your bundle already carries Forja's sources: don't let the package enqueue its own.
add_filter( 'forja/enqueue_assets', '__return_false' );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! Assets::is_field_screen( $hook ) ) {
		return;
	}

	$url = get_stylesheet_directory_uri();

	wp_enqueue_style( 'theme-admin', $url . '/assets/build/css/admin.css' );
	wp_enqueue_script( 'theme-admin', $url . '/assets/build/js/admin.js', array(), null, true );
} );
```

That's plain WordPress code. The only thing Forja contributes is
`Assets::is_field_screen()`, so you don't copy the list of screens where it
renders fields: that list grows — posts, taxonomies, profiles, options pages —
and a copy falls behind without anyone noticing.

## select2, if you use a bundler

The relational fields (`post_object`, `page_link`, `user`…) use select2, which
WordPress doesn't ship. By default Forja enqueues it as a standalone asset and
there is nothing to configure.

But if you already have a bundler, an asset coming in from the outside is an
annoying exception: it skips your build, isn't minified with the rest and
doesn't share the cache. You can pull it into your bundle by importing it, and
tell Forja not to enqueue it:

```ts
import 'oswa-forja/js/vendor/select2';
```

```php
add_filter( 'forja/enqueue_select2', '__return_false' );
```

With one condition: **jQuery is not bundled**. WordPress provides it as a global,
and duplicating it would break every plugin that depends on that same instance.
In `vite.config.ts`:

```ts
build: {
    rollupOptions: {
        external: [ 'jquery' ],
        output: { globals: { jquery: 'jQuery' } },
    },
},
```

Verified in the reference theme: the bundle grows by what select2 weighs — about
70 kB — and jQuery does not appear inside.

::: tip No bundler?
Run `bun run build` inside the package. Forja detects its own artefacts and
enqueues them by itself, with no filter and nothing to import.
:::
