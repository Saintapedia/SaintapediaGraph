<?php

namespace MediaWiki\Extension\SaintapediaGraph\Mermaid;

use Exception;

/**
 * Cargo query → GraphModel → Mermaid source.
 *
 * Modes:
 *  A) hierarchy — parent_field
 *  B) edge — source_field + target_field
 *  C) dual — nodes_table + edges_table (nodes from one table, edges from another)
 */
class DiagramService {

	/**
	 * @param array<string, string> $params
	 * @return array{source:string,nodeCount:int,edgeCount:int,warnings:list<string>}
	 * @throws Exception
	 */
	public function buildFromParams( array $params ): array {
		global $wgSaintapediaGraphDefaultLimit, $wgSaintapediaGraphMaxLimit;

		$params = $this->normalizeParams( $params );
		$mode = $this->detectMode( $params );

		$limit = $params['limit'] !== ''
			? (int)$params['limit']
			: (int)( $wgSaintapediaGraphDefaultLimit ?? 500 );
		$maxLimit = (int)( $wgSaintapediaGraphMaxLimit ?? 1000 );
		if ( $limit > $maxLimit ) {
			$limit = $maxLimit;
		}
		if ( $limit < 1 ) {
			$limit = 1;
		}

		if ( $mode === 'dual' ) {
			return $this->buildDualMode( $params, (string)$limit );
		}

		if ( ( $params['tables'] ?? '' ) === '' && ( $params['table'] ?? '' ) === '' ) {
			throw new Exception( wfMessage( 'saintapediagraph-error-missing-tables' )->text() );
		}

		$tables = $params['tables'] !== '' ? $params['tables'] : $params['table'];
		$fields = $this->ensureFields( $params['fields'], $params, $mode );

		$rows = $this->runCargoQuery(
			$tables,
			$fields,
			$params['where'],
			$params['join_on'],
			$params['group_by'],
			$params['having'],
			$params['order_by'],
			(string)$limit,
			$params['offset']
		);

		return $this->buildFromRows( $rows, $params );
	}

