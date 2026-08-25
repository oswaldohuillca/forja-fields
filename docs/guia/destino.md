# Opciones de un grupo

| Clave | Por defecto | Descripción |
|---|---|---|
| `title` | `''` | Título del metabox |
| `object_type` | `'post'` | `post`, `term`, `user` u `option` |
| `object_subtypes` | `array()` | Post types, taxonomías o roles según `object_type`; vacío significa todos |
| `templates` | `array()` | Slugs de plantilla; usa `'default'` para la plantilla por defecto |
| `object_ids` | `array()` | Identificadores concretos de objeto |
| `condition` | `null` | Función que recibe el objeto y devuelve si la caja aplica |
| `context` | `'normal'` | Contexto de `add_meta_box()` |
| `priority` | `'default'` | Prioridad de `add_meta_box()` |
| `instruction_placement` | `'label'` | `label` o `field` |
| `label_placement` | `'top'` | `top` o `left` |

## Cómo elegir el destino

Los criterios se acumulan: **todos los declarados deben cumplirse**, y los que se
dejan vacíos no filtran.

```php
// Sólo páginas con la plantilla templates/home.php
'object_subtypes' => array( 'page' ),
'templates'       => array( 'templates/home.php' ),

// Un CPT entero, en la columna lateral
'object_subtypes' => array( 'proyecto' ),
'context'         => 'side',

// Sólo la página configurada como portada
'object_ids' => array( (int) get_option( 'page_on_front' ) ),

// Cualquier otra regla
'condition' => static fn ( WP_Post $post ): bool => $post->post_parent > 0,
```

El slug de una plantilla es su ruta relativa a la raíz del tema
(`templates/home.php`), tal como aparece en la cabecera `Template Name`.

> **Ojo:** el filtro por plantilla se evalúa en el servidor. Si cambias la
> plantilla en el editor, la caja no aparece ni desaparece hasta que guardas y
> recargas. ACF resuelve esto con JavaScript; está en el roadmap.
