# Validación

`required` no se queda en el atributo HTML: el servidor lo comprueba de nuevo al
guardar. Si un valor no pasa la validación **no se escribe**, de modo que un
envío manipulado no puede borrar un dato bueno, y el editor ve un aviso con lo
que se rechazó.

Los campos de medios validan además que el identificador corresponda a un
adjunto existente y que su tipo encaje con `mime_types`.

Para reglas propias:

```php
add_filter( 'forja/validate_field', function ( string $error, $value, $field ) {
	if ( 'titular' === $field->name() && mb_strlen( (string) $value ) < 10 ) {
		return 'El titular necesita al menos 10 caracteres.';
	}

	return $error;
}, 10, 3 );
```
