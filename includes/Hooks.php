<?php

namespace MediaWiki\Extension\SaintapediaGraph;

use MediaWiki\Extension\SaintapediaGraph\ParserFunctions\SaintapediaGraphParserFunction;
use MediaWiki\Hook\ParserFirstCallInitHook;
use Parser;

/**
 * Hook handlers for SaintapediaGraph.
 */
class Hooks implements ParserFirstCallInitHook {

	/**
	 * Register parser functions:
	 *  - {{#saintapedia_graph: … }}  (primary)
	 *  - {{#cargo_mermaid: … }}      (alias)
	 *
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook(
			'saintapedia_graph',
			[ SaintapediaGraphParserFunction::class, 'run' ],
			Parser::SFH_OBJECT_ARGS
		);
		$parser->setFunctionHook(
			'cargo_mermaid',
			[ SaintapediaGraphParserFunction::class, 'run' ],
			Parser::SFH_OBJECT_ARGS
		);
	}
}
