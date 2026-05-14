<?php

/**
 * $Header$

 * @copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details.
 *
 * @package search
 */

/**
 * Initialization
 */
require_once '../kernel/includes/setup_inc.php';

use Bitweaver\Search\SearchLib;
use Bitweaver\Liberty\LibertyContent;
use Bitweaver\KernelTools;

$searchlib = new SearchLib();

$gBitSystem->verifyPackage( 'search' );

// contentType list created in mod_package_search.php at present
// but this is left in case a different search option is used
if( empty( $contentTypes ) ) {
	$contentTypes = [ '' => KernelTools::tra( 'All Content' ) ];
	foreach( $gLibertySystem->mContentTypes as $cType ) {
		$contentTypes[$cType['content_type_guid']] = $gLibertySystem->getContentTypeName( $cType['content_type_guid'] );
	}
}
asort($contentTypes);
$gBitSmarty->assign( 'contentTypes', $contentTypes );

if( !empty($_REQUEST["highlight"]) ) {
  $_REQUEST["words"]=$_REQUEST["highlight"];
} else {
	// a nice big, groovy search will be cool to have one day...
	$gBitSystem->display( 'bitpackage:search/search.tpl', 'Search', [ 'display_mode' => 'display' ]);
	die;
}

if ($gBitSystem->isFeatureActive("search_stats")) {
	$searchlib->register_search($_REQUEST["words"] ?? '');
}

if (!isset($_REQUEST["content_type_guid"])) {
	$_REQUEST["content_type_guid"] = '';
	$where2 = "Page";
} else {
	$where2 = $contentTypes[$_REQUEST["content_type_guid"]];
}

LibertyContent::prepGetList($_REQUEST);

$_REQUEST['usePart'] = isset( $_REQUEST['usePart'] ) && $_REQUEST['usePart']=='on' ? true : false;
$gBitSmarty->assign('usePart', $_REQUEST['usePart']);
$gBitSmarty->assign('searchType', $_REQUEST['usePart'] ? "Using Partial Word Search" : "Using Exact Word Search");

$_REQUEST['useAnd'] = isset( $_REQUEST['useAnd'] ) && $_REQUEST['useAnd']=='on' ? true : false;
$gBitSmarty->assign('useAnd', $_REQUEST['useAnd']);

// Build the query using words
$_REQUEST["words"] = !isset($_REQUEST["words"]) || (empty($_REQUEST["words"])) ? '' : strip_tags($_REQUEST["words"]);

$gBitSmarty->assign('words', $_REQUEST["words"]);
$results = $searchlib->find( $_REQUEST );

if ($_REQUEST['cant'] <> 1) $where2 .= "s";
$gBitSmarty->assign('where2', KernelTools::tra($where2));
$gBitSmarty->assign('content_type_guid', $_REQUEST["content_type_guid"]);

$stubContent = new LibertyContent();
if ( $_REQUEST['cant'] > 0 ) {
	foreach( array_keys( $results ) as $k ) {
		if( empty( $results[$k]['title'] ) ) {
			$date_format = $gBitSystem->get_long_date_format();
			$date_format = $gBitSystem->mServerTimestamp->get_display_offset()
				? preg_replace( "/ ?%Z/", "", $date_format )
				: preg_replace( "/%Z/", "UTC", $date_format );
			$date_string = $gBitSystem->mServerTimestamp->getDisplayDateFromUTC( $results[$k]['created'] );
			$results[$k]['title'] = $gBitSystem->mServerTimestamp->strftime( $date_format, $date_string, true );
		}
		if( !empty( $results[$k]['data'] ) ) {
			$results[$k]['parsed'] = $stubContent->parseDataHash( $results[$k] );
		}
	}
}
LibertyContent::postGetList( $_REQUEST );
$gBitSmarty->assign( 'listInfo', $_REQUEST['listInfo'] );

// Find search results (build array)
$gBitSmarty->assign('results', $results);

// Display the template
$gBitSystem->display( 'bitpackage:search/search.tpl', 'Search Results for: '.strip_tags($_REQUEST["highlight"]), [ 'display_mode' => 'display' ]);
