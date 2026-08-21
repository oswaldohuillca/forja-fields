<?php
/**
 * Fachada pública para los temas.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja;

defined( 'ABSPATH' ) || exit;

/**
 * Lo que un tema necesita escribir para poner sus assets donde toca.
 *
 * Antes había que copiar en cada proyecto la lista de pantallas del escritorio
 * donde Forja pinta campos, y esa lista **ya vive dentro del paquete**.
 * Duplicarla la condenaba a desincronizarse: al añadir el contexto de páginas de
 * opciones, un tema que no actualizara su copia se quedaba sin estilos ahí sin
 * enterarse.
 *
 * Uso:
 *
 *     use Forja\ForjaFields;
 *
 *     ForjaFields::css( '/assets/build/css/admin.css' );
 *     ForjaFields::js( '/assets/build/js/admin.js' );
 *
 * Se puede llamar en cualquier momento —incluso en la raíz del `functions.php`—
 * porque el encolado se aplaza hasta `admin_enqueue_scripts`.
 */
final class ForjaFields {

	/**
	 * Assets declarados, en orden de declaración.
	 *
	 * @var array<int, array{kind: string, path: string, deps: array<int, string>, handle: string}>
	 */
	private static array $queue = array();

	/**
	 * Si el gancho de encolado ya está puesto.
	 *
	 * @var bool
	 */
	private static bool $hooked = false;

	/**
	 * Declara una hoja de estilos para las pantallas de Forja.
	 *
	 * @param string             $path   Ruta relativa al tema, o URL completa.
	 * @param array<int, string> $deps   Dependencias registradas.
	 * @param string|null        $handle Identificador; se deriva del archivo si se omite.
	 * @return void
	 */
	public static function css( string $path, array $deps = array(), ?string $handle = null ): void {
		self::add( 'css', $path, $deps, $handle );
	}

	/**
	 * Declara un script para las pantallas de Forja.
	 *
	 * @param string             $path   Ruta relativa al tema, o URL completa.
	 * @param array<int, string> $deps   Dependencias registradas.
	 * @param string|null        $handle Identificador; se deriva del archivo si se omite.
	 * @return void
	 */
	public static function js( string $path, array $deps = array(), ?string $handle = null ): void {
		self::add( 'js', $path, $deps, $handle );
	}

	/**
	 * Apunta un asset y se asegura de que el gancho esté puesto.
	 *
	 * @param string             $kind   css o js.
	 * @param string             $path   Ruta relativa al tema, o URL completa.
	 * @param array<int, string> $deps   Dependencias registradas.
	 * @param string|null        $handle Identificador.
	 * @return void
	 */
	private static function add( string $kind, string $path, array $deps, ?string $handle ): void {
		self::$queue[] = array(
			'kind'   => $kind,
			'path'   => $path,
			'deps'   => array_values( array_map( 'strval', $deps ) ),
			'handle' => $handle ?? self::handle_for( $path ),
		);

		if ( self::$hooked ) {
			return;
		}

		self::$hooked = true;

		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Encola lo declarado, si la pantalla lo merece.
	 *
	 * @param string $hook_suffix Pantalla actual de administración.
	 * @return void
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( ! Assets::is_field_screen( $hook_suffix ) ) {
			return;
		}

		foreach ( self::$queue as $asset ) {
			$url = self::url( $asset['path'] );

			if ( '' === $url ) {
				continue;
			}

			$version = self::version( $asset['path'] );

			if ( 'css' === $asset['kind'] ) {
				wp_enqueue_style( $asset['handle'], $url, $asset['deps'], $version );

				continue;
			}

			wp_enqueue_script( $asset['handle'], $url, $asset['deps'], $version, true );
		}
	}

	/**
	 * Identificador derivado del nombre del archivo.
	 *
	 * @param string $path Ruta declarada.
	 * @return string Identificador único y legible.
	 */
	private static function handle_for( string $path ): string {
		$name = pathinfo( $path, PATHINFO_FILENAME );
		$kind = pathinfo( $path, PATHINFO_EXTENSION );

		return 'forja-tema-' . sanitize_key( $name . '-' . $kind );
	}

	/**
	 * Dirección desde la que se sirve un asset.
	 *
	 * Una ruta que no exista devuelve cadena vacía y se salta, en lugar de
	 * emitir una etiqueta que daría 404.
	 *
	 * @param string $path Ruta relativa al tema, o URL completa.
	 * @return string URL, o cadena vacía si el archivo no está.
	 */
	private static function url( string $path ): string {
		if ( self::is_absolute( $path ) ) {
			return $path;
		}

		return is_readable( self::file( $path ) )
			? get_stylesheet_directory_uri() . '/' . ltrim( $path, '/' )
			: '';
	}

	/**
	 * Ruta en disco de un asset del tema.
	 *
	 * @param string $path Ruta relativa al tema.
	 * @return string Ruta absoluta.
	 */
	private static function file( string $path ): string {
		return get_stylesheet_directory() . '/' . ltrim( $path, '/' );
	}

	/**
	 * Versión con la que se rompe la caché.
	 *
	 * Se usa la fecha de modificación del archivo: cambia sola al recompilar,
	 * que es justo lo que hace falta mientras se desarrolla.
	 *
	 * @param string $path Ruta declarada.
	 * @return string|null Versión, o null si no se puede saber.
	 */
	private static function version( string $path ): ?string {
		if ( self::is_absolute( $path ) ) {
			return null;
		}

		$time = filemtime( self::file( $path ) );

		return false === $time ? null : (string) $time;
	}

	/**
	 * Indica si la ruta ya es una dirección completa.
	 *
	 * @param string $path Ruta declarada.
	 * @return bool True si no hay que resolverla contra el tema.
	 */
	private static function is_absolute( string $path ): bool {
		return str_starts_with( $path, 'http://' )
			|| str_starts_with( $path, 'https://' )
			|| str_starts_with( $path, '//' );
	}
}
