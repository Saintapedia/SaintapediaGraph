<?php

/**
 * Write Special:Import XML dumps from the page catalog.
 *
 *   php maintenance/buildImportXml.php
 *
 * Does not boot MediaWiki.
 */

use MediaWiki\Extension\SaintapediaGraph\Maintenance\ImportXmlBuilder;
use MediaWiki\Extension\SaintapediaGraph\Maintenance\PageCatalog;

$root = dirname( __DIR__ );
$autoload = $root . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require_once $autoload;
} else {
	require_once $root . '/includes/Maintenance/PageCatalog.php';
	require_once $root . '/includes/Maintenance/ImportXmlBuilder.php';
}

$dir = $root . '/docs/import';
if ( !is_dir( $dir ) && !mkdir( $dir, 0777, true ) && !is_dir( $dir ) ) {
	fwrite( STDERR, "Could not create $dir\n" );
	exit( 1 );
}

$help = ImportXmlBuilder::build( PageCatalog::pages(), $root );
$examples = ImportXmlBuilder::build( PageCatalog::catalog( true ), $root );
file_put_contents( "$dir/SaintapediaGraph-help.xml", $help );
file_put_contents( "$dir/SaintapediaGraph-examples.xml", $examples );

echo "Wrote docs/import/SaintapediaGraph-help.xml (" . strlen( $help ) . " bytes)\n";
echo "Wrote docs/import/SaintapediaGraph-examples.xml (" . strlen( $examples ) . " bytes)\n";
