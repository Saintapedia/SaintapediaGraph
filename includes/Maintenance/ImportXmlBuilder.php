<?php

namespace MediaWiki\Extension\SaintapediaGraph\Maintenance;

/**
 * Build a MediaWiki Special:Import XML dump from PageCatalog entries.
 * No MediaWiki runtime required.
 */
class ImportXmlBuilder {

	/**
	 * @param list<array{title:string,file:string}> $pages
	 * @param string $extensionRoot
	 * @return string
	 */
	public static function build( array $pages, string $extensionRoot ): string {
		$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$out .= '<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.11/"'
			. ' version="0.11" xml:lang="en">' . "\n";
		foreach ( $pages as $entry ) {
			$path = PageCatalog::resolve( $extensionRoot, $entry['file'] );
			$text = file_get_contents( $path );
			if ( $text === false ) {
				throw new \RuntimeException( 'Missing source file: ' . $entry['file'] );
			}
			$out .= self::pageXml( $entry['title'], $text );
		}
		$out .= "</mediawiki>\n";
		return $out;
	}

	public static function namespaceId( string $title ): int {
		if ( str_starts_with( $title, 'Help:' ) ) {
			return 12;
		}
		if ( str_starts_with( $title, 'Template:' ) ) {
			return 10;
		}
		return 0;
	}

	private static function pageXml( string $title, string $text ): string {
		$ns = self::namespaceId( $title );
		$escTitle = self::esc( $title );
		$escText = self::esc( $text );
		$bytes = strlen( $text );
		return <<<XML
  <page>
    <title>{$escTitle}</title>
    <ns>{$ns}</ns>
    <revision>
      <contributor>
        <username>SaintapediaGraph</username>
      </contributor>
      <origin>0</origin>
      <model>wikitext</model>
      <format>text/x-wiki</format>
      <text bytes="{$bytes}" xml:space="preserve">{$escText}</text>
      <sha1 />
    </revision>
  </page>

XML;
	}

	private static function esc( string $s ): string {
		return htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}
}
