# Dónde pueden vivir los campos

## Taxonomías y usuarios

Los campos no viven sólo en las entradas. Cambiando `object_type` aparecen en
otras pantallas del escritorio, con la misma declaración:

```php
// En las categorías. Se guarda en termmeta.
forja_register_box( 'ficha_categoria', array(
	'title'           => 'Ficha de la categoría',
	'object_type'     => 'term',
	'object_subtypes' => array( 'category' ),
	'fields'          => array( /* ... */ ),
) );

// En el perfil. `object_subtypes` filtra aquí por ROL.
forja_register_box( 'perfil_autor', array(
	'title'           => 'Datos de autor',
	'object_type'     => 'user',
	'object_subtypes' => array( 'author', 'editor' ),
	'fields'          => array( /* ... */ ),
) );
```

Se leen indicando el tipo de objeto:

```php
forja_get_field( 'cabecera', $term_id, 'term' );
forja_get_field( 'cargo', $user_id, 'user' );
```

Funcionan todos los tipos de campo, compuestos incluidos. En las taxonomías hay
dos pantallas: la de alta, donde los campos van apilados, y la de edición, donde
van como filas de la tabla del escritorio.

## Páginas de opciones

```php
forja_register_box( 'ajustes_sitio', array(
	'title'       => 'Ajustes del sitio',
	'object_type' => 'option',
	'icon'        => 'dashicons-hammer',
	'fields'      => array(
		array( 'type' => 'text',  'name' => 'telefono', 'label' => 'Teléfono' ),
		array( 'type' => 'image', 'name' => 'logo',     'label' => 'Logotipo' ),
	),
) );
```

Crea su propia pantalla en el escritorio. Opciones adicionales: `capability`
(por defecto `manage_options`), `menu_title`, `parent_slug` para colgarla de un
menú existente, `icon`, `position` y `button_label`.

Se leen con un atajo que evita repetir el prefijo:

```php
forja_get_option( 'telefono', 'ajustes_sitio' );
```

Funcionan todos los tipos de campo, compuestos incluidos.
