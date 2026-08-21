# Roadmap de Forja

Plan de trabajo por capas. **Cada casilla marcada está implementada y verificada**, así
que al retomar el proyecto basta con buscar la primera casilla vacía.

Convención:

- `[x]` hecho y comprobado
- `[ ]` pendiente
- `[~]` empezado pero incompleto

---

## Capa 0 — Cimientos ✅

Objetivo: una fila de campo visualmente idéntica a ACF, guardándose de verdad.

- [x] Estructura de carpetas del paquete
- [x] `composer.json` de tipo `library`, con PSR-4 (`Forja\` → `src/`) y `files` (`bootstrap.php`)
- [x] Composer instalado en el contenedor Docker
- [x] Arranque automático vía autoload, con selección de la versión más alta si hay varias copias
- [x] `Paths`: resolución de URL independiente de la ubicación (plugin, tema o mu-plugin)
- [x] Tema de prueba (`themes/forja-test`) que consume la librería con un repositorio `path`
- [x] Verificado de punta a punta: metabox pintado, assets servidos por HTTP, guardado y saneado
- [x] Toolchain de assets: Bun + Vite + TypeScript
- [x] Build verificado: CSS extraído a archivo propio, JS en formato IIFE
- [x] `tsconfig.json` en modo estricto; `bun run typecheck` pasa sin errores
- [x] Tokens de diseño portados de `_variables.scss` a custom properties
- [x] CSS del render portado: `acf-field`, `acf-fields`, `acf-postbox`, inputs
- [x] Capa de almacenamiento abstracta (post, term, user, comment, option)
- [x] Catálogo de tipos de campo (`FieldRegistry`)
- [x] Registro de grupos de campos (`BoxRegistry`, `Box`)
- [x] Renderer portado de `acf_render_field_wrap()` — markup verificado byte a byte
- [x] Contexto de posts: `add_meta_box()` + guardado con nonce por caja
- [x] API pública: `forja_register_box()`, `forja_get_field()`, `forja_the_field()`
- [x] Campos `text` y `textarea` funcionando de punta a punta
- [x] Paridad comprobada contra SCF: 14 casos de campo con markup idéntico (`tools/compare-with-scf.php`)
- [x] CSS de `.acf-fields.-left` portado (`label_placement => 'left'`)
- [x] PHPCS con WordPress-Extra y WordPress-Docs: 35 archivos sin errores ni avisos
- [x] Repositorio con la etiqueta `v0.1.0` y pasos de publicación documentados en el README
- [x] Distribución de assets resuelta: el paquete entrega fuentes y el tema los compila en su bundle (`forja/enqueue_assets`)

## Capa 1 — Campos sin dependencias de JS ✅

**Completa: 16 de 16.** Cubren la mayoría de proyectos a medida.

- [x] `text`
- [x] `textarea`
- [x] `number`
- [x] `range`
- [x] `email`
- [x] `url`
- [x] `password`
- [x] `true_false`
- [x] `radio`
- [x] `checkbox`
- [x] `button_group`
- [x] `select` (nativo, sin select2)
- [x] `message`
- [x] `separator`
- [x] `tab`
- [x] `accordion`

## Capa 2 — Campos con dependencias externas

Aquí entra el JS de verdad. Conviene abordarlos por familia, porque comparten
infraestructura.

- [x] Familia `wp.media`: `image`, `file` y `gallery`
- [x] Familia relacional: `post_object`, `page_link`, `relationship`, `taxonomy` y `user`, con búsqueda remota
- [x] Familia pickers: `date_picker`, `date_time_picker`, `time_picker`, `color_picker`
- [x] `wysiwyg` (TinyMCE, con tablas opcionales, funcional dentro de repetidores)
- [x] `link` (modal de enlaces del núcleo)
- [x] `oembed` (vista previa vía el endpoint REST del núcleo)
- [x] `icon_picker` (Iconify, sin build ni catálogo empaquetado)
- [ ] `google_map`

## Capa 3 — Campos compuestos

Los que más JS propio requieren. El `repeater` es el que aparece en la UI actual
de los editores, así que tiene prioridad dentro de esta capa.

- [x] `group`
- [x] `repeater` (tabla, añadir, quitar, reordenar y límites en servidor; sin paginación ni plegado)
- [x] `flexible_content` (capas, orden y límites; sin renombrar ni desactivar capas)
- [x] `clone` (conjuntos reutilizables, `seamless`, `group`, prefijos y `overrides`)

## Transversales

- [ ] Reevaluar el destino en vivo al cambiar la plantilla en el editor (hoy exige recargar)
- [x] Tests de navegador con Playwright: 35 casos sobre filas nuevas, iconos, relacionales, contenido flexible, condicionales en vivo, arrastre y el editor de bloques
- [x] `save_terms` y `load_terms` del campo `taxonomy`, con la interfaz `ObjectAware`
- [ ] Decidir si `assets/build/` se versiona: hoy `.gitignore` lo excluye, pero el comentario y `Assets.php` dan por hecho que viaja dentro del paquete para los temas sin bundler
- [x] Lógica condicional entre campos (grupos OR con reglas AND, con ámbito por fila)
- [x] Validación de campos requeridos en servidor (`Validation\Validator`); falta el aviso en cliente antes de enviar
- [x] Contexto de taxonomías (alta y edición de términos)
- [x] Contexto de usuarios (perfil, con filtrado por rol)
- [x] Páginas de opciones (`object_type => option`, con menú propio)
- [x] Compatibilidad de datos con ACF/SCF en el repetidor: lee y escribe `campo_N_subcampo`
- [x] Internacionalización: dominio `forja-fields` verificado y `languages/forja-fields.pot` generado con `composer make-pot`
- [x] Tests con Pest: 215 casos sobre saneado, medios, agrupado, clonado, validación, almacenamiento y el ciclo de guardado completo en entradas, términos y usuarios

---

## Decisiones tomadas

Registradas aquí para no volver a discutirlas en cada sesión.

| Decisión | Motivo |
|---|---|
| Librería de Composer, no plugin | Se consume desde el `functions.php` del tema. Los campos y el código que los usa se versionan juntos y nadie puede desactivarlos desde el escritorio. |
| El paquete distribuye fuentes, no artefactos | El tema importa `assets/src` en su propio bundle: un solo archivo, sin CSS duplicado y con el pipeline del proyecto al mando. El filtro `forja/enqueue_assets` desactiva el encolado propio de Forja. Si el paquete trae un build (`bun run build`), lo encola él, para temas sin bundler. |
| `Paths` en lugar de `plugin_dir_url()` | El paquete vive en el `vendor/` de un tema, no en `WP_PLUGIN_DIR`. |
| Gana la versión más alta si hay varias copias | El tema y otro plugin pueden traer cada uno la suya; Composer no deduplica entre `vendor/` distintos. |
| Sin panel de administración para crear campos | Los grupos se declaran por código, estilo CMB2. Elimina ~8.600 líneas de CSS y las 25 clases de reglas de ubicación de ACF. |
| Se conservan los nombres de clase CSS `acf-` | El requisito es paridad visual exacta. Renombrar el prefijo arriesga fallos visuales silenciosos en las ~9.600 líneas portadas. |
| Prefijo propio sólo en PHP (`forja_`, `forja/`) | Ahí el riesgo de colisión es nulo y la marca sí importa. |
| CSS puro con anidamiento nativo, sin Sass | El anidamiento nativo ya es seguro en los navegadores que soporta wp-admin. Quita una dependencia del toolchain. |
| Vite en lugar de webpack | Sin bloques de Gutenberg ni imports `@wordpress/*`, el `dependency-extraction-webpack-plugin` y los `.asset.php` dejan de hacer falta. |
| Bun en lugar de npm/Node | Decisión del proyecto. |
| Formato de salida IIFE, modo librería de Vite | Los scripts se encolan como scripts clásicos. El modo librería es lo que hace que Vite extraiga el CSS en vez de inyectarlo desde JS. |
| Un archivo de CSS y de TypeScript por responsabilidad | Añadir un tipo de campo no debe obligar a tocar un archivo compartido. La entrada sólo importa; Vite los une en un bundle. |
| El selector de iconos usa Iconify por API, sin empaquetar catálogo | Las colecciones completas pasan de 100 MB. La API permite CORS y sirve cada icono en ~150 bytes con caché inmutable de una semana, así que el navegador busca directamente, como hace icones.js.org. Sin build ni endpoint propio. |
| En la parte pública el SVG se incrusta, no se pide con JavaScript | El componente web de Iconify añadiría una dependencia para el visitante y una petición por icono en cada carga. Se descarga una vez, se guarda en un transitorio y se incrusta: sin JavaScript, sin salto de maquetado e indexable. |
| Un campo puede aportar sus propias reglas de validación | `Field::validate()`. El validador se ocupa de `required`, que es común; lo que sólo tiene sentido para un tipo —cuántas imágenes admite una galería— vive en el tipo. |
| La galería descarta al leer los adjuntos borrados | Un identificador huérfano obligaría a comprobarlo en cada iteración de la plantilla. WordPress cachea los objetos, y una galería tiene pocas imágenes. |
| El `oembed` guarda la dirección, no el HTML | El markup de un proveedor cambia con el tiempo; guardarlo dejaría el sitio con vídeos rotos. Se resuelve al pintar. |
| La vista previa del `oembed` usa el endpoint REST del núcleo | `oembed/1.0/proxy` ya resuelve proveedores y caché; registrar uno propio sería reimplementarlo peor. |
| El plugin de tablas se sirve desde el paquete | WordPress no lo empaqueta. Se incluye el oficial de la misma versión de TinyMCE (LGPL 2.1) en vez de exigir una extensión de terceros. |
| Las barras del editor se declaran enteras | Los ajustes de `wp.editor.getDefaultSettings()` vienen de `print_default_editor_scripts()`, pensados para el bloque clásico: una barra mínima que no es la del editor de entradas. |
| `bootstrap.php` no sale antes de tiempo si no hay WordPress | Composer sólo incluye el archivo una vez en toda la petición, aunque haya varios `vendor/`. Salir ahí dejaría el paquete sin arrancar para siempre. |
| El `wysiwyg` no usa `wp_editor()` | Esa función ata la configuración del editor al identificador del control, y dentro de un repetidor se duplicaría. Se emite un textarea pelado y el JavaScript arranca TinyMCE con `wp.editor.initialize()`, que es la API pensada para editores que aparecen después de cargar. |
| Fechas y horas con controles nativos, no con jQuery UI | `date`, `time` y `datetime-local` no añaden dependencias, funcionan en móvil y ya vienen traducidos. El formato de almacenamiento sigue siendo el de ACF, así que se convierte en ambos sentidos. |
| Las fechas se interpretan con `createFromFormat`, no con `strtotime` | `strtotime` acepta casi cualquier cosa: «2026-13-01» pasaría desplazándose a enero del año siguiente. |
| Las condiciones se resuelven en el navegador, no en el servidor | Tienen que reaccionar mientras se escribe. El servidor sólo emite las reglas como JSON en `data-conditions`. |
| Una regla que apunta a un campo inexistente nunca se cumple | Un nombre mal escrito oculta el campo y se nota, en lugar de pasar desapercibido dejándolo siempre visible. |
| Cada tipo devuelve su tipo nativo, no la cadena de la base de datos | `number` devuelve int o float, `true_false` bool, los medios int. Un `true_false` devolviendo «'0'» es una trampa: en PHP es una cadena no vacía. |
| Un `number` sin rellenar devuelve null, no cero | El cero es un valor legítimo; confundirlos impide distinguir «no lo tocaron» de «pusieron cero». |
| Los contextos comparten una clase base | Leer, sanear, validar y escribir es idéntico en las cuatro pantallas; sólo cambian los hooks y dónde se pinta. Al escribir el tercero ya se repetía tres veces. |
| En usuarios, `object_subtypes` filtra por rol | Es el subtipo natural de un usuario. Basta con que uno de sus roles encaje. |
| Los compuestos devuelven sus errores al escribir | `Composite::write_value()` devuelve mensajes en vez de void. Un compuesto que no valida no escribe nada, igual que un campo simple. |
| El repetidor guarda en el formato de ACF, no serializado | Una clave por subcampo y fila (`banner_0_titulo`). Permite leer lo que ya hay en un sitio existente sin migrar, y deja cada subcampo consultable con `meta_query`. |
| Reordenar usa la API nativa de arrastrar y soltar | Evita jQuery UI, que es la única razón por la que ACF lo necesita. |
| Tests de integración con Pest, no unitarios con dobles | El código se apoya en una docena de funciones del núcleo de WordPress; simularlas costaría más que ejecutarlas y probaría los dobles en vez del comportamiento. |
| Un valor inválido no sobrescribe el guardado | Si alguien se salta el `required` del navegador o manda un adjunto que no existe, su envío se ignora y se avisa, en vez de borrar un dato bueno. |
| Se adopta la redacción de ACF en los textos visibles | Los editores ya la conocen, y de paso el comparador puede exigir igualdad byte a byte. |
| Pestañas y acordeones se resuelven en el servidor | ACF los monta con JavaScript reestructurando el DOM. Como aquí el renderer conoce la lista completa de campos, puede emitir la estructura final directamente y dejar al JS sólo abrir y cerrar. |
| Los campos de una caja se construyen al primer uso, no al registrarla | `Box` guarda las definiciones y las convierte en campos la primera vez que se las piden. Así un `clone` puede apuntar a algo declarado después, y el orden del `functions.php` —que suele ser accidental— deja de romper. A cambio, los errores de declaración salen al pintar, y por eso se les añade el identificador de la caja. |
| `clone` admite `overrides` | Ajustar la etiqueta o el carácter obligatorio de un campo clonado sin duplicar el conjunto. Es lo que ACF no puede hacer, porque allí los campos viven en la base de datos, y lo que justifica usar `clone` en vez de compartir una variable de PHP. |
| Una opción declarada que no surte efecto es un error | `prefix_name` junto a `display => group` no hace nada, porque el grupo ya prefija. Ignorarlo en silencio se paga meses después; lo mismo con un `overrides` que nombra un campo inexistente. |
| El `clone` se expande una vez y desaparece | En ACF el clon existe en tiempo de ejecución porque los campos viven en la base de datos y sólo se pueden referenciar por clave. Declarados por código, la sustitución se hace una vez: al terminar no queda ningún campo `clone`, y el renderer, el guardado y la lectura no saben que existió. |
| Un `clone` sin prefijo deja las claves intactas | Es lo que permite partir un ACF existente en conjuntos reutilizables sin migrar ni un metadato: el campo se sigue leyendo por su nombre propio. |
| En modo `seamless` los campos heredan las condiciones del clon | Al desaparecer el clon, ACF pierde sus reglas de visibilidad. Aquí pasan a los campos que no tengan las suyas, que es lo que se espera al escribir «muestra este bloque sólo si…». |
| Los `msgid` están en español, no en inglés | Los textos visibles reproducen los de ACF para que el markup sea idéntico, y el WordPress de referencia está en español. Gettext admite cualquier idioma de origen y el paquete no va al directorio de wordpress.org, así que traducir es español → destino. Pasarlos a inglés sería tocar 45 llamadas y arriesgar el comparador. |
| El `.pot` se genera con una herramienta del repo | El contenedor no trae wp-cli ni gettext, y añadir cualquiera de los dos por un archivo que se regenera de tarde en tarde no compensa. `tools/make-pot.php` usa el tokenizador de PHP, que es el enfoque de wp-cli, sobre una superficie de una decena de funciones. |
| No hay modo oscuro | WordPress no tiene. El `acf-dark.scss` de ACF vive en `third-party.php` y se engancha a `doing_dark_mode`, un hook del plugin Dark Mode de un tercero, descontinuado y nunca integrado en el núcleo. Los esquemas de color del núcleo (Medianoche, Ectoplasma) oscurecen el menú, no el área de contenido donde están los campos. |
| La inicialización de los campos vive en un solo sitio | Había dos listas: dieciséis comportamientos al cargar la página y tres en las filas que se añaden después. Un `image` dentro de un repetidor funcionaba en la primera fila y no en las siguientes. `modules/fields.ts` la centraliza; duplicarla vuelve a abrir la misma clase de fallo. |
| Los tests de navegador prueban sobre una taxonomía | El editor de bloques mete los metaboxes en un cajón plegable cuyo control cambia de nombre y estructura entre versiones. La pantalla de términos es clásica, y el código que se prueba es el mismo. |
| Un test de comportamiento tiene que fallar sin el arreglo | El del interruptor pasaba en ambos casos: la casilla la conmuta el `label` por sí sola. Se comprueba la clase `-on`, que es lo que aporta el JavaScript. Un test que no puede fallar por el motivo que dice no es una red. |
| El buscador de iconos pide el máximo de resultados y pagina | Con un límite bajo la API de Iconify **reparte** un icono por colección en vez de devolver los mejores: `limit=60` para «home» daba `reicon:home2`, `arcticons:homee`, `selfhst:homer`… Con `limit=999` —su tope, el mismo con el que trabaja icon-sets.iconify.design— devuelve la lista bien ordenada. Se pagina de 96 en 96 para no meter mil nodos por campo. |
| Una búsqueda nueva cancela la anterior | El antirrebote no impide que dos consultas estén en vuelo, y ganaba la que contestara la última, no la más reciente. Se aborta la anterior y se descarta cualquier respuesta que no sea la de la última búsqueda pedida. |
| select2 se empaqueta como asset, no por npm | WordPress no lo trae. Meterlo por npm obligaría a cada tema que consuma la librería a replicar un alias de jQuery en su propia configuración de Vite, porque select2 lo importa. Como asset estático se encola con `jquery` por dependencia y no toca el build de nadie. Es el mismo camino que el plugin de tablas de TinyMCE. |
| La búsqueda remota se pide por nombre de campo, no por tipo de contenido | El endpoint no acepta un post type ni una taxonomía por parámetro: recibe el nombre de un campo declarado y ejecuta la consulta que ese campo define. Así no sirve para listar nada que no esté ya expuesto en un formulario. El filtro por tipo del `relationship` se cruza con los que declara el campo, nunca los sustituye. |
| El `page_link` guarda el identificador, no la dirección | Guardar el enlace resuelto dejaría el sitio con URLs rotas en cuanto cambiara un slug. Se resuelve al leer, igual que hace ACF. |
| El `relationship` conserva el orden de lo elegido | Es lo único que lo distingue de un `post_object` múltiple, y la razón de que tenga interfaz propia en vez de select2. |
| El cajón de metaboxes se abre por JavaScript en los tests | Su control es a la vez el tirador para redimensionar el panel, y Playwright lo ve tapado por la capa que gestiona el arrastre. Lo que se prueba es lo que hay dentro del cajón, no cómo se abre. |
| El textarea oculto es la señal de que TinyMCE arrancó | Al inicializarse lo esconde y lo sustituye por su iframe. Exigirle visibilidad hacía fallar un test que en realidad estaba comprobando lo correcto. |
| Un campo que toca el objeto lo recibe de quien lo tiene, no lo guarda | `taxonomy` con `save_terms` necesita saber sobre qué entrada trabaja, y ningún campo lo sabía. Dar estado al campo era la opción fácil y la peor: las instancias se comparten entre objetos y peticiones. En su lugar, la interfaz `ObjectAware` recibe el objeto como argumento, del contexto al guardar y de `forja_get_field()` al leer. |
| Cada contexto declara su tipo de objeto | Antes repetía el literal en dos sitios (`storage->for('post')`). Ahora `Context::object_type()` lo declara una vez, y de paso es lo que permite pasárselo a los campos que lo necesitan. |
| `save_terms` reemplaza los términos, no los añade | El campo representa el estado completo de esa taxonomía para la entrada. Añadir dejaría imposible quitar un término desde el formulario. |
| Metaboxes en el cajón del editor de bloques | Es donde ACF los pone hoy y los editores ya están acostumbrados. |
| Licencia GPLv2 o posterior | Obra derivada de Secure Custom Fields. |
