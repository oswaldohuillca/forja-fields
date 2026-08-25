---
layout: home

hero:
  name: Forja
  text: Campos personalizados por código
  tagline: La API de desarrollo de CMB2, la experiencia de edición de ACF. Sin panel de administración y sin plugin que activar.
  actions:
    - theme: brand
      text: Empezar
      link: /guia/instalacion
    - theme: alt
      text: Ver los campos
      link: /campos/

features:
  - title: No es un plugin
    details: Se instala con Composer en tu tema. Los campos y el código que los usa se versionan juntos, y nadie puede desactivarlos desde el escritorio.
  - title: Los campos viven en el repositorio
    details: Se declaran en PHP, al estilo de CMB2. Sin panel para crearlos, sin exportar e importar JSON entre entornos.
  - title: La interfaz que tus editores conocen
    details: Se porta el markup y el CSS de ACF/Secure Custom Fields, no se reinventa. Una herramienta compara ambos campo a campo.
  - title: Compatible con los datos de ACF
    details: Repetidores como campo_0_subcampo, fechas como Ymd. Un sitio existente se lee sin migrar nada.
---

## Un ejemplo completo

```php
add_action( 'forja/register_boxes', function () {
	forja_register_box( 'portada', array(
		'title'           => 'Contenido de portada',
		'object_subtypes' => array( 'page' ),
		'fields'          => array(
			array( 'type' => 'text',  'name' => 'titular', 'label' => 'Titular' ),
			array( 'type' => 'image', 'name' => 'fondo',   'label' => 'Imagen de fondo' ),
		),
	) );
} );
```

Y en la plantilla:

```php
forja_the_field( 'titular' );

$fondo = forja_get_field( 'fondo' );
```

## Qué hay dentro

**35 tipos de campo**, desde `text` hasta `relationship`, pasando por repetidores,
contenido flexible y un selector de iconos que busca en las más de 200.000
colecciones de Iconify sin empaquetar ningún catálogo.

Todo verificado: 215 tests de integración contra un WordPress real, 35 tests de
navegador, y un comparador que exige markup idéntico al de ACF campo a campo.
