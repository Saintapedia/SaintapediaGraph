<?php

namespace MediaWiki\Extension\SaintapediaGraph\ParserFunctions;

use MediaWiki\Extension\SaintapediaGraph\GraphRenderer;
use MediaWiki\Extension\SaintapediaGraph\Mermaid\DiagramService;
use Exception;
use Parser;
use PPFrame;

/**
 * {{#saintapedia_graph: … }} and {{#cargo_mermaid: … }}
 */
class SaintapediaGraphParserFunction {

	/**
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return array
	 */
	public static function run( Parser $parser, PPFrame $frame, array $args ) {
		$params = self::parseArgs( $frame, $args );

		// Count Cargo work as expensive. Dual mode runs two queries.
		$isDual = ( $params['nodes_table'] ?? '' ) !== ''
			&& ( $params['edges_table'] ?? '' ) !== '';
		$expensiveCalls = $isDual ? 2 : 1;
		for ( $i = 0; $i < $expensiveCalls; $i++ ) {
			if ( !$parser->incrementExpensiveFunctionCount() ) {
				return self::errorBox(
					wfMessage( 'saintapediagraph-error-expensive' )->text()
				);
			}
		}

		try {
			$service = new DiagramService();
			$result = $service->buildFromParams( $params );
		} catch ( \Throwable $e ) {
			return self::errorBox( $e->getMessage() );
		}

		$format = strtolower( $params['format'] ?? 'mermaid' );
		$warningsHtml = self::warningsHtml( $result['warnings'] ?? [] );

		if ( $format === 'raw' || $format === 'source' || $format === 'text' ) {
			$html = $warningsHtml
				. '<pre class="saintapedia-graph-source">'
				. htmlspecialchars( $result['source'], ENT_QUOTES | ENT_HTML5, 'UTF-8' )
				. '</pre>';
			return [ $html, 'noparse' => true, 'isHTML' => true ];
		}

		$renderer = new GraphRenderer();
		$html = $warningsHtml . $renderer->render( $parser, $result['source'], $params );
		return [ $html, 'noparse' => true, 'isHTML' => true ];
	}

	/**
	 * @param PPFrame $frame
	 * @param array $args
	 * @return array<string, string>
	 */
	private static function parseArgs( PPFrame $frame, array $args ): array {
		$params = [];
		foreach ( $args as $arg ) {
			$expanded = trim( $frame->expand( $arg ) );
			if ( $expanded === '' ) {
				continue;
			}
			$eq = strpos( $expanded, '=' );
			if ( $eq === false ) {
				if ( !isset( $params['tables'] ) ) {
					$params['tables'] = $expanded;
				}
				continue;
			}
			$key = strtolower( trim( substr( $expanded, 0, $eq ) ) );
			$key = str_replace( [ ' ', '-' ], '_', $key );
			$key = preg_replace( '/_+/', '_', $key ) ?? $key;
			$params[$key] = trim( substr( $expanded, $eq + 1 ) );
		}
		return $params;
	}

	/**
	 * @param list<string> $warnings
	 * @return string
	 */
	private static function warningsHtml( array $warnings ): string {
		if ( $warnings === [] ) {
			return '';
		}
		$html = '<div class="saintapedia-graph-warnings">';
		foreach ( $warnings as $w ) {
			$html .= '<p class="saintapedia-graph-warning">'
				. htmlspecialchars( $w, ENT_QUOTES | ENT_HTML5, 'UTF-8' )
				. '</p>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * @param string $message
	 * @return array
	 */
	private static function errorBox( string $message ): array {
		$html = '<div class="error saintapedia-graph-error">'
			. htmlspecialchars( $message, ENT_QUOTES | ENT_HTML5, 'UTF-8' )
			. '</div>';
		return [ $html, 'noparse' => true, 'isHTML' => true ];
	}
}
