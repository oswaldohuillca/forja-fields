# Arquitectura

## Principio rector

Forja separa cuatro responsabilidades que en ACF están entrelazadas:

1. **Qué campos existen** — el catálogo de tipos.
2. **Cómo se pintan** — el envoltorio común, idéntico para todos los tipos.
3. **Dónde se guardan** — la tabla de destino según el objeto contenedor.
4. **Dónde aparecen** — la pantalla del escritorio que los monta.

Mantenerlas separadas es lo que permite añadir un tipo de campo sin tocar el
render, y añadir una pantalla nueva sin tocar los campos.

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
    FieldSets.php         Listas de campos con nombre, para clonarlas
    CloneResolver.php     Sustituye cada `clone` por los campos a los que apunta

  Fields/
    Field.php             Clase base: configuración, saneado, formato, reglas
    Composite.php         Contrato de los campos que ocupan varias claves
    TextInput.php         Base de text, email, url, password y number
    ChoiceField.php       Base de select, radio, checkbox y button_group
    MediaField.php        Base de image, file y gallery
    DateTimeField.php     Base de los tres selectores de fecha y hora
    …                     Un archivo por tipo concreto

  Render/
    Renderer.php          Port de acf_render_field_wrap(); AQUÍ vive la paridad
    Layout.php            Agrupa la lista plana en pestañas y acordeones
    Html.php              Escapado de atributos

  Storage/
    Storage.php           Contrato get/update/delete
    MetaStorage.php       post, term, user y comment
    OptionStorage.php     Páginas de opciones
    StorageFactory.php    Resuelve la implementación por tipo de objeto

  Context/
    Context.php           Base: leer, sanear, validar, escribir y nonces
    PostContext.php       Entradas y CPTs
    TermContext.php       Alta y edición de términos
    UserContext.php       Perfiles de usuario
    OptionsContext.php    Páginas de ajustes propias

  Validation/
    Validator.php         Campos obligatorios y punto de extensión

  Icons/
    Iconify.php           Resuelve nombres de icono a SVG, con caché

assets/
  src/css/                Un archivo por responsabilidad; la entrada sólo importa
  src/js/modules/         Un archivo por comportamiento
  src/js/types/           Superficie de las APIs de WordPress, en un único sitio
  vendor/tinymce/table/   Plugin de tablas que WordPress no empaqueta
  build/                  Generado por Vite; no se versiona

tools/
  compare-with-scf.php    Compara el markup contra ACF/SCF, caso a caso
```

## Flujo de una petición

**Al pintar una pantalla:**

```
(el hook depende del contexto)
  └─ Context::…
       ├─ BoxRegistry::for_subtype()      ¿qué cajas aplican?
       ├─ Box::matches_object()           ¿aplica a ESTE objeto?
       ├─ Context::read()                 valores actuales
       └─ Renderer::render_fields()
            ├─ Layout::parse()            agrupa pestañas y acordeones
            └─ Renderer::render_field_wrap()   markup exterior, idéntico a ACF
                 └─ Field::render_input()      sólo el control
```

**Al guardar:**

```
(save_post, edited_term, profile_update, o el envío de una página de opciones)
  └─ Context::…
       ├─ comprueba permisos
       ├─ verifica el nonce de CADA caja
       │    (si no viaja, la caja no se pintó → se salta, no se borra nada)
       └─ Context::write()
            ├─ Field::sanitize()      normaliza lo que entra
            ├─ Validator::validate()  required + reglas del tipo
            └─ Storage::update()      o Composite::write_value()
```

**Al leer desde una plantilla:**

```
forja_get_field()
  ├─ BoxRegistry::find_field()   ¿de qué campo es esta clave?
  ├─ Storage::get()              o Composite::read_value()
  └─ Field::format_value()       da forma al valor
