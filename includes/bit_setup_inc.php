<?php
namespace Bitweaver\Search;

global $gBitSystem, $gLibertySystem ;

$pRegisterHash = [
	'package_name' => 'search',
	'package_path' => dirname( dirname( __FILE__ ) ).'/',
	'service' => LIBERTY_SERVICE_SEARCH,
];

// fix to quieten down VS Code which can't see the dynamic creation of these ...
define( 'SEARCH_PKG_NAME', $pRegisterHash['package_name'] );
define( 'SEARCH_PKG_URL', BIT_ROOT_URL . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'SEARCH_PKG_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'SEARCH_PKG_INCLUDE_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/');
define( 'SEARCH_PKG_CLASS_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/classes/');
define( 'SEARCH_PKG_ADMIN_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/admin/');

$gBitSystem->registerPackage( $pRegisterHash );

if( $gBitSystem->isPackageActive( 'search' ) ) {
	$menuHash = [
		'package_name'  => SEARCH_PKG_NAME,
		'index_url'     => SEARCH_PKG_URL.'index.php',
		'menu_template' => 'bitpackage:search/menu_search.tpl',
	];
	$gBitSystem->registerAppMenu( $menuHash );

	// **********  SEARCH  ************
	include_once SEARCH_PKG_INCLUDE_PATH . 'refresh_functions.php';
	if( $gBitSystem->isFeatureActive("search_index_on_submit") ) {
		// Index synchronously on every save
		$gLibertySystem->registerService( LIBERTY_SERVICE_SEARCH, SEARCH_PKG_NAME,
			[ 'content_store_function' => 'refresh_index'] );
	} else {
		// Background random refresh only — no synchronous indexing on save
		include_once SEARCH_PKG_INCLUDE_PATH . 'refresh.php';
		register_shutdown_function("\\Bitweaver\\Liberty\\refresh_search_index");
		$gLibertySystem->registerService( LIBERTY_SERVICE_SEARCH, SEARCH_PKG_NAME, [] );
	}
}