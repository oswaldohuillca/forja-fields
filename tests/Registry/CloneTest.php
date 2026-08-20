<?php
/**
 * Expansión de los campos de tipo «clone».
 *
 * @package Forja
 */

declare( strict_types = 1 );

use Forja\Registry\BoxRegistry;
use Forja\Registry\CloneResolver;
use Forja\Registry\FieldRegistry;

/**
 * Construye un expansor sobre un mapa fijo de conjuntos.
 *
 * @param array<string, array<int, array<string, mixed>>> $sets Conjuntos disponibles.
 * @return CloneResolver Expansor.
 */
function forja_test_resolver( array $sets ): CloneResolver {
	return new CloneResolver(
		static fn ( string $reference ): ?array => $sets[ $reference ] ?? null
	);
}

beforeEach( function () {
	$this->seo = array(
		array(
			'type'  => 'text',
			'name'  => 'titulo',
			'label' => 'Título',
		),
		array(
			'type'  => 'textarea',
			'name'  => 'descripcion',
			'label' => 'Descripción',
		),
	);

	$this->resolver = forja_test_resolver( array( 'seo' => $this->seo ) );
} );

it( 'sustituye el clon por los campos del conjunto', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type' => 'text',
				'name' => 'antes',
			),
			array(
				'type'  => 'clone',
				'clone' => 'seo',
			),
			array(
				'type' => 'text',
				'name' => 'despues',
			),
		)
	);

	expect( array_column( $fields, 'name' ) )
		->toBe( array( 'antes', 'titulo', 'descripcion', 'despues' ) );
} );

it( 'no deja ningún campo de tipo clone en el árbol', function () {
	$fields = $this->resolver->expand( array( array( 'type' => 'clone', 'clone' => 'seo' ) ) );

	expect( array_column( $fields, 'type' ) )->not->toContain( 'clone' );
} );

it( 'admite el conjunto escrito en línea, sin registrarlo', function () {
	$fields = forja_test_resolver( array() )->expand(
		array(
			array(
				'type'  => 'clone',
				'clone' => array( array( 'type' => 'text', 'name' => 'suelto' ) ),
			),
		)
	);

	expect( $fields )->toHaveCount( 1 )
		->and( $fields[0]['name'] )->toBe( 'suelto' );
} );

it( 'prefija los nombres cuando se pide', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'        => 'clone',
				'name'        => 'ficha',
				'clone'       => 'seo',
				'prefix_name' => true,
			),
		)
	);

	expect( array_column( $fields, 'name' ) )
		->toBe( array( 'ficha_titulo', 'ficha_descripcion' ) );
} );

it( 'deja los nombres intactos si no se pide prefijo', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'  => 'clone',
				'name'  => 'ficha',
				'clone' => 'seo',
			),
		)
	);

	expect( array_column( $fields, 'name' ) )->toBe( array( 'titulo', 'descripcion' ) );
} );

it( 'prefija las etiquetas cuando se pide', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'         => 'clone',
				'name'         => 'ficha',
				'label'        => 'SEO',
				'clone'        => 'seo',
				'prefix_label' => true,
			),
		)
	);

	expect( array_column( $fields, 'label' ) )->toBe( array( 'SEO Título', 'SEO Descripción' ) );
} );

it( 'propaga el carácter obligatorio del clon a lo que trae', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'     => 'clone',
				'name'     => 'ficha',
				'clone'    => 'seo',
				'required' => true,
			),
		)
	);

	expect( $fields[0]['required'] )->toBeTrue()
		->and( $fields[1]['required'] )->toBeTrue();
} );

it( 'no relaja un subcampo obligatorio cuando el clon es opcional', function () {
	$fields = forja_test_resolver(
		array(
			'uno' => array(
				array(
					'type'     => 'text',
					'name'     => 'obligatorio',
					'required' => true,
				),
			),
		)
	)->expand( array( array( 'type' => 'clone', 'clone' => 'uno' ) ) );

	expect( $fields[0]['required'] )->toBeTrue();
} );

it( 'hereda las reglas de visibilidad del clon a los campos que no tienen las suyas', function () {
	$condicion = array(
		'field' => 'activar',
		'value' => '1',
	);

	$fields = forja_test_resolver(
		array(
			'par' => array(
				array( 'type' => 'text', 'name' => 'libre' ),
				array(
					'type'              => 'text',
					'name'              => 'con_regla',
					'conditional_logic' => array(
						'field' => 'otro',
						'value' => 'x',
					),
				),
			),
		)
	)->expand(
		array(
			array(
				'type'              => 'clone',
				'clone'             => 'par',
				'conditional_logic' => $condicion,
			),
		)
	);

	expect( $fields[0]['conditional_logic'] )->toBe( $condicion )
		->and( $fields[1]['conditional_logic']['field'] )->toBe( 'otro' );
} );

