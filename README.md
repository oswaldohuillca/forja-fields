# Forja

**Librería de Composer** para crear campos personalizados de WordPress **desde el
código de tu tema**, con la interfaz de administración de ACF/Secure Custom Fields
que tus editores ya conocen.

La idea es simple: la API de desarrollo de CMB2, la experiencia de edición de ACF.

- No es un plugin. Se instala en el tema y no hay nada que activar.
- Sin panel para crear campos. Los grupos se declaran en PHP y viven en el repositorio.
- Paridad visual con ACF/SCF: se porta su markup y su CSS, no se reinventa.
- Toolchain moderno: Bun, Vite, TypeScript y CSS puro con anidamiento nativo.

## Requisitos

| | |
|---|---|
| PHP | 8.1 o superior |
| WordPress | 6.4 o superior |
| Composer | 2.x |
| Bun | 1.3 o superior (sólo para desarrollar la librería) |

## Instalación en un tema

```bash
cd wp-content/themes/mi-tema
composer require apros/forja
```

Y en el `functions.php` del tema:

```php
require_once get_stylesheet_directory() . '/vendor/autoload.php';
```

Forja se arranca sola: el autoload de Composer incluye su `bootstrap.php`, que
la engancha a WordPress en `after_setup_theme`.

### Los assets los compila tu tema

Forja **no distribuye CSS ni JavaScript compilados**. En su lugar, importas sus
fuentes desde el bundle del tema. Así sale un único archivo, sin CSS duplicado y
con tu pipeline al mando.

En el `vite.config.ts` del tema, un atajo hacia los fuentes del paquete:

```ts
resolve: {
    alias: {
        'apros-forja': resolve( import.meta.dirname, 'vendor/apros/forja/assets/src' ),
    },
},
```

En la entrada de administración del tema:

```ts
// assets/src/admin.ts
import 'apros-forja/js/forja-input';  // arrastra también el CSS de los campos

import './admin.css';                  // tus estilos propios, después
```

Y en el `functions.php`, encola tu bundle y desactiva el de Forja:

```php
add_filter( 'forja/enqueue_assets', '__return_false' );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$url = get_stylesheet_directory_uri();

	wp_enqueue_style( 'tema-admin', $url . '/assets/build/css/admin.css' );
	wp_enqueue_script( 'tema-admin', $url . '/assets/build/js/admin.js', array(), null, true );
} );
```

El tema `wp-content/themes/forja-test` de este repositorio está montado
exactamente así y sirve de referencia.

> Si tu tema no tiene bundler, ejecuta `bun run build` dentro del paquete: Forja
> detecta sus propios artefactos y los encola por su cuenta, sin necesidad del
> filtro ni de importar nada.

## Uso

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

### Opciones de un grupo

| Clave | Por defecto | Descripción |
|---|---|---|
| `title` | `''` | Título del metabox |
| `object_type` | `'post'` | `post`, `term`, `user`, `comment` u `option` |
| `object_subtypes` | `array()` | Post types, taxonomías o roles según `object_type`; vacío significa todos |
| `templates` | `array()` | Slugs de plantilla; usa `'default'` para la plantilla por defecto |
| `object_ids` | `array()` | Identificadores concretos de objeto |
| `condition` | `null` | Función que recibe el objeto y devuelve si la caja aplica |
| `context` | `'normal'` | Contexto de `add_meta_box()` |
| `priority` | `'default'` | Prioridad de `add_meta_box()` |
| `instruction_placement` | `'label'` | `label` o `field` |
| `label_placement` | `'top'` | `top` o `left` |

### Cómo elegir el destino

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

### Opciones comunes de un campo

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

### Tipos disponibles

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

### Qué devuelve un campo de medios

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

### Repetidor

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

### Grupo

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

### Contenido flexible

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

### Reutilizar campos con `clone`

Declara el conjunto una vez y clónalo donde haga falta:

