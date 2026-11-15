<?php
namespace Bitweaver\Search;

use Bitweaver\KernelTools;

// $Header$

// Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See below for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details.

$feedback = [];

$formSearchToggles = [
	'search_stats' => [
		'label' => 'Search Statistics',
		'note' => 'Record searches made and their frequency.',
//		'page' => 'SearchStats',
	],
	'search_index_on_submit' => [
		'label' => 'Index On Submit',
		'note' => 'Index articles, blogs and wiki pages immdiately on submission. If unchecked, pages will be updated randomly according the the refresh rate below.',
	],
];

$formSearchInts = [
	'search_refresh_rate' => [
		'label' => 'Search Refresh Rate',
		'note' => 'Varies the rate at which updates to the search index are made, 1 = every page read, while rate>1 will introduce a random chance of a refresh every "rate" pages',
	],
	'search_min_wordlength' => [
		'label' => 'Minimum number of letters for search words',
		'note' => 'By settings this value to 3, you can ignore search words such as "a" or "or", however searches for a number like "13" will be ignored as well.',
	],
	'search_max_syllwords' => [
		'label' => 'Maximum number of words',
		'note' => 'The maximum number of words containing a syllable that can be serached for in any one search.',
	],
	'search_syll_age' => [
		'label' => 'Age in hours of search cache',
		'note' => 'Define the Maximum age of cached search results for any given syllable. The results cache will be used to provide a search result if it is available, and will be cleared after either the age, or when the results cache reaches it\'s limit',
	],
	'search_lru_purge_rate' => [
		'label' => 'Least Recently Used (LRU) list purging rate',
		'note' => 'Purge the results cache every "rate" pages. This will keep space available in the cache for new search results',
	],
	'search_lru_length' => [
		'label' => 'Least Recently Used (LRU) list length',
		'note' => 'Limit the results cache to this number of entries',
	],
];

if( !empty( $_REQUEST['del_index'] ) ) {
	require_once SEARCH_PKG_INCLUDE_PATH.'/refresh_functions.php';
	\Bitweaver\Liberty\delete_index_content_type( $_REQUEST["where"] );
	$feedback['success'] = KernelTools::tra( "The search index was successfully deleted." );
}

if( !empty( $_REQUEST['del_index_reindex'] ) ) {
	require_once SEARCH_PKG_INCLUDE_PATH.'/refresh_functions.php';
	$count = \Bitweaver\Liberty\rebuild_index( $_REQUEST["where"] );
	$feedback['success'] = KernelTools::tra( "The search index was successfully deleted." ).KernelTools::tra( "Number of items re-indexed" ).": ".$count;
}

if( !empty( $_REQUEST['index_unindexed'] ) ) {
	require_once SEARCH_PKG_INCLUDE_PATH.'/refresh_functions.php';
	$count = \Bitweaver\Liberty\rebuild_index( $_REQUEST["where"], true );
	$feedback['success'] = KernelTools::tra( "Number of unindexed items indexed" ).": ".$count;
}

if( !empty( $_REQUEST['del_searchwords'] ) ) {
	require_once SEARCH_PKG_INCLUDE_PATH.'/refresh_functions.php';
	\Bitweaver\Liberty\delete_search_words_and_syllables();
	$feedback['success'] = KernelTools::tra( "The searchwords were successfully purged from the database." );
}

if( !empty( $_REQUEST['store_prefs'] ) ) {
	foreach( $formSearchInts as $item => $data ) {
		simple_set_int( $item, SEARCH_PKG_NAME );
		$formSearchInts[$item]['value'] = $_REQUEST[$item];
	}
	foreach( $formSearchToggles as $item => $data ) {
		simple_set_toggle( $item, SEARCH_PKG_NAME );
	}
}

foreach( $formSearchInts as $item => $data ) {
	$formSearchInts[$item]['value'] = $gBitSystem->getConfig( $item );
}
$gBitSmarty->assign( 'formSearchToggles', $formSearchToggles );
$gBitSmarty->assign( 'formSearchInts', $formSearchInts );
$gBitSmarty->assign( 'feedback', $feedback );

