<?php
/**
 * JSON endpoint for live search module (mod_ajax_search).
 * Returns [{title, href, type}, ...] for the given ?highlight= term.
 */

require_once '../kernel/includes/setup_inc.php';

use Bitweaver\Search\SearchLib;
use Bitweaver\Liberty\LibertyContent;
use Bitweaver\KernelTools;

$gBitSystem->verifyPackage( 'search' );

header( 'Content-Type: application/json; charset=utf-8' );

$words = isset( $_REQUEST['highlight'] ) ? strip_tags( trim( $_REQUEST['highlight'] ) ) : '';
if( strlen( $words ) < 3 ) {
	echo '[]';
	exit;
}

$_REQUEST['words']   = $words;
$_REQUEST['max_records'] = 10;
LibertyContent::prepGetList( $_REQUEST );

$searchlib = new SearchLib();
$results   = $searchlib->find( $_REQUEST );

$out = [];
foreach( $results as $r ) {
	$type = '';
	if( !empty( $r['content_type_guid'] ) ) {
		$type = KernelTools::tra( $gLibertySystem->getContentTypeName( $r['content_type_guid'] ) );
	}
	$out[] = [
		'title' => $r['title'] ?? '',
		'href'  => $r['href']  ?? '',
		'type'  => $type,
	];
}

echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
