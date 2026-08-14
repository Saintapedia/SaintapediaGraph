<?php

namespace MediaWiki\Extension\SaintapediaGraph\Maintenance;

/**
 * Wiki pages this extension can import via maintenance/importPages.php.
 * No MediaWiki runtime required — unit-testable from the standalone suite.
 */
class PageCatalog {

	/**
	 * @return list<array{title:string,file:string}>
	 */
	public static function pages(): array {
		return [
			[
				'title' => 'Help:Saintapedia Graph',
				'file' => 'docs/Help-Saintapedia_Graph.wikitext',
			],
			[
				'title' => 'Template:Org chart',
				'file' => 'templates/Org_chart.wikitext',
			],
			[
				'title' => 'Template:Funding network',
				'file' => 'templates/Funding_network.wikitext',
			],
		];
	}

	/**
	 * Absolute path for a catalog file relative to the extension root.
	 *
	 * @param string $extensionRoot
	 * @param string $relative
	 * @return string
	 */
	public static function resolve( string $extensionRoot, string $relative ): string {
		return rtrim( $extensionRoot, "/\\" ) . DIRECTORY_SEPARATOR
			. str_replace( [ '/', '\\' ], DIRECTORY_SEPARATOR, $relative );
	}
}
