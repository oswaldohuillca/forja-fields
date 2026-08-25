# Getting started

Field groups are declared on the `forja/register_boxes` hook:

```php
add_action( 'forja/register_boxes', function () {
	forja_register_box( 'home', array(
		'title'           => 'Home content',
		'object_type'     => 'post',
		'object_subtypes' => array( 'page' ),
		'fields'          => array(
			array(
				'type'         => 'text',
				'name'         => 'headline',
				'label'        => 'Headline',
				'instructions' => '60 characters at most.',
				'required'     => true,
				'wrapper'      => array( 'width' => '50' ),
			),
			array(
				'type'    => 'textarea',
				'name'    => 'standfirst',
				'label'   => 'Standfirst',
				'rows'    => 3,
				'wrapper' => array( 'width' => '50' ),
			),
		),
	) );
} );
```

And you read them from the template:

```php
$headline = forja_get_field( 'headline' );

forja_the_field( 'standfirst' );
```

## Group options

| Key | Default | Description |
|---|---|---|
| `title` | `''` | Meta box title |
| `object_type` | `'post'` | `post`, `term`, `user` or `option` |
| `object_subtypes` | `array()` | Post types, taxonomies or roles depending on `object_type`; empty means all |
| `templates` | `array()` | Template slugs; use `'default'` for the default template |
| `object_ids` | `array()` | Specific object identifiers |
| `condition` | `null` | Callback that receives the object and returns whether the box applies |
| `context` | `'normal'` | `add_meta_box()` context |
| `priority` | `'default'` | `add_meta_box()` priority |
| `instruction_placement` | `'label'` | `label` or `field` |
| `label_placement` | `'top'` | `top` or `left` |

## Choosing where fields appear

Criteria stack up: **every one you declare must match**, and the ones you leave
empty don't filter.

```php
// Only pages using the templates/home.php template
'object_subtypes' => array( 'page' ),
'templates'       => array( 'templates/home.php' ),

// A whole custom post type, in the side column
'object_subtypes' => array( 'project' ),
'context'         => 'side',

// Only the page set as the front page
'object_ids' => array( (int) get_option( 'page_on_front' ) ),

// Any other rule
'condition' => static fn ( WP_Post $post ): bool => $post->post_parent > 0,
```

A template's slug is its path relative to the theme root
(`templates/home.php`), exactly as it appears in the `Template Name` header.

::: warning
Template filtering is evaluated on the server. If you change the template in the
editor, the box won't appear or disappear until you save and reload. ACF solves
this with JavaScript; it's on the roadmap.
:::
