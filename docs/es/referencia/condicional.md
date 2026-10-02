# Lógica condicional

Un campo puede depender del valor de otro. Se admiten tres formas, de la más
corta a la más explícita:

```php
// Una regla suelta.
'conditional_logic' => array( 'field' => 'tipo', 'value' => 'video' ),

// Varias reglas: deben cumplirse TODAS.
'conditional_logic' => array(
	array( 'field' => 'tipo', 'value' => 'video' ),
	array( 'field' => 'avanzado', 'value' => '1' ),
),

// Grupos alternativos: basta con que UNO se cumpla entero.
'conditional_logic' => array(
	array( array( 'field' => 'tipo', 'value' => 'video' ) ),
	array( array( 'field' => 'tipo', 'value' => 'audio' ) ),
),
```

Operadores: `==` (por defecto), `!=`, `>`, `<`, `>=`, `<=`, `contains`,
`!contains`, `empty` y `!empty`. También se aceptan las grafías de ACF
(`==contains`, `!=empty`, `!==`).

Dentro de un repetidor o de un contenido flexible, una regla mira a su
**hermano de la misma fila**, no al de la primera. Y una regla que apunta a un
campo inexistente nunca se cumple, para que un nombre mal escrito se note.

## Obligatorio solo si se muestra

Un campo con `required` y una condición solo es obligatorio cuando la condición
lo muestra:

```php
array(
	'type'              => 'text',
	'name'              => 'telefono',
	'label'             => 'Teléfono',
	'required'          => true,
	'conditional_logic' => array( 'field' => 'mostrar_contacto', 'value' => '1' ),
),
```

Con el interruptor apagado, el teléfono no se ve y se puede guardar vacío.
Encendido, hay que rellenarlo. El navegador y el servidor lo deciden igual: el
servidor vuelve a evaluar las reglas con los valores enviados, así que no
rechaza lo que el editor no veía.
