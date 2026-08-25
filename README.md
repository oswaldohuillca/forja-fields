# Forja

**Librería de Composer** para crear campos personalizados de WordPress **desde el
código de tu tema**, con la interfaz de administración de ACF/Secure Custom Fields
que tus editores ya conocen.

La idea es simple: la API de desarrollo de CMB2, la experiencia de edición de ACF.

- No es un plugin. Se instala en el tema y no hay nada que activar.
- Sin panel para crear campos. Los grupos se declaran en PHP y viven en el repositorio.
- Paridad visual con ACF/SCF: se porta su markup y su CSS, no se reinventa.
- Compatible con los datos de ACF: un sitio existente se lee sin migrar nada.

```bash
composer require oswa/forja
```

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

```php
forja_the_field( 'titular' );
```

## Documentación

La documentación completa vive en `docs/` y se publica como sitio:

```bash
bun run docs          # servidor de desarrollo, con recarga
bun run docs:build    # sitio estático en docs/.vitepress/dist
```

El sitio se publica con el **inglés por defecto** y el español en `/es/`. La
versión española es la completa y la canónica; la inglesa cubre la entrada.

| Dónde | Qué contiene |
|---|---|
| [Guía](docs/es/guia/instalacion.md) | Instalar, declarar el primer grupo y elegir dónde aparece |
| [Campos](docs/es/campos/index.md) | Los 35 tipos, sus opciones y qué devuelve cada uno |
| [Referencia](docs/es/referencia/valores.md) | Valores devueltos, lógica condicional, validación y extensión |
| [Arquitectura](docs/es/desarrollo/arquitectura.md) | El porqué de cada decisión, dependencias externas y seguridad |
| [ROADMAP.md](ROADMAP.md) | Estado de cada fase y decisiones ya tomadas |

## Requisitos

| | |
|---|---|
| PHP | 8.1 o superior |
| WordPress | 6.4 o superior |
| Composer | 2.x |
| Bun | 1.3 o superior (sólo para desarrollar la librería) |

## Licencia

GPL-2.0 o posterior. Obra derivada de
[Secure Custom Fields](https://github.com/WordPress/secure-custom-fields).
