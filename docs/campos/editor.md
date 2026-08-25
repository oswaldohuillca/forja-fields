# Editor enriquecido

```php
array(
	'type'    => 'wysiwyg',
	'name'    => 'cuerpo',
	'label'   => 'Cuerpo',
	'toolbar' => 'basic',   // full (por defecto) o basic
	'tabs'    => 'all',     // all, visual o text
)
```

Devuelve el HTML **tal como se guardó**, sin aplicar `wpautop()` ni los filtros
de `the_content`: eso es decisión de la plantilla.

```php
echo wp_kses_post( wpautop( forja_get_field( 'cuerpo' ) ) );
```

A quien no tenga permiso de `unfiltered_html` se le filtra el contenido con
`wp_kses_post()` al guardar, el mismo criterio que aplica WordPress al contenido
de una entrada.

Funciona **dentro de un repetidor**, incluidas las filas que se añaden sobre la
marcha.

## Tablas

WordPress empaqueta TinyMCE pero **no** su plugin `table` — es justo lo que
añaden extensiones como «Advanced Editor Tools». Forja incluye el plugin oficial
de la misma versión, así que basta con pedirlo:

```php
array( 'type' => 'wysiwyg', 'name' => 'cuerpo', 'table' => true )
```

Añade a la barra los botones de insertar tabla y editar filas y celdas. El HTML
resultante sobrevive al saneado: `wp_kses_post()` admite tablas con sus
atributos habituales.

El archivo vive en `assets/vendor/tinymce/table/`, bajo LGPL 2.1 y sin
modificar. Su versión debe coincidir con la de TinyMCE que traiga WordPress; el
README de esa carpeta explica cómo comprobarlo al actualizar.
