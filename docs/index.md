---
layout: home

hero:
  name: Forja
  text: Custom fields, defined in code
  tagline: CMB2's developer API with ACF's editing experience. No admin panel, no plugin to activate.
  actions:
    - theme: brand
      text: Get started
      link: /guide/installation
    - theme: alt
      text: Browse the fields
      link: /fields/
---

## A complete example

```php
add_action( 'forja/register_boxes', function () {
	forja_register_box( 'home', array(
		'title'           => 'Home content',
		'object_subtypes' => array( 'page' ),
		'fields'          => array(
			array( 'type' => 'text',  'name' => 'headline',   'label' => 'Headline' ),
			array( 'type' => 'image', 'name' => 'background', 'label' => 'Background image' ),
		),
	) );
} );
```

And in your template:

```php
forja_the_field( 'headline' );

$background = forja_get_field( 'background' );
```

## What you get

- **Not a plugin.** It installs into your theme with Composer. Fields and the code
  using them are versioned together, and nobody can deactivate them from the dashboard.
- **Fields live in your repository.** Declared in PHP, CMB2 style. No panel to create
  them, no exporting and importing JSON between environments.
- **The interface your editors already know.** The markup and CSS of ACF/Secure Custom
  Fields are ported, not reinvented. A tool compares both, field by field.
- **Compatible with ACF's data.** Repeaters as `field_0_subfield`, dates as `Ymd`.
  An existing site reads without migrating anything.

**35 field types**, from `text` to `relationship`, including repeaters, flexible
content, and an icon picker that searches Iconify's 200,000+ icons without
bundling any catalogue.

::: tip Documentation in Spanish
This project is written in Spanish, and so is most of its documentation. The
pages translated here cover installation and the field reference — enough to
decide whether Forja fits your project and to get the first group of fields
working.

The [Spanish site](/es/) covers everything: repeaters, flexible content, `clone`,
conditional logic, the relational fields and the architecture notes. When the
two versions disagree, **Spanish is the canonical one**.
:::
