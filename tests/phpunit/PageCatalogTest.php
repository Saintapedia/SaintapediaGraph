<?php

namespace MediaWiki\Extension\SaintapediaGraph\Tests;

use MediaWiki\Extension\SaintapediaGraph\Maintenance\PageCatalog;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\SaintapediaGraph\Maintenance\PageCatalog
 */
class PageCatalogTest extends TestCase {

	public function testPagesHaveTitlesAndReadableFiles() {
		$root = dirname( __DIR__, 2 );
		$pages = PageCatalog::pages();
		$this->assertNotEmpty( $pages );

		$titles = [];
		foreach ( $pages as $entry ) {
			$this->assertArrayHasKey( 'title', $entry );
			$this->assertArrayHasKey( 'file', $entry );
			$this->assertNotSame( '', $entry['title'] );
			$this->assertDoesNotMatchRegularExpression( '/\.\./', $entry['file'] );
			$path = PageCatalog::resolve( $root, $entry['file'] );
			$this->assertFileExists( $path, $entry['file'] );
			$this->assertNotFalse( file_get_contents( $path ) );
			$titles[] = $entry['title'];
		}

		$this->assertContains( 'Help:Saintapedia Graph', $titles );
		$this->assertContains( 'Template:Org chart', $titles );
		$this->assertContains( 'Template:Funding network', $titles );
		$this->assertSame( count( $titles ), count( array_unique( $titles ) ) );
	}

	public function testResolveJoinsRootAndRelative() {
		$path = PageCatalog::resolve( '/ext/SaintapediaGraph', 'docs/Help-Saintapedia_Graph.wikitext' );
		$this->assertStringEndsWith(
			'docs' . DIRECTORY_SEPARATOR . 'Help-Saintapedia_Graph.wikitext',
			$path
		);
		$this->assertStringStartsWith( '/ext/SaintapediaGraph', $path );
	}
}