	/**
	 * Dual-table mode: nodes from one Cargo table, edges from another.
	 *
	 * @param array<string, string> $params
	 * @param string $limit
	 * @return array{source:string,nodeCount:int,edgeCount:int,warnings:list<string>}
	 * @throws Exception
	 */
	private function buildDualMode( array $params, string $limit ): array {
		if ( $params['nodes_table'] === '' || $params['edges_table'] === '' ) {
			throw new Exception( wfMessage( 'saintapediagraph-error-missing-dual-tables' )->text() );
		}
		if ( $params['source_field'] === '' || $params['target_field'] === '' ) {
			throw new Exception( wfMessage( 'saintapediagraph-error-missing-edge-fields' )->text() );
		}

		// Node query
		$nodeParams = $params;
		$nodeParams['tables'] = $params['nodes_table'];
		$nodeParams['fields'] = $params['nodes_fields'] !== ''
			? $params['nodes_fields']
			: $params['fields'];
		$nodeParams['where'] = $params['nodes_where'] !== ''
			? $params['nodes_where']
			: '';
		$nodeParams['join_on'] = $params['nodes_join_on'] !== ''
			? $params['nodes_join_on']
			: '';
		$nodeParams['order_by'] = $params['nodes_order_by'] !== ''
			? $params['nodes_order_by']
			: $params['order_by'];
		// Build needed fields without requiring parent (nodes-only side).
		$nodeFields = $this->ensureNodeOnlyFields( $nodeParams['fields'], $nodeParams );

		$nodeRows = $this->runCargoQuery(
			$nodeParams['tables'],
			$nodeFields,
			$nodeParams['where'],
			$nodeParams['join_on'],
			'',
			'',
			$nodeParams['order_by'],
			$limit,
			$params['offset']
		);

		// Edge query
		$edgeParams = $params;
		$edgeParams['tables'] = $params['edges_table'];
		$edgeParams['fields'] = $params['edges_fields'] !== ''
			? $params['edges_fields']
			: '';
		$edgeParams['where'] = $params['edges_where'] !== ''
			? $params['edges_where']
			: '';
		$edgeParams['join_on'] = $params['edges_join_on'] !== ''
			? $params['edges_join_on']
			: '';
		$edgeParams['order_by'] = $params['edges_order_by'] !== ''
			? $params['edges_order_by']
			: '';
		$edgeFieldBase = $edgeParams['fields'] !== '' ? $edgeParams['fields'] : (
			$params['source_field'] . ',' . $params['target_field']
			. ( $params['edge_label'] !== '' ? ',' . $params['edge_label'] : '' )
		);
		// Edges table only needs source/target/label — not node_id from the orgs table.
		$edgeNeeded = [ $params['source_field'], $params['target_field'] ];
		if ( $params['edge_label'] !== '' ) {
			$edgeNeeded[] = $params['edge_label'];
		}
		$edgeFields = $this->mergeNeededFields( $edgeFieldBase, $edgeNeeded );

		$edgeRows = $this->runCargoQuery(
			$edgeParams['tables'],
			$edgeFields,
			$edgeParams['where'],
			$edgeParams['join_on'],
			'',
			'',
			$edgeParams['order_by'],
			$limit,
			'0'
		);

		$graph = new GraphModel();
		$this->addNodesFromRows( $graph, $nodeRows, $params );
		// Do not overwrite node styles from the edges query.
		$this->addEdgesFromRows( $graph, $edgeRows, $params, false );

		return $this->finalizeGraph( $graph, $params );
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @param array<string, string> $params
	 * @return array{source:string,nodeCount:int,edgeCount:int,warnings:list<string>}
	 * @throws Exception
	 */
	public function buildFromRows( array $rows, array $params ): array {
		$params = $this->normalizeParams( $params );
		$mode = $this->detectMode( $params );
		if ( $mode === 'dual' ) {
			// Dual mode needs two queries; rows alone are not enough.
			throw new Exception( wfMessage( 'saintapediagraph-error-dual-needs-params' )->text() );
		}

		$graph = $this->rowsToGraph( $rows, $params, $mode );
		return $this->finalizeGraph( $graph, $params );
	}

	/**
	 * @param GraphModel $graph
	 * @param array<string, string> $params
	 * @return array{source:string,nodeCount:int,edgeCount:int,warnings:list<string>}
	 * @throws Exception
	 */
	private function finalizeGraph( GraphModel $graph, array $params ): array {
		global $wgSaintapediaGraphClickable, $wgSaintapediaGraphDefaultDirection,
			$wgSaintapediaGraphDefaultTheme, $wgSaintapediaGraphBreakCycles,
			$wgSaintapediaGraphWarnCycles;

		if ( $graph->getNodeCount() === 0 ) {
			throw new Exception( wfMessage( 'saintapediagraph-error-no-results' )->text() );
		}

		$warnings = $graph->getWarnings();

		// Cycle handling
		$cycles = $graph->findCycles();
		if ( $cycles ) {
			$break = $this->toBool(
				$params['break_cycles'] ?? '',
				(bool)( $wgSaintapediaGraphBreakCycles ?? false )
			);
			$warn = $this->toBool(
				$params['warn_cycles'] ?? '',
				(bool)( $wgSaintapediaGraphWarnCycles ?? true )
			);
			if ( $break ) {
				$removed = $graph->breakCycles();
				if ( $warn ) {
					$warnings[] = wfMessage( 'saintapediagraph-warning-cycles-broken', $removed )->text();
				}
			} elseif ( $warn ) {
				// Count is DFS-reported cycles (may overlap); good enough for a warning.
				$warnings[] = wfMessage(
					'saintapediagraph-warning-cycles',
					count( $cycles )
				)->text();
			}
		}

		$builder = new MermaidBuilder( $graph, [
			'direction' => $params['direction'] !== ''
				? $params['direction']
				: ( $wgSaintapediaGraphDefaultDirection ?? 'TD' ),
			'clickable' => $this->toBool(
				$params['clickable'] ?? '',
				(bool)( $wgSaintapediaGraphClickable ?? true )
			),
			'theme' => $params['theme'] !== ''
				? $params['theme']
				: ( $wgSaintapediaGraphDefaultTheme ?? 'default' ),
			'link_style' => $params['link_style'] !== '' ? $params['link_style'] : '-->',
		] );

		return [
			'source' => $builder->build(),
			'nodeCount' => $graph->getNodeCount(),
			'edgeCount' => $graph->getEdgeCount(),
			'warnings' => $warnings,
		];
	}

	/**
	 * @param array<string, string> $params
	 * @return string 'hierarchy'|'edge'|'dual'
	 * @throws Exception
	 */
	public function detectMode( array $params ): string {
		$hasNodesTable = ( $params['nodes_table'] ?? '' ) !== '';
		$hasEdgesTable = ( $params['edges_table'] ?? '' ) !== '';
		if ( $hasNodesTable || $hasEdgesTable ) {
			if ( !$hasNodesTable || !$hasEdgesTable ) {
				throw new Exception( wfMessage( 'saintapediagraph-error-missing-dual-tables' )->text() );
			}
			return 'dual';
		}

		$hasParent = ( $params['parent_field'] ?? '' ) !== '';
		$hasSource = ( $params['source_field'] ?? '' ) !== '';
		$hasTarget = ( $params['target_field'] ?? '' ) !== '';

		if ( $hasSource xor $hasTarget ) {
			throw new Exception( wfMessage( 'saintapediagraph-error-missing-edge-fields' )->text() );
		}

		if ( $hasParent && $hasSource && $hasTarget ) {
			throw new Exception( wfMessage( 'saintapediagraph-error-mixed-modes' )->text() );
		}

		if ( $hasSource && $hasTarget ) {
			return 'edge';
		}
		if ( $hasParent ) {
			return 'hierarchy';
		}
		// Fail early rather than defaulting to hierarchy and then missing parent_field.
		throw new Exception( wfMessage( 'saintapediagraph-error-missing-mode' )->text() );
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, string>
	 */
	public function normalizeParams( array $params ): array {
		$out = [];
		foreach ( $params as $k => $v ) {
			$key = strtolower( trim( (string)$k ) );
			$key = str_replace( [ ' ', '-' ], '_', $key );
			$key = preg_replace( '/_+/', '_', $key ) ?? $key;
			$out[$key] = is_string( $v ) ? trim( $v ) : (string)$v;
		}

		if ( ( $out['tables'] ?? '' ) === '' && ( $out['table'] ?? '' ) !== '' ) {
			$out['tables'] = $out['table'];
		}

		$defaults = [
			'tables' => '',
			'table' => '',
			'fields' => '_pageName',
			'where' => '',
			'join_on' => '',
			'group_by' => '',
			'having' => '',
			'order_by' => '',
			'limit' => '',
			'offset' => '',
			'direction' => '',
			'node_id' => '_pageName',
			'node_label' => '',
			'parent_field' => '',
			'source_field' => '',
			'target_field' => '',
			'edge_label' => '',
			'link_style' => '-->',
			'clickable' => 'yes',
			'theme' => '',
			'format' => 'mermaid',
			'page_field' => '',
			'style_by' => '',
			'subgraph_by' => '',
			'break_cycles' => '',
			'warn_cycles' => '',
			// Dual-table mode
			'nodes_table' => '',
			'edges_table' => '',
			'nodes_fields' => '',
			'edges_fields' => '',
			'nodes_where' => '',
			'edges_where' => '',
			'nodes_join_on' => '',
			'edges_join_on' => '',
			'nodes_order_by' => '',
			'edges_order_by' => '',
		];
		foreach ( $defaults as $k => $v ) {
			if ( !isset( $out[$k] ) ) {
				$out[$k] = $v;
			}
		}

		// Dual mode: page clicks default to _pageName. Node identity should usually
		// also be _pageName so edge endpoints (Page fields) match. Callers may still
		// override node_id=Name if Name equals the page title.
		$isDual = ( $out['nodes_table'] ?? '' ) !== '' && ( $out['edges_table'] ?? '' ) !== '';
		if ( $isDual && $out['page_field'] === '' ) {
			$out['page_field'] = '_pageName';
		}

		if ( $out['node_label'] === '' ) {
			// Prefer a human Name column when dual nodes_fields mention it.
			if ( $isDual ) {
				$nf = $out['nodes_fields'] !== '' ? $out['nodes_fields'] : $out['fields'];
				if ( preg_match( '/(^|[=,])\s*Name\s*($|[,])/', $nf )
					|| preg_match( '/=Name\b/', $nf )
					|| preg_match( '/\bName\b/', $nf )
				) {
					$out['node_label'] = 'Name';
				}
			}
		}
		if ( $out['node_label'] === '' ) {
			$out['node_label'] = $out['node_id'] !== '' ? $out['node_id'] : '_pageName';
		}
		return $out;
	}

	/**
	 * Fields needed for node-only queries (dual mode nodes side).
	 *
	 * @param string $fieldsStr
	 * @param array<string, string> $params
	 * @return string
	 */
	private function ensureNodeOnlyFields( string $fieldsStr, array $params ): string {
		$needed = [ $params['node_id'], $params['node_label'] ];
		$pageField = $params['page_field'] !== '' ? $params['page_field'] : '_pageName';
		$needed[] = $pageField;
		if ( $params['style_by'] !== '' ) {
			$needed[] = $params['style_by'];
		}
		if ( $params['subgraph_by'] !== '' ) {
			$needed[] = $params['subgraph_by'];
		}
		return $this->mergeNeededFields( $fieldsStr, $needed );
	}

	/**
	 * @param string $fieldsStr
	 * @param array<string, string> $params
	 * @param string $mode
	 * @return string
	 * @throws Exception
	 */
	private function ensureFields( string $fieldsStr, array $params, string $mode ): string {
		$needed = [ $params['node_id'], $params['node_label'] ];

		if ( $mode === 'hierarchy' ) {
			if ( $params['parent_field'] === '' ) {
				throw new Exception( wfMessage( 'saintapediagraph-error-missing-parent' )->text() );
			}
			$needed[] = $params['parent_field'];
			$pageField = $params['page_field'] !== '' ? $params['page_field'] : '_pageName';
			$needed[] = $pageField;
		} else {
			if ( $params['source_field'] === '' || $params['target_field'] === '' ) {
				throw new Exception( wfMessage( 'saintapediagraph-error-missing-edge-fields' )->text() );
			}
			$needed[] = $params['source_field'];
			$needed[] = $params['target_field'];
			if ( $params['edge_label'] !== '' ) {
				$needed[] = $params['edge_label'];
			}
			if ( $params['page_field'] !== '' ) {
				$needed[] = $params['page_field'];
			}
		}

		if ( $params['style_by'] !== '' ) {
			$needed[] = $params['style_by'];
		}
		if ( $params['subgraph_by'] !== '' ) {
			$needed[] = $params['subgraph_by'];
		}

		return $this->mergeNeededFields( $fieldsStr, $needed );
	}

	/**
	 * @param string $fieldsStr
	 * @param list<string> $needed
	 * @return string
	 */
	private function mergeNeededFields( string $fieldsStr, array $needed ): string {
		$existing = [];
		if ( trim( $fieldsStr ) !== '' ) {
			foreach ( $this->splitFieldList( $fieldsStr ) as $part ) {
				if ( strpos( $part, '=' ) !== false ) {
					[ $src, $alias ] = array_map( 'trim', explode( '=', $part, 2 ) );
					$existing[$this->fieldKey( $alias )] = true;
					$existing[$this->fieldKey( $src )] = true;
				} else {
					$existing[$this->fieldKey( $part )] = true;
					$existing[$this->fieldKey( str_replace( '_', ' ', $part ) )] = true;
				}
			}
		}

		$additions = [];
		foreach ( $needed as $field ) {
			if ( $field === '' ) {
				continue;
			}
			$base = $field;
			if ( strpos( $field, '.' ) !== false ) {
				$base = substr( $field, strrpos( $field, '.' ) + 1 );
			}
			if (
				isset( $existing[$this->fieldKey( $field )] ) ||
				isset( $existing[$this->fieldKey( $base )] ) ||
				isset( $existing[$this->fieldKey( str_replace( '_', ' ', $base ) )] )
			) {
				continue;
			}
			$additions[] = $field;
			$existing[$this->fieldKey( $field )] = true;
		}

		if ( $additions === [] ) {
			return $fieldsStr !== '' ? $fieldsStr : '_pageName';
		}
		if ( trim( $fieldsStr ) === '' ) {
			return implode( ',', $additions );
		}
		return $fieldsStr . ',' . implode( ',', $additions );
	}

	/**
	 * @param string $fieldsStr
	 * @return list<string>
	 */
	private function splitFieldList( string $fieldsStr ): array {
		if ( class_exists( 'CargoUtils' ) && method_exists( 'CargoUtils', 'smartSplit' ) ) {
			$parts = \CargoUtils::smartSplit( ',', $fieldsStr );
			$out = [];
			foreach ( $parts as $p ) {
				$p = trim( (string)$p );
				if ( $p !== '' ) {
					$out[] = $p;
				}
			}
			return $out;
		}
		$out = [];
		foreach ( explode( ',', $fieldsStr ) as $p ) {
			$p = trim( $p );
			if ( $p !== '' ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	private function fieldKey( string $name ): string {
		if ( strpos( $name, '.' ) !== false ) {
			$name = substr( $name, strrpos( $name, '.' ) + 1 );
		}
		return strtolower( str_replace( [ ' ', '_' ], '', $name ) );
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	private function runCargoQuery(
		string $tables,
		string $fields,
		string $where,
		string $joinOn,
		string $groupBy,
		string $having,
		string $orderBy,
		string $limit,
		string $offset
	): array {
		if ( !class_exists( 'CargoSQLQuery' ) ) {
			throw new Exception( wfMessage( 'saintapediagraph-error-no-cargo' )->text() );
		}
		try {
			$query = \CargoSQLQuery::newFromValues(
				$tables, $fields, $where, $joinOn, $groupBy, $having, $orderBy, $limit, $offset
			);
			return $query->run();
		} catch ( Exception $e ) {
			throw new Exception(
				wfMessage( 'saintapediagraph-error-query', $e->getMessage() )->text()
			);
		}
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @param array<string, string> $params
	 * @param string $mode
	 * @return GraphModel
	 */
	private function rowsToGraph( array $rows, array $params, string $mode ): GraphModel {
		$graph = new GraphModel();
		if ( $mode === 'hierarchy' ) {
			$this->addHierarchyFromRows( $graph, $rows, $params );
		} else {
			$this->addEdgesFromRows( $graph, $rows, $params, true );
		}
		return $graph;
	}

	/**
	 * @param GraphModel $graph
	 * @param list<array<string, mixed>> $rows
	 * @param array<string, string> $params
	 */
	private function addNodesFromRows( GraphModel $graph, array $rows, array $params ): void {
		$nodeIdField = $params['node_id'];
		$nodeLabelField = $params['node_label'];
		$pageField = $params['page_field'] !== '' ? $params['page_field'] : '_pageName';
		$styleBy = $params['style_by'];
		$subgraphBy = $params['subgraph_by'];

		foreach ( $rows as $row ) {
			$idRaw = $this->cell( $row, $nodeIdField );
			if ( $idRaw === '' ) {
				// Fall back to page name
				$idRaw = $this->cell( $row, $pageField );
			}
			if ( $idRaw === '' ) {
				continue;
			}
			$label = $this->cell( $row, $nodeLabelField );
			if ( $label === '' ) {
				$label = $idRaw;
			}
			$page = $this->cell( $row, $pageField );
			if ( $page === '' ) {
				$page = $idRaw;
			}
			$style = $styleBy !== '' ? $this->cell( $row, $styleBy ) : null;
			$subgraph = $subgraphBy !== '' ? $this->cell( $row, $subgraphBy ) : null;
			$graph->addNode( MermaidEscaper::nodeId( $idRaw ), $label, $page, $style, $subgraph );
		}
	}

	/**
	 * @param GraphModel $graph
	 * @param list<array<string, mixed>> $rows
	 * @param array<string, string> $params
	 */
	private function addHierarchyFromRows( GraphModel $graph, array $rows, array $params ): void {
		$nodeIdField = $params['node_id'];
		$nodeLabelField = $params['node_label'];
		$pageField = $params['page_field'] !== '' ? $params['page_field'] : '_pageName';
		$parentField = $params['parent_field'];
		$styleBy = $params['style_by'];
		$subgraphBy = $params['subgraph_by'];

		// Remember child subgraph so synthetic parents can inherit a group box.
		/** @var array<string, string> parentId => subgraph key */
		$parentSubgraphHint = [];

		foreach ( $rows as $row ) {
			$idRaw = $this->cell( $row, $nodeIdField );
			if ( $idRaw === '' ) {
				continue;
			}
			$label = $this->cell( $row, $nodeLabelField );
			if ( $label === '' ) {
				$label = $idRaw;
			}
			$page = $this->cell( $row, $pageField );
			if ( $page === '' ) {
				$page = $idRaw;
			}
			$style = $styleBy !== '' ? $this->cell( $row, $styleBy ) : null;
			$subgraph = $subgraphBy !== '' ? $this->cell( $row, $subgraphBy ) : null;
			$nid = MermaidEscaper::nodeId( $idRaw );
			$graph->addNode( $nid, $label, $page, $style, $subgraph );

			$parentRaw = $this->cell( $row, $parentField );
			if ( $parentRaw !== '' ) {
				$pid = MermaidEscaper::nodeId( $parentRaw );
				// Prefer the first child's subgraph as a placement hint for missing parents.
				if ( $subgraph !== null && $subgraph !== '' && !isset( $parentSubgraphHint[$pid] ) ) {
					$parentSubgraphHint[$pid] = $subgraph;
				}
				$graph->addNode(
					$pid,
					$parentRaw,
					$parentRaw,
					null,
					$parentSubgraphHint[$pid] ?? null
				);
				$graph->addEdge( $pid, $nid );
			}
		}
	}

	/**
	 * @param GraphModel $graph
	 * @param list<array<string, mixed>> $rows
	 * @param array<string, string> $params
	 * @param bool $createMissingNodes
	 */
	private function addEdgesFromRows(
		GraphModel $graph,
		array $rows,
		array $params,
		bool $createMissingNodes
	): void {
		$sourceField = $params['source_field'];
		$targetField = $params['target_field'];
		$edgeLabelField = $params['edge_label'];
		$styleBy = $params['style_by'];
		$subgraphBy = $params['subgraph_by'];

		foreach ( $rows as $row ) {
			$srcRaw = $this->cell( $row, $sourceField );
			$tgtRaw = $this->cell( $row, $targetField );
			if ( $srcRaw === '' || $tgtRaw === '' ) {
				continue;
			}
			$sid = MermaidEscaper::nodeId( $srcRaw );
			$tid = MermaidEscaper::nodeId( $tgtRaw );

			if ( $createMissingNodes ) {
				// Pure edge mode: style/subgraph on the row apply to the source node.
				$style = $styleBy !== '' ? $this->cell( $row, $styleBy ) : null;
				$subgraph = $subgraphBy !== '' ? $this->cell( $row, $subgraphBy ) : null;
				$graph->addNode( $sid, $srcRaw, $srcRaw, $style, $subgraph );
				$graph->addNode( $tid, $tgtRaw, $tgtRaw, null, $subgraph );
			} else {
				// Dual mode: only create missing endpoints; never overwrite node styles.
				if ( !$graph->hasNode( $sid ) ) {
					$graph->addNode( $sid, $srcRaw, $srcRaw, null, null );
				}
				if ( !$graph->hasNode( $tid ) ) {
					$graph->addNode( $tid, $tgtRaw, $tgtRaw, null, null );
				}
			}

			$edgeLabel = $edgeLabelField !== '' ? $this->cell( $row, $edgeLabelField ) : '';
			$graph->addEdge( $sid, $tid, $edgeLabel !== '' ? $edgeLabel : null );
		}
	}

	/**
	 * @param array<string, mixed> $row
	 * @param string $field
	 * @return string
	 */
	private function cell( array $row, string $field ): string {
		if ( $field === '' ) {
			return '';
		}
		if ( array_key_exists( $field, $row ) ) {
			return $this->stringify( $row[$field] );
		}
		$base = $field;
		if ( strpos( $field, '.' ) !== false ) {
			$base = substr( $field, strrpos( $field, '.' ) + 1 );
			if ( array_key_exists( $base, $row ) ) {
				return $this->stringify( $row[$base] );
			}
		}
		$space = str_replace( '_', ' ', $base );
		if ( array_key_exists( $space, $row ) ) {
			return $this->stringify( $row[$space] );
		}
		$want = $this->fieldKey( $field );
		foreach ( $row as $k => $v ) {
			if ( $this->fieldKey( (string)$k ) === $want ) {
				return $this->stringify( $v );
			}
		}
		return '';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private function stringify( $value ): string {
		if ( $value === null ) {
			return '';
		}
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( [ $this, 'stringify' ], $value ) );
		}
		$s = trim( (string)$value );
		if ( preg_match( '/^\[\[([^|\]]+)(?:\|[^\]]+)?\]\]$/', $s, $m ) ) {
			return trim( $m[1] );
		}
		return $s;
	}

	/**
	 * @param string $value
	 * @param bool $default
	 * @return bool
	 */
	private function toBool( string $value, bool $default ): bool {
		if ( $value === '' ) {
			return $default;
		}
		$v = strtolower( trim( $value ) );
		if ( in_array( $v, [ 'yes', 'true', '1', 'on', 'y' ], true ) ) {
			return true;
		}
		if ( in_array( $v, [ 'no', 'false', '0', 'off', 'n' ], true ) ) {
			return false;
		}
		return $default;
	}
}
