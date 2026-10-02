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
**No se empaqueta ningún catálogo**: el buscador consulta la API cuando hace
falta, sin proceso de build.

El navegador no habla con Iconify: le pide la búsqueda y las miniaturas a tu
WordPress, que las trae por lotes y las guarda en caché. Una página de
resultados es una sola petición. Pedirlas una a una a la API pública acababa en
un bloqueo temporal de Cloudflare y en miniaturas rotas. No hay que configurar
nada.

Las colecciones animadas (`line-md`, `svg-spinners`) no aparecen en los
resultados: dibujan con animaciones que se pierden al incrustar el icono, y se
verían como una raya.

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

> **Servicio externo.** Tu servidor consulta `api.iconify.design`, tanto para el
> buscador del escritorio como para los iconos de la parte pública. Iconify es
> autoalojable; el filtro `forja/iconify_api` apunta a tu instancia si necesitas
> que no salga nada a internet. El filtro decide adónde llama **el servidor**:
> no lo apuntes a un intermediario propio en el escritorio, porque Forja ya
> trae el suyo.

El nombre del icono se valida antes de construir la URL, y el SVG que devuelve
la API pasa por una lista blanca de etiquetas antes de entrar en la página. El
porqué de cada decisión está en
[Arquitectura](/es/desarrollo/arquitectura#servicios-y-dependencias-externas).
