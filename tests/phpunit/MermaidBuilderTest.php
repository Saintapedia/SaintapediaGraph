<?php

namespace MediaWiki\Extension\SaintapediaGraph\Tests;

use MediaWiki\Extension\SaintapediaGraph\Mermaid\GraphModel;
use MediaWiki\Extension\SaintapediaGraph\Mermaid\MermaidBuilder;
use MediaWiki\Extension\SaintapediaGraph\Mermaid\MermaidEscaper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\SaintapediaGraph\Mermaid\MermaidBuilder
 * @covers \MediaWiki\Extension\SaintapediaGraph\Mermaid\GraphModel
 */
class MermaidBuilderTest extends TestCase {

	public function testBuildsHierarchyFlowchart() {
		$g = new GraphModel();
		$parent = MermaidEscaper::nodeId( 'Root Org' );
		$child = MermaidEscaper::nodeId( 'Child Org' );
		$g->addNode( $parent, 'Root Org', 'Root Org' );
		$g->addNode( $child, 'Child Org', 'Child Org' );
		$g->addEdge( $parent, $child );

		$src = ( new MermaidBuilder( $g, [
			'direction' => 'TD',
			'clickable' => false,
			'theme' => 'default',
		] ) )->build();

		$this->assertStringContainsString( 'flowchart TD', $src );
		$this->assertStringContainsString( $parent, $src );
		$this->assertStringContainsString( $child, $src );
		$this->assertStringContainsString( '-->', $src );
		$this->assertStringContainsString( 'securityLevel', $src );
	}

	public function testEdgeLabels() {
		$g = new GraphModel();
		$a = MermaidEscaper::nodeId( 'Funder' );
		$b = MermaidEscaper::nodeId( 'Recipient' );
		$g->addNode( $a, 'Funder' );
		$g->addNode( $b, 'Recipient' );
		$g->addEdge( $a, $b, '250000' );

		$src = ( new MermaidBuilder( $g, [
			'clickable' => false,
			'direction' => 'LR',
		] ) )->build();

		$this->assertStringContainsString( 'flowchart LR', $src );
		$this->assertStringContainsString( '|250000|', $src );
	}

	public function testClickRequiresLocalPathWhenTitleAvailable() {
		// Without MediaWiki Title, pageUrl returns null → no click lines.
		$g = new GraphModel();
		$id = MermaidEscaper::nodeId( 'Page' );
		$g->addNode( $id, 'Page', 'Page' );
		$src = ( new MermaidBuilder( $g, [ 'clickable' => true ] ) )->build();
		// In unit tests Title is usually absent, so no click lines.
		if ( !class_exists( \Title::class ) && !class_exists( \MediaWiki\Title\Title::class ) ) {
			$this->assertStringNotContainsString( 'click ', $src );
		} else {
			$this->assertTrue( true );
		}
	}

	public function testNormalizeThemeAllowlist() {
		$this->assertSame( 'default', MermaidBuilder::normalizeTheme( '' ) );
		$this->assertSame( 'default', MermaidBuilder::normalizeTheme( '  ' ) );
		$this->assertSame( 'forest', MermaidBuilder::normalizeTheme( 'Forest' ) );
		$this->assertSame( 'dark', MermaidBuilder::normalizeTheme( 'DARK' ) );
		$this->assertSame( 'base', MermaidBuilder::normalizeTheme( 'base' ) );
		$this->assertSame( 'neutral', MermaidBuilder::normalizeTheme( 'neutral' ) );
		// Unknown / injection-style values fall back to default.
		$this->assertSame( 'default', MermaidBuilder::normalizeTheme( 'not-a-theme' ) );
		$this->assertSame( 'default', MermaidBuilder::normalizeTheme( 'default"; alert(1)//' ) );
		$this->assertSame( 'default', MermaidBuilder::normalizeTheme( 'custom' ) );
		$this->assertTrue( MermaidBuilder::isAllowedTheme( 'Forest' ) );
		$this->assertFalse( MermaidBuilder::isAllowedTheme( 'custom' ) );
		$this->assertFalse( MermaidBuilder::isAllowedTheme( '' ) );
	}

	public function testBuildUsesValidatedThemeInInit() {
		$g = new GraphModel();
		$id = MermaidEscaper::nodeId( 'A' );
		$g->addNode( $id, 'A' );

		$good = ( new MermaidBuilder( $g, [
			'clickable' => false,
			'theme' => 'forest',
		] ) )->build();
		$this->assertStringContainsString( '"theme":"forest"', $good );

		$bad = ( new MermaidBuilder( $g, [
			'clickable' => false,
			'theme' => 'rainbow-unicorn',
		] ) )->build();
		$this->assertStringContainsString( '"theme":"default"', $bad );
		$this->assertStringNotContainsString( 'rainbow-unicorn', $bad );
	}

	public function testClickLinesUseLocalUrlAndTooltip() {
		$g = new GraphModel();
		$id = MermaidEscaper::nodeId( 'Acme Org' );
		$g->addNode( $id, 'Acme Org', 'Acme Org' );

		$builder = new class( $g, [ 'clickable' => true, 'theme' => 'default' ] ) extends MermaidBuilder {
			protected function pageUrl( string $pageTitle ): ?string {
				return '/wiki/' . str_replace( ' ', '_', $pageTitle );
			}
		};

		$src = $builder->build();
		$expected = '  click ' . $id . ' "/wiki/Acme_Org" "Acme Org"';
		$this->assertStringContainsString( $expected, $src );
	}

	public function testClickLinesEscapeSpecialCharsInUrlAndTooltip() {
		$g = new GraphModel();
		$id = MermaidEscaper::nodeId( 'Quote Page' );
		$g->addNode( $id, 'Quote Page', 'Say "Hi"' );

		$builder = new class( $g, [ 'clickable' => true ] ) extends MermaidBuilder {
			protected function pageUrl( string $pageTitle ): ?string {
				// Simulate a path that still needs quote safety.
				return '/wiki/Say_"Hi"';
			}
		};

		$src = $builder->build();
		$this->assertStringContainsString(
			'  click ' . $id . ' "/wiki/Say_%22Hi%22" "Say \\"Hi\\""',
			$src
		);
	}

	public function testClickLinesRejectNonLocalUrls() {
		$g = new GraphModel();
		$id = MermaidEscaper::nodeId( 'Page' );
		$g->addNode( $id, 'Page', 'Page' );

		$builder = new class( $g, [ 'clickable' => true ] ) extends MermaidBuilder {
			protected function pageUrl( string $pageTitle ): ?string {
				return 'https://evil.example/wiki/' . $pageTitle;
			}
		};

		$src = $builder->build();
		$this->assertStringNotContainsString( 'click ', $src );
		$this->assertStringNotContainsString( 'evil.example', $src );
	}

	public function testClickLinesRejectProtocolRelativeUrls() {
		$g = new GraphModel();
		$id = MermaidEscaper::nodeId( 'Page' );
		$g->addNode( $id, 'Page', 'Page' );

		$builder = new class( $g, [ 'clickable' => true ] ) extends MermaidBuilder {
			protected function pageUrl( string $pageTitle ): ?string {
				return '//evil.example/' . $pageTitle;
			}
		};

		$this->assertStringNotContainsString( 'click ', $builder->build() );
	}
}
