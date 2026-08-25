# Reutilizar campos con `clone`

Declara el conjunto una vez y clónalo donde haga falta:

```php
add_action( 'forja/register_boxes', function () {
	forja_register_fields( 'seo', array(
		array( 'type' => 'text', 'name' => 'seo_titulo', 'label' => 'Título SEO' ),
		array( 'type' => 'textarea', 'name' => 'seo_descripcion', 'label' => 'Descripción' ),
	) );

	forja_register_box( 'pagina', array(
		'object_type' => 'post',
		'fields'      => array(
			array( 'type' => 'text', 'name' => 'entradilla', 'label' => 'Entradilla' ),
			array( 'type' => 'clone', 'clone' => 'seo' ),
		),
	) );
} );
```

El orden de declaración **no importa**: los campos de una caja se construyen la
primera vez que alguien los pide, no al registrarla, así que el conjunto puede
declararse después. Si el identificador no existe, Forja lanza una excepción
diciendo cuál falta y en qué caja estaba.

Un conjunto no se pinta en ninguna parte por sí mismo: no tiene título ni
destino. Existe sólo para clonarse. También puedes clonar **otra caja** por su
identificador, o escribir la lista directamente en la clave `clone`.

## Qué claves se usan

Por defecto, ninguna. Los campos clonados guardan bajo su propio nombre, igual
que si los hubieras escrito ahí a mano:

```php
forja_get_field( 'seo_titulo' );   // no 'seo_seo_titulo'
```

Es lo que permite partir un ACF existente en conjuntos reutilizables **sin
migrar ni un metadato**. Si necesitas el mismo conjunto dos veces en la misma
caja, sí hacen falta claves distintas, y ahí entran las dos opciones de prefijo:

| Opción | Efecto |
|---|---|
| `display` | `seamless` (por defecto) inserta los campos en el sitio del clon. `group` los envuelve en un campo `group`, con su etiqueta y su borde. |
| `prefix_name` | Antepone el nombre del clon a la clave: `ficha_seo_titulo`. Sólo vale con `seamless`: combinarlo con `group` es un error, porque el grupo ya antepone su nombre. |
| `prefix_label` | Antepone la etiqueta del clon a la de cada campo: «SEO Título». |

```php
array(
	'type'        => 'clone',
	'name'        => 'escritorio',
	'label'       => 'Escritorio',
	'clone'       => 'medidas',
	'prefix_name' => true,      // escritorio_ancho, escritorio_alto
),
array(
	'type'        => 'clone',
	'name'        => 'movil',
	'label'       => 'Móvil',
	'clone'       => 'medidas',
	'prefix_name' => true,      // movil_ancho, movil_alto
),
```

## Ajustar campos sueltos

Rara vez el conjunto encaja tal cual en los dos sitios. Con `overrides` cambias
lo que haga falta de un campo concreto, sin duplicar el conjunto:

```php
array(
	'type'      => 'clone',
	'clone'     => 'medidas',
	'overrides' => array(
		'ancho' => array( 'label' => 'Anchura útil', 'required' => true ),
	),
)
```

Las claves son los nombres **del conjunto de origen**, antes de cualquier
prefijo. Nombrar un campo que el conjunto no trae es un error, y el mensaje
lista los que sí hay: casi siempre es una errata, y en silencio se traduciría en
un ajuste que no se aplica sin decir por qué.

Funciona igual con `display => 'group'`.

Esto es lo que hace que `clone` valga más que una variable de PHP, y lo que ACF
no puede ofrecer: allí los campos viven en la base de datos y retocar una copia
obliga a duplicar el grupo entero.

## Lo que hereda cada copia

Un clon marcado como `required` vuelve obligatorio todo lo que trae. Al revés no
funciona: un clon opcional no relaja lo que el conjunto declaró obligatorio.

Si el clon lleva `conditional_logic`, sus reglas pasan a los campos clonados que
no tengan las suyas. Es una diferencia deliberada con ACF, donde en modo
`seamless` el clon desaparece y esas reglas se pierden.

Los clones se pueden anidar —un conjunto que clona otro— y se pueden usar dentro
de un `repeater`, un `group` o una capa de contenido flexible.

## Cuándo no lo necesitas

El clon existe en ACF porque los campos viven en la base de datos y no hay forma
de que un grupo referencie a otro salvo por su clave. Declarándolos por código,
compartir una lista es una variable:

```php
$medidas = array(
	array( 'type' => 'number', 'name' => 'ancho' ),
	array( 'type' => 'number', 'name' => 'alto' ),
);
```

Si eso te vale, úsalo: es más directo. `clone` aporta lo que una variable no da
— los `overrides`, los prefijos, el envoltorio en grupo y poder referenciar una
caja por su identificador.
