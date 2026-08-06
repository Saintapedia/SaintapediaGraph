<?php

namespace MediaWiki\Extension\SaintapediaGraph\Mermaid;

/**
 * GraphModel → Mermaid flowchart source.
 */
class MermaidBuilder {

	/**
	 * Built-in Mermaid themes accepted by mermaid@10 (bundled).
	 * Unknown values fall back to "default" so %%{init}%% never breaks render.
	 *
	 * @var list<string>
	 */
	public const ALLOWED_THEMES = [ 'default', 'base', 'dark', 'forest', 'neutral' ];

	private GraphModel $graph;
	/** @var array<string, mixed> */
	private array $options;

	/**
	 * @param GraphModel $graph
	 * @param array<string, mixed> $options
	 */
	public function __construct( GraphModel $graph, array $options = [] ) {
		global $wgSaintapediaGraphDefaultDirection, $wgSaintapediaGraphClickable,
			$wgSaintapediaGraphDefaultTheme, $wgSaintapediaGraphStylePalette;

		$this->graph = $graph;
		$this->options = $options + [
			'direction' => $wgSaintapediaGraphDefaultDirection ?? 'TD',
			'clickable' => $wgSaintapediaGraphClickable ?? true,
			'theme' => $wgSaintapediaGraphDefaultTheme ?? 'default',
			'link_style' => '-->',
			'style_palette' => $wgSaintapediaGraphStylePalette ?? [
				'#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f',
				'#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#bab0ac',
			],
		];
	}

	/**
	 * Whether $theme is a known Mermaid built-in (case-insensitive).
	 *
	 * @param string $theme
	 * @return bool
	 */
	public static function isAllowedTheme( string $theme ): bool {
		return in_array( strtolower( trim( $theme ) ), self::ALLOWED_THEMES, true );
	}

	/**
	 * Normalize a theme name to a Mermaid built-in, or "default".
	 *
	 * @param string $theme
	 * @return string
	 */
	public static function normalizeTheme( string $theme ): string {
		$theme = strtolower( trim( $theme ) );
		if ( $theme === '' || !self::isAllowedTheme( $theme ) ) {
			return 'default';
		}
		return $theme;
	}

