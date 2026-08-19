<?php
/**
 * Compara el markup de Forja con el de Secure Custom Fields.
 *
 * Pinta el mismo campo con los dos y enfrenta el resultado. Es la comprobación
 * objetiva de la paridad: si la estructura DOM coincide, el CSS portado se
 * aplica igual.
 *
 * Sólo se ejecuta a mano, desde el contenedor y con SCF presente:
 *
 *     docker exec -w /var/www/html acf-wordpress-1 \
 *         php wp-content/packages/forja/tools/compare-with-scf.php
 *
 * @package Forja
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	exit( 'Sólo por línea de comandos.' );
}

require '/var/www/html/wp-load.php';

$scf = '/var/www/html/wp-content/plugins/secure-custom-fields/secure-custom-fields.php';

if ( ! is_readable( $scf ) ) {
	exit( "No se encuentra Secure Custom Fields; nada que comparar.\n" );
}

require_once $scf;

/*
 * SCF se carga después de que WordPress haya disparado `init`, así que hay que
 * volver a dispararlo para que registre sus tipos de campo. Antes se desengancha
 * el registro de cajas de Forja, que ya corrió y fallaría por duplicado.
 */
remove_all_actions( 'forja/register_boxes' );

// Volver a disparar `init` hace que el núcleo reintente registrar bloques y
// fuentes ya registrados. Son avisos esperados de esta herramienta, no fallos.
add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

do_action( 'init' );

/**
 * Sustituye los identificadores propios de cada implementación.
 *
 * Forja y ACF nombran los controles distinto a propósito —`forja[campo]` frente
 * a `acf[field_xxx]`—, así que esas diferencias se neutralizan antes de
 * comparar. Lo que queda es la estructura de verdad.
 *
 * @param string $html Markup a normalizar.
 * @param string $name Atributo «name» que usa esta implementación.
 * @param string $id   Prefijo de los identificadores de esta implementación.
 * @return string Markup normalizado.
 */
function forja_normalize( string $html, string $name, string $id ): string {
	$replacements = array(
		// Atributo «name»: `forja[campo]` frente a la clave interna de ACF.
		'/name="' . preg_quote( $name, '/' ) . '(\[\])?"/' => 'name="NAME$1"',
		// Identificadores, incluidos los sufijos de cada opción.
		'/(id|for|aria-labelledby)="' . preg_quote( $id, '/' ) . '(-[a-z0-9-]+)?"/' => '$1="ID$2"',
		// ACF usa su clave interna; Forja usa el nombre del campo.
		'/data-key="[^"]*"/' => 'data-key="KEY"',
		/*
		 * Atributos que ACF emite como gancho para comportamientos de
		 * JavaScript que Forja no porta. No los usa ninguna regla de CSS, así
		 * que su ausencia no afecta a la paridad visual:
		 *
		 * - `data-ui`, `data-ajax`, `data-multiple`, `data-placeholder`:
		 *   configuración de select2, que llega con la Capa 2.
		 * - `data-allow_null`: permite deseleccionar desde el navegador; en
		 *   Forja la opción se resuelve en el servidor.
		 * - `data-other_choice`: la opción «otro» con campo libre, que no se
		 *   ha portado.
		 */
		'/ data-(ui|ajax|multiple|placeholder|allow_null|other_choice)="[^"]*"/' => '',

		// ACF deja `class=""` cuando no hay clases; Forja omite los vacíos.
		'/ class=""/' => '',

		// Espaciado: ACF mete saltos de línea entre etiquetas, Forja no.
		'/>\s+</'  => '><',
		'/\s+\/>/' => '/>',
	);

	foreach ( $replacements as $pattern => $replacement ) {
		$html = (string) preg_replace( $pattern, $replacement, $html );
	}

	return trim( $html );
}

/**
 * Casos a comparar: configuración de Forja y su equivalente en ACF.
 */