```php
add_action( 'forja/register_boxes', function () {
	forja_register_fields( 'seo', array(
		array( 'type' => 'text', 'name' => 'seo_titulo', 'label' => 'Título SEO' ),
		array( 'type' => 'textarea', 'name' => 'seo_descripcion', 'label' => 'Descripción' ),
	) );

	forja_register_box( 'pagina', array(
		'object_type' => 'post',
		'fields'      => array(
			array( 'type' => 'text', 'name' => 'entradilla', 'label' => 'Entradilla' ),
			array( 'type' => 'clone', 'clone' => 'seo' ),
		),
	) );
} );
```

El orden de declaración **no importa**: los campos de una caja se construyen la
primera vez que alguien los pide, no al registrarla, así que el conjunto puede
declararse después. Si el identificador no existe, Forja lanza una excepción
diciendo cuál falta y en qué caja estaba.

Un conjunto no se pinta en ninguna parte por sí mismo: no tiene título ni
destino. Existe sólo para clonarse. También puedes clonar **otra caja** por su
identificador, o escribir la lista directamente en la clave `clone`.

#### Qué claves se usan

Por defecto, ninguna. Los campos clonados guardan bajo su propio nombre, igual
que si los hubieras escrito ahí a mano:

```php
forja_get_field( 'seo_titulo' );   // no 'seo_seo_titulo'
```

Es lo que permite partir un ACF existente en conjuntos reutilizables **sin
migrar ni un metadato**. Si necesitas el mismo conjunto dos veces en la misma
caja, sí hacen falta claves distintas, y ahí entran las dos opciones de prefijo:

| Opción | Efecto |
|---|---|
| `display` | `seamless` (por defecto) inserta los campos en el sitio del clon. `group` los envuelve en un campo `group`, con su etiqueta y su borde. |
| `prefix_name` | Antepone el nombre del clon a la clave: `ficha_seo_titulo`. Sólo vale con `seamless`: combinarlo con `group` es un error, porque el grupo ya antepone su nombre. |
| `prefix_label` | Antepone la etiqueta del clon a la de cada campo: «SEO Título». |

```php
array(
	'type'        => 'clone',
	'name'        => 'escritorio',
	'label'       => 'Escritorio',
	'clone'       => 'medidas',
	'prefix_name' => true,      // escritorio_ancho, escritorio_alto
),
array(
	'type'        => 'clone',
	'name'        => 'movil',
	'label'       => 'Móvil',
	'clone'       => 'medidas',
	'prefix_name' => true,      // movil_ancho, movil_alto
),
```

#### Ajustar campos sueltos

Rara vez el conjunto encaja tal cual en los dos sitios. Con `overrides` cambias
lo que haga falta de un campo concreto, sin duplicar el conjunto:

```php
array(
	'type'      => 'clone',
	'clone'     => 'medidas',
	'overrides' => array(
		'ancho' => array( 'label' => 'Anchura útil', 'required' => true ),
	),
)
```

Las claves son los nombres **del conjunto de origen**, antes de cualquier
prefijo. Nombrar un campo que el conjunto no trae es un error, y el mensaje
lista los que sí hay: casi siempre es una errata, y en silencio se traduciría en
un ajuste que no se aplica sin decir por qué.

Funciona igual con `display => 'group'`.

Esto es lo que hace que `clone` valga más que una variable de PHP, y lo que ACF
no puede ofrecer: allí los campos viven en la base de datos y retocar una copia
obliga a duplicar el grupo entero.

#### Lo que hereda cada copia

Un clon marcado como `required` vuelve obligatorio todo lo que trae. Al revés no
funciona: un clon opcional no relaja lo que el conjunto declaró obligatorio.

Si el clon lleva `conditional_logic`, sus reglas pasan a los campos clonados que
no tengan las suyas. Es una diferencia deliberada con ACF, donde en modo
`seamless` el clon desaparece y esas reglas se pierden.

