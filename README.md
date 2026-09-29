# Forja

**English** · [Español](README.es.md)

A **Composer library** for building WordPress custom fields **from your theme's
code**, with the ACF/Secure Custom Fields admin interface your editors already
know.

The idea is simple: CMB2's developer API, ACF's editing experience.

- Not a plugin. It installs into the theme and there is nothing to activate.
- No field-builder screen. Field groups are declared in PHP and live in the repository.
- Visual parity with ACF/SCF: their markup and CSS are ported, not reinvented.
- Compatible with ACF data: an existing site is read without migrating anything.

```bash
composer require forja-wp/forja-fields
```

```php
add_action( 'forja/register_boxes', function () {
	forja_register_box( 'hero', array(
		'title'           => 'Hero content',
		'object_subtypes' => array( 'page' ),
		'fields'          => array(
			array( 'type' => 'text',  'name' => 'headline',   'label' => 'Headline' ),
			array( 'type' => 'image', 'name' => 'background', 'label' => 'Background image' ),
		),
	) );
} );
```

```php
forja_the_field( 'headline' );
```

## Documentation

The full documentation lives in `docs/` and is published as a site:

```bash
bun run docs          # dev server, with live reload
bun run docs:build    # static site in docs/.vitepress/dist
```

The site defaults to **English**, with Spanish under `/es/`. The Spanish
version is the complete, canonical one; the English one covers getting started.

| Where | What it covers |
|---|---|
| [Installation](docs/guide/installation.md) | Installing and wiring up the assets |
| [Getting started](docs/guide/getting-started.md) | Declaring your first field group |
| [Fields](docs/fields/index.md) | The 35 field types |
| [Reference](docs/es/referencia/valores.md) (Spanish) | Return values, conditional logic, validation and extending |
| [Architecture](docs/es/desarrollo/arquitectura.md) (Spanish) | The reasoning behind each decision, external dependencies and security |
| [ROADMAP.md](ROADMAP.md) (Spanish) | Status of each phase and decisions already made |

## Requirements

| | |
|---|---|
| PHP | 8.1 or newer |
| WordPress | 6.4 or newer |
| Composer | 2.x |
| Bun | 1.3 or newer (only to work on the library itself) |

## License

GPL-2.0-or-later. Derivative work of
[Secure Custom Fields](https://github.com/WordPress/secure-custom-fields).
