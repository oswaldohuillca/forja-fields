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
    Iconify.php           Resuelve nombres de icono a SVG, con caché, uno a uno o por lotes

  Ajax/
    Search.php            Búsqueda remota de los campos relacionales
    Icons.php             Intermediario del selector de iconos con Iconify

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
de `WP_PLUGIN_DIR`, y aquí el paquete vive en `themes/mi-tema/vendor/forja-wp/forja-fields/`.
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

### Un campo que toca el objeto lo recibe como argumento

Casi todos los campos son autosuficientes: reciben un valor, lo sanean y lo
guardan bajo su clave. `taxonomy` con `save_terms` no lo es. Además del
metadato, tiene que **asignar el término a la entrada**, que es lo que hace que
salga en sus archivos y en las consultas por taxonomía.

Eso obliga a conocer el objeto en curso, y ningún campo lo conocía: `sanitize()`
recibe un valor suelto y `Composite::write_value()`, un almacén ya ligado al
objeto pero sin decir a cuál.

La salida fácil era guardar el objeto en el campo con un setter antes de usarlo.
Es también la peor: **las instancias de campo se comparten**. `Box` las construye
una vez y las reutiliza para lo que haga falta en esa petición, así que un
`->set_object()` seguido de una llamada crea un estado que puede quedarse
colgado y contaminar la siguiente lectura.

En su lugar, la interfaz `ObjectAware` recibe el objeto **como argumento**:

```php
public function read_from_object( int|string $object_id, string $object_type ): mixed;
public function write_to_object( int|string $object_id, string $object_type, mixed $value ): void;
```

Quien lo aporta es quien ya lo tiene: `Context` al pintar y al guardar, y
`forja_get_field()` al leer desde la plantilla. El campo sigue sin estado.

Para que el contexto pudiera decirlo, se añadió `Context::object_type()`. Antes
cada contexto repetía su literal en dos sitios (`storage->for( 'post' )`); ahora
lo declara una vez.

Dos consecuencias que conviene tener presentes:

- **`read_from_object()` devuelve null cuando no aplica**, y entonces se usa el
  metadato. Así el mismo camino sirve para un campo con `load_terms` y para uno
  sin él, sin ramas en quien llama.
- **Dentro de un repetidor no funciona.** Los subcampos se guardan a través de
  `Composite::write_value()`, que no recibe el objeto. Un `taxonomy` en una fila
  guarda su metadato y nada más; está documentado en el README.

### Los campos compuestos deciden sus propias claves

Un campo normal es una clave y un valor. Un repetidor ocupa una clave por
subcampo y fila, así que necesita decidir por su cuenta qué leer y qué escribir:
para eso está la interfaz `Composite`.

El contexto le pasa las tres operaciones del almacén (`get`, `set`, `delete`) ya
ligadas al objeto en curso, de modo que el campo no sabe si guarda en un post,
un término o una página de opciones.

`write_value()` **devuelve los errores** encontrados. Un compuesto que no valida
no escribe nada, igual que un campo simple.

**Un compuesto puede ir dentro de otro.** El exterior no guarda al interior como
un valor: le pasa las tres operaciones del almacén con el prefijo de su
posición, y el interior compone el resto con su lógica de siempre. Un repetidor
`items` en la fila 0 de `bloques` acaba en `bloques_0_items` y
`bloques_0_items_0_item`, el formato de ACF, sin saber que está anidado. Lo
comparten los tres compuestos en el rasgo `StoresSubFields`. Antes cada uno
guardaba sus subcampos con `sanitize()`, que con un array avisaba de «Array to
string conversion». El aviso salía antes de las cabeceras y rompía la
redirección del guardado.

Al quitar una fila, sus subcompuestos borran todas sus claves con
`delete_value()`. Sin eso quedarían huérfanas y reaparecerían al volver a
crecer la lista.

En el navegador, el anidamiento destapó dos fallos más:

- **Los eventos suben.** El repetidor exterior atendía también los clics del
  interior, y «Añadir» en el interior añadía además una fila al exterior. Cada
  repetidor y cada contenido flexible ignoran ahora lo que pertenece a un
  compuesto anidado, en los clics y en el arrastre.
- **El reindexado tocaba otro nivel.** Al renumerar se cambiaba la primera
  aparición del índice anterior en cada `name`. En
  `bloques[1][items][1][item]`, «la primera `[1]`» es la del nivel exterior.
  `reindex.ts` cambia solo el segmento que va justo detrás del nombre base del
  campo, que se lee de su campo oculto.

### Las plantillas de filas y capas se pintan sin `required`

El repetidor pinta una fila plantilla (`acfcloneindex`) y el contenido flexible
una por capa, ocultas dentro del formulario, para que el JavaScript las clone al
añadir. El navegador valida también los controles ocultos. Un subcampo con
`required` dejaba la plantilla vacía e inválida, y «Actualizar» cancelaba el
envío sin ningún mensaje.