Los clones se pueden anidar —un conjunto que clona otro— y se pueden usar dentro
de un `repeater`, un `group` o una capa de contenido flexible.

#### Cuándo no lo necesitas

El clon existe en ACF porque los campos viven en la base de datos y no hay forma
de que un grupo referencie a otro salvo por su clave. Declarándolos por código,
compartir una lista es una variable:

```php
$medidas = array(
	array( 'type' => 'number', 'name' => 'ancho' ),
	array( 'type' => 'number', 'name' => 'alto' ),
);
```

Si eso te vale, úsalo: es más directo. `clone` aporta lo que una variable no da
— los `overrides`, los prefijos, el envoltorio en grupo y poder referenciar una
caja por su identificador.

### Tipos que devuelve cada campo

WordPress entrega todos los metadatos como cadenas. Forja los devuelve con su
tipo nativo:

| Tipo | Devuelve | Sin rellenar |
|---|---|---|
| `number` | `int` o `float` | `null` |
| `range` | `int` o `float` | el mínimo |
| `true_false` | `bool` | `false` |
| `image`, `file` | según `return_format` | `0`, `''` o `null` |
| `checkbox`, `select` múltiple | `array` | `array()` |
| `repeater`, `flexible_content` | `array` de filas | `array()` |
| `group` | `array` por subcampo | `array()` |

Un `number` sin rellenar devuelve `null` y no `0` a propósito: el cero es un
valor legítimo, y confundirlos impediría distinguir «no lo tocaron» de
«pusieron cero».

### Editor enriquecido

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

#### Tablas

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

### Fechas y horas

Usan los controles nativos del navegador, no jQuery UI: sin dependencias, con
buen comportamiento en móvil y ya traducidos. **El formato de almacenamiento es
el de ACF**, así que un sitio existente se lee sin migrar.

| Tipo | Se guarda como | Ejemplo |
|---|---|---|
| `date_picker` | `Ymd` | `20260819` |
| `time_picker` | `H:i:s` | `14:30:00` |
| `date_time_picker` | `Y-m-d H:i:s` | `2026-08-19 09:05:00` |

`return_format` es un formato de `date()` y decide qué recibe la plantilla; sin
él, se devuelve lo almacenado tal cual.

```php
array( 'type' => 'date_picker', 'name' => 'evento', 'return_format' => 'd/m/Y' )
```

```php
forja_get_field( 'evento' );  // '19/08/2026'
```

Una fecha que no encaje se descarta en lugar de desplazarse: `2026-13-01` se
guarda vacío, no como enero de 2027.

### Lógica condicional

Un campo puede depender del valor de otro. Se admiten tres formas, de la más
corta a la más explícita:

```php
// Una regla suelta.
'conditional_logic' => array( 'field' => 'tipo', 'value' => 'video' ),

// Varias reglas: deben cumplirse TODAS.
'conditional_logic' => array(
	array( 'field' => 'tipo', 'value' => 'video' ),
	array( 'field' => 'avanzado', 'value' => '1' ),
),

// Grupos alternativos: basta con que UNO se cumpla entero.
'conditional_logic' => array(
	array( array( 'field' => 'tipo', 'value' => 'video' ) ),
	array( array( 'field' => 'tipo', 'value' => 'audio' ) ),
),
```

Operadores: `==` (por defecto), `!=`, `>`, `<`, `>=`, `<=`, `contains`,
`!contains`, `empty` y `!empty`. También se aceptan las grafías de ACF
(`==contains`, `!=empty`, `!==`).

Dentro de un repetidor o de un contenido flexible, una regla mira a su
**hermano de la misma fila**, no al de la primera. Y una regla que apunta a un
campo inexistente nunca se cumple, para que un nombre mal escrito se note.

### Campos que apuntan a otros objetos

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

> Todavía no están `save_terms` ni `load_terms` de ACF, que además de guardar el
> metadato asignan los términos al objeto. El campo guarda en metadatos, que es
> el comportamiento por defecto de ACF.

