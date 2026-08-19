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

Eso es todo. Forja se arranca sola: el autoload de Composer incluye su
`bootstrap.php`, que la engancha a WordPress en `after_setup_theme`.

Los assets compilados viajan dentro del paquete, así que `composer install` en un
servidor de despliegue no necesita Bun ni Node.

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
| `label_placement` | `'top'` | `top` o `left` (el CSS de `left` aún no está portado) |

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
| `message` | `message`, `esc_html`, `new_lines` | Sólo presentación, no guarda nada |
| `separator` | — | Sólo presentación; la etiqueta titula la sección |
| `tab` | `selected`, `endpoint` | Agrupa los campos que le siguen en una pestaña |
| `accordion` | `open`, `multi_expand`, `endpoint` | Anida los campos que le siguen en un panel plegable |

Todos aceptan además `readonly` y `disabled`.

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

## Documentación

- [ROADMAP.md](ROADMAP.md) — plan de trabajo y estado de cada fase
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — cómo encajan las piezas

## Licencia

GPL-2.0-or-later.

Forja porta markup y estilos de [Secure Custom Fields](https://github.com/WordPress/secure-custom-fields),
publicado bajo GPLv2. Copyright de la obra original: WordPress.org y Advanced Custom Fields.
