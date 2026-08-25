# Campos compuestos

## Repetidor

```php
array(
	'type'         => 'repeater',
	'name'         => 'banner',
	'label'        => 'Banner',
	'button_label' => 'Añadir banner',
	'min'          => 1,
	'max'          => 5,
	'sub_fields'   => array(
		array( 'type' => 'image', 'name' => 'desktop', 'label' => 'Desktop', 'wrapper' => array( 'width' => '15' ) ),
		array( 'type' => 'text',  'name' => 'title',   'label' => 'Title' ),
		array( 'type' => 'url',   'name' => 'button_url', 'label' => 'Button url' ),
	),
)
```

En la plantilla devuelve una lista de filas, con cada subcampo ya formateado:

```php
foreach ( forja_get_field( 'banner' ) as $fila ) {
	echo wp_get_attachment_image( $fila['desktop'], 'large' );
	echo esc_html( $fila['title'] );
}
```

`wrapper.width` en un subcampo fija el ancho de su columna.

**Compatibilidad con ACF.** Se almacena en el mismo formato, una clave por
subcampo y fila:

```
banner            => 2
banner_0_titulo   => 'Primera'
banner_1_titulo   => 'Segunda'
```

Es decir: un sitio que ya tenga repetidores de ACF se lee sin migrar nada. Y
cada subcampo sigue siendo consultable con `meta_query`, cosa que se perdería
serializando un array.

## Grupo

Un repetidor de una sola fila: agrupa subcampos bajo un nombre común.

```php
array(
	'type'       => 'group',
	'name'       => 'direccion',
	'label'      => 'Dirección',
	'sub_fields' => array(
		array( 'type' => 'text',   'name' => 'calle',  'label' => 'Calle' ),
		array( 'type' => 'number', 'name' => 'numero', 'label' => 'Número' ),
	),
)
```

Se almacena como `direccion_calle` y `direccion_numero`, el formato de ACF. En
la plantilla devuelve un array indexado por nombre de subcampo:

```php
$direccion = forja_get_field( 'direccion' );

echo esc_html( $direccion['calle'] );
```

`layout` acepta `block` (etiquetas arriba, por defecto) o `row` (a la
izquierda). A diferencia del acordeón, que sólo agrupa visualmente, aquí el
nombre del grupo forma parte de la clave.

## Contenido flexible

Un repetidor en el que cada fila puede tener una forma distinta:

```php
array(
	'type'         => 'flexible_content',
	'name'         => 'secciones',
	'button_label' => 'Añadir sección',
	'layouts'      => array(
		'banner' => array(
			'label'      => 'Banner',
			'sub_fields' => array(
				array( 'type' => 'image', 'name' => 'imagen', 'label' => 'Imagen' ),
				array( 'type' => 'text',  'name' => 'titular', 'label' => 'Titular' ),
			),
		),
		'texto'  => array(
			'label'      => 'Texto',
			'sub_fields' => array(
				array( 'type' => 'textarea', 'name' => 'cuerpo', 'label' => 'Cuerpo' ),
			),
		),
	),
)
```

Cada fila devuelve su capa en la clave `acf_fc_layout`:

```php
foreach ( forja_get_field( 'secciones' ) as $seccion ) {
	match ( $seccion['acf_fc_layout'] ) {
		'banner' => get_template_part( 'partials/banner', null, $seccion ),
		'texto'  => get_template_part( 'partials/texto', null, $seccion ),
		default  => null,
	};
}
```

Se almacena en el formato de ACF: la clave del campo guarda la lista ordenada de
capas, y los valores usan el mismo esquema que el repetidor. El índice es la
**posición en la lista**, no la posición dentro de su capa.