#### Cómo funciona la búsqueda

El desplegable es [select2](https://select2.org), que el paquete sirve desde
`assets/vendor/` porque WordPress no lo incluye. Se encola solo, y sólo en las
pantallas donde haya uno de estos campos.

Las consultas van a `admin-ajax.php`, y el endpoint **no acepta un tipo de
contenido ni una taxonomía por parámetro**: recibe el nombre de un campo
declarado y ejecuta la consulta que ese campo define, con su nonce propio. Así no
se puede usar para listar nada que no esté ya expuesto en un formulario.

Si select2 no llegara a cargar, el `<select>` sigue funcionando: se queda sin
búsqueda, pero muestra y guarda lo que ya estuviera elegido.

### Iconos

```php
array(
	'type'        => 'icon_picker',
	'name'        => 'icono',
	'label'       => 'Icono',
	'collections' => array( 'mdi', 'tabler' ),  // vacío = todas
)
```

Busca sobre [Iconify](https://iconify.design), que reúne más de 200.000 iconos.
**No se empaqueta ningún catálogo**: el buscador consulta la API desde el
navegador, igual que hace [icones.js.org](https://icones.js.org). Sin proceso de
build ni endpoint propio.

Se piden los 999 resultados que admite la API como máximo y se muestran
paginados de 96 en 96, igual que hace el propio buscador de Iconify. El límite
importa más de lo que parece: **pidiendo pocos, la API reparte un icono por
colección** en vez de devolver los mejores, y una búsqueda como «home» acababa
mostrando `reicon:home2` o `selfhst:homer` en lugar de `material-symbols:home`.

En la plantilla, el icono se incrusta como SVG en línea:

```php
forja_the_icon( 'icono', 'w-6 h-6' );
```

El SVG se descarga **una sola vez** y se guarda en un transitorio. Son unos 150
bytes y usa `currentColor`, así que hereda el color del CSS. Deliberadamente no
se usa el componente web de Iconify: añadiría una dependencia de JavaScript para
el visitante y una petición por icono en cada carga.

Se guarda con la misma forma que ACF, así que un sitio existente con
`dashicons`, adjuntos o URLs se sigue leyendo:

```php
array( 'type' => 'iconify', 'value' => 'mdi:home' )
```

Los dashicons de ACF se resuelven por la colección homónima de Iconify, sin
tratarlos aparte.

> **Servicio externo.** Las búsquedas del escritorio van a `api.iconify.design`.
> Iconify es autoalojable; el filtro `forja/iconify_api` apunta a tu instancia
> si necesitas que no salga nada del servidor.

El nombre del icono se valida antes de construir la URL, y el SVG que devuelve
la API pasa por una lista blanca de etiquetas antes de entrar en la página. El
porqué de cada decisión está en
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md#servicios-y-dependencias-externas).

### Galería

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

### Enlaces y contenido incrustado

`link` abre el mismo modal que el botón de enlace del editor y guarda las tres
piezas de un enlace:

```php
$enlace = forja_get_field( 'cta' );
// array( 'title' => 'Ver más', 'url' => 'https://…', 'target' => '_blank' )
```

Devuelve `null` si no hay enlace, así que se comprueba con un `if`. Con
`return_format => 'url'` devuelve sólo la dirección.

`oembed` **guarda la dirección, no el HTML**. Es deliberado: el markup de un
proveedor cambia con el tiempo, y guardarlo dejaría el sitio lleno de vídeos
rotos. El HTML se resuelve al pintar:

```php
echo forja_get_field( 'video' );   // el HTML incrustado
```

Con `return_format => 'url'` devuelve la dirección sin resolver. La vista previa
en el escritorio usa el endpoint `oembed/1.0/proxy` de la API REST del núcleo,
que ya se ocupa de los proveedores y de la caché.

### Taxonomías y usuarios

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

### Páginas de opciones

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

### Validación

`required` no se queda en el atributo HTML: el servidor lo comprueba de nuevo al
guardar. Si un valor no pasa la validación **no se escribe**, de modo que un
envío manipulado no puede borrar un dato bueno, y el editor ve un aviso con lo
que se rechazó.

Los campos de medios validan además que el identificador corresponda a un
adjunto existente y que su tipo encaje con `mime_types`.

Para reglas propias:

```php
add_filter( 'forja/validate_field', function ( string $error, $value, $field ) {
	if ( 'titular' === $field->name() && mb_strlen( (string) $value ) < 10 ) {
		return 'El titular necesita al menos 10 caracteres.';
	}

	return $error;
}, 10, 3 );
```

### Pestañas y acordeones

No son campos, sino instrucciones de maquetado: se declaran en línea y todo lo
que viene después les pertenece, hasta el siguiente del mismo tipo.

```php
'fields' => array(
	array( 'type' => 'tab', 'name' => 'banner', 'label' => 'Banner', 'selected' => true ),
	array( 'type' => 'text', 'name' => 'titulo' ),      // en la pestaña Banner
	array( 'type' => 'tab', 'name' => 'seo', 'label' => 'SEO' ),
	array( 'type' => 'text', 'name' => 'meta_titulo' ), // en la pestaña SEO

	// Cierra el grupo: lo que siga no está en ninguna pestaña.
	array( 'type' => 'tab', 'name' => 'fin', 'endpoint' => true ),

	array( 'type' => 'accordion', 'name' => 'avanzado', 'label' => 'Avanzado' ),
	array( 'type' => 'text', 'name' => 'clase_css' ),   // dentro del acordeón
),
```

La diferencia entre ambos está en el DOM: el acordeón **anida** a sus campos
dentro de su panel, mientras que la pestaña los deja como hermanos y sólo
alterna su visibilidad. Es lo que hace ACF, y el CSS del envoltorio depende de
ello.

### Opciones de los campos de elección

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
están implementados. Consulta el [ROADMAP.md](ROADMAP.md).

## Desarrollo de la librería

```bash
composer install   # herramientas de análisis de código
bun install
bun run build
```

| Comando | Qué hace |
|---|---|
| `bun run build` | Compila los assets a `assets/build/` |
| `bun run watch` | Recompila al guardar |
| `bun run typecheck` | Comprueba los tipos sin emitir nada |
| `composer lint` | Revisa los estándares de código de WordPress |
| `composer test` | Ejecuta la suite de Pest |

Para probar los cambios contra un tema real sin publicar en Packagist, usa un
repositorio de tipo `path` en el `composer.json` del tema:

```json
{
    "repositories": [
        { "type": "path", "url": "../../packages/forja", "options": { "symlink": true } }
    ],
    "require": { "apros/forja": "@dev" }
}
```

El `wp-content/themes/forja-test` de este repositorio es exactamente eso.

### Tests

La suite usa [Pest](https://pestphp.com) y son tests de **integración**: cargan
un WordPress real en lugar de simularlo. El código se apoya en una docena de
funciones del núcleo, y simularlas costaría más que ejecutarlas, además de
probar los dobles en vez del comportamiento.

```bash
docker exec -w /var/www/html/wp-content/packages/forja acf-wordpress-1 \
    php vendor/bin/pest
```

Si tu WordPress no está en `/var/www/html`, indícalo con la variable de entorno
`FORJA_WP_LOAD`.

Cubren desde el saneado de cada tipo hasta el ciclo de guardado completo —envío,
nonce, permisos, validación y escritura— en entradas, términos y usuarios. Ese
último bloque es el que comprueba las garantías que ninguna pieza suelta puede
demostrar por sí misma: que un envío sin nonce no borra nada, que un valor
inválido conserva el anterior, y que un repetidor más corto limpia las filas que
sobran.

### Tests de navegador

Pest y el comparador miran lo que emite el servidor. Ninguno ejecuta el
TypeScript del paquete, y ahí es donde vive la mitad del comportamiento: si un
botón deja de responder, el markup sigue siendo correcto y nadie se entera.

Eso lo cubre Playwright, contra el WordPress de desarrollo y el tema
`forja-test`:

```bash
# Una vez: crea el usuario con el que entran los tests
docker exec -w /var/www/html acf-wordpress-1 \
    php wp-content/packages/forja/tools/e2e-user.php

bun run test:e2e        # o test:e2e:ui para verlos correr
```

Se configuran con variables de entorno: `FORJA_E2E_URL` (por defecto
`http://localhost:8080`), `FORJA_E2E_USER`, `FORJA_E2E_PASS` y `FORJA_E2E_TERM`.

Prueban sobre la pantalla de una **categoría**, no sobre la de entradas. El
editor de bloques esconde los metaboxes en un cajón plegable cuyo control cambia
de estructura entre versiones; la de taxonomías es clásica y el código que
interesa —clonar una fila y arrancarle los campos— es exactamente el mismo.

### Traducciones

Las cadenas usan el dominio `forja-fields`. La plantilla se regenera con:

```bash
docker exec -w /var/www/html/wp-content/packages/forja acf-wordpress-1 \
    composer make-pot
```

Escribe `languages/forja-fields.pot` y **sale con error** si encuentra una
cadena con otro dominio o construida dinámicamente: en ambos casos esa cadena no
se traduciría nunca. Un test lo ejecuta en cada pasada, así que la plantilla no
se queda atrás sin que nadie lo note.

Una advertencia: los `msgid` están **en español**, no en inglés. Los textos
visibles reproducen los de ACF para que el markup sea idéntico, y el WordPress
de referencia está en español. Traducir es, por tanto, español → idioma destino.
Es válido en gettext, aunque no sea la convención de wordpress.org.

Para traducir, coloca el `.mo` en `languages/forja-fields-{locale}.mo`.

### Comprobar la paridad con ACF

Con Secure Custom Fields presente en la instalación, esta herramienta pinta los
mismos campos con las dos implementaciones y compara el markup resultante:

```bash
docker exec -w /var/www/html acf-wordpress-1 \
    php wp-content/packages/forja/tools/compare-with-scf.php
```

Sirve como test de regresión: si un cambio se desvía del original, sale ahí.

## Publicar una versión

Lo que se distribuye son los **fuentes**: `assets/build/` está en `.gitignore` a
propósito, porque es el tema quien compila.

```bash
bun run typecheck                   # tipos
composer lint                       # estándares de código
git tag -a v0.2.0 -m "Forja 0.2.0"
git push origin main --tags
```

Para consumirlo sin Packagist, basta con declarar el repositorio en el tema:

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:apros/forja.git" }
    ],
    "require": { "apros/forja": "^0.2" }
}
```

## Documentación

Este README es la guía de uso: qué se puede declarar y qué devuelve. El **porqué**
de cada decisión vive en los otros dos documentos.

| Documento | Qué contiene |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Cómo encajan las piezas, las decisiones de diseño con su razón, las dependencias externas y los criterios de seguridad |
| [ROADMAP.md](ROADMAP.md) | Estado de cada fase y la tabla de decisiones tomadas, para no rediscutirlas |
| [CLAUDE.md](CLAUDE.md) | Contexto para quien retome el proyecto: las reglas que condicionan el código y cómo se verifica un cambio |

Si una decisión te sorprende al leer el código, búscala en `ARCHITECTURE.md`
antes de cambiarla: casi todas responden a algo que se probó y no funcionaba.

## Licencia

GPL-2.0-or-later.

Forja porta markup y estilos de [Secure Custom Fields](https://github.com/WordPress/secure-custom-fields),
publicado bajo GPLv2. Copyright de la obra original: WordPress.org y Advanced Custom Fields.
