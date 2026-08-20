<?php
/**
 * Genera el archivo de plantilla de traducción.
 *
 * Hace el trabajo de `wp i18n make-pot`, que no está disponible: el contenedor
 * no trae wp-cli ni gettext, y añadir cualquiera de los dos por un archivo que
 * se regenera de tarde en tarde no compensa. La superficie a cubrir es pequeña
 * y conocida —una decena de funciones de WordPress— así que se extrae con el
 * tokenizador de PHP, que es el mismo enfoque que usa wp-cli.
 *
 * Uso:
 *
 *     php tools/make-pot.php
 *
 * Escribe `languages/forja-fields.pot` y avisa por la salida de error de todo
 * lo que no ha podido extraer.
 *
 * @package Forja
 */

declare( strict_types = 1 );

/**
 * Dominio de texto del paquete.
 */
const FORJA_TEXT_DOMAIN = 'forja-fields';

/**
 * Funciones de traducción y en qué posición está cada argumento.
 *
 * `text` es la cadena a traducir; `plural` y `context` sólo existen en algunas.
 * `domain` sirve para detectar llamadas con el dominio equivocado, que serían
 * cadenas que nunca se traducen.
 */
const FORJA_I18N_FUNCTIONS = array(
	'__'           => array( 'text' => 0, 'domain' => 1 ),
	'_e'           => array( 'text' => 0, 'domain' => 1 ),
	'esc_html__'   => array( 'text' => 0, 'domain' => 1 ),
	'esc_html_e'   => array( 'text' => 0, 'domain' => 1 ),
	'esc_attr__'   => array( 'text' => 0, 'domain' => 1 ),
	'esc_attr_e'   => array( 'text' => 0, 'domain' => 1 ),
	'_x'           => array( 'text' => 0, 'context' => 1, 'domain' => 2 ),
	'esc_html_x'   => array( 'text' => 0, 'context' => 1, 'domain' => 2 ),
	'esc_attr_x'   => array( 'text' => 0, 'context' => 1, 'domain' => 2 ),
	'_n'           => array( 'text' => 0, 'plural' => 1, 'domain' => 3 ),
	'_nx'          => array( 'text' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4 ),
	'_n_noop'      => array( 'text' => 0, 'plural' => 1, 'domain' => 2 ),
	'_nx_noop'     => array( 'text' => 0, 'plural' => 1, 'context' => 2, 'domain' => 3 ),
);

/**
 * Recorre un directorio y devuelve sus archivos PHP.
 *
 * @param string $dir Directorio raíz.
 * @return array<int, string> Rutas de archivo.
 */
function forja_pot_php_files( string $dir ): array {
	if ( ! is_dir( $dir ) ) {
		return array();
	}

	$files    = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === $file->getExtension() ) {
			$files[] = $file->getPathname();
		}
	}

	// El orden del sistema de archivos no es estable entre máquinas, y sin
	// ordenar el .pot cambiaría de un equipo a otro sin que cambie el código.
	sort( $files );

	return $files;
}

/**
 * Extrae las cadenas traducibles de un archivo.
 *
 * @param string             $file     Ruta del archivo.
 * @param string             $relative Ruta relativa, para las referencias.
 * @param array<int, string> $warnings Avisos acumulados, por referencia.
 * @return array<string, array<string, mixed>> Entradas indexadas por su clave de gettext.
 */
