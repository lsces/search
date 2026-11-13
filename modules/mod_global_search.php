<?php
/**
 * $Header$
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 *
 * $Id$
 * @author  Luis Argerich (lrargerich@yahoo.com)
 * @package search
 * @subpackage modules
 */

/**
 * Initialization
 */

global $gLibertySystem;
use Bitweaver\Search\SearchLib;
use Bitweaver\KernelTools;
if( empty( $contentTypes ) ) {
	$contentTypes = SearchLib::getContentTypeOptions();
}
$gBitSmarty->assign( 'contentTypes', $contentTypes );
if( $gBitSmarty->getTemplateVars( 'searchScopes' ) === null ) {
	$gBitSmarty->assign( 'searchScopes', SearchLib::getScopes() );
}
// (the Search page has already said what is ticked; this module is drawn after it)
if( $gBitSmarty->getTemplateVars( 'scopeSelected' ) === null ) {
	$gBitSmarty->assign( 'scopeSelected', [] );
}
