# Iconos

```php
array(
	'type'        => 'icon_picker',
	'name'        => 'icono',
	'label'       => 'Icono',
	'collections' => array( 'mdi', 'tabler' ),  // vacío = todas
)
```

Busca sobre [Iconify](https://iconify.design), que reúne más de 200.000 iconos.
**No se empaqueta ningún catálogo**: el buscador consulta la API desde el
navegador, igual que hace [icones.js.org](https://icones.js.org). Sin proceso de
build ni endpoint propio.

Se piden los 999 resultados que admite la API como máximo y se muestran
paginados de 96 en 96, igual que hace el propio buscador de Iconify. El límite
importa más de lo que parece: **pidiendo pocos, la API reparte un icono por
colección** en vez de devolver los mejores, y una búsqueda como «home» acababa
mostrando `reicon:home2` o `selfhst:homer` en lugar de `material-symbols:home`.

En la plantilla, el icono se incrusta como SVG en línea:

```php
forja_the_icon( 'icono', 'w-6 h-6' );
```

El SVG se descarga **una sola vez** y se guarda en un transitorio. Son unos 150
bytes y usa `currentColor`, así que hereda el color del CSS. Deliberadamente no
se usa el componente web de Iconify: añadiría una dependencia de JavaScript para
el visitante y una petición por icono en cada carga.

Se guarda con la misma forma que ACF, así que un sitio existente con
`dashicons`, adjuntos o URLs se sigue leyendo:

```php
array( 'type' => 'iconify', 'value' => 'mdi:home' )
```

Los dashicons de ACF se resuelven por la colección homónima de Iconify, sin
tratarlos aparte.

> **Servicio externo.** Las búsquedas del escritorio van a `api.iconify.design`.
> Iconify es autoalojable; el filtro `forja/iconify_api` apunta a tu instancia
> si necesitas que no salga nada del servidor.

El nombre del icono se valida antes de construir la URL, y el SVG que devuelve
la API pasa por una lista blanca de etiquetas antes de entrar en la página. El
porqué de cada decisión está en
[Arquitectura](/desarrollo/arquitectura#servicios-y-dependencias-externas).
