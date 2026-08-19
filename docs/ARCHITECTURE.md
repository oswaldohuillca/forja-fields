# Arquitectura

## Principio rector

Forja separa tres responsabilidades que en ACF están entrelazadas:

1. **Qué campos existen** — el catálogo de tipos.
2. **Cómo se pintan** — el envoltorio común, idéntico para todos los tipos.
3. **Dónde se guardan** — la tabla de destino según el objeto contenedor.

Mantenerlas separadas es lo que permite añadir un tipo de campo nuevo sin tocar
el render, y añadir un contexto nuevo (taxonomías, usuarios) sin tocar los campos.

## Mapa de directorios

```
bootstrap.php             Punto de entrada; lo incluye el autoload de Composer
includes/api.php          API pública: las únicas funciones que usa un proyecto
src/
  Plugin.php              Contenedor: construye e inyecta las dependencias
  Paths.php               Traduce la ruta en disco a URL pública
  Assets.php              Encolado del CSS y JS compilados
  Registry/
    FieldRegistry.php     Catálogo tipo → clase; construye instancias de campo
    Box.php               Un grupo de campos y su destino
    BoxRegistry.php       Todos los grupos declarados; consulta por contexto
  Fields/
    Field.php             Clase base: configuración, saneado, identificadores
    Text.php              Campo de texto
    Textarea.php          Campo multilínea
  Render/
    Renderer.php          Port de acf_render_field_wrap(); AQUÍ vive la paridad
    Html.php              Escapado de atributos
  Storage/
    Storage.php           Contrato get/update/delete
    MetaStorage.php       post, term, user y comment
    OptionStorage.php     Páginas de opciones
    StorageFactory.php    Resuelve la implementación por tipo de objeto
  Context/
    PostContext.php       add_meta_box() y guardado en la pantalla de entradas
assets/
  src/css/tokens.css      Tokens portados de _variables.scss
  src/css/forja-input.css Estilos del render de campos
  src/js/forja-input.ts   Comportamiento en el admin
  build/                  Generado por Vite; SÍ se versiona (viaja en el paquete)
```

## Flujo de una petición

**Al pintar la pantalla de edición:**

```
add_meta_boxes
  └─ PostContext::add_meta_boxes()
       ├─ BoxRegistry::for_context('post', $post_type)   ¿qué cajas aplican?
       └─ add_meta_box()  +  filtro postbox_classes_* → añade .acf-postbox

(WordPress pinta el postbox)
  └─ PostContext::render_meta_box()
       ├─ wp_nonce_field()            un nonce por caja
       ├─ Storage::get()              valores actuales
       └─ Renderer::render_fields()
            └─ Renderer::render_field_wrap()   markup exterior, idéntico a ACF
                 └─ Field::render_input()      sólo el control
```

**Al guardar:**

```
save_post
  └─ PostContext::save()
       ├─ descarta autosaves, revisiones y usuarios sin permiso
       ├─ por cada caja: verifica SU nonce
       │    (si el nonce no viaja, la caja no se pintó → se salta, no se borra nada)
       └─ Field::sanitize()  →  Storage::update()
```

## Decisiones de diseño

### Es una librería, y eso condiciona el arranque

Forja no es un plugin: se instala con Composer dentro de un tema. De ahí salen
dos requisitos que un plugin no tiene.

**No se puede usar `plugin_dir_url()`.** Esa función asume que el archivo cuelga
de `WP_PLUGIN_DIR`, y aquí el paquete vive en `themes/mi-tema/vendor/apros/forja/`.
`Paths` deduce la URL comparando la ruta normalizada del paquete contra
`WP_CONTENT_DIR` y `ABSPATH`, con un filtro `forja/base_url` para los casos raros
(enlaces simbólicos, contenido fuera del árbol de WordPress).

**Puede haber más de una copia cargada.** Composer deduplica dentro de un mismo
`vendor/`, pero no entre el `vendor/` del tema y el de un plugin que también
incluya Forja. Por eso `bootstrap.php` no arranca nada al incluirse: cada copia
se limita a anunciarse con su versión y su ruta, y en `after_setup_theme` —el
primer momento en que ya se cargaron los plugins y el `functions.php` del tema—
arranca sólo la más alta. Es el mismo mecanismo de CMB2.

### El envoltorio es del renderer, no del campo

`Renderer::render_field_wrap()` produce el `<div class="acf-field">` con su
etiqueta, instrucciones y modificadores de ancho. Un tipo de campo sólo
implementa `render_input()`.

Esto no es preferencia estética: las ~9.600 líneas de CSS portadas dependen de
esa estructura DOM exacta. Centralizarla en un único método significa que ningún
tipo de campo puede romper la paridad visual por su cuenta.

### El almacenamiento se abstrajo desde el primer día

Las cuatro tablas de metadatos de WordPress comparten API, así que `MetaStorage`
las cubre todas parametrizando el tipo. `OptionStorage` cubre las páginas de
opciones. Un campo nunca sabe dónde acaba su valor.

Añadir esto al principio cuesta unas 80 líneas; retrofitearlo después de tener
treinta tipos de campo escritos es un refactor doloroso.

### Un nonce por caja, y su ausencia significa «no toques nada»

Si el nonce de una caja no llega en el `$_POST`, esa caja no se pintó en el
formulario que se está enviando. Puede ser una edición rápida, una escritura vía
REST o una importación. Saltarla en lugar de procesarla evita el fallo clásico
de borrar datos existentes al guardar desde una pantalla que no incluía el campo.

### Sin reglas de ubicación

ACF necesita 25 clases en `includes/locations/` porque su panel ofrece un
desplegable de condiciones que hay que evaluar en tiempo de ejecución. Al
declarar los grupos por código, el destino se indica directamente en
`object_type` y `object_subtypes`, y todo ese subsistema desaparece.

### El build usa el modo librería de Vite

Los scripts se encolan con `wp_enqueue_script()` como scripts clásicos, no como
módulos ES, así que la salida debe ser IIFE. Rollup no admite IIFE con varias
entradas, de modo que cada bundle tiene su propia entrada e importa su CSS.

El modo librería es además lo que hace que Vite **extraiga** el CSS a un archivo
propio; en modo normal lo inyecta desde el JS mediante un `<style>`, lo que
impediría encolarlo con `wp_enqueue_style()`.

Cuando haya varios bundles, la configuración pasará a un bucle de builds, uno
por entrada.

## Cómo añadir un tipo de campo

1. Crea la clase en `src/Fields/`, extendiendo `Forja\Fields\Field`.
2. Implementa `type()` y `render_input()`.
3. Sobrescribe `defaults()` si el tipo tiene opciones propias.
4. Sobrescribe `sanitize()` si el saneado por defecto no sirve.
5. Regístrala en el constructor de `FieldRegistry`, o desde fuera con
   `forja_register_field_type()` en el hook `forja/register_field_types`.

No toques el renderer: si el campo necesita markup exterior distinto, es señal
de que la diferencia debería resolverse con una clase CSS, no con otro envoltorio.
