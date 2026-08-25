# Añadir un tipo de campo propio

Los 35 tipos que trae Forja no tienen nada de especial: se registran igual que
uno tuyo. Escribe una clase que extienda `Forja\Fields\Field`, implementa
`type()` y `render_input()`, y regístrala:

```php
add_action( 'forja/register_field_types', function () {
	forja_register_field_type( \MiTema\Campos\Firma::class );
} );
```

A partir de ahí `'type' => 'firma'` funciona en cualquier caja, incluidas las
filas de un repetidor. El envoltorio —etiqueta, instrucciones, ancho, reglas de
visibilidad— lo pone el renderer, así que tu clase sólo se ocupa del control.

Si además ocupa varias claves de metadatos, implementa `Forja\Fields\Composite`;
si necesita tocar el objeto y no sólo sus metadatos, `Forja\Fields\ObjectAware`.
Ambos contratos están explicados en [Arquitectura](/desarrollo/arquitectura).
