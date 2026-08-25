# Uso

Los grupos de campos se declaran en el hook `forja/register_boxes`:

```php
add_action( 'forja/register_boxes', function () {
	forja_register_box( 'portada', array(
		'title'           => 'Contenido de portada',
		'object_type'     => 'post',
		'object_subtypes' => array( 'page' ),
		'fields'          => array(
			array(
				'type'         => 'text',
				'name'         => 'titular',
				'label'        => 'Titular',
				'instructions' => 'Máximo 60 caracteres.',
				'required'     => true,
				'wrapper'      => array( 'width' => '50' ),
			),
			array(
				'type'    => 'textarea',
				'name'    => 'bajada',
				'label'   => 'Bajada',
				'rows'    => 3,
				'wrapper' => array( 'width' => '50' ),
			),
		),
	) );
} );
```

Y se leen desde la plantilla:

```php
$titular = forja_get_field( 'titular' );

forja_the_field( 'bajada' );
```
