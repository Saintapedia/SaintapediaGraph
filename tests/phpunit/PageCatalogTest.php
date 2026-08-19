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
		$this->assertContains( 'Template:Saintapedia Graph examples', $titles );
		$this->assertNotContains( 'Template:OrgDemo', $titles );
		$this->assertNotContains( 'Template:GrantDemo', $titles );
		$this->assertSame( count( $titles ), count( array_unique( $titles ) ) );
	}

	public function testDefaultCatalogOmitsExamples() {
		$titles = array_column( PageCatalog::catalog(), 'title' );
		$this->assertSame( array_column( PageCatalog::pages(), 'title' ), $titles );
		$this->assertNotContains( 'Template:OrgDemo', $titles );
	}

	public function testExampleCatalogIsOptInAndCollisionSafe() {
		$root = dirname( __DIR__, 2 );
		$examples = PageCatalog::examplePages();
		$this->assertNotEmpty( $examples );

		$titles = [];
		foreach ( $examples as $entry ) {
			$this->assertArrayHasKey( 'title', $entry );
			$this->assertArrayHasKey( 'file', $entry );
			$this->assertDoesNotMatchRegularExpression( '/\.\./', $entry['file'] );
			$path = PageCatalog::resolve( $root, $entry['file'] );
			$this->assertFileExists( $path, $entry['file'] );
			$text = file_get_contents( $path );
			$this->assertNotFalse( $text );
			$titles[] = $entry['title'];
		}

		$this->assertContains( 'Template:OrgDemo', $titles );
		$this->assertContains( 'Template:GrantDemo', $titles );
		foreach ( [
			'Template:DemoPlace',
			'Template:DemoHouse',
			'Template:DemoSchool',
			'Template:DemoBishop',
			'Template:DemoParishLink',
			'Template:DemoPatronage',
			'Template:DemoSuccessor',
		] as $need ) {
			$this->assertContains( $need, $titles );
		}
		$this->assertContains( 'Help:Saintapedia Graph/Examples', $titles );
		$this->assertContains( 'Help:Saintapedia Graph/Province', $titles );
		$this->assertContains( 'Help:Saintapedia Graph/Funding', $titles );
		$this->assertNotContains( 'Template:Organization', $titles );
		$this->assertNotContains( 'Template:Grant', $titles );
		foreach ( $titles as $title ) {
			if ( str_starts_with( $title, 'Help:' ) ) {
				$this->assertStringStartsWith( 'Help:Saintapedia Graph', $title );
			}
		}
		$this->assertSame( count( $titles ), count( array_unique( $titles ) ) );

		$merged = array_column( PageCatalog::catalog( true ), 'title' );
		foreach ( [ 'Help:Saintapedia Graph', 'Template:OrgDemo', 'Template:GrantDemo' ] as $need ) {
			$this->assertContains( $need, $merged );
		}
		$this->assertSame( count( $merged ), count( array_unique( $merged ) ) );
	}

	public function testDemoTemplatesUseOrgDemoAndGrantDemoTables() {
		$root = dirname( __DIR__, 2 );
		$byTitle = [];
		foreach ( PageCatalog::examplePages() as $entry ) {
			$byTitle[$entry['title']] = file_get_contents(
				PageCatalog::resolve( $root, $entry['file'] )
			);
		}

		$this->assertStringContainsString( '_table=OrgDemo', $byTitle['Template:OrgDemo'] );
		$this->assertStringContainsString( 'ParentOrg=Page', $byTitle['Template:OrgDemo'] );
		$this->assertStringNotContainsString( '_table=Organizations', $byTitle['Template:OrgDemo'] );

		$this->assertStringContainsString( '_table=GrantDemo', $byTitle['Template:GrantDemo'] );
		$this->assertStringContainsString( 'Funder=Page', $byTitle['Template:GrantDemo'] );
		$this->assertStringNotContainsString( '_table=Grants', $byTitle['Template:GrantDemo'] );

		$province = $byTitle['Help:Saintapedia Graph/Province'];
		$this->assertStringContainsString( '<pre>', $province );
		$this->assertStringContainsString( 'tables=OrgDemo', $province );
		$this->assertMatchesRegularExpression( '/Diocese|Parish|Archdiocese/', $province );

		$funding = $byTitle['Help:Saintapedia Graph/Funding'];
		$this->assertStringContainsString( '<pre>', $funding );
		$this->assertStringContainsString( 'edges=GrantDemo', $funding );
	}

	public function testCargoDemoTemplatesDeclareAndStore(): void {
		$root = dirname( __DIR__, 2 );
		$cargo = [];
		foreach ( PageCatalog::examplePages() as $entry ) {
			if ( !str_starts_with( $entry['title'], 'Template:' ) ) {
				continue;
			}
			if ( $entry['title'] === 'Template:Saintapedia Graph examples' ) {
				continue;
			}
			$text = file_get_contents( PageCatalog::resolve( $root, $entry['file'] ) );
			$this->assertStringContainsString( '{{#cargo_declare:_table=', $text, $entry['title'] );
			$this->assertStringContainsString( '{{#cargo_store:_table=', $text, $entry['title'] );
			$this->assertStringContainsString( '== Cargo declaration ==', $text, $entry['title'] );
			$cargo[] = $entry['title'];
		}
		$this->assertNotEmpty( $cargo );
		$orgChart = file_get_contents( PageCatalog::resolve( $root, 'templates/Org_chart.wikitext' ) );
		$this->assertStringNotContainsString( '#cargo_declare', $orgChart );
	}

	public function testExampleNavIsRightAlignedTable() {
		$root = dirname( __DIR__, 2 );
		$path = null;
		foreach ( array_merge( PageCatalog::pages(), PageCatalog::examplePages() ) as $entry ) {
			if ( $entry['title'] === 'Template:Saintapedia Graph examples' ) {
				$path = PageCatalog::resolve( $root, $entry['file'] );
				break;
			}
		}
		$this->assertNotNull( $path );
		$text = file_get_contents( $path );
		$this->assertStringContainsString( 'float:right', $text );
		$this->assertStringContainsString( 'class="wikitable"', $text );
		$this->assertStringContainsString( 'Help:Saintapedia Graph/Province', $text );
		$this->assertStringContainsString( 'Help:Saintapedia Graph/Funding', $text );
		$this->assertStringContainsString( 'Help:Saintapedia Graph/Examples', $text );
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
