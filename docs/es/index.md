---
layout: home

hero:
  name: Forja
  text: Campos personalizados por código
  tagline: La API de desarrollo de CMB2, la experiencia de edición de ACF. Sin panel de administración y sin plugin que activar.
  actions:
    - theme: brand
      text: Empezar
      link: /es/guia/instalacion
    - theme: alt
      text: Ver los campos
      link: /es/campos/

features:
  - title: No es un plugin
    details: Se instala con Composer en tu tema. Los campos y el código que los usa se versionan juntos, y nadie puede desactivarlos desde el escritorio.
  - title: Los campos viven en el repositorio
    details: Se declaran en PHP, al estilo de CMB2. Sin panel para crearlos, sin exportar e importar JSON entre entornos.
  - title: La interfaz que tus editores conocen
    details: Se porta el markup y el CSS de ACF/Secure Custom Fields, no se reinventa. Una herramienta compara ambos campo a campo.
  - title: Compatible con los datos de ACF
    details: Repetidores como campo_0_subcampo, fechas como Ymd. Un sitio existente se lee sin migrar nada.
---

## Un ejemplo completo

```php
add_action( 'forja/register_boxes', function () {
	forja_register_box( 'portada', array(
		'title'           => 'Contenido de portada',
		'object_subtypes' => array( 'page' ),
		'fields'          => array(
			array( 'type' => 'text',  'name' => 'titular', 'label' => 'Titular' ),
			array( 'type' => 'image', 'name' => 'fondo',   'label' => 'Imagen de fondo' ),
		),
	) );
} );
```

Y en la plantilla:

```php
forja_the_field( 'titular' );

$fondo = forja_get_field( 'fondo' );
```

## Los 35 tipos de campo

Agrupados por para qué sirven. La referencia completa —cada opción, qué guarda y
qué devuelve— está en [Los campos](/es/campos/).

| Familia | Tipos |
|---|---|
| **Texto y números** | `text` `textarea` `number` `range` `email` `url` `password` |
| **Elección** | `select` `radio` `checkbox` `button_group` `true_false` |
| **Contenido enriquecido** | `wysiwyg` `link` `oembed` |
| **Medios** | `image` `file` `gallery` `icon_picker` |
| **Fecha, hora y color** | `date_picker` `time_picker` `date_time_picker` `color_picker` |
| **Relacionales** | `post_object` `page_link` `relationship` `taxonomy` `user` |
| **Compuestos** | `repeater` `group` `flexible_content` |
| **Presentación** | `message` `separator` `tab` `accordion` |

Cuatro que conviene destacar:

- **`repeater` y `flexible_content`** guardan en el formato de ACF —`banner_0_titulo`,
  una clave por subcampo y fila—, así que un sitio existente se lee sin migrar nada.
- **Los relacionales** buscan por AJAX en vez de volcar el catálogo entero en el
  HTML. Un sitio con miles de entradas sigue siendo usable.
- **`icon_picker`** busca en vivo entre los más de 200.000 iconos de Iconify. No
  se empaqueta ningún catálogo, y en la parte pública sale un SVG en línea sin
  JavaScript.
- **`clone`** no es un tipo del catálogo: se resuelve al declarar la caja, así que
  nada por debajo llega a verlo. Incorpora un conjunto de campos reutilizable en
  tantos grupos como quieras, con prefijos y ajustes por campo.

Todos funcionan dentro de una fila de repetidor, incluidos el `wysiwyg` y los
relacionales.

Todo verificado: 215 tests de integración contra un WordPress real, 35 tests de
navegador, y un comparador que exige markup idéntico al de ACF campo a campo.