	/**
	 * @return string
	 */
	public function build(): string {
		$lines = [];

		$theme = self::normalizeTheme( (string)( $this->options['theme'] ?? 'default' ) );
		$init = json_encode( [
			'theme' => $theme,
			'securityLevel' => 'loose',
			'flowchart' => [ 'htmlLabels' => false, 'useMaxWidth' => true ],
		], JSON_UNESCAPED_SLASHES );
		if ( $init !== false ) {
			$lines[] = '%%{init: ' . $init . '}%%';
		}

		$direction = strtoupper( (string)$this->options['direction'] );
		if ( !in_array( $direction, [ 'TD', 'TB', 'BT', 'LR', 'RL' ], true ) ) {
			$direction = 'TD';
		}
		if ( $direction === 'TB' ) {
			$direction = 'TD';
		}
		$lines[] = "flowchart $direction";

		$link = (string)( $this->options['link_style'] ?? '-->' );
		$allowedLinks = [ '-->', '---', '-.->', '==>', '<-->' ];
		if ( !in_array( $link, $allowedLinks, true ) ) {
			$link = '-->';
		}

		$subgraphs = $this->graph->getSubgraphGroups();
		$nodesInSubgraph = [];
		foreach ( $subgraphs as $members ) {
			foreach ( $members as $nid ) {
				$nodesInSubgraph[$nid] = true;
			}
		}

		foreach ( $subgraphs as $sgKey => $memberIds ) {
			$sgId = MermaidEscaper::subgraphId( $sgKey );
			$sgLabel = MermaidEscaper::label( $sgKey );
			// Concatenate — "$sgId[...]" would be parsed as array access in double quotes.
			$lines[] = '  subgraph ' . $sgId . '["' . $sgLabel . '"]';
			foreach ( $memberIds as $nid ) {
				$node = $this->graph->getNodes()[$nid] ?? null;
				if ( $node ) {
					$lines[] = '    ' . $this->formatNode( $node );
				}
			}
			$lines[] = '  end';
		}

		foreach ( $this->graph->getNodes() as $nid => $node ) {
			if ( isset( $nodesInSubgraph[$nid] ) ) {
				continue;
			}
			$lines[] = '  ' . $this->formatNode( $node );
		}

		foreach ( $this->graph->getEdges() as $edge ) {
			if ( $edge['label'] !== null ) {
				$el = MermaidEscaper::edgeLabel( $edge['label'] );
				$lines[] = '  ' . $edge['from'] . " $link|$el| " . $edge['to'];
			} else {
				$lines[] = '  ' . $edge['from'] . " $link " . $edge['to'];
			}
		}

		if ( !empty( $this->options['clickable'] ) ) {
			foreach ( $this->graph->getNodes() as $node ) {
				if ( $node['page'] === null || $node['page'] === '' ) {
					continue;
				}
				$url = $this->pageUrl( $node['page'] );
				if ( $url === null || $url === '' || !$this->isLocalPath( $url ) ) {
					continue;
				}
				$tip = MermaidEscaper::clickTooltip( $node['page'] );
				$safeUrl = MermaidEscaper::clickUrl( $url );
				$lines[] = '  click ' . $node['id'] . ' "' . $safeUrl . '" "' . $tip . '"';
			}
		}

		foreach ( $this->buildStyleLines() as $sl ) {
			$lines[] = '  ' . $sl;
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @param array $node
	 * @return string
	 */
	private function formatNode( array $node ): string {
		$label = MermaidEscaper::label( $node['label'] );
		if ( $label === '' ) {
			$label = $node['id'];
		}
		return $node['id'] . '["' . $label . '"]';
	}

	/**
	 * @return list<string>
	 */
	private function buildStyleLines(): array {
		$palette = $this->options['style_palette'] ?? [];
		if ( !is_array( $palette ) || $palette === [] ) {
			$palette = [
				'#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f',
				'#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#bab0ac',
			];
		}

		$styleValues = $this->graph->getStyleValues();
		if ( $styleValues === [] ) {
			return [];
		}

		$lines = [];
		$classMembers = [];
		$i = 0;
		foreach ( $styleValues as $value ) {
			$class = MermaidEscaper::className( $value );
			$color = $palette[$i % count( $palette )];
			$i++;
			$lines[] = "classDef $class fill:$color,stroke:#333,color:#fff,stroke-width:1px;";
			$classMembers[$class] = [];
		}

		foreach ( $this->graph->getNodes() as $node ) {
			if ( $node['style'] === null ) {
				continue;
			}
			$class = MermaidEscaper::className( $node['style'] );
			$classMembers[$class][] = $node['id'];
		}

		foreach ( $classMembers as $class => $ids ) {
			if ( $ids === [] ) {
				continue;
			}
			$lines[] = 'class ' . implode( ',', $ids ) . " $class;";
		}

		return $lines;
	}

	/**
	 * Resolve a page title to a same-origin local URL.
	 * Protected so unit tests can inject a Title-free resolver.
	 *
	 * @param string $pageTitle
	 * @return string|null
	 */
	protected function pageUrl( string $pageTitle ): ?string {
		if ( class_exists( \MediaWiki\Title\Title::class ) ) {
			$title = \MediaWiki\Title\Title::newFromText( $pageTitle );
			if ( $title ) {
				return $title->getLocalURL();
			}
		}
		if ( class_exists( \Title::class ) ) {
			$title = \Title::newFromText( $pageTitle );
			if ( $title ) {
				return $title->getLocalURL();
			}
		}
		return null;
	}

	/**
	 * Accept only same-origin wiki paths (no protocol-relative or absolute URLs).
	 * Required because Mermaid is initialized with securityLevel=loose for clicks.
	 *
	 * @param string $url
	 * @return bool
	 */
	protected function isLocalPath( string $url ): bool {
		if ( $url === '' ) {
			return false;
		}
		if ( isset( $url[0] ) && $url[0] === '/' && ( !isset( $url[1] ) || $url[1] !== '/' ) ) {
			return true;
		}
		if ( preg_match( '#^(index\.php|w/index\.php)\?#', $url ) ) {
			return true;
		}
		return false;
	}
}
