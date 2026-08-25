# Tipos que devuelve cada campo

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
