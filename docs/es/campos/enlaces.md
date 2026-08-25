# Enlaces y contenido incrustado

`link` abre el mismo modal que el botón de enlace del editor y guarda las tres
piezas de un enlace:

```php
$enlace = forja_get_field( 'cta' );
// array( 'title' => 'Ver más', 'url' => 'https://…', 'target' => '_blank' )
```

Devuelve `null` si no hay enlace, así que se comprueba con un `if`. Con
`return_format => 'url'` devuelve sólo la dirección.

`oembed` **guarda la dirección, no el HTML**. Es deliberado: el markup de un
proveedor cambia con el tiempo, y guardarlo dejaría el sitio lleno de vídeos
rotos. El HTML se resuelve al pintar:

```php
echo forja_get_field( 'video' );   // el HTML incrustado
```

Con `return_format => 'url'` devuelve la dirección sin resolver. La vista previa
en el escritorio usa el endpoint `oembed/1.0/proxy` de la API REST del núcleo,
que ya se ocupa de los proveedores y de la caché.
