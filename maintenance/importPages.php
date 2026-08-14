<?php

namespace MediaWiki\Extension\SaintapediaGraph\Maintenance;

use ContentHandler;
use Maintenance;
use User;
use WikiPage;

/**
 * Import bundled help + templates onto this wiki.
 *
 * MediaWiki 1.40+:
 *   php maintenance/run.php SaintapediaGraph:importPages
 *
 * MediaWiki 1.39 (direct):
 *   php extensions/SaintapediaGraph/maintenance/importPages.php
 *
 * @ingroup Maintenance
 */

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class ImportPages extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Import Saintapedia Graph help page and templates from the extension tree.'
		);
		$this->addOption( 'dry-run', 'List pages that would be created or updated' );
		$this->addOption( 'overwrite', 'Replace existing pages' );
		$this->requireExtension( 'SaintapediaGraph' );
	}

	public function execute() {
		$root = dirname( __DIR__ );
		$dry = $this->hasOption( 'dry-run' );
		$overwrite = $this->hasOption( 'overwrite' );

		foreach ( PageCatalog::pages() as $entry ) {
			$path = PageCatalog::resolve( $root, $entry['file'] );
			if ( !is_readable( $path ) ) {
				$this->fatalError( "Missing source file: {$entry['file']}" );
			}
			$text = file_get_contents( $path );
			if ( $text === false ) {
				$this->fatalError( "Could not read: {$entry['file']}" );
			}

			$title = $this->newTitle( $entry['title'] );
			if ( $title === null ) {
				$this->fatalError( "Bad title: {$entry['title']}" );
			}

			$exists = $title->exists();
			$action = $exists ? ( $overwrite ? 'update' : 'skip' ) : 'create';

			if ( $dry ) {
				$this->output( "{$action}\t{$entry['title']}\t{$entry['file']}\n" );
				continue;
			}

			if ( $action === 'skip' ) {
				$this->output( "skip (exists, pass --overwrite)\t{$entry['title']}\n" );
				continue;
			}

			$this->savePage( $title, $text, $exists
				? 'Update Saintapedia Graph bundled page'
				: 'Import Saintapedia Graph bundled page'
			);
			$this->output( "{$action}\t{$entry['title']}\n" );
		}
	}

	/**
	 * @param string $text
	 * @return \MediaWiki\Title\Title|\Title|null
	 */
	private function newTitle( string $text ) {
		if ( class_exists( \MediaWiki\Title\Title::class ) ) {
			return \MediaWiki\Title\Title::newFromText( $text );
		}
		if ( class_exists( \Title::class ) ) {
			return \Title::newFromText( $text );
		}
		return null;
	}

	/**
	 * @param \MediaWiki\Title\Title|\Title $title
	 * @return \MediaWiki\User\User|User
	 */
	private function systemUser() {
		if ( class_exists( \MediaWiki\User\User::class )
			&& method_exists( \MediaWiki\User\User::class, 'newSystemUser' )
		) {
			$user = \MediaWiki\User\User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );
			if ( $user ) {
				return $user;
			}
		}
		$user = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );
		if ( !$user ) {
			$this->fatalError( 'Could not create the Maintenance script system user' );
		}
		return $user;
	}

	/**
	 * @param \MediaWiki\Title\Title|\Title $title
	 * @param string $text
	 * @param string $summary
	 */
	private function savePage( $title, string $text, string $summary ): void {
		$content = ContentHandler::makeContent( $text, $title );
		$user = $this->systemUser();

		if ( method_exists( $this, 'getServiceContainer' )
			&& method_exists( $this->getServiceContainer(), 'getWikiPageFactory' )
		) {
			$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		} else {
			$page = WikiPage::factory( $title );
		}

		if ( !method_exists( $page, 'doUserEditContent' ) ) {
			$this->fatalError( 'WikiPage::doUserEditContent is not available on this MediaWiki' );
		}

		$status = $page->doUserEditContent( $content, $user, $summary, EDIT_FORCE_BOT );
		if ( !$status->isOK() ) {
			$msg = method_exists( $status, 'getWikiText' )
				? $status->getWikiText( false, false, 'en' )
				: 'edit failed';
			$this->fatalError( "Failed to save {$title->getPrefixedText()}: $msg" );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = ImportPages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