it( 'envuelve los campos en un grupo con display a group', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'    => 'clone',
				'name'    => 'ficha',
				'label'   => 'Ficha',
				'clone'   => 'seo',
				'display' => 'group',
			),
		)
	);

	expect( $fields )->toHaveCount( 1 )
		->and( $fields[0]['type'] )->toBe( 'group' )
		->and( $fields[0]['name'] )->toBe( 'ficha' )
		->and( array_column( $fields[0]['sub_fields'], 'name' ) )->toBe( array( 'titulo', 'descripcion' ) );
} );

it( 'limpia las claves propias del clon al convertirlo en grupo', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'    => 'clone',
				'name'    => 'ficha',
				'clone'   => 'seo',
				'display' => 'group',
			),
		)
	);

	expect( $fields[0] )->not->toHaveKey( 'clone' )
		->and( $fields[0] )->not->toHaveKey( 'display' );
} );

it( 'expande los clones que hay dentro de un repetidor', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'       => 'repeater',
				'name'       => 'filas',
				'sub_fields' => array(
					array( 'type' => 'text', 'name' => 'propio' ),
					array( 'type' => 'clone', 'clone' => 'seo' ),
				),
			),
		)
	);

	expect( array_column( $fields[0]['sub_fields'], 'name' ) )
		->toBe( array( 'propio', 'titulo', 'descripcion' ) );
} );

it( 'expande los clones que hay dentro de una capa de contenido flexible', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'    => 'flexible_content',
				'name'    => 'secciones',
				'layouts' => array(
					'texto' => array(
						'label'      => 'Texto',
						'sub_fields' => array(
							array( 'type' => 'clone', 'clone' => 'seo' ),
						),
					),
				),
			),
		)
	);

	expect( array_column( $fields[0]['layouts']['texto']['sub_fields'], 'name' ) )
		->toBe( array( 'titulo', 'descripcion' ) );
} );

it( 'expande un clon que apunta a un conjunto que a su vez clona otro', function () {
	$fields = forja_test_resolver(
		array(
			'base'     => array( array( 'type' => 'text', 'name' => 'base_campo' ) ),
			'compuesto' => array(
				array( 'type' => 'clone', 'clone' => 'base' ),
				array( 'type' => 'text', 'name' => 'extra' ),
			),
		)
	)->expand( array( array( 'type' => 'clone', 'clone' => 'compuesto' ) ) );

	expect( array_column( $fields, 'name' ) )->toBe( array( 'base_campo', 'extra' ) );
} );

it( 'avisa cuando la referencia no existe', function () {
	forja_test_resolver( array() )->expand( array( array( 'type' => 'clone', 'clone' => 'inexistente' ) ) );
} )->throws( InvalidArgumentException::class, 'inexistente' );

it( 'corta un conjunto que se clona a sí mismo', function () {
	$sets = array();

	$resolver = new CloneResolver(
		static function ( string $reference ) use ( &$sets ): ?array {
			return $sets[ $reference ] ?? null;
		}
	);

	$sets['bucle'] = array( array( 'type' => 'clone', 'clone' => 'bucle' ) );

	$resolver->expand( array( array( 'type' => 'clone', 'clone' => 'bucle' ) ) );
} )->throws( InvalidArgumentException::class, 'ciclo' );

it( 'exige un nombre cuando se pide prefijo', function () {
	$this->resolver->expand(
		array(
			array(
				'type'        => 'clone',
				'clone'       => 'seo',
				'prefix_name' => true,
			),
		)
	);
} )->throws( InvalidArgumentException::class, 'prefix_name' );

it( 'exige un nombre cuando el clon se convierte en grupo', function () {
	$this->resolver->expand(
		array(
			array(
				'type'    => 'clone',
				'clone'   => 'seo',
				'display' => 'group',
			),
		)
	);
} )->throws( InvalidArgumentException::class, 'name' );

it( 'ajusta un campo concreto sin duplicar el conjunto', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'      => 'clone',
				'clone'     => 'seo',
				'overrides' => array(
					'titulo' => array(
						'label'    => 'Titular',
						'required' => true,
					),
				),
			),
		)
	);

	expect( $fields[0]['label'] )->toBe( 'Titular' )
		->and( $fields[0]['required'] )->toBeTrue()
		->and( $fields[0]['type'] )->toBe( 'text' )
		->and( $fields[1]['label'] )->toBe( 'Descripción' );
} );

it( 'ajusta también los campos que van dentro de un grupo', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'      => 'clone',
				'name'      => 'ficha',
				'clone'     => 'seo',
				'display'   => 'group',
				'overrides' => array(
					'titulo' => array( 'label' => 'Titular' ),
				),
			),
		)
	);

	expect( $fields[0]['sub_fields'][0]['label'] )->toBe( 'Titular' );
} );

it( 'aplica los ajustes antes de prefijar, usando los nombres del conjunto', function () {
	$fields = $this->resolver->expand(
		array(
			array(
				'type'         => 'clone',
				'name'         => 'ficha',
				'label'        => 'Ficha',
				'clone'        => 'seo',
				'prefix_name'  => true,
				'prefix_label' => true,
				'overrides'    => array(
					'titulo' => array( 'label' => 'Titular' ),
				),
			),
		)
	);

	expect( $fields[0]['name'] )->toBe( 'ficha_titulo' )
		->and( $fields[0]['label'] )->toBe( 'Ficha Titular' );
} );

