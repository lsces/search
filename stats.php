<?php
/**
 * $Header$
 *
 * @copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 *
 * $Id$
 * @author  Luis Argerich (lrargerich@yahoo.com)
 * @package search
 * @subpackage functions
 */
 
/**
 * requires setup
 */
require_once '../kernel/includes/setup_inc.php';

use Bitweaver\Search\SearchStatsLib;

$gBitSystem->verifyFeature( 'search_stats' );
$gBitSystem->verifyPermission( 'p_admin' );

$searchstatslib = new SearchStatsLib();

if (isset($_REQUEST["clear"])) {
	$searchstatslib->clear_search_stats();
}

$sort_mode = $_REQUEST["sort_mode"] ?? 'hits_desc';
$offset = $_REQUEST["offset"] ?? 0;

if (isset($_REQUEST['page'])) {
	$page = &$_REQUEST['page'];
	$offset = ($page - 1) * $max_records;
}
$gBitSmarty->assign('offset', $offset);

$find = $_REQUEST["find"] ?? '';

$gBitSmarty->assign('find', $find);

$gBitSmarty->assign('sort_mode', $sort_mode);
$channels = $searchstatslib->list_search_stats($offset, $max_records, $sort_mode, $find);

$cant_pages = ceil($channels["cant"] / $max_records);
$gBitSmarty->assign('cant_pages', $cant_pages);
$gBitSmarty->assign('actual_page', 1 + $offset / $max_records);

if ($channels["cant"] > ($offset + $max_records)) {
	$gBitSmarty->assign('next_offset', $offset + $max_records);
} else {
	$gBitSmarty->assign('next_offset', -1);
}

// If offset is > 0 then prev_offset
if ($offset > 0) {
	$gBitSmarty->assign('prev_offset', $offset - $max_records);
} else {
	$gBitSmarty->assign('prev_offset', -1);
}

$gBitSmarty->assign('channels', $channels["data"]);



// Display the template
$gBitSystem->display( 'bitpackage:stats/search_stats.tpl', null, array( 'display_mode' => 'display' ));
