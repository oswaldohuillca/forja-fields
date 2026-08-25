# Fechas y horas

Usan los controles nativos del navegador, no jQuery UI: sin dependencias, con
buen comportamiento en móvil y ya traducidos. **El formato de almacenamiento es
el de ACF**, así que un sitio existente se lee sin migrar.

| Tipo | Se guarda como | Ejemplo |
|---|---|---|
| `date_picker` | `Ymd` | `20260819` |
| `time_picker` | `H:i:s` | `14:30:00` |
| `date_time_picker` | `Y-m-d H:i:s` | `2026-08-19 09:05:00` |

`return_format` es un formato de `date()` y decide qué recibe la plantilla; sin
él, se devuelve lo almacenado tal cual.

```php
array( 'type' => 'date_picker', 'name' => 'evento', 'return_format' => 'd/m/Y' )
```

```php
forja_get_field( 'evento' );  // '19/08/2026'
```

Una fecha que no encaje se descarta en lugar de desplazarse: `2026-13-01` se
guarda vacío, no como enero de 2027.
