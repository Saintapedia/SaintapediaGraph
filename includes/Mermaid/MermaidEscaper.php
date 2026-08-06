<?php

namespace MediaWiki\Extension\SaintapediaGraph\Mermaid;

/**
 * Escape helpers for Mermaid node IDs, labels, and class names.
 */
class MermaidEscaper {

	/**
	 * Stable, Mermaid-safe node ID (ASCII, hashed suffix).
	 *
	 * @param string $raw
	 * @return string
	 */
	public static function nodeId( string $raw ): string {
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return 'n_empty';
		}

		$slug = preg_replace( '/[^\p{L}\p{N}_]+/u', '_', $raw );
		$slug = preg_replace( '/_+/', '_', $slug ?? '' );
		$slug = trim( $slug, '_' );

		if ( $slug === '' || !preg_match( '/^[\p{L}_]/u', $slug ) ) {
			$slug = 'n_' . $slug;
		}
		if ( mb_strlen( $slug ) > 48 ) {
			$slug = mb_substr( $slug, 0, 40 );
		}

		$hash = substr( md5( $raw ), 0, 8 );
		$ascii = preg_replace( '/[^A-Za-z0-9_]/', '_', $slug );
		$ascii = preg_replace( '/_+/', '_', $ascii ?? 'n' );
		$ascii = trim( $ascii, '_' );
		if ( $ascii === '' || !preg_match( '/^[A-Za-z_]/', $ascii ) ) {
			$ascii = 'n_' . $ascii;
		}

		return $ascii . '_' . $hash;
	}

	/**
	 * Escape text for a Mermaid node label inside ["…"].
	 *
	 * @param string $raw
	 * @return string
	 */
	public static function label( string $raw ): string {
		$raw = trim( html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$raw = strip_tags( $raw );
		$raw = preg_replace( '/\s+/u', ' ', $raw ) ?? $raw;
		$raw = str_replace(
			[ '\\', '"', "'", "\n", "\r", "\t", '[', ']', '{', '}', ';', '`' ],
			[ '', '', '', ' ', ' ', ' ', '(', ')', '(', ')', ',', '' ],
			$raw
		);
		return trim( $raw );
	}

	/**
	 * Escape edge label (no pipes).
	 *
	 * @param string $raw
	 * @return string
	 */
	public static function edgeLabel( string $raw ): string {
		$raw = self::label( $raw );
		$raw = str_replace( '|', '/', $raw );
		if ( mb_strlen( $raw ) > 64 ) {
			$raw = mb_substr( $raw, 0, 61 ) . '...';
		}
		return $raw;
	}

	/**
	 * Escape a Mermaid click tooltip (the third token of
	 * `click id "url" "tooltip"`).
	 *
	 * Strips control characters / newlines that would break the diagram line,
	 * then escapes backslashes and double quotes for the quoted string.
	 *
	 * @param string $pageTitle
	 * @return string
	 */
	public static function clickTarget( string $pageTitle ): string {
		$pageTitle = trim( $pageTitle );
		// Control chars / newlines would terminate or corrupt the click line.
		$pageTitle = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $pageTitle ) ?? $pageTitle;
		$pageTitle = preg_replace( '/\s+/u', ' ', $pageTitle ) ?? $pageTitle;
		$pageTitle = trim( $pageTitle );
		return str_replace( [ '\\', '"' ], [ '\\\\', '\\"' ], $pageTitle );
	}

	/**
	 * Sanitize a classDef / class name token (style_by).
	 *
	 * @param string $raw
	 * @return string
	 */
	public static function className( string $raw ): string {
		$raw = preg_replace( '/[^A-Za-z0-9_]/', '_', $raw ) ?? 'cls';
		$raw = trim( $raw, '_' );
		if ( $raw === '' || !preg_match( '/^[A-Za-z_]/', $raw ) ) {
			$raw = 'cls_' . $raw;
		}
		return 'sg_' . $raw;
	}

	/**
	 * Sanitize a subgraph ID.
	 *
	 * @param string $raw
	 * @return string
	 */
	public static function subgraphId( string $raw ): string {
		return 'sg_' . self::nodeId( $raw );
	}
}
