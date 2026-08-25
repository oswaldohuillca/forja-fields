# Los campos

## Opciones comunes de un campo

| Clave | Por defecto | Descripción |
|---|---|---|
| `type` | — | Obligatorio. Tipo de campo |
| `name` | — | Obligatorio. Clave de almacenamiento |
| `label` | `''` | Etiqueta visible |
| `instructions` | `''` | Texto de ayuda bajo la etiqueta |
| `required` | `false` | Marca el asterisco y añade `required` al control |
| `default_value` | `''` | Valor cuando aún no se ha guardado nada |
| `placeholder` | `''` | Texto de marcador |
| `wrapper` | `array()` | `width` en porcentaje, `class` e `id` extra |
| `conditional_logic` | `array()` | Reglas que deciden si el campo se muestra |

## Tipos disponibles

| Tipo | Opciones propias | Notas |
|---|---|---|
| `text` | `maxlength`, `prepend`, `append` | |
| `textarea` | `rows`, `maxlength` | Conserva los saltos de línea |
| `number` | `min`, `max`, `step`, `prepend`, `append` | Vacío se guarda como `''`, no como `0` |
| `range` | `min`, `max`, `step`, `prepend`, `append` | Deslizador con campo numérico sincronizado |
| `email` | `maxlength`, `prepend`, `append` | Se sanea con `sanitize_email()` |
| `url` | `maxlength`, `prepend`, `append` | Se sanea con `sanitize_url()`; imprime con `esc_url()` |
| `password` | `maxlength` | Se almacena en claro; no lo uses para credenciales |
| `select` | `choices`, `multiple`, `allow_null` | Nativo, con opciones fijas |
| `radio` | `choices`, `layout`, `allow_null` | |
| `checkbox` | `choices`, `layout` | Guarda un array |
| `button_group` | `choices`, `layout`, `allow_null` | Radios estilizados como botones segmentados |
| `true_false` | `message`, `ui`, `ui_on_text`, `ui_off_text` | Guarda `1` o `0`; con `ui` pinta el interruptor |
| `date_picker` | `return_format`, `min`, `max` | Control nativo; se guarda como `Ymd` |
| `time_picker` | `return_format`, `min`, `max` | Control nativo; se guarda como `H:i:s` |
| `date_time_picker` | `return_format`, `min`, `max` | Control nativo; se guarda como `Y-m-d H:i:s` |
| `color_picker` | `enable_opacity`, `palette` | Selector del núcleo; hexadecimal, o `rgba()` con opacidad |
| `wysiwyg` | `tabs`, `toolbar`, `rows`, `media_upload`, `table` | TinyMCE; funciona también dentro de repetidores |
| `link` | `return_format` | Modal de enlaces del núcleo; guarda texto, URL y destino |
| `oembed` | `width`, `height`, `return_format` | Guarda la dirección; el HTML se resuelve al pintar |
| `image` | `preview_size`, `library`, `mime_types`, `return_format` | Guarda el ID; se valida contra la mediateca |
| `file` | `library`, `mime_types`, `return_format` | Guarda el ID del adjunto |
| `gallery` | `preview_size`, `min`, `max`, `mime_types`, `return_format` | Lista ordenada de imágenes |
| `icon_picker` | `collections`, `return_format` | Buscador sobre Iconify; guarda `mdi:home` |
| `message` | `message`, `esc_html`, `new_lines` | Sólo presentación, no guarda nada |
| `separator` | — | Sólo presentación; la etiqueta titula la sección |
| `tab` | `selected`, `endpoint` | Agrupa los campos que le siguen en una pestaña |
| `accordion` | `open`, `multi_expand`, `endpoint` | Anida los campos que le siguen en un panel plegable |
| `repeater` | `sub_fields`, `min`, `max`, `button_label` | Lista de filas; compatible con los datos de ACF |
| `group` | `sub_fields`, `layout` | Subcampos bajo un nombre común, sin repetición |
| `flexible_content` | `layouts`, `min`, `max`, `button_label` | Filas de distinta forma, a elegir por el editor |
| `clone` | `clone`, `display`, `prefix_name`, `prefix_label`, `overrides` | Incorpora un conjunto de campos declarado aparte |
| `post_object` | `post_type`, `taxonomy`, `post_status`, `multiple` | Guarda el ID de la entrada; busca por AJAX |
| `page_link` | las de `post_object` | Guarda el ID; devuelve el enlace |
| `relationship` | `filters`, `min`, `max`, más las de `post_object` | Dos paneles; conserva el orden |
| `taxonomy` | `taxonomy`, `field_type`, `hide_empty` | Casillas, radios o desplegable |
| `user` | `role`, `multiple` | Guarda el ID del usuario |

Todos aceptan además `readonly` y `disabled`.

## Opciones de los campos de elección

`choices` admite dos formas. Un array asociativo define valor y etiqueta por
separado; una lista simple usa cada elemento para ambas cosas:

```php
'choices' => array( 'borrador' => 'Borrador', 'publicado' => 'Publicado' ),
'choices' => array( 'norte', 'sur', 'este', 'oeste' ),
```

`layout` acepta `vertical` (por defecto, salvo en `button_group`) u `horizontal`.

Los valores enviados **se validan contra las opciones declaradas**: cualquier
cosa que no esté en la lista se descarta en lugar de almacenarse.

Los tipos restantes de ACF (`tab`, `accordion`, `image`, `repeater`…) aún no
están implementados. Consulta el [roadmap](https://github.com/oswaldohuillca/forja/blob/main/ROADMAP.md) del repositorio.
