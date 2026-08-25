# Forja

Librería de Composer que replica la interfaz de edición de ACF/Secure Custom
Fields, pero con los campos declarados por código al estilo de CMB2. No es un
plugin: se instala en un tema y se carga desde su `functions.php`.

## Las dos reglas que condicionan casi todo

Antes de cambiar nada, ten presentes estas dos. Casi cada decisión rara del
código responde a una de ellas.

**Se conservan los nombres de clase CSS `acf-`.** El CSS está portado de Secure
Custom Fields y depende de esa estructura DOM exacta. Renombrar el prefijo
provoca fallos visuales silenciosos.

**El formato de almacenamiento es el de ACF.** Repetidores como
`campo_0_subcampo`, fechas como `Ymd`, horas como `H:i:s`. Es lo que permite
leer un sitio existente sin migrar nada.

## Dónde va cada cosa al documentar

Cuando expliques por qué una decisión es como es, o descubras algo investigando,
**escríbelo aquí, no sólo en la conversación**. El chat se pierde; el proyecto se
retoma entre sesiones. Si el motivo de una decisión sólo vive en un mensaje, la
siguiente persona la cambia sin saber que respondía a algo que ya se probó y no
funcionaba.

| Documento | Qué contiene |
|---|---|
| `README.md` | Portada corta: qué es, cómo se instala y adónde ir. **No crece**: si algo no cabe en una frase, va al sitio. |
| `docs/` | El manual, publicado con VitePress (`bun run docs`). Guía, campos, referencia y desarrollo. |
| `docs/en/` | Traducción parcial: instalación, primeros pasos y referencia de campos. **El español es canónico**: si discrepan, manda el español. Al cambiar algo documentado ahí, actualiza también la página inglesa o quítala; una traducción desactualizada es peor que no tenerla. |
| `docs/desarrollo/arquitectura.md` | El porqué: decisiones con su razón, dependencias externas, seguridad, cómo se prueba |
| `ROADMAP.md` | Estado de cada fase y la tabla de «Decisiones tomadas», para no rediscutirlas |

Documenta también los callejones sin salida: qué se intentó y por qué no valía.
Ahorra repetir el intento.

## Cómo se trabaja

En `ROADMAP.md` sólo se marca `[x]` lo que está implementado **y verificado**.
Una casilla marcada significa que alguien lo ejecutó y lo comprobó, no que el
código exista.

Antes de dar por cerrado un bloque de trabajo:

```bash
bun run docs:build                              # falla si hay un enlace roto
bun run typecheck
bun run build
composer lint                                   # PHPCS con WordPress-Extra
composer make-pot                               # plantilla de traducción
docker exec -w /var/www/html/wp-content/packages/forja acf-wordpress-1 \
    php vendor/bin/pest                         # suite de integración
bun run test:e2e                                # Playwright, el único que ejecuta el TS

# Paridad de markup contra ACF, caso a caso
docker exec -w /var/www/html acf-wordpress-1 \
    php wp-content/packages/forja/tools/compare-with-scf.php
```

El entorno es Docker (`acf-wordpress-1`), con PHP 8.3 y Composer dentro del
contenedor. Bun y Vite corren en el anfitrión.

## Estilo

- Código y comentarios **en español**, igual que el resto del repositorio.
- Los comentarios explican **por qué**, no qué hace la línea siguiente.
- Un archivo de CSS y de TypeScript por responsabilidad: añadir un tipo de campo
  no debe obligar a tocar un archivo compartido.
- Los tests son de integración contra un WordPress real, no unitarios con dobles.
- **Un comportamiento nuevo del navegador necesita un test de Playwright.** Pest
  y el comparador sólo ven markup: si un botón deja de responder, los dos siguen
  en verde. Antes de dar por bueno un test así, comprueba que **falla sin el
  arreglo**; si pasa en ambos casos, no está probando lo que dice.