$cases = array(
	'text'         => array(
		'forja' => array( 'type' => 'text', 'name' => 'campo', 'label' => 'Etiqueta', 'instructions' => 'Ayuda', 'required' => true ),
		'acf'   => array( 'type' => 'text', 'instructions' => 'Ayuda', 'required' => 1 ),
	),
	'text (ancho)' => array(
		'forja' => array( 'type' => 'text', 'name' => 'campo', 'label' => 'Etiqueta', 'wrapper' => array( 'width' => '50' ) ),
		'acf'   => array( 'type' => 'text', 'wrapper' => array( 'width' => '50' ) ),
	),
	'text (afijos)' => array(
		'forja' => array( 'type' => 'text', 'name' => 'campo', 'label' => 'Etiqueta', 'prepend' => 'S/', 'append' => 'IGV' ),
		'acf'   => array( 'type' => 'text', 'prepend' => 'S/', 'append' => 'IGV' ),
	),
	'textarea'     => array(
		'forja' => array( 'type' => 'textarea', 'name' => 'campo', 'label' => 'Etiqueta', 'rows' => 3 ),
		'acf'   => array( 'type' => 'textarea', 'rows' => 3 ),
	),
	'number'       => array(
		'forja' => array( 'type' => 'number', 'name' => 'campo', 'label' => 'Etiqueta', 'min' => 0, 'max' => 10 ),
		'acf'   => array( 'type' => 'number', 'min' => 0, 'max' => 10 ),
	),
	'email'        => array(
		'forja' => array( 'type' => 'email', 'name' => 'campo', 'label' => 'Etiqueta' ),
		'acf'   => array( 'type' => 'email' ),
	),
	'url'          => array(
		'forja' => array( 'type' => 'url', 'name' => 'campo', 'label' => 'Etiqueta' ),
		'acf'   => array( 'type' => 'url' ),
	),
	'select'       => array(
		'forja' => array( 'type' => 'select', 'name' => 'campo', 'label' => 'Etiqueta', 'choices' => array( 'a' => 'A', 'b' => 'B' ) ),
		'acf'   => array( 'type' => 'select', 'choices' => array( 'a' => 'A', 'b' => 'B' ) ),
	),
	'radio'        => array(
		'forja' => array( 'type' => 'radio', 'name' => 'campo', 'label' => 'Etiqueta', 'choices' => array( 'a' => 'A', 'b' => 'B' ) ),
		'acf'   => array( 'type' => 'radio', 'choices' => array( 'a' => 'A', 'b' => 'B' ) ),
	),
	'checkbox'     => array(
		'forja' => array( 'type' => 'checkbox', 'name' => 'campo', 'label' => 'Etiqueta', 'choices' => array( 'a' => 'A', 'b' => 'B' ) ),
		'acf'   => array( 'type' => 'checkbox', 'choices' => array( 'a' => 'A', 'b' => 'B' ) ),
	),
	'button_group' => array(
		'forja' => array( 'type' => 'button_group', 'name' => 'campo', 'label' => 'Etiqueta', 'choices' => array( 'a' => 'A', 'b' => 'B' ) ),
		'acf'   => array( 'type' => 'button_group', 'choices' => array( 'a' => 'A', 'b' => 'B' ) ),
	),
	'true_false'   => array(
		'forja' => array( 'type' => 'true_false', 'name' => 'campo', 'label' => 'Etiqueta' ),
		'acf'   => array( 'type' => 'true_false' ),
	),
	'true_false (ui)' => array(
		'forja' => array( 'type' => 'true_false', 'name' => 'campo', 'label' => 'Etiqueta', 'ui' => true, 'ui_on_text' => 'Sí', 'ui_off_text' => 'No' ),
		'acf'   => array( 'type' => 'true_false', 'ui' => 1, 'ui_on_text' => 'Sí', 'ui_off_text' => 'No' ),
	),
	'separator'    => array(
		'forja' => array( 'type' => 'separator', 'name' => 'campo', 'label' => 'Etiqueta' ),
		'acf'   => array( 'type' => 'separator' ),
	),
);

$registry = forja()->fields();
$renderer = new Forja\Render\Renderer();

$equal   = 0;
$differ  = array();

foreach ( $cases as $label => $case ) {
	// Forja.
	ob_start();
	$renderer->render_field_wrap( $registry->make( $case['forja'] ), '', 'forja' );
	$mine = forja_normalize( ob_get_clean(), 'forja[campo]', 'forja-campo' );

	// ACF / SCF.
	ob_start();
	acf_render_field_wrap(
		array_merge(
			$case['acf'],
			array(
				'key'   => 'field_campo',
				'name'  => 'campo',
				'label' => 'Etiqueta',
			)
		)
	);
	$theirs = forja_normalize( ob_get_clean(), 'field_campo', 'field_campo' );

	if ( $mine === $theirs ) {
		++$equal;
		printf( "  ✅ %s\n", $label );
		continue;
	}

	$differ[ $label ] = array( $mine, $theirs );
	printf( "  ⚠️  %s\n", $label );
}

printf( "\n%d de %d idénticos.\n", $equal, count( $cases ) );

foreach ( $differ as $label => $pair ) {
	printf( "\n=== %s ===\n  FORJA: %s\n  SCF:   %s\n", $label, $pair[0], $pair[1] );
}
