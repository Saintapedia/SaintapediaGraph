<?php

namespace MediaWiki\Extension\SaintapediaGraph;

use MediaWiki\Extension\SaintapediaGraph\Mermaid\MermaidBuilder;
use Parser;

/**
 * Emit a Mermaid mount point and load bundled Mermaid via ResourceLoader.
 *
 * Mermaid source is stored base64-encoded in data-mermaid so MediaWiki HTML
 * tidy / Remex cannot wrap diagram lines in <p>/<pre> tags.
 */
class GraphRenderer {

	/** @var int */
	private static $instanceCounter = 0;

	/**
	 * @param Parser $parser
	 * @param string $mermaidSource
	 * @param array<string, string> $params
	 * @return string HTML
	 */
	public function render( Parser $parser, string $mermaidSource, array $params = [] ): string {
		global $wgSaintapediaGraphUseStandaloneRenderer,
			$wgSaintapediaGraphDefaultTheme;

		$output = $parser->getOutput();
		$theme = $params['theme'] ?? '';
		if ( $theme === '' ) {
			$theme = $wgSaintapediaGraphDefaultTheme ?? 'default';
		}
		// Keep data-theme in lockstep with the validated theme in %%{init}%%.
		$theme = MermaidBuilder::normalizeTheme( (string)$theme );

		$useStandalone = $wgSaintapediaGraphUseStandaloneRenderer ?? true;
		if ( !$useStandalone ) {
			return '<pre class="saintapedia-graph-source">'
				. htmlspecialchars( $mermaidSource, ENT_QUOTES | ENT_HTML5, 'UTF-8' )
				. '</pre>';
		}

		$output->addModules( [ 'ext.saintapediaGraph' ] );

		self::$instanceCounter++;
		$unique = self::$instanceCounter . '-' . substr( md5( $mermaidSource . self::$instanceCounter ), 0, 10 );
		$id = 'saintapedia-graph-' . $unique;

		// PHP 8+ base64_encode() always returns a string; empty input is the
		// only case that would leave data-mermaid blank.
		if ( $mermaidSource === '' ) {
			return '<div class="error saintapedia-graph-error">'
				. htmlspecialchars(
					wfMessage( 'saintapediagraph-error-render' )->text(),
					ENT_QUOTES | ENT_HTML5, 'UTF-8'
				)
				. '</div>';
		}
		$b64 = base64_encode( $mermaidSource );

		// data-theme mirrors the theme embedded in the Mermaid source (%%{init}%%)
		// for debugging; client rendering uses the source init block.
		return self::rawElement(
			'div',
			[
				'id' => $id,
				'class' => 'saintapedia-graph',
				'data-theme' => $theme,
				'data-mermaid' => $b64,
			],
			// Empty mount; JS fills with SVG. Keep a noscript fallback.
			'<noscript><pre class="saintapedia-graph-source">'
				. htmlspecialchars( $mermaidSource, ENT_QUOTES | ENT_HTML5, 'UTF-8' )
				. '</pre></noscript>'
		);
	}

	/**
	 * @param string $element
	 * @param array $attribs
	 * @param string $contents
	 * @return string
	 */
	private static function rawElement( string $element, array $attribs, string $contents ): string {
		if ( class_exists( \MediaWiki\Html\Html::class ) ) {
			return \MediaWiki\Html\Html::rawElement( $element, $attribs, $contents );
		}
		if ( class_exists( \Html::class ) ) {
			return \Html::rawElement( $element, $attribs, $contents );
		}
		$attr = '';
		foreach ( $attribs as $k => $v ) {
			$attr .= ' ' . htmlspecialchars( $k ) . '="' . htmlspecialchars( (string)$v ) . '"';
		}
		return "<{$element}{$attr}>{$contents}</{$element}>";
	}
}
