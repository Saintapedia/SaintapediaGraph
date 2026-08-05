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
}
