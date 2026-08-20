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
- [ ] Familia `select2`: `post_object`, `page_link`, `relationship`, `taxonomy`, `user`
- [x] Familia pickers: `date_picker`, `date_time_picker`, `time_picker`, `color_picker`
- [x] `wysiwyg` (TinyMCE, con tablas opcionales, funcional dentro de repetidores)
- [x] `link` (modal de enlaces del núcleo)
- [x] `oembed` (vista previa vía el endpoint REST del núcleo)
- [ ] `icon_picker`
- [ ] `google_map`

## Capa 3 — Campos compuestos

Los que más JS propio requieren. El `repeater` es el que aparece en la UI actual
de los editores, así que tiene prioridad dentro de esta capa.

- [x] `group`
- [x] `repeater` (tabla, añadir, quitar, reordenar y límites en servidor; sin paginación ni plegado)
- [x] `flexible_content` (capas, orden y límites; sin renombrar ni desactivar capas)
- [ ] `clone`

## Transversales

- [ ] Reevaluar el destino en vivo al cambiar la plantilla en el editor (hoy exige recargar)
- [x] Lógica condicional entre campos (grupos OR con reglas AND, con ámbito por fila)
- [ ] Modo oscuro (port de `acf-dark.scss`)
- [x] Validación de campos requeridos en servidor (`Validation\Validator`); falta el aviso en cliente antes de enviar
- [x] Contexto de taxonomías (alta y edición de términos)
- [x] Contexto de usuarios (perfil, con filtrado por rol)
- [x] Páginas de opciones (`object_type => option`, con menú propio)
- [x] Compatibilidad de datos con ACF/SCF en el repetidor: lee y escribe `campo_N_subcampo`
- [ ] Internacionalización y archivo `.pot`
- [~] Tests con Pest: 125 casos sobre saneado, medios, agrupado, validación y almacenamiento; falta cubrir el contexto de guardado completo

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
| Metaboxes en el cajón del editor de bloques | Es donde ACF los pone hoy y los editores ya están acostumbrados. |
| Licencia GPLv2 o posterior | Obra derivada de Secure Custom Fields. |
