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

## The 35 field types

Grouped by what they're for. The full reference — every option, what each one
stores and what it returns — lives in [The fields](/fields/).

| Family | Types |
|---|---|
| **Text and numbers** | `text` `textarea` `number` `range` `email` `url` `password` |
| **Choice** | `select` `radio` `checkbox` `button_group` `true_false` |
| **Rich content** | `wysiwyg` `link` `oembed` |
| **Media** | `image` `file` `gallery` `icon_picker` |
| **Date, time and colour** | `date_picker` `time_picker` `date_time_picker` `color_picker` |
| **Relational** | `post_object` `page_link` `relationship` `taxonomy` `user` |
| **Composite** | `repeater` `group` `flexible_content` |
| **Presentation** | `message` `separator` `tab` `accordion` |

Four worth calling out:

- **`repeater` and `flexible_content`** store in ACF's format — `banner_0_title`,
  one key per sub-field and row — so an existing site reads without migrating.
- **The relational ones** search over AJAX instead of dumping the whole catalogue
  into the HTML. A site with thousands of posts stays usable.
- **`icon_picker`** searches Iconify's 200,000+ icons live. No catalogue is
  bundled, and the front end gets an inline SVG with no JavaScript.
- **`clone`** isn't a type in the registry: it's resolved when the group is
  declared, so nothing downstream ever sees it. It pulls a reusable field set
  into as many groups as you like, with optional prefixes and per-field
  overrides.

Every type works inside a repeater row, including `wysiwyg` and the relational
ones.

::: tip Documentation in Spanish
This project is written in Spanish, and so is most of its documentation. The
pages translated here cover installation and the field reference — enough to
decide whether Forja fits your project and to get the first group of fields
working.

The [Spanish site](/es/) covers everything: repeaters, flexible content, `clone`,
conditional logic, the relational fields and the architecture notes. When the
two versions disagree, **Spanish is the canonical one**.
:::