Mientras se pinta una plantilla, `Html::template()` hace que `Html::attributes()`
emita `required` como `data-forja-required`. Al clonar, `initClonedRow()` lo
convierte otra vez en `required`, pero no en las plantillas anidadas que trae
la fila (la de un repetidor dentro de otro), que siguen siendo plantillas. Es un
contador y no un booleano, porque las plantillas se anidan.

Se descartaron dos alternativas:

- **`disabled` en los controles de la plantilla.** Tampoco se validan ni se
  envían, pero `disabled` ya es una opción declarable de varios campos. Al
  clonar, el JavaScript no sabría cuáles quitar sin una segunda marca, así que
  acaba siendo esta misma solución con una pieza más.
- **Reutilizar `data-required`.** Ya existe en el envoltorio `.acf-field`, y
  convertirlo pondría `required` en un `<div>`.

Hacerlo en `Html::attributes()` y no en cada tipo de campo cubre todos los
controles que emiten `required`, también los que se añadan después, siempre que
pasen por ahí. La plantilla sigue viajando en el envío, y `write_value()` la
descarta como siempre.

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

### El campo `link` toma prestado el modal del núcleo

`wpLink` está pensado para insertar un enlace en un editor, no para rellenar
campos. Forja le da un `<textarea>` oculto de usar y tirar como editor, rellena
el modal al abrirse y lee sus campos al cerrarse. Dos detalles de `wplink.js`
condicionan cómo se hace, y los dos se descubrieron por un fallo:

**Los eventos se escuchan sobre `document`.** El núcleo dispara `wplink-open` y
`wplink-close` con `$( document ).trigger()`. En jQuery un evento sube del
elemento hacia el documento, nunca baja, así que escuchando en
`document.documentElement` los manejadores no se ejecutaban: el modal se abría
vacío y al aceptar el campo se quedaba sin valor.

**Aceptar se detecta por el área de texto, no por el botón.** `wpLink` emite el
mismo `wplink-close` al aceptar y al cancelar. ACF los distingue mirando si el
puntero o el foco están sobre `#wp-link-submit`, y se portó así, pero **falla
con el teclado**. Al pulsar Intro en la URL, ni el puntero ni el foco están en el
botón, y el enlace se descartaba como si se hubiera cancelado. Lo fiable es lo
que hace `htmlUpdate()` al aceptar: escribe el `<a>` en el área de texto
**antes** de cerrar. Al cancelar no la toca, y si la URL está vacía el modal ni
siquiera se cierra. Por eso un área de texto con contenido significa «aceptado».

**El campo imprime el modal si nadie lo ha hecho.** Encolar `wplink` solo trae
el script. El HTML de `#wp-link-wrap` lo imprime `_WP_Editors::wp_link_dialog()`,
y el núcleo solo lo llama cuando hay un editor en la página. En un tipo de
contenido sin `editor`, en un término, en un perfil o en una página de opciones
el botón no abría nada. Por eso `render_input()` engancha `Link::print_dialog()`
a `admin_print_footer_scripts`. La guarda estática `$link_dialog_printed` del
núcleo evita el duplicado cuando además hay un editor, llame quien llame
primero. Engancharlo desde `render_input()` cubre también las filas y capas
nuevas, porque sus plantillas se pintan en el servidor con la página.

Los tests de navegador del campo corren sobre dos tipos de contenido del tema de
prueba sin `show_in_rest`: `forja_clasico`, con editor, y `forja_sin_editor`,
sin él. El de término usa etiquetas y no categorías, porque en el tema las
categorías tienen otras cajas con editor y el núcleo imprimiría el modal de
todas formas: el test pasaría también sin el arreglo.

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
| `icon_picker` | `api.iconify.design`, siempre desde el servidor | El buscador deja de encontrar. Los iconos ya cacheados se siguen pintando, en el escritorio y en la parte pública |
| `oembed` | El endpoint `oembed/1.0/proxy` del núcleo, que a su vez llama al proveedor | La vista previa queda vacía |
| `wysiwyg` con `table` | Nada externo: el plugin viaja en `assets/vendor/` | — |

### Por qué el catálogo de iconos no se empaqueta

Las colecciones completas de Iconify pasan de 100 MB, y una sola —`mdi`— son
3,1 MB de JSON. Nada de eso tiene sentido dentro de un paquete de Composer.

Los iconos se piden a la API cuando hacen falta. Iconify es autoalojable: el
filtro `forja/iconify_api` apunta a una instancia propia cuando el proyecto no
puede depender de un servicio externo. Lo usa sólo el servidor; el navegador
nunca habla con Iconify (ver la sección siguiente).

