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
