<?php

/**
 * Minimal bootstrap for standalone PHPUnit (no full MediaWiki).
 */

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require_once $autoload;
} else {
	spl_autoload_register( static function ( string $class ): void {
		$prefix = 'MediaWiki\\Extension\\SaintapediaGraph\\';
		$base = dirname( __DIR__ ) . '/includes/';
		if ( strpos( $class, $prefix ) !== 0 ) {
			$tprefix = 'MediaWiki\\Extension\\SaintapediaGraph\\Tests\\';
			if ( strpos( $class, $tprefix ) !== 0 ) {
				return;
			}
			$rel = str_replace( '\\', '/', substr( $class, strlen( $tprefix ) ) ) . '.php';
			$file = dirname( __DIR__ ) . '/tests/phpunit/' . $rel;
			if ( is_readable( $file ) ) {
				require $file;
			}
			return;
		}
		$rel = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		$file = $base . $rel;
		if ( is_readable( $file ) ) {
			require $file;
		}
	} );
}

if ( !function_exists( 'wfMessage' ) ) {
	function wfMessage( $key, ...$params ) {
		return new class( $key, $params ) {
			private string $key;
			private array $params;

			public function __construct( $key, $params ) {
				$this->key = (string)$key;
				$this->params = $params;
			}

			public function text(): string {
				return $this->key . ( $this->params ? ': ' . implode( ', ', $this->params ) : '' );
			}

			public function plain(): string {
				return $this->text();
			}

			public function parse(): string {
				return htmlspecialchars( $this->text() );
			}

			public function escaped(): string {
				return htmlspecialchars( $this->text() );
			}
		};
	}
}

if ( !class_exists( 'MWException', false ) ) {
	class MWException extends Exception {
	}
}
