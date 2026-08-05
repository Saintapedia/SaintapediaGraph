<?php

namespace MediaWiki\Extension\SaintapediaGraph\Tests;

use MediaWiki\Extension\SaintapediaGraph\Mermaid\DiagramService;
use MediaWiki\Extension\SaintapediaGraph\Mermaid\GraphModel;
use MediaWiki\Extension\SaintapediaGraph\Mermaid\MermaidBuilder;
use MediaWiki\Extension\SaintapediaGraph\Mermaid\MermaidEscaper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\SaintapediaGraph\Mermaid\DiagramService
 * @covers \MediaWiki\Extension\SaintapediaGraph\Mermaid\GraphModel
 * @covers \MediaWiki\Extension\SaintapediaGraph\Mermaid\MermaidBuilder
 */
class DiagramServiceTest extends TestCase {

	public function testHierarchyWithStyleAndSubgraph() {
		$service = new DiagramService();
		$rows = [
			[ 'Name' => 'Root', 'ParentOrg' => '', 'Type' => 'Foundation', 'Country' => 'US' ],
			[ 'Name' => 'Child', 'ParentOrg' => 'Root', 'Type' => 'Nonprofit', 'Country' => 'US' ],
			[ 'Name' => 'Other', 'ParentOrg' => 'Root', 'Type' => 'Company', 'Country' => 'UK' ],
		];
		$result = $service->buildFromRows( $rows, [
			'node_id' => 'Name',
			'node_label' => 'Name',
			'parent_field' => 'ParentOrg',
			'style_by' => 'Type',
			'subgraph_by' => 'Country',
			'clickable' => 'no',
			'direction' => 'TD',
			'warn_cycles' => 'no',
		] );

		$this->assertSame( 3, $result['nodeCount'] );
		$this->assertSame( 2, $result['edgeCount'] );
		$this->assertStringContainsString( 'classDef', $result['source'] );
		$this->assertStringContainsString( 'subgraph', $result['source'] );
	}

	public function testMissingModeThrowsEarly() {
		$service = new DiagramService();
		$this->expectException( \Exception::class );
		$service->detectMode( [
			'parent_field' => '',
			'source_field' => '',
			'target_field' => '',
			'nodes_table' => '',
			'edges_table' => '',
		] );
	}

	public function testDetectModeDual() {
		$service = new DiagramService();
		$this->assertSame( 'dual', $service->detectMode( [
			'nodes_table' => 'Organizations',
			'edges_table' => 'Grants',
			'parent_field' => '',
			'source_field' => 'Funder',
			'target_field' => 'Recipient',
		] ) );
	}

	public function testDetectModeMixedThrows() {
		$service = new DiagramService();
		$this->expectException( \Exception::class );
		$service->detectMode( [
			'parent_field' => 'Parent',
			'source_field' => 'A',
			'target_field' => 'B',
			'nodes_table' => '',
			'edges_table' => '',
		] );
	}

	public function testSyntheticParentInheritsSubgraph() {
		$service = new DiagramService();
		// Parent "Root" is not a full row — only referenced via ParentOrg.
		$rows = [
			[
				'Name' => 'Child',
				'ParentOrg' => 'Root',
				'Type' => 'Nonprofit',
				'Country' => 'Canada',
			],
		];
		$result = $service->buildFromRows( $rows, [
			'node_id' => 'Name',
			'node_label' => 'Name',
			'parent_field' => 'ParentOrg',
			'subgraph_by' => 'Country',
			'clickable' => 'no',
			'warn_cycles' => 'no',
		] );
		// Parent should appear inside the Canada subgraph.
		$this->assertStringContainsString( 'Canada', $result['source'] );
		$this->assertStringContainsString( 'subgraph', $result['source'] );
		$this->assertSame( 2, $result['nodeCount'] );
	}

	public function testCycleBreakCapped() {
		$g = new GraphModel();
		$a = MermaidEscaper::nodeId( 'A' );
		$b = MermaidEscaper::nodeId( 'B' );
		$c = MermaidEscaper::nodeId( 'C' );
		$g->addNode( $a, 'A' );
		$g->addNode( $b, 'B' );
		$g->addNode( $c, 'C' );
		$g->addEdge( $a, $b );
		$g->addEdge( $b, $c );
		$g->addEdge( $c, $a );

		$this->assertNotEmpty( $g->findCycles() );
		$removed = $g->breakCycles( 8 );
		$this->assertGreaterThan( 0, $removed );
		$this->assertSame( [], $g->findCycles() );
	}

	public function testEdgeModeFromRows() {
		$service = new DiagramService();
		$rows = [
			[ 'Funder' => 'Ford', 'Recipient' => 'OKF', 'Amount' => '100' ],
			[ 'Funder' => 'Ford', 'Recipient' => 'Lab', 'Amount' => '50' ],
		];
		$result = $service->buildFromRows( $rows, [
			'source_field' => 'Funder',
			'target_field' => 'Recipient',
			'edge_label' => 'Amount',
			'clickable' => 'no',
			'direction' => 'LR',
			'warn_cycles' => 'no',
		] );

		$this->assertSame( 3, $result['nodeCount'] );
		$this->assertSame( 2, $result['edgeCount'] );
		$this->assertStringContainsString( '|100|', $result['source'] );
	}

	public function testStyleClassNames() {
		$this->assertStringStartsWith( 'sg_', MermaidEscaper::className( 'Foundation' ) );
		$g = new GraphModel();
		$id = MermaidEscaper::nodeId( 'X' );
		$g->addNode( $id, 'X', null, 'Foundation', 'US' );
		$src = ( new MermaidBuilder( $g, [ 'clickable' => false ] ) )->build();
		$this->assertStringContainsString( 'classDef', $src );
		$this->assertStringContainsString( 'subgraph', $src );
	}

	public function testDualNormalizePageField() {
		$service = new DiagramService();
		$out = $service->normalizeParams( [
			'nodes_table' => 'Organizations',
			'edges_table' => 'Grants',
			'nodes_fields' => '_pageName,Name,Type',
			'source_field' => 'Funder',
			'target_field' => 'Recipient',
		] );
		$this->assertSame( '_pageName', $out['page_field'] );
		$this->assertSame( 'Name', $out['node_label'] );
		$this->assertSame( '_pageName', $out['node_id'] );
	}
}
