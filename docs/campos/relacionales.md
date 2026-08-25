# Campos relacionales

## Campos que apuntan a otros objetos

Cinco tipos comparten mecánica: guardan **identificadores** y traen las opciones
buscando, no volcando el catálogo en el HTML.

```php
array( 'type' => 'post_object',  'name' => 'destacada',  'post_type' => array( 'post' ) ),
array( 'type' => 'page_link',    'name' => 'enlace',     'post_type' => array( 'page' ) ),
array( 'type' => 'relationship', 'name' => 'relacionadas' ),
array( 'type' => 'taxonomy',     'name' => 'temas',      'taxonomy'  => 'category' ),
array( 'type' => 'user',         'name' => 'responsable' ),
```

Opciones comunes: `multiple`, `allow_null`, `min`, `max` y `return_format`
(`id` por defecto, o `object` para recibir el `WP_Post`, `WP_User` o `WP_Term`).

| Tipo | Opciones propias | Qué guarda |
|---|---|---|
| `post_object` | `post_type`, `taxonomy`, `post_status` | Identificador de entrada |
| `page_link` | las de `post_object` | Identificador; devuelve el enlace |
| `relationship` | `filters`, más las de `post_object` | Lista ordenada de identificadores |
| `taxonomy` | `taxonomy`, `field_type`, `hide_empty` | Identificadores de término |
| `user` | `role` | Identificador de usuario |

**`page_link` guarda el identificador, no la dirección.** Guardar el enlace ya
resuelto dejaría el sitio con URLs rotas en cuanto cambiara un slug; se resuelve
al leer, igual que hace ACF.

**`relationship` conserva el orden** en que se colocan los elementos. Es lo único
que lo distingue de un `post_object` múltiple, y la razón de que tenga interfaz
propia —dos paneles— en lugar de un desplegable.

`taxonomy` acepta `field_type` con `checkbox` (por defecto), `radio`, `select` o
`multi_select`. Las dos primeras pintan la lista completa; las otras dos, un
desplegable con búsqueda.

## Asignar los términos de verdad

Por defecto `taxonomy` es un metadato y nada más: guarda identificadores, pero la
entrada no queda clasificada. Dos opciones cambian eso:

```php
array(
	'type'       => 'taxonomy',
	'name'       => 'temas',
	'taxonomy'   => 'category',
	'save_terms' => true,   // al guardar, asigna los términos a la entrada
	'load_terms' => true,   // al leer, los toma de la entrada en vez del metadato
)
```

Con `save_terms`, elegir un término hace que la entrada salga en sus archivos,
en los menús y en las consultas por taxonomía. El campo **reemplaza** los
términos de esa taxonomía, no los añade: representa el estado completo, así que
quitar uno del formulario lo quita de verdad, y vaciar la selección los borra
todos.

Con `load_terms`, el valor sale de la entrada. Es lo que hace que el formulario
muestre la realidad aunque alguien haya cambiado las categorías desde la lista de
entradas o con una importación.

Ambas sólo aplican a **entradas**: un término, un usuario o una página de
opciones no tienen taxonomías, y ahí las opciones se ignoran sin fallar.

> Dentro de un repetidor o de una capa flexible el campo sigue guardando sólo el
> metadato: la sincronización necesita saber a qué objeto pertenece el valor, y
> un subcampo no lo sabe.

## Cómo funciona la búsqueda

El desplegable es [select2](https://select2.org), que el paquete sirve desde
`assets/vendor/` porque WordPress no lo incluye. Se encola solo, y sólo en las
pantallas donde haya uno de estos campos.

Las consultas van a `admin-ajax.php`, y el endpoint **no acepta un tipo de
contenido ni una taxonomía por parámetro**: recibe el nombre de un campo
declarado y ejecuta la consulta que ese campo define, con su nonce propio. Así no
se puede usar para listar nada que no esté ya expuesto en un formulario.

Si select2 no llegara a cargar, el `<select>` sigue funcionando: se queda sin
búsqueda, pero muestra y guarda lo que ya estuviera elegido.
