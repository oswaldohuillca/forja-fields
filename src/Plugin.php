<?php
/**
 * Contenedor y arranque del plugin.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja;

use Forja\Ajax\Search;
use Forja\Context\OptionsContext;
use Forja\Context\PostContext;
use Forja\Context\TermContext;
use Forja\Context\UserContext;
use Forja\Registry\BoxRegistry;
use Forja\Registry\FieldRegistry;
use Forja\Render\Renderer;
use Forja\Storage\StorageFactory;
use Forja\Validation\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Une las piezas del plugin y las engancha a WordPress.
 *
 * Se instancia una sola vez desde `forja()`. Las dependencias se construyen
 * de forma perezosa para que cargar el plugin en una petición del front-end
 * no arrastre nada del lado de administración.
 */
final class Plugin {

	/**
	 * Resolución de rutas y URLs del paquete.
	 *
	 * @var Paths
	 */
	private Paths $paths;

	/**
	 * Constructor.
	 *
	 * @param string $dir Directorio raíz del paquete.
	 */
	public function __construct( string $dir ) {
		$this->paths = new Paths( $dir );
	}

	/**
	 * Resolución de rutas y URLs.
	 *
	 * @return Paths Resolutor de rutas.
	 */
	public function paths(): Paths {
		return $this->paths;
	}

	/**
	 * Catálogo de tipos de campo.
	 *
	 * @var FieldRegistry|null
	 */
	private ?FieldRegistry $field_registry = null;

	/**
	 * Registro de cajas.
	 *
	 * @var BoxRegistry|null
	 */
	private ?BoxRegistry $box_registry = null;

	/**
	 * Fábrica de almacenamiento.
	 *
	 * @var StorageFactory|null
	 */
	private ?StorageFactory $storage = null;

	/**
	 * Renderizador de campos.
	 *
	 * @var Renderer|null
	 */
	private ?Renderer $renderer = null;

	/**
	 * Indica si el arranque ya se ejecutó.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Engancha el plugin a WordPress.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', array( $this, 'on_init' ), 5 );

		( new Assets( $this->paths ) )->register_hooks();

		// Búsqueda remota de los campos relacionales.
		( new Search( $this->boxes() ) )->register_hooks();

		// Cada pantalla del escritorio donde pueden aparecer campos tiene su
		// contexto; todos comparten la misma mecánica de leer y guardar.
		$contexts = array(
			PostContext::class,
			TermContext::class,
			UserContext::class,
			OptionsContext::class,
		);

		foreach ( $contexts as $context ) {
			( new $context( $this->boxes(), $this->renderer(), $this->storage(), new Validator() ) )->register_hooks();
		}
	}

	/**
	 * Punto de entrada para que los proyectos declaren sus campos.
	 *
	 * @return void
	 */
	public function on_init(): void {
		load_textdomain( 'forja-fields', $this->paths->dir( 'languages/forja-fields-' . determine_locale() . '.mo' ) );

		/**
		 * Momento en que se deben registrar tipos de campo adicionales.
		 *
		 * @param FieldRegistry $registry Catálogo de tipos de campo.
		 */
		do_action( 'forja/register_field_types', $this->fields() );

		/**
		 * Momento en que se deben declarar los grupos de campos.
		 *
		 * @param BoxRegistry $registry Registro de cajas.
		 */
		do_action( 'forja/register_boxes', $this->boxes() );
	}

	/**
	 * Catálogo de tipos de campo.
	 *
	 * @return FieldRegistry Catálogo.
	 */
	public function fields(): FieldRegistry {
		if ( null === $this->field_registry ) {
			$this->field_registry = new FieldRegistry();
		}

		return $this->field_registry;
	}

	/**
	 * Registro de cajas.
	 *
	 * @return BoxRegistry Registro.
	 */
	public function boxes(): BoxRegistry {
		if ( null === $this->box_registry ) {
			$this->box_registry = new BoxRegistry( $this->fields() );
		}

		return $this->box_registry;
	}

	/**
	 * Fábrica de almacenamiento.
	 *
	 * @return StorageFactory Fábrica.
	 */
	public function storage(): StorageFactory {
		if ( null === $this->storage ) {
			$this->storage = new StorageFactory();
		}

		return $this->storage;
	}

	/**
	 * Renderizador de campos.
	 *
	 * @return Renderer Renderizador.
	 */
	public function renderer(): Renderer {
		if ( null === $this->renderer ) {
			$this->renderer = new Renderer();
		}

		return $this->renderer;
	}
}
