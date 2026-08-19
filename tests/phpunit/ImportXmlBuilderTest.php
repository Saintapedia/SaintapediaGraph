<?php

namespace MediaWiki\Extension\SaintapediaGraph\Tests;

use MediaWiki\Extension\SaintapediaGraph\Maintenance\ImportXmlBuilder;
use MediaWiki\Extension\SaintapediaGraph\Maintenance\PageCatalog;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\SaintapediaGraph\Maintenance\ImportXmlBuilder
 */
class ImportXmlBuilderTest extends TestCase {

	public function testBuildEmitsWellFormedXmlWithCatalogTitles(): void {
		$root = dirname( __DIR__, 2 );
		$xml = ImportXmlBuilder::build( PageCatalog::pages(), $root );
		$doc = new \DOMDocument();
		$this->assertTrue( $doc->loadXML( $xml ) );
		$titles = [];
		foreach ( $doc->getElementsByTagName( 'title' ) as $node ) {
			$titles[] = $node->textContent;
		}
		$this->assertSame( array_column( PageCatalog::pages(), 'title' ), $titles );
		$this->assertStringContainsString( 'xmlns="http://www.mediawiki.org/xml/export-0.11/"', $xml );
	}

	public function testHelpPageUsesHelpNamespace(): void {
		$root = dirname( __DIR__, 2 );
		$xml = ImportXmlBuilder::build( [
			[ 'title' => 'Help:Saintapedia Graph', 'file' => 'docs/Help-Saintapedia_Graph.wikitext' ],
			[ 'title' => 'Template:Org chart', 'file' => 'templates/Org_chart.wikitext' ],
			[ 'title' => 'Demo Archdiocese of Northbridge', 'file' => 'examples/pages/Demo_Archdiocese_of_Northbridge.wikitext' ],
		], $root );
		$doc = new \DOMDocument();
		$doc->loadXML( $xml );
		$ns = [];
		foreach ( $doc->getElementsByTagName( 'page' ) as $page ) {
			$title = $page->getElementsByTagName( 'title' )->item( 0 )->textContent;
			$ns[$title] = (int)$page->getElementsByTagName( 'ns' )->item( 0 )->textContent;
		}
		$this->assertSame( 12, $ns['Help:Saintapedia Graph'] );
		$this->assertSame( 10, $ns['Template:Org chart'] );
		$this->assertSame( 0, $ns['Demo Archdiocese of Northbridge'] );
	}

	public function testCommittedDumpsMatchCatalog(): void {
		$root = dirname( __DIR__, 2 );
		$help = $root . '/docs/import/SaintapediaGraph-help.xml';
		$examples = $root . '/docs/import/SaintapediaGraph-examples.xml';
		$this->assertFileExists( $help );
		$this->assertFileExists( $examples );
		$this->assertSame(
			array_column( PageCatalog::pages(), 'title' ),
			$this->xmlTitles( $help )
		);
		$this->assertSame(
			array_column( PageCatalog::catalog( true ), 'title' ),
			$this->xmlTitles( $examples )
		);
	}

	/**
	 * @return list<string>
	 */
	private function xmlTitles( string $path ): array {
		$doc = new \DOMDocument();
		$doc->load( $path );
		$titles = [];
		foreach ( $doc->getElementsByTagName( 'title' ) as $node ) {
			$titles[] = $node->textContent;
		}
		return $titles;
	}
}
