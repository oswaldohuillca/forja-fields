# Roadmap de Forja

Plan de trabajo por capas. **Cada casilla marcada está implementada y verificada**, así
que al retomar el proyecto basta con buscar la primera casilla vacía.

Convención:

- `[x]` hecho y comprobado
- `[ ]` pendiente
- `[~]` empezado pero incompleto

---

## Capa 0 — Cimientos

Objetivo: una fila de campo visualmente idéntica a ACF, guardándose de verdad.

**Queda una decisión pendiente sobre la distribución de los assets; ver el final de la lista.**

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
- [ ] **Decidir cómo se distribuyen los assets compilados**: hoy `assets/build/` está en `.gitignore`, así que un `composer require` entregaría el paquete sin CSS ni JS. Ver «Publicar una versión» en el README

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

- [ ] Familia `wp.media`: `image`, `file`, `gallery`
- [ ] Familia `select2`: `post_object`, `page_link`, `relationship`, `taxonomy`, `user`
- [ ] Familia pickers: `date_picker`, `date_time_picker`, `time_picker`, `color_picker`
- [ ] `wysiwyg` (TinyMCE)
- [ ] `link`
- [ ] `oembed`
- [ ] `icon_picker`
- [ ] `google_map`

## Capa 3 — Campos compuestos

Los que más JS propio requieren. El `repeater` es el que aparece en la UI actual
de los editores, así que tiene prioridad dentro de esta capa.

- [ ] `group`
- [ ] `repeater` (incluida la tabla, reordenación y paginación)
- [ ] `flexible_content`
- [ ] `clone`

## Transversales

- [ ] Reevaluar el destino en vivo al cambiar la plantilla en el editor (hoy exige recargar)
- [ ] Lógica condicional entre campos (port de `_acf-condition.js`)
- [ ] Modo oscuro (port de `acf-dark.scss`)
- [ ] Validación de campos requeridos en servidor y cliente
- [ ] Contexto de taxonomías
- [ ] Contexto de usuarios
- [ ] Páginas de opciones
- [ ] Compatibilidad de datos con ACF/SCF para los campos compuestos
- [ ] Internacionalización y archivo `.pot`
- [ ] Tests de PHP (PHPUnit) y tests de integración de guardado

---

## Decisiones tomadas

Registradas aquí para no volver a discutirlas en cada sesión.

| Decisión | Motivo |
|---|---|
| Librería de Composer, no plugin | Se consume desde el `functions.php` del tema. Los campos y el código que los usa se versionan juntos y nadie puede desactivarlos desde el escritorio. |
| Los assets compilados deben viajar en el paquete | Quien instala con Composer no ejecuta Bun ni Vite. **Pendiente de resolver:** hoy `assets/build/` está en `.gitignore`. |
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
| Pestañas y acordeones se resuelven en el servidor | ACF los monta con JavaScript reestructurando el DOM. Como aquí el renderer conoce la lista completa de campos, puede emitir la estructura final directamente y dejar al JS sólo abrir y cerrar. |
| Metaboxes en el cajón del editor de bloques | Es donde ACF los pone hoy y los editores ya están acostumbrados. |
| Licencia GPLv2 o posterior | Obra derivada de Secure Custom Fields. |
