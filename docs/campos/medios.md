# Imágenes, archivos y galerías

## Qué devuelve un campo de medios

Se almacena **siempre el identificador del adjunto**, que es lo que sobrevive a
que cambie la URL o se regeneren los tamaños. `return_format` sólo decide en qué
forma llega a la plantilla:

| Valor | Devuelve | Cuando está vacío |
|---|---|---|
| `id` (por defecto) | `int` con el identificador | `0` |
| `url` | `string` con la URL del archivo | `''` |
| `array` | `array` con `id`, `url`, `title`, `filename`, `filesize`, `mime_type`, `alt`, `description` y `caption`; en imágenes además `width`, `height` y `sizes` | `null` |

Cada formato tiene su propio valor para «sin rellenar», elegido para que el tipo
devuelto no cambie según haya dato o no.

```php
array( 'type' => 'image', 'name' => 'portada', 'return_format' => 'array' )
```

```php
$img = forja_get_field( 'portada' );

echo '<img src="' . esc_url( $img['sizes']['large']['url'] ) . '" alt="' . esc_attr( $img['alt'] ) . '">';
```

Si necesitas el valor crudo pese al formato declarado, usa
`forja_get_field_raw()`.

## Galería

```php
array(
	'type'         => 'gallery',
	'name'         => 'imagenes',
	'label'        => 'Imágenes',
	'max'          => 8,
	'preview_size' => 'thumbnail',
)
```

Guarda una **lista ordenada** de identificadores; el orden es el de las
miniaturas, que se reordenan arrastrando. `return_format` funciona igual que en
`image`, aplicado a cada elemento:

```php
foreach ( forja_get_field( 'imagenes' ) as $id ) {
	echo wp_get_attachment_image( $id, 'large' );
}
```

Al guardar se descartan los duplicados y todo lo que no sea una imagen de la
mediateca. Al leer se omiten los adjuntos que se hayan borrado, para que la
plantilla no tenga que comprobarlo.

> El panel lateral de ACF, que permite editar título y texto alternativo sin
> salir del campo, no está portado. Se editan desde la mediateca.
