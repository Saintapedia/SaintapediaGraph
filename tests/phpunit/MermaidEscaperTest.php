<?php

namespace MediaWiki\Extension\SaintapediaGraph\Tests;

use MediaWiki\Extension\SaintapediaGraph\Mermaid\MermaidEscaper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\SaintapediaGraph\Mermaid\MermaidEscaper
 */
class MermaidEscaperTest extends TestCase {

	public function testNodeIdIsStable() {
		$a = MermaidEscaper::nodeId( 'Open Knowledge Foundation' );
		$b = MermaidEscaper::nodeId( 'Open Knowledge Foundation' );
		$this->assertSame( $a, $b );
		$this->assertMatchesRegularExpression( '/^[A-Za-z_][A-Za-z0-9_]*$/', $a );
	}

	public function testLabelStripsQuotes() {
		$label = MermaidEscaper::label( "Foo \"Bar\"\nBaz" );
		$this->assertStringNotContainsString( "\n", $label );
		$this->assertStringNotContainsString( '"', $label );
	}

	public function testClassAndSubgraph() {
		$this->assertStringStartsWith( 'sg_', MermaidEscaper::className( 'Nonprofit' ) );
		$this->assertStringStartsWith( 'sg_', MermaidEscaper::subgraphId( 'United States' ) );
	}

	public function testEdgeLabelStripsPipes() {
		$this->assertStringNotContainsString( '|', MermaidEscaper::edgeLabel( 'a|b' ) );
	}

	public function testClickTargetEscapesQuotesAndBackslashes() {
		$this->assertSame(
			'Foo \\"Bar\\" path\\\\x',
			MermaidEscaper::clickTarget( 'Foo "Bar" path\\x' )
		);
	}

	public function testClickTargetStripsControlCharactersAndNewlines() {
		$tip = MermaidEscaper::clickTarget( "Page\nTitle\twith\0junk" );
		$this->assertSame( 'Page Title with junk', $tip );
		$this->assertStringNotContainsString( "\n", $tip );
		$this->assertStringNotContainsString( "\t", $tip );
		$this->assertStringNotContainsString( "\0", $tip );
	}

	public function testClickTargetTrimsWhitespace() {
		$this->assertSame( 'Acme Org', MermaidEscaper::clickTarget( "  Acme Org  \n" ) );
	}
}
