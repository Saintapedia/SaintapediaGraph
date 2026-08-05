<?php

namespace MediaWiki\Extension\SaintapediaGraph\Mermaid;

/**
 * In-memory directed graph for Mermaid generation.
 */
class GraphModel {

	/** @var array<string, array{id:string,label:string,page:?string,style:?string,subgraph:?string}> */
	private array $nodes = [];

	/** @var list<array{from:string,to:string,label:?string}> */
	private array $edges = [];

	/** @var array<string, true> */
	private array $edgeKeys = [];

	/** @var list<string> */
	private array $warnings = [];

	/**
	 * @param string $id
	 * @param string $label
	 * @param string|null $page
	 * @param string|null $style Raw style_by field value
	 * @param string|null $subgraph Raw subgraph_by field value
	 */
	public function addNode(
		string $id,
		string $label,
		?string $page = null,
		?string $style = null,
		?string $subgraph = null
	): void {
		if ( isset( $this->nodes[$id] ) ) {
			$existing = &$this->nodes[$id];
			if ( $existing['page'] === null && $page !== null ) {
				$existing['page'] = $page;
			}
			if ( ( $existing['label'] === '' || $existing['label'] === $id ) && $label !== '' ) {
				$existing['label'] = $label;
			}
			if ( $existing['style'] === null && $style !== null && $style !== '' ) {
				$existing['style'] = $style;
			}
			if ( $existing['subgraph'] === null && $subgraph !== null && $subgraph !== '' ) {
				$existing['subgraph'] = $subgraph;
			}
			return;
		}
		$this->nodes[$id] = [
			'id' => $id,
			'label' => $label,
			'page' => $page,
			'style' => ( $style !== null && $style !== '' ) ? $style : null,
			'subgraph' => ( $subgraph !== null && $subgraph !== '' ) ? $subgraph : null,
		];
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @param string|null $label
	 * @return bool
	 */
	public function addEdge( string $from, string $to, ?string $label = null ): bool {
		if ( $from === '' || $to === '' || $from === $to ) {
			return false;
		}
		$key = $from . "\0" . $to . "\0" . ( $label ?? '' );
		if ( isset( $this->edgeKeys[$key] ) ) {
			return false;
		}
		$this->edgeKeys[$key] = true;
		$this->edges[] = [
			'from' => $from,
			'to' => $to,
			'label' => ( $label !== null && $label !== '' ) ? $label : null,
		];
		return true;
	}

	/**
	 * @param string $id
	 * @return bool
	 */
	public function hasNode( string $id ): bool {
		return isset( $this->nodes[$id] );
	}

	/** @return array<string, array> */
	public function getNodes(): array {
		return $this->nodes;
	}

	/** @return list<array> */
	public function getEdges(): array {
		return $this->edges;
	}

	public function getNodeCount(): int {
		return count( $this->nodes );
	}

	public function getEdgeCount(): int {
		return count( $this->edges );
	}

	/**
	 * @param string $msg
	 */
	public function addWarning( string $msg ): void {
		$this->warnings[] = $msg;
	}

	/**
	 * @return list<string>
	 */
	public function getWarnings(): array {
		return $this->warnings;
	}

	/**
	 * Unique style values present on nodes.
	 *
	 * @return list<string>
	 */
	public function getStyleValues(): array {
		$values = [];
		foreach ( $this->nodes as $node ) {
			if ( $node['style'] !== null ) {
				$values[$node['style']] = true;
			}
		}
		return array_keys( $values );
	}

	/**
	 * Group node IDs by subgraph key.
	 *
	 * @return array<string, list<string>>
	 */
	public function getSubgraphGroups(): array {
		$groups = [];
		foreach ( $this->nodes as $id => $node ) {
			if ( $node['subgraph'] !== null ) {
				$groups[$node['subgraph']][] = $id;
			}
		}
		return $groups;
	}

	/**
	 * Detect directed cycles using DFS.
	 *
	 * @return list<list<string>> Each cycle as a list of node IDs
	 */
	public function findCycles(): array {
		$adj = [];
		foreach ( $this->nodes as $id => $_ ) {
			$adj[$id] = [];
		}
		foreach ( $this->edges as $edge ) {
			if ( isset( $adj[$edge['from']] ) ) {
				$adj[$edge['from']][] = $edge['to'];
			}
		}

		$WHITE = 0;
		$GRAY = 1;
		$BLACK = 2;
		$color = array_fill_keys( array_keys( $adj ), $WHITE );
		$path = [];
		$pathIndex = [];
		$cycles = [];

		$dfs = function ( string $u ) use (
			&$dfs, &$adj, &$color, &$path, &$pathIndex, &$cycles, $WHITE, $GRAY, $BLACK
		) {
			$color[$u] = $GRAY;
			$path[] = $u;
			$pathIndex[$u] = count( $path ) - 1;

			foreach ( $adj[$u] as $v ) {
				if ( !isset( $color[$v] ) ) {
					continue;
				}
				if ( $color[$v] === $WHITE ) {
					$dfs( $v );
				} elseif ( $color[$v] === $GRAY ) {
					$start = $pathIndex[$v] ?? 0;
					$cycles[] = array_slice( $path, $start );
				}
			}

			array_pop( $path );
			unset( $pathIndex[$u] );
			$color[$u] = $BLACK;
		};

		foreach ( array_keys( $adj ) as $node ) {
			if ( $color[$node] === $WHITE ) {
				$dfs( $node );
			}
		}

		return $cycles;
	}

	/**
	 * Remove edges that close a cycle (keeps a DAG-ish edge set).
	 *
	 * Capped passes avoid pathological work on dense graphs.
	 *
	 * @param int $maxPasses
	 * @return int Number of edges removed
	 */
	public function breakCycles( int $maxPasses = 32 ): int {
		$totalRemoved = 0;
		for ( $pass = 0; $pass < $maxPasses; $pass++ ) {
			$cycles = $this->findCycles();
			if ( !$cycles ) {
				break;
			}

			$drop = [];
			foreach ( $cycles as $cycle ) {
				$n = count( $cycle );
				if ( $n < 1 ) {
					continue;
				}
				// Drop the back-edge that closes the cycle (last → first).
				$from = $cycle[$n - 1];
				$to = $cycle[0];
				$drop[$from . "\0" . $to] = true;
			}
			if ( $drop === [] ) {
				break;
			}

			$removed = 0;
			$newEdges = [];
			$this->edgeKeys = [];
			foreach ( $this->edges as $edge ) {
				$key = $edge['from'] . "\0" . $edge['to'];
				if ( isset( $drop[$key] ) ) {
					$removed++;
					continue;
				}
				$ek = $edge['from'] . "\0" . $edge['to'] . "\0" . ( $edge['label'] ?? '' );
				$this->edgeKeys[$ek] = true;
				$newEdges[] = $edge;
			}
			$this->edges = $newEdges;
			$totalRemoved += $removed;
			if ( $removed === 0 ) {
				break;
			}
		}

		return $totalRemoved;
	}
}