### Por qué el selector pide los iconos a través del servidor

Al principio el navegador consultaba la API directamente, como icones.js.org:
la API admite CORS y cada miniatura era un `<img>` apuntando a su `.svg`. Eso
son **96 peticiones por página de resultados**. Unas cuantas búsquedas bastaban
para que Cloudflare, delante de `api.iconify.design`, bloqueara la IP un rato
(HTTP 429, «error code: 1015»). El navegador recibía texto plano en lugar de
SVG, lo descartaba por ORB, y las miniaturas salían rotas.

Hay que contar con dos limitaciones de la API. No admite varias colecciones en
una petición, solo `/{prefijo}.json?icons=a,b,c` para una. Y una página de
«home» mezcla unas 30 a 50 colecciones.

Ahora el navegador le pide todo a este WordPress, por admin-ajax
(`Ajax\Icons`):

- **Una petición por búsqueda.** El servidor la reenvía y la recuerda un día.
- **Una petición por página de miniaturas**, con los 96 nombres. El servidor
  saca de la caché lo que tiene y pide el resto agrupado por colección, todas
  las colecciones en paralelo (`Requests::request_multiple`). Cada icono se
  guarda 30 días con la misma clave que usa `Iconify::svg()`, así que el que
  elige el editor ya está en caché para la parte pública.
- **El SVG llega dentro del JSON** y se pinta como `<img>` con una dirección
  `data:`. Una imagen no ejecuta nada de lo que traiga el SVG, aunque el saneado
  fallara.

Medido en este entorno: la primera página de «home», en frío, unos 4 segundos;
repetida, 0,6. Con la API directa, una búsqueda eran 73 peticiones desde el
navegador antes incluso de pasar de página. Ahora son cero.

Se descartaron dos alternativas:

- **Agrupar por colección en el navegador** y construir allí el SVG desde el
  JSON. Pasa de 96 peticiones por página a unas 40: reduce el riesgo a la
  mitad, pero no lo elimina, y cada editor vuelve a pedir lo mismo.
- **Un intermediario que sirve cada miniatura como un `.svg` aparte**, como el
  de referencia del tema intriga. Funciona, pero cada miniatura arranca
  WordPress, 96 veces por página. Para no repetir el lote de una colección
  entre esas peticiones simultáneas hace falta un candado. Para esperarlo hay
  que leer el transitorio de la base de datos saltándose la caché de opciones.
  Y como `<img>` no manda el nonce de la API REST, los permisos se comprueban
  leyendo la cookie a mano. Pidiendo la página entera en una petición
  desaparecen las tres cosas: no hay peticiones simultáneas que coordinar, y
  `fetch()` sí manda el nonce.

Va por admin-ajax y no por la API REST por lo mismo que `Ajax\Search`: lo
piden pantallas del escritorio con nonce, y funciona igual con los enlaces
permanentes «simples», donde la URL de la API REST lleva `?rest_route=`.

**El JSON se convierte a SVG en el servidor** (`Iconify::from_collection()`):
`body`, las dimensiones y el desplazamiento del icono o, si no los trae, los de
la colección (16 × 16 en el origen por defecto), y los `aliases` siguiendo su
`parent`. El resultado se comprobó contra el `.svg` de la API y sale idéntico
byte a byte. Los iconos con `hFlip`, `vFlip` o `rotate` no se construyen: son
raros, y uno mal girado es peor que pedirlo hecho, así que se piden en `.svg`.

**Las colecciones animadas no se ofrecen** (`line-md`, `svg-spinners`). Sus
trazos empiezan ocultos y aparecen con `<animate>`, que el saneado quita al
incrustar el icono. En la parte pública se verían como una raya suelta.

**La parte pública no pasa por el intermediario.** `Iconify::svg()` sigue
descargando del servidor directo a Iconify: si pasara por aquí, WordPress se
llamaría a sí mismo por cada icono.

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
con una lista blanca de etiquetas y atributos: formas, gradientes, máscaras y
recortes, y nada de `script`, `foreignObject`, enlaces, animaciones ni
manejadores de eventos. Un detalle: `wp_kses()` pasa los atributos a
minúsculas, y algunos de SVG distinguen mayúsculas (`viewBox`,
`gradientUnits`…), así que se restauran después. En HTML el navegador los
corregiría solo, pero no en un contexto XML: un feed, un sitemap o el `<img>`
con dirección `data:` de las miniaturas.

**El intermediario de iconos no está abierto.** Pide el nonce de la pantalla y
la capacidad `edit_posts`, y no se registra para anónimos: si no, cualquiera
podría usar el sitio como intermediario gratuito de Iconify. Acepta como mucho
200 nombres por petición, y cada uno se valida antes de formar una URL.

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
