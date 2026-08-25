# Pestañas y acordeones

No son campos, sino instrucciones de maquetado: se declaran en línea y todo lo
que viene después les pertenece, hasta el siguiente del mismo tipo.

```php
'fields' => array(
	array( 'type' => 'tab', 'name' => 'banner', 'label' => 'Banner', 'selected' => true ),
	array( 'type' => 'text', 'name' => 'titulo' ),      // en la pestaña Banner
	array( 'type' => 'tab', 'name' => 'seo', 'label' => 'SEO' ),
	array( 'type' => 'text', 'name' => 'meta_titulo' ), // en la pestaña SEO

	// Cierra el grupo: lo que siga no está en ninguna pestaña.
	array( 'type' => 'tab', 'name' => 'fin', 'endpoint' => true ),

	array( 'type' => 'accordion', 'name' => 'avanzado', 'label' => 'Avanzado' ),
	array( 'type' => 'text', 'name' => 'clase_css' ),   // dentro del acordeón
),
```

La diferencia entre ambos está en el DOM: el acordeón **anida** a sus campos
dentro de su panel, mientras que la pestaña los deja como hermanos y sólo
alterna su visibilidad. Es lo que hace ACF, y el CSS del envoltorio depende de
ello.
