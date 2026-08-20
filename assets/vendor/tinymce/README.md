# TinyMCE: plugin de tablas

WordPress empaqueta TinyMCE 4.9.11, pero **no incluye el plugin `table`**. Es la
razón por la que existen extensiones como «Advanced Editor Tools»: añaden
exactamente este archivo.

Aquí va el plugin oficial de esa misma versión, para que el campo `wysiwyg`
pueda ofrecer tablas sin depender de ningún plugin de terceros.

| | |
|---|---|
| Origen | `https://cdn.jsdelivr.net/npm/tinymce@4.9.11/plugins/table/plugin.min.js` |
| Versión | 4.9.11, la misma que trae WordPress |
| Licencia | LGPL 2.1 |

La versión tiene que coincidir con la de WordPress: TinyMCE no garantiza que un
plugin de una versión funcione en otra.

## Licencia

TinyMCE 4.x se distribuye bajo LGPL 2.1, compatible con la GPLv2+ de este
paquete. El archivo se incluye sin modificar y conserva su licencia original.

Para actualizarlo, comprueba antes qué versión de TinyMCE trae la versión de
WordPress a la que apuntas:

```bash
grep -o 'majorVersion:"[^"]*"' wp-includes/js/tinymce/tinymce.min.js
```