// Searchable Content: one section per package, in the menu's order (the Themes > Menus page is the one source of package names and order). A
// package is ticked searchable or not - that is the whole switch; what a person may see inside it is the access rules' job. A package whose menu is
// off is still listed, and defaults to off until ticked. Each type of an active package also has its own tick (on until switched off).
$menuOrder = array_flip( array_keys( $gBitSystem->mAppMenu['bar'] ?? [] ) );
$searchSections = [];
foreach( $gLibertySystem->mContentTypes as $guid => $cType ) {
	if( !$gBitSystem->isPackageActive( strtolower( (string)$cType['handler_package'] ) ) ) {
		continue;
	}
	$section = SearchLib::sectionOf( $guid );
	if( !isset( $searchSections[$section] ) ) {
		$searchSections[$section] = [
			'title'       => SearchLib::sectionHeading( $section ),
			'menu_hidden' => !empty( $gBitSystem->mAppMenu['bar'][$section]['is_disabled'] ),
			'types'       => [],
		];
	}
	// types a provider's scopes cover (the media galleries) are searched through that list, not as entries of their own
	if( in_array( $guid, SearchLib::claimedTypes(), true ) ) {
		$searchSections[$section]['by_scope'] = true;
		continue;
	}
	$searchSections[$section]['types'][$guid] = [ 'name' => $gLibertySystem->getContentTypeName( $guid ) ];
}
uksort( $searchSections, function( $a, $b ) use ( $menuOrder, $searchSections ) {
	return ( $menuOrder[$a] ?? PHP_INT_MAX ) <=> ( $menuOrder[$b] ?? PHP_INT_MAX ) ?: strcasecmp( $searchSections[$a]['title'], $searchSections[$b]['title'] );
} );

if( !empty( $_REQUEST['store_content'] ) ) {
	// the sections shown on the form; one not shown (regrouped away) keeps its setting
	foreach( array_keys( $_REQUEST['search_section'] ?? [] ) as $section ) {
		$section = strtolower( (string)$section );
		$gBitSystem->storeConfig( 'search_on_'.$section, !empty( $_REQUEST['search_on'][$section] ) ? 'y' : 'n', SEARCH_PKG_NAME );
	}
	foreach( array_keys( $_REQUEST['search_type_text'] ?? [] ) as $guid ) {
		if( isset( $gLibertySystem->mContentTypes[$guid] ) ) {
			$gBitSystem->storeConfig( 'search_type_on_'.$guid, !empty( $_REQUEST['search_type_on'][$guid] ) ? null : 'n', SEARCH_PKG_NAME );
			$text = trim( (string)$_REQUEST['search_type_text'][$guid] );
			$pos  = (int)( $_REQUEST['search_type_position'][$guid] ?? 0 );
			$gBitSystem->storeConfig( 'search_type_text_'.$guid, $text !== '' && $text !== (string)$gLibertySystem->getContentTypeName( $guid ) ? $text : null, SEARCH_PKG_NAME );
			$gBitSystem->storeConfig( 'search_type_position_'.$guid, $pos > 0 ? $pos : null, SEARCH_PKG_NAME );
		}
	}
	// the old per-type ticks, Restrict Types and the regrouping setting no longer mean anything: clear them
	foreach( array_keys( $gBitSystem->mConfig ) as $key ) {
		if( str_starts_with( $key, 'search_pkg_' ) || str_starts_with( $key, 'search_group_under_' ) || $key === 'search_restrict_types' ) {
			$gBitSystem->storeConfig( $key, null, SEARCH_PKG_NAME );
		}
	}
}

foreach( $searchSections as $section => &$data ) {
	$data['searchable'] = SearchLib::isSectionSearchable( $section );
	foreach( $data['types'] as $guid => &$type ) {
		$type['text']     = (string)$gBitSystem->getConfig( 'search_type_text_'.$guid ) ?: $type['name'];
		$type['position'] = (string)$gBitSystem->getConfig( 'search_type_position_'.$guid );
		$type['on']       = SearchLib::isTypeOn( $guid );
	}
	unset( $type );
}
unset( $data );
$gBitSmarty->assign( 'searchSections', $searchSections );

// The "Perform action on" list of the Delete / Rebuild Index tab: every content type of an active package (including the ones a provider's scopes
// cover, which the grid above leaves out), each named with its package - Food and Stock both have a "Component" and a "Movement".
if( empty( $contentTypes ) ) {
	$indexTypes = [];
	foreach( $gLibertySystem->mContentTypes as $guid => $cType ) {
		if( $gBitSystem->isPackageActive( strtolower( (string)$cType['handler_package'] ) ) ) {
			$indexTypes[$guid] = SearchLib::sectionHeading( SearchLib::sectionOf( $guid ) ).' - '.$gLibertySystem->getContentTypeName( $guid );
		}
	}
	asort( $indexTypes, SORT_FLAG_CASE | SORT_STRING );
	$contentTypes = [ '' => KernelTools::tra( 'All Content' ) ] + $indexTypes;
	$gBitSmarty->assign( 'contentTypes', $contentTypes );
}