function forja_pot_extract( string $file, string $relative, array &$warnings ): array {
	$tokens  = token_get_all( (string) file_get_contents( $file ) );
	$entries = array();
	$comment = null;

	foreach ( $tokens as $index => $token ) {
		// Un comentario «translators:» documenta la llamada siguiente. Se guarda
		// hasta encontrarla, porque entre ambos suele haber saltos de línea.
		if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			if ( false !== stripos( $token[1], 'translators:' ) ) {
				$comment = forja_pot_clean_comment( $token[1] );
			}

			continue;
		}

		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( FORJA_I18N_FUNCTIONS[ $token[1] ] ) ) {
			continue;
		}

		// Un método que se llame igual —`$obj->__()`— no es una traducción.
		if ( forja_pot_is_member_call( $tokens, $index ) ) {
			continue;
		}

		$args = forja_pot_arguments( $tokens, $index );

		if ( null === $args ) {
			continue;
		}

		$spec      = FORJA_I18N_FUNCTIONS[ $token[1] ];
		$reference = $relative . ':' . $token[2];
		$text      = $args[ $spec['text'] ] ?? null;

		if ( null === $text ) {
			$warnings[] = sprintf( '%s: el texto de %s() no es una cadena literal y no se puede extraer.', $reference, $token[1] );
			$comment    = null;
			continue;
		}

		$domain = $args[ $spec['domain'] ] ?? null;

		if ( FORJA_TEXT_DOMAIN !== $domain ) {
			$warnings[] = sprintf(
				'%s: %s() usa el dominio «%s» en vez de «%s»; esa cadena no se traducirá nunca.',
				$reference,
				$token[1],
				$domain ?? '(ninguno)',
				FORJA_TEXT_DOMAIN
			);
		}

		$context = isset( $spec['context'] ) ? ( $args[ $spec['context'] ] ?? null ) : null;
		$plural  = isset( $spec['plural'] ) ? ( $args[ $spec['plural'] ] ?? null ) : null;
		$key     = ( $context ?? '' ) . "\4" . $text;

		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = array(
				'text'       => $text,
				'plural'     => $plural,
				'context'    => $context,
				'comments'   => array(),
				'references' => array(),
			);
		}

		$entries[ $key ]['references'][] = $reference;

		if ( null !== $comment ) {
			$entries[ $key ]['comments'][] = $comment;
		}

		$comment = null;
	}

	return $entries;
}

/**
 * Indica si la llamada es a un método o a una constante de clase.
 *
 * @param array<int, mixed> $tokens Tokens del archivo.
 * @param int               $index  Posición del nombre de función.
 * @return bool True si va precedida de «->», «?->» o «::».
 */
function forja_pot_is_member_call( array $tokens, int $index ): bool {
	for ( $i = $index - 1; $i >= 0; $i-- ) {
		$token = $tokens[ $i ];

		if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}

		return is_array( $token )
			? in_array( $token[0], array( T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON ), true )
			: false;
	}

	return false;
}

/**
 * Devuelve los argumentos literales de una llamada.
 *
 * Los argumentos que no son cadenas literales —una variable, una concatenación
 * o una constante— se devuelven como null en su posición. Es información
 * suficiente: el extractor sólo necesita saber que no puede leerlos.
 *
 * @param array<int, mixed> $tokens Tokens del archivo.
 * @param int               $index  Posición del nombre de función.
 * @return array<int, string|null>|null Argumentos, o null si no era una llamada.
 */
function forja_pot_arguments( array $tokens, int $index ): ?array {
	$total = count( $tokens );
	$i     = $index + 1;

	while ( $i < $total && is_array( $tokens[ $i ] ) && T_WHITESPACE === $tokens[ $i ][0] ) {
		++$i;
	}

	if ( $i >= $total || '(' !== $tokens[ $i ] ) {
		return null;
	}

	$depth     = 0;
	$args      = array();
	$position  = 0;
	$literal   = null;
	$only_text = true;

	for ( ; $i < $total; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) ) {
			if ( '(' === $token || '[' === $token ) {
				++$depth;

				if ( $depth > 1 ) {
					$only_text = false;
				}

				continue;
			}

			if ( ')' === $token || ']' === $token ) {
				--$depth;

				if ( 0 === $depth ) {
					$args[ $position ] = $only_text ? $literal : null;

					return $args;
				}

				continue;
			}

			// Una coma del primer nivel cierra un argumento; las de dentro de
			// una llamada anidada pertenecen a esa llamada, no a esta.
			if ( ',' === $token && 1 === $depth ) {
				$args[ $position ] = $only_text ? $literal : null;
				++$position;
				$literal   = null;
				$only_text = true;

				continue;
			}

			if ( 1 === $depth ) {
				$only_text = false;
			}

			continue;
		}

		if ( in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}

		if ( 1 !== $depth ) {
			continue;
		}

		if ( T_CONSTANT_ENCAPSED_STRING === $token[0] && null === $literal ) {
			$literal = forja_pot_unquote( $token[1] );

			continue;
		}

		// Cualquier otra cosa en el argumento —una concatenación, una variable—
		// lo vuelve no literal.
		$only_text = false;
	}

	return null;
}

/**
 * Convierte el literal de PHP en su valor.
 *
 * @param string $raw Literal tal como aparece en el código, con comillas.
 * @return string Valor de la cadena.
 */
