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
}
