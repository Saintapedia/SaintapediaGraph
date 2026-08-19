<?php

namespace MediaWiki\Extension\SaintapediaGraph\Maintenance;

/**
 * Wiki pages this extension can import via maintenance/importPages.php.
 * No MediaWiki runtime required — unit-testable from the standalone suite.
 */
class PageCatalog {

	/**
	 * @return list<array{title:string,file:string}>
	 */
	public static function pages(): array {
		return [
			[
				'title' => 'Help:Saintapedia Graph',
				'file' => 'docs/Help-Saintapedia_Graph.wikitext',
			],
			[
				'title' => 'Template:Org chart',
				'file' => 'templates/Org_chart.wikitext',
			],
			[
				'title' => 'Template:Funding network',
				'file' => 'templates/Funding_network.wikitext',
			],
			[
				'title' => 'Template:Saintapedia Graph examples',
				'file' => 'examples/help/Template_Saintapedia_Graph_examples.wikitext',
			],
		];
	}

	/**
	 * Optional demo Cargo tables + rows. Names are OrgDemo / GrantDemo so they
	 * do not collide with a wiki's own Organization / Grant templates.
	 *
	 * @return list<array{title:string,file:string}>
	 */
	public static function examplePages(): array {
		return [
			[
				'title' => 'Template:OrgDemo',
				'file' => 'examples/pages/Template_OrgDemo.wikitext',
			],
			[
				'title' => 'Template:GrantDemo',
				'file' => 'examples/pages/Template_GrantDemo.wikitext',
			],
			[
				'title' => 'Template:DemoPlace',
				'file' => 'examples/pages/Template_DemoPlace.wikitext',
			],
			[
				'title' => 'Template:DemoHouse',
				'file' => 'examples/pages/Template_DemoHouse.wikitext',
			],
			[
				'title' => 'Template:DemoSchool',
				'file' => 'examples/pages/Template_DemoSchool.wikitext',
			],
			[
				'title' => 'Template:DemoBishop',
				'file' => 'examples/pages/Template_DemoBishop.wikitext',
			],
			[
				'title' => 'Template:DemoParishLink',
				'file' => 'examples/pages/Template_DemoParishLink.wikitext',
			],
			[
				'title' => 'Template:DemoPatronage',
				'file' => 'examples/pages/Template_DemoPatronage.wikitext',
			],
			[
				'title' => 'Template:DemoSuccessor',
				'file' => 'examples/pages/Template_DemoSuccessor.wikitext',
			],
			[
				'title' => 'Demo Archdiocese of Northbridge',
				'file' => 'examples/pages/Demo_Archdiocese_of_Northbridge.wikitext',
			],
			[
				'title' => 'Demo Diocese of Eastfield',
				'file' => 'examples/pages/Demo_Diocese_of_Eastfield.wikitext',
			],
			[
				'title' => 'Demo Diocese of Westmere',
				'file' => 'examples/pages/Demo_Diocese_of_Westmere.wikitext',
			],
			[
				'title' => 'Demo Cathedral of St. Anne',
				'file' => 'examples/pages/Demo_Cathedral_of_St_Anne.wikitext',
			],
			[
				'title' => 'Demo Parish of St. Brigid',
				'file' => 'examples/pages/Demo_Parish_of_St_Brigid.wikitext',
			],
			[
				'title' => 'Demo Parish of St. Columba',
				'file' => 'examples/pages/Demo_Parish_of_St_Columba.wikitext',
			],
			[
				'title' => 'Demo Archdiocese of Harbourview',
				'file' => 'examples/pages/Demo_Archdiocese_of_Harbourview.wikitext',
			],
			[
				'title' => 'Demo Parish of Our Lady of the Harbor',
				'file' => 'examples/pages/Demo_Parish_of_Our_Lady_of_the_Harbor.wikitext',
			],
			[
				'title' => 'Demo Sisters of Mercy Outreach',
				'file' => 'examples/pages/Demo_Sisters_of_Mercy_Outreach.wikitext',
			],
			[
				'title' => 'Demo Catholic Community Foundation',
				'file' => 'examples/pages/Demo_Catholic_Community_Foundation.wikitext',
			],
			[
				'title' => 'GrantDemo:CCF St Anne 2024',
				'file' => 'examples/pages/GrantDemo_CCF_St_Anne_2024.wikitext',
			],
			[
				'title' => 'GrantDemo:CCF St Brigid 2024',
				'file' => 'examples/pages/GrantDemo_CCF_St_Brigid_2024.wikitext',
			],
			[
				'title' => 'GrantDemo:CCF Eastfield 2023',
				'file' => 'examples/pages/GrantDemo_CCF_Eastfield_2023.wikitext',
			],
			[
				'title' => 'GrantDemo:Northbridge Columba 2024',
				'file' => 'examples/pages/GrantDemo_Northbridge_Columba_2024.wikitext',
			],
			[
				'title' => 'GrantDemo:Mercy Harbor 2023',
				'file' => 'examples/pages/GrantDemo_Mercy_Harbor_2023.wikitext',
			],
			[
				'title' => 'GrantDemo:CCF Harbourview 2024',
				'file' => 'examples/pages/GrantDemo_CCF_Harbourview_2024.wikitext',
			],
			[
				'title' => 'GrantDemo:Harbourview Harbor 2024',
				'file' => 'examples/pages/GrantDemo_Harbourview_Harbor_2024.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Examples',
				'file' => 'examples/help/Examples.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Create tables',
				'file' => 'examples/help/Create_tables.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Province',
				'file' => 'examples/help/Province.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/One diocese',
				'file' => 'examples/help/One_diocese.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/By country',
				'file' => 'examples/help/By_country.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Funding',
				'file' => 'examples/help/Funding.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/One funder',
				'file' => 'examples/help/One_funder.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Templates',
				'file' => 'examples/help/Templates.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Raw source',
				'file' => 'examples/help/Raw_source.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Deaneries',
				'file' => 'examples/help/Deaneries.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Religious houses',
				'file' => 'examples/help/Religious_houses.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Schools',
				'file' => 'examples/help/Schools.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Episcopal lineage',
				'file' => 'examples/help/Episcopal_lineage.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Twin parishes',
				'file' => 'examples/help/Twin_parishes.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Patron saints',
				'file' => 'examples/help/Patron_saints.wikitext',
			],
			[
				'title' => 'Help:Saintapedia Graph/Successors',
				'file' => 'examples/help/Successors.wikitext',
			],
			[
				'title' => 'Saintapedia Graph demo',
				'file' => 'examples/pages/Saintapedia_Graph_demo.wikitext',
			],
		];
	}

	/**
	 * @return list<array{title:string,file:string}>
	 */
	public static function catalog( bool $includeExamples = false ): array {
		if ( !$includeExamples ) {
			return self::pages();
		}
		return array_merge( self::pages(), self::examplePages() );
	}

	/**
	 * Absolute path for a catalog file relative to the extension root.
	 *
	 * @param string $extensionRoot
	 * @param string $relative
	 * @return string
	 */
	public static function resolve( string $extensionRoot, string $relative ): string {
		return rtrim( $extensionRoot, "/\\" ) . DIRECTORY_SEPARATOR
			. str_replace( [ '/', '\\' ], DIRECTORY_SEPARATOR, $relative );
	}
}