function forja_pot_unquote( string $raw ): string {
	$quote = $raw[0];
	$inner = substr( $raw, 1, -1 );

	if ( "'" === $quote ) {
		return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $inner );
	}

	return stripcslashes( $inner );
}

/**
 * Limpia un comentario para traductores.
 *
 * @param string $raw Comentario tal como aparece en el código.
 * @return string Texto del comentario, en una sola línea.
 */
function forja_pot_clean_comment( string $raw ): string {
	$clean = preg_replace( '#^/\*+|\*+/$|^//#', '', trim( $raw ) );
	$lines = array_map(
		static fn ( string $line ): string => trim( ltrim( trim( $line ), '*' ) ),
		explode( "\n", (string) $clean )
	);

	return trim( implode( ' ', array_filter( $lines ) ) );
}

/**
 * Escapa una cadena para el formato PO.
 *
 * @param string $value Valor a escapar.
 * @return string Valor entre comillas.
 */
function forja_pot_quote( string $value ): string {
	$escaped = str_replace(
		array( '\\', '"', "\n", "\t", "\r" ),
		array( '\\\\', '\\"', '\\n', '\\t', '\\r' ),
		$value
	);

	return '"' . $escaped . '"';
}

// --- Ejecución ---------------------------------------------------------------

$root     = dirname( __DIR__ );
$warnings = array();
$entries  = array();

foreach ( array( 'src', 'includes' ) as $dir ) {
	foreach ( forja_pot_php_files( $root . '/' . $dir ) as $file ) {
		$relative = substr( $file, strlen( $root ) + 1 );

		foreach ( forja_pot_extract( $file, $relative, $warnings ) as $key => $entry ) {
			if ( isset( $entries[ $key ] ) ) {
				$entries[ $key ]['references'] = array_merge( $entries[ $key ]['references'], $entry['references'] );
				$entries[ $key ]['comments']   = array_merge( $entries[ $key ]['comments'], $entry['comments'] );

				// Una cadena que aparece con y sin plural es un error sutil:
				// gettext se queda con la primera forma que ve.
				if ( null === $entries[ $key ]['plural'] ) {
					$entries[ $key ]['plural'] = $entry['plural'];
				}

				continue;
			}

			$entries[ $key ] = $entry;
		}
	}
}

ksort( $entries );

$lines = array(
	'# Plantilla de traducción de Forja.',
	'# Generada con tools/make-pot.php; no la edites a mano.',
	'#',
	'# Ojo: los msgid están en español, no en inglés. El paquete se escribe en',
	'# español y sus textos visibles reproducen los de ACF para que el markup sea',
	'# idéntico. Traducir es, por tanto, español → idioma destino.',
	'msgid ""',
	'msgstr ""',
	'"Project-Id-Version: Forja\\n"',
	'"MIME-Version: 1.0\\n"',
	'"Content-Type: text/plain; charset=UTF-8\\n"',
	'"Content-Transfer-Encoding: 8bit\\n"',
	'"X-Domain: ' . FORJA_TEXT_DOMAIN . '\\n"',
	'"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
	'',
);

foreach ( $entries as $entry ) {
	foreach ( array_unique( $entry['comments'] ) as $comment ) {
		$lines[] = '#. ' . $comment;
	}

	$lines[] = '#: ' . implode( ' ', array_unique( $entry['references'] ) );

	if ( null !== $entry['context'] ) {
		$lines[] = 'msgctxt ' . forja_pot_quote( $entry['context'] );
	}

	$lines[] = 'msgid ' . forja_pot_quote( $entry['text'] );

	if ( null !== $entry['plural'] ) {
		$lines[] = 'msgid_plural ' . forja_pot_quote( $entry['plural'] );
		$lines[] = 'msgstr[0] ""';
		$lines[] = 'msgstr[1] ""';
	} else {
		$lines[] = 'msgstr ""';
	}

	$lines[] = '';
}

if ( ! is_dir( $root . '/languages' ) ) {
	mkdir( $root . '/languages', 0755, true );
}

file_put_contents( $root . '/languages/' . FORJA_TEXT_DOMAIN . '.pot', implode( "\n", $lines ) );

printf( "languages/%s.pot: %d cadenas.\n", FORJA_TEXT_DOMAIN, count( $entries ) );

foreach ( array_unique( $warnings ) as $warning ) {
	fwrite( STDERR, '  aviso: ' . $warning . "\n" );
}

exit( array() === $warnings ? 0 : 1 );
