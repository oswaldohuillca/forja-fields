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
| `object_subtypes` | `array()` | Post types o taxonomías; vacío significa todos |
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
| `select` | `choices`, `multiple`, `allow_null` | Nativo; la versión con búsqueda llega en la Capa 2 |
| `radio` | `choices`, `layout`, `allow_null` | |
| `checkbox` | `choices`, `layout` | Guarda un array |
| `button_group` | `choices`, `layout`, `allow_null` | Radios estilizados como botones segmentados |
| `true_false` | `message`, `ui`, `ui_on_text`, `ui_off_text` | Guarda `1` o `0`; con `ui` pinta el interruptor |
| `date_picker` | `return_format`, `min`, `max` | Control nativo; se guarda como `Ymd` |
| `time_picker` | `return_format`, `min`, `max` | Control nativo; se guarda como `H:i:s` |
| `date_time_picker` | `return_format`, `min`, `max` | Control nativo; se guarda como `Y-m-d H:i:s` |
| `color_picker` | `enable_opacity`, `palette` | Selector del núcleo; hexadecimal, o `rgba()` con opacidad |
| `wysiwyg` | `tabs`, `toolbar`, `rows`, `media_upload`, `table` | TinyMCE; funciona también dentro de repetidores |
| `image` | `preview_size`, `library`, `mime_types`, `return_format` | Guarda el ID; se valida contra la mediateca |
| `file` | `library`, `mime_types`, `return_format` | Guarda el ID del adjunto |
| `message` | `message`, `esc_html`, `new_lines` | Sólo presentación, no guarda nada |
| `separator` | — | Sólo presentación; la etiqueta titula la sección |
| `tab` | `selected`, `endpoint` | Agrupa los campos que le siguen en una pestaña |
| `accordion` | `open`, `multi_expand`, `endpoint` | Anida los campos que le siguen en un panel plegable |
| `repeater` | `sub_fields`, `min`, `max`, `button_label` | Lista de filas; compatible con los datos de ACF |
| `group` | `sub_fields`, `layout` | Subcampos bajo un nombre común, sin repetición |
| `flexible_content` | `layouts`, `min`, `max`, `button_label` | Filas de distinta forma, a elegir por el editor |

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

- [ROADMAP.md](ROADMAP.md) — plan de trabajo y estado de cada fase
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — cómo encajan las piezas

## Licencia

GPL-2.0-or-later.

Forja porta markup y estilos de [Secure Custom Fields](https://github.com/WordPress/secure-custom-fields),
publicado bajo GPLv2. Copyright de la obra original: WordPress.org y Advanced Custom Fields.