it( 'avisa cuando un ajuste nombra un campo que no existe', function () {
	$this->resolver->expand(
		array(
			array(
				'type'      => 'clone',
				'clone'     => 'seo',
				'overrides' => array( 'titulazo' => array( 'label' => 'X' ) ),
			),
		)
	);
} )->throws( InvalidArgumentException::class, 'titulazo' );

it( 'no deja combinar prefix_name con display a group', function () {
	$this->resolver->expand(
		array(
			array(
				'type'        => 'clone',
				'name'        => 'ficha',
				'clone'       => 'seo',
				'display'     => 'group',
				'prefix_name' => true,
			),
		)
	);
} )->throws( InvalidArgumentException::class, 'prefix_name' );

it( 'clona una caja que se registró después', function () {
	$registry = new BoxRegistry( new FieldRegistry() );

	// El orden es el contrario al que hacía falta antes: primero quien clona.
	$registry->register(
		'destino',
		array(
			'fields' => array( array( 'type' => 'clone', 'clone' => 'origen' ) ),
		)
	);

	$registry->register(
		'origen',
		array(
			'fields' => array( array( 'type' => 'text', 'name' => 'heredado' ) ),
		)
	);

	$nombres = array_map(
		static fn ( $field ): string => $field->name(),
		$registry->get( 'destino' )->fields()
	);

	expect( $nombres )->toBe( array( 'heredado' ) );
} );

it( 'clona un conjunto declarado después de la caja', function () {
	$registry = new BoxRegistry( new FieldRegistry() );

	$registry->register(
		'caja',
		array(
			'fields' => array( array( 'type' => 'clone', 'clone' => 'tardio' ) ),
		)
	);

	$registry->sets()->register( 'tardio', array( array( 'type' => 'text', 'name' => 'tarde' ) ) );

	expect( $registry->get( 'caja' )->fields()[0]->name() )->toBe( 'tarde' );
} );

it( 'corta dos cajas que se clonan mutuamente', function () {
	$registry = new BoxRegistry( new FieldRegistry() );

	$registry->register( 'a', array( 'fields' => array( array( 'type' => 'clone', 'clone' => 'b' ) ) ) );
	$registry->register( 'b', array( 'fields' => array( array( 'type' => 'clone', 'clone' => 'a' ) ) ) );

	$registry->get( 'a' )->fields();
} )->throws( InvalidArgumentException::class, 'ciclo' );

it( 'dice en qué caja estaba el clon roto', function () {
	$registry = new BoxRegistry( new FieldRegistry() );

	$registry->register(
		'caja_con_errata',
		array(
			'fields' => array( array( 'type' => 'clone', 'clone' => 'no_existe' ) ),
		)
	);

	$registry->get( 'caja_con_errata' )->fields();
} )->throws( InvalidArgumentException::class, 'caja_con_errata' );

it( 'construye los campos una sola vez', function () {
	$registry = new BoxRegistry( new FieldRegistry() );

	$registry->register(
		'caja',
		array(
			'fields' => array( array( 'type' => 'text', 'name' => 'uno' ) ),
		)
	);

	$box = $registry->get( 'caja' );

	expect( $box->fields()[0] )->toBe( $box->fields()[0] );
} );

it( 'clona desde una caja registrada, y las claves quedan como si se hubieran escrito a mano', function () {
	$registry = new BoxRegistry( new FieldRegistry() );

	$registry->sets()->register( 'seo_compartido', $this->seo );

	$registry->register(
		'origen',
		array(
			'fields' => array(
				array( 'type' => 'clone', 'clone' => 'seo_compartido' ),
			),
		)
	);

	$registry->register(
		'destino',
		array(
			'fields' => array(
				array(
					'type'        => 'clone',
					'name'        => 'copia',
					'clone'       => 'origen',
					'prefix_name' => true,
				),
			),
		)
	);

	$nombres = array_map(
		static fn ( $field ): string => $field->name(),
		$registry->get( 'destino' )->fields()
	);

	expect( $nombres )->toBe( array( 'copia_titulo', 'copia_descripcion' ) );
} );

it( 'deja los campos clonados legibles por forja_get_field', function () {
	$registry = new BoxRegistry( new FieldRegistry() );

	$registry->sets()->register(
		'medidas',
		array(
			array(
				'type' => 'number',
				'name' => 'ancho',
			),
		)
	);

	$registry->register(
		'caja',
		array(
			'fields' => array( array( 'type' => 'clone', 'clone' => 'medidas' ) ),
		)
	);

	// El campo se encuentra por su nombre propio: el clon no dejó rastro en la
	// clave, que es justo lo que permite leer datos de un ACF existente.
	expect( $registry->find_field( 'ancho' ) )->not->toBeNull()
		->and( $registry->find_field( 'ancho' )->format_value( '42' ) )->toBe( 42 );
} );