```

## Decisiones de diseño

### Es una librería, y eso condiciona el arranque

Forja no es un plugin: se instala con Composer dentro de un tema. De ahí salen
tres requisitos que un plugin no tiene.

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
arranca sólo la más alta.

**`bootstrap.php` no puede salir antes de tiempo.** Composer lleva el registro de
los archivos de autoload en `$GLOBALS['__composer_autoload_files']`, que es
**global entre autoloaders**: dos `vendor/` con el mismo paquete comparten
identificador, así que el archivo se incluye una única vez en toda la petición.
Si esa vez ocurriera antes de que WordPress esté cargado —una herramienta de
línea de comandos, otro paquete que arranque antes— y saliéramos ahí, ya no
habría segunda oportunidad. Por eso se registra siempre y sólo se difiere lo que
necesita WordPress.

### El envoltorio es del renderer, no del campo

`Renderer::render_field_wrap()` produce el `<div class="acf-field">` con su
etiqueta, instrucciones y modificadores de ancho. Un tipo de campo sólo
implementa `render_input()`.

No es preferencia estética: las líneas de CSS portadas dependen de esa
estructura DOM exacta. Centralizarla en un único método significa que ningún
tipo de campo puede romper la paridad visual por su cuenta.

El envoltorio admite tres formas, igual que ACF:

| Elemento | Dentro | Dónde se usa |
|---|---|---|
| `div` | dos `div` | lo habitual |
| `tr` | dos `td` | `form-table` del escritorio: perfiles, edición de términos |
| `td` | un `div`, **sin etiqueta** | celdas de la tabla de un repetidor |

En el caso `td` la etiqueta se omite porque ya vive en la cabecera de la
columna, y de eso depende el ancho.

### El almacenamiento se abstrajo desde el primer día

Las cuatro tablas de metadatos de WordPress comparten API, así que `MetaStorage`
las cubre todas parametrizando el tipo. `OptionStorage` cubre las páginas de
opciones. Un campo nunca sabe dónde acaba su valor.

Añadir esto al principio cuesta unas 80 líneas; retrofitearlo después de tener
treinta tipos de campo escritos es un refactor doloroso.

### Los campos compuestos deciden sus propias claves

Un campo normal es una clave y un valor. Un repetidor ocupa una clave por
subcampo y fila, así que necesita decidir por su cuenta qué leer y qué escribir:
para eso está la interfaz `Composite`.

El contexto le pasa las tres operaciones del almacén (`get`, `set`, `delete`) ya
ligadas al objeto en curso, de modo que el campo no sabe si guarda en un post,
un término o una página de opciones.

`write_value()` **devuelve los errores** encontrados. Un compuesto que no valida
no escribe nada, igual que un campo simple.

### El `clone` se resuelve al registrar, y luego deja de existir

Es el campo que más se aparta de ACF, y conviene entender por qué.

En ACF el clon tiene que existir en tiempo de ejecución. Los campos viven en la
base de datos, un grupo sólo puede referenciar a otro por su clave, y la
sustitución ocurre en cada petición mediante filtros. De ahí sale toda la
maquinaria del `class-acf-field-clone.php`: claves compuestas
(`clave_del_clon_clave_del_campo`), copias de seguridad en `__key`, `__name` y
`__label`, y un filtro que restaura la clave original al pintar para que la
lógica condicional del navegador siga encontrando su objetivo.

Aquí los campos se declaran por código, así que el clon se puede expandir **una
sola vez**, sobre las definiciones en crudo y antes de instanciar nada. Al
terminar `CloneResolver::expand()` no queda ningún campo de tipo `clone` en el
árbol: el renderer, el guardado y la lectura no saben que existió. Nada de lo
anterior hace falta.

`CloneResolver` no conoce ninguno de los dos registros. Recibe en el constructor
una función que traduce un identificador en definiciones, y `BoxRegistry` la
compone para que busque primero entre los conjuntos reutilizables y después
entre las cajas registradas. Las definiciones de cada caja se guardan sin tocar
en `BoxRegistry::$definitions`: clonar necesita el array en crudo, no instancias
de `Field`, porque cada copia puede ajustarse, renombrarse o prefijarse antes de
construirse.

#### Por qué los campos se construyen bajo demanda

La primera versión expandía al registrar, dentro de `BoxRegistry::register()`.
Funcionaba, pero imponía una regla difícil de recordar: la fuente tenía que
declararse antes que quien la clonaba. El orden dentro de un `functions.php`
largo es accidental, no una decisión, y esa regla convertía mover un bloque de
sitio en un fallo fatal.

Ahora `Box` guarda las definiciones y una función que las convierte en campos, y
`Box::fields()` la ejecuta la primera vez que se la llama, cacheando el
resultado. Para entonces `forja/register_boxes` ya terminó y el registro está
completo, así que **el orden deja de importar**. El cambio quedó dentro de `Box`:
los nueve puntos del paquete que consumen `Box::fields()` no se tocaron.

El precio es que los errores de declaración salen a la luz al pintar la pantalla
y no al arrancar, y que la traza apunta a quien pidió los campos en vez de a la
línea que los declaró. Por eso `BoxRegistry::build()` captura la excepción y la
relanza añadiendo el identificador de la caja: sin ese dato no habría por dónde
empezar a buscar.

#### Lo demás que conviene tener presente

- **Sin prefijo, las claves no cambian.** Un campo clonado guarda bajo su propio
  nombre, así que un sitio con datos de ACF se puede reorganizar en conjuntos
  reutilizables sin migrar ni un metadato.
- **`overrides` es lo que justifica el campo.** Ajustar la etiqueta o el carácter
  obligatorio de un campo concreto sin duplicar el conjunto es justo lo que ACF
  no puede hacer, porque allí una copia no se retoca sin duplicar el grupo. Un
  nombre que no existe en el conjunto lanza un error con la lista de los que sí:
  en silencio sería un ajuste que no se aplica sin decir por qué.
- **En `seamless` las condiciones se heredan.** Al desaparecer el clon, ACF
  pierde sus reglas de visibilidad; aquí pasan a los campos que no tengan las
  suyas. Es una divergencia deliberada.
- **Las combinaciones sin sentido fallan.** `prefix_name` junto a
  `display => 'group'` no hace nada, porque el grupo ya antepone su nombre a las
  claves. Se lanza un error en vez de ignorarlo: una opción declarada que no
  surte efecto se paga meses después.
- **Los ciclos se cortan.** Dos cajas que se clonan mutuamente se detectan por el
  rastro de referencias visitadas, y hay además un tope de anidamiento.

En muchos casos el clon no es necesario: compartir una lista de campos entre dos
cajas es una variable de PHP. Lo que `clone` aporta y una variable no son los
`overrides`, el prefijado de claves y etiquetas, el envoltorio en un `group` y
poder referenciar una caja por su identificador.

### Un nonce por caja, y su ausencia significa «no toques nada»

Si el nonce de una caja no llega en el `$_POST`, esa caja no se pintó en el
formulario que se está enviando. Puede ser una edición rápida, una escritura vía
REST o una importación. Saltarla en lugar de procesarla evita el fallo clásico
de borrar datos existentes al guardar desde una pantalla que no incluía el campo.

### Un valor inválido no sobrescribe lo guardado

Si alguien se salta el `required` del navegador o manda un adjunto que no
existe, su envío se ignora y se avisa. Nunca se borra un dato bueno por un envío
malo.

La validación tiene dos niveles:

- `Validator` se ocupa de `required`, que es común a todos los tipos, y expone
  el filtro `forja/validate_field` para reglas de proyecto.
- `Field::validate()` recoge lo que sólo tiene sentido para un tipo concreto:
  cuántas imágenes admite una galería, cuántas filas un repetidor.

### Sin reglas de ubicación

ACF necesita 25 clases en `includes/locations/` porque su panel ofrece un
desplegable de condiciones que hay que evaluar en tiempo de ejecución. Al
declarar los grupos por código, el destino se indica directamente en
`object_type`, `object_subtypes`, `templates`, `object_ids` y `condition`, y todo
ese subsistema desaparece.

### Las pestañas y los acordeones se resuelven en el servidor

ACF los monta con JavaScript, reestructurando el DOM después de cargar. Puede
hacerlo porque su servidor renderiza campo a campo sin saber qué viene después.

Nuestro renderer sí conoce la lista completa, así que `Layout` agrupa antes de
pintar nada. Menos código, sin parpadeo al cargar y sin depender de jQuery.

La diferencia entre ambos sí se respeta:

- El **acordeón anida**: sus campos van dentro de `.acf-input.acf-accordion-content`.
- La **pestaña no anida**. Sus campos siguen siendo hijos directos de
  `.acf-fields` y sólo se marcan con `data-forja-tab`. Envolverlos en un panel
  rompería la regla `.acf-fields > .acf-field`, que es la que les da padding y
  bordes.

### Los contextos comparten una clase base

Cada pantalla se engancha a hooks distintos y pinta en un sitio distinto, pero
leer, sanear, validar y escribir se hace igual en todas. Eso vive en `Context`.

Al escribir el tercer contexto la lógica ya estaba triplicada; extraerla dejó
`PostContext` en dos tercios de su tamaño.

Un detalle no obvio: `for_subtype()` devuelve las cajas cuyo subtipo esté vacío
**o** coincida, lo que sirve para post types y taxonomías. No sirve para
usuarios, donde el subtipo es el rol y el contexto compara contra la lista de
roles de la persona. Para eso está `for_object_type()`: «sin subtipo» y «sin
filtro» no significan lo mismo cuando el filtrado lo hace el contexto.

### El build usa el modo librería de Vite

Los scripts se encolan con `wp_enqueue_script()` como scripts clásicos, no como
módulos ES, así que la salida debe ser IIFE. Rollup no admite IIFE con varias
entradas, de modo que hay una entrada que importa el resto.

El modo librería es además lo que hace que Vite **extraiga** el CSS a un archivo
propio; en modo normal lo inyecta desde el JS mediante un `<style>`, lo que
impediría encolarlo con `wp_enqueue_style()`.

En la práctica el paquete distribuye **fuentes**, y es el tema quien las compila
dentro de su propio bundle. El filtro `forja/enqueue_assets` desactiva el
encolado propio de Forja.

## Servicios y dependencias externas

Tres campos dependen de algo que no está en el paquete. Conviene tenerlo
localizado.

| Campo | De qué depende | Qué pasa si falla |
|---|---|---|
| `icon_picker` | `api.iconify.design` | El buscador deja de encontrar. Los iconos ya cacheados se siguen pintando |
| `oembed` | El endpoint `oembed/1.0/proxy` del núcleo, que a su vez llama al proveedor | La vista previa queda vacía |
| `wysiwyg` con `table` | Nada externo: el plugin viaja en `assets/vendor/` | — |

### Por qué el catálogo de iconos no se empaqueta

Las colecciones completas de Iconify pasan de 100 MB, y una sola —`mdi`— son
3,1 MB de JSON. Nada de eso tiene sentido dentro de un paquete de Composer.

La API permite CORS y sirve cada icono en unos 150 bytes con caché inmutable de
una semana, así que el navegador consulta directamente, igual que hace
icones.js.org. Sin proceso de build y sin endpoint propio.

Iconify es autoalojable: el filtro `forja/iconify_api` apunta a una instancia
propia cuando el proyecto no puede depender de un servicio externo.

### Por qué el SVG se incrusta y no se pide con JavaScript

El componente web `iconify-icon` añadiría una dependencia de JavaScript para el
visitante y una petición por icono en cada carga. En su lugar, `Iconify::svg()`
descarga el icono una vez, lo guarda en un transitorio y lo devuelve para
incrustarlo en línea: sin JavaScript, sin salto de maquetado e indexable.

Los fallos también se cachean, pero sólo una hora: si la API está caída o el
nombre no existe, no tiene sentido reintentarlo en cada carga de cada página.

### El plugin de tablas de TinyMCE sí se empaqueta

WordPress trae TinyMCE 4.9.11 con 22 plugins, y `table` no está entre ellos —es
lo que añaden extensiones como «Advanced Editor Tools». El paquete incluye el
oficial de esa misma versión, bajo LGPL 2.1 y sin modificar.

Se registra al arrancar cada editor, **no** con el filtro `mce_external_plugins`:
ese filtro sólo lo aplica `wp_editor()`, y estos editores reciben sus ajustes de
`print_default_editor_scripts()`, que no lo tiene en cuenta.

## Seguridad

Tres puntos donde entra contenido que no controlamos.

**Todo valor enviado se sanea por tipo, y lo que no encaja se descarta.** Los
campos con opciones validan contra la lista declarada; los de medios comprueban
que el identificador sea un adjunto existente del tipo esperado; el color sólo
admite hexadecimal o `rgba()`. Un valor arbitrario que acabe interpolado en un
atributo `style` o en una URL es el fallo que se está evitando.

**El nombre de un icono acaba formando parte de una URL**, así que se valida
contra `^[a-z0-9-]+:[a-z0-9-]+$` antes de construirla.

**El SVG que devuelve Iconify entra en la página**, así que pasa por `wp_kses`
con una lista blanca de etiquetas y atributos: nada de `script`, `foreignObject`
ni manejadores de eventos. Un detalle: `wp_kses()` pasa los atributos a
minúsculas y `viewBox` distingue mayúsculas, así que se restaura después. En
HTML el navegador lo corregiría solo, pero no si el SVG acaba en un feed o un
sitemap.

**El `wysiwyg` sigue el criterio de WordPress**: quien tiene `unfiltered_html`
conserva su HTML, y al resto se le aplica `wp_kses_post()`.

## Tests

La suite usa Pest y son tests de **integración**: cargan un WordPress real en
lugar de simularlo. El código se apoya en una docena de funciones del núcleo, y
simularlas costaría más que ejecutarlas, además de probar los dobles en vez del
comportamiento.

`tests/Pest.php` carga `wp-load.php` antes que nada y expone dos ayudas:
`forja_test_field()` construye un campo suelto y `forja_test_render()` devuelve
su markup.

Aparte está `tools/compare-with-scf.php`, que pinta el mismo campo con Forja y
con SCF y enfrenta el resultado. Es la comprobación objetiva de la paridad: si
la estructura DOM coincide, el CSS portado se aplica igual. Las diferencias
deliberadas —atributos gancho de JavaScript que no portamos, el `rel="noopener"`
que añadimos de más— están normalizadas y documentadas dentro de la propia
herramienta, de modo que cualquier diferencia **nueva** salta.

## Cómo añadir un tipo de campo

1. Crea la clase en `src/Fields/`, extendiendo `Forja\Fields\Field` o una de las
   bases (`TextInput`, `ChoiceField`, `MediaField`, `DateTimeField`).
2. Implementa `type()` y `render_input()`.
3. Sobrescribe `defaults()` si el tipo tiene opciones propias, `sanitize()` si el
   saneado por defecto no sirve, `format_value()` si lo almacenado no es lo que
   debe recibir la plantilla, y `validate()` si tiene reglas propias.
4. Si ocupa varias claves de almacenamiento, implementa `Composite`.
5. Regístrala en el constructor de `FieldRegistry`, o desde fuera con
   `forja_register_field_type()` en el hook `forja/register_field_types`.
6. Si necesita estilos o comportamiento, añade `assets/src/css/fields/tu-campo.css`
   y `assets/src/js/modules/tu-campo.ts`, y una línea en cada entrada.

No toques el renderer: si el campo necesita markup exterior distinto, es señal
de que la diferencia debería resolverse con una clase CSS, no con otro
envoltorio.
