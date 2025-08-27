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
 * required setup
 */
require_once '../kernel/includes/setup_inc.php';

include_once DIRECTORY_PKG_PATH.'dir_lib.php';

$gBitSystem->verifyFeature( 'feature_directory' );
$gBitSystem->verifyPermission( 'bit_p_view_directory' );

$gBitSmarty->assign('words', $_REQUEST['words']);
$gBitSmarty->assign('where', $_REQUEST['where']);
$gBitSmarty->assign('how', $_REQUEST['how']);

$sort_mode = $_REQUEST["sort_mode"] ?? 'hits_desc';
$gBitSmarty->assign('sort_mode', $sort_mode);

$offset = $_REQUEST["offset"] ?? 0;

if (isset($_REQUEST['page'])) {
	$page = &$_REQUEST['page'];
	$offset = ($page - 1) * $max_records;
}
$gBitSmarty->assign('offset', $offset);

$find = $_REQUEST["find"] ?? '';

$gBitSmarty->assign('find', $find);

$items = $_REQUEST['where'] == 'all'
	? $dirlib->dir_search($_REQUEST['words'], $_REQUEST['how'], $offset, $max_records, $sort_mode)
	: $dirlib->dir_search_cat($_REQUEST['parent'], $_REQUEST['words'], $_REQUEST['how'], $offset, $max_records, $sort_mode);

$cant_pages = ceil($items["cant"] / $max_records);
$gBitSmarty->assign('cant_pages', $cant_pages);
$gBitSmarty->assign('actual_page', 1 + $offset / $max_records);

if ($items["cant"] > ($offset + $max_records)) {
	$gBitSmarty->assign('next_offset', $offset + $max_records);
} else {
	$gBitSmarty->assign('next_offset', -1);
}

if ($offset > 0) {
	$gBitSmarty->assign('prev_offset', $offset - $max_records);
} else {
	$gBitSmarty->assign('prev_offset', -1);
}

$gBitSmarty->assign('items', $items["data"]);

$section = 'directory';
// Display the template
$gBitSystem->display( 'bitpackage:search/directory_search.tpl', null, array( 'display_mode' => 'display' ));
