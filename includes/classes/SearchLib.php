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
 */

/**
 * @package search
 */

namespace Bitweaver\Search;

use Bitweaver\BitBase;
use Bitweaver\Liberty\LibertyBase;
use Bitweaver\KernelTools;
use Bitweaver\Liberty\LibertyContent;

class SearchLib extends BitBase {

	public $wordlist_cache;

	public function __construct() {
		parent::__construct();
		$this->wordlist_cache = []; // for caching queries to the LRU-cache-list.
	}

	public function register_search($words) {
		$words = strtolower($words);
		$words = addslashes($words);
		$words = preg_split("/\s/", $words);
		foreach ($words as $word) {
			// `term` is C(50): a longer value throws a PDOException (500)
			$word = mb_substr( trim($word), 0, 50 );
			if( $word === '' ) {
				continue;
			}
			$cant = $this->mDb->getOne("SELECT COUNT(*) FROM `" . BIT_DB_PREFIX .
				"search_stats` WHERE `term`=?", [ $word ], );
				$query = $cant
				? "UPDATE `" . BIT_DB_PREFIX . "search_stats` SET `hits`= `hits` + 1 WHERE `term`=?"
				: "INSERT INTO `" . BIT_DB_PREFIX . "search_stats` (`term`,`hits`) VALUES (?,1)";
			$result = $this->mDb->query($query, [ $word ]);
		}
	}

	public function find( &$pParamHash ) { // $where, $words, $offset, $max_records, $plUsePart = false) {
		$typed = array_values( array_unique( preg_split("/[\W]+/", strtolower($pParamHash['words']), -1, PREG_SPLIT_NO_EMPTY) ) );
		// Each typed word keeps its own list of variants, so AND can ask for "any variant of every word" (a flat list would demand every variant of every word).
		// With usePart the variants are the words in the index that start with the typed word; without it the typed word alone, an exact (indexed) match.
		$terms = [];
		foreach( $typed as $word ) {
			$variants = [ $word ];
			if( !empty( $pParamHash['usePart'] ) ) {
				$variants = array_values( array_unique( array_merge( $variants, $this->get_wordlist_from_syllables( [ $word ] ) ) ) );
			}
			$terms[$word] = $variants;
		}
		$pParamHash['terms'] = $terms;
		$pParamHash['words'] = $terms ? array_values( array_unique( array_merge( ...array_values( $terms ) ) ) ) : [];
		$res = $this->find_exact_generic( $pParamHash );
		return $res;
	}

	/*
	 * This function checks the search_syllable table to see how old the "syllable" is
	 * If the syllable is to old or doesn't exist, it refreshes the syllable/word list stored in search_words
	 * Then, it get a list of words from the search_words table and returns an array of them
	*/
	public function get_wordlist_from_syllables($syllables) {
		global $gBitSystem;
		$search_syll_age = $gBitSystem->getConfig( 'search_syll_age', SEARCH_PKG_NAME );
		$ret = [];
		foreach($syllables as $syllable) {
			$bindvars = [ $syllable ];
			$age      = time() - (int)$this->mDb->getOne(
						"select `last_updated` from `" . BIT_DB_PREFIX . "search_syllable` where `syllable`=?",
						$bindvars, );
			if(!$age || $age > ($search_syll_age * 3600)) {// older than search_syll_age hours
				$a = $this->refresh_lru_wordlist($syllable);
			}
			$lruList = $this->get_lru_wordlist($syllable);
			if (is_array($lruList)) {
				$ret = array_merge($ret, $lruList);
			}
			// update lru last used value (Used to purge oldest last used records)
			$now = time();
			$this->mDb->query("update `" . BIT_DB_PREFIX . "search_syllable` set `last_used`=? where `syllable`=?",
				[ (int) $now, $syllable ], );
		}
		return $ret;
	}

	public function get_lru_wordlist($syllable) {
		$ret = [];
		if(!isset($this->wordlist_cache[$syllable])) {
				   $query  = "select `searchword` from `" . BIT_DB_PREFIX . "search_words` where `syllable`=?";
				$result = $this->mDb->query($query, [ $syllable ]);
				if ($result->RecordCount() > 0) {
					while ($res = $result->fetchRow()) {
						$this->wordlist_cache[$syllable][]=$res["searchword"];
					}
					$ret = $this->wordlist_cache[$syllable];
				}
		}
		return $ret;
	}

	public function refresh_lru_wordlist($syllable) {
		global $gBitSystem;
		$search_max_syllwords = $gBitSystem->getConfig( 'search_max_syllwords', SEARCH_PKG_NAME );;
		$search_lru_length = $gBitSystem->getConfig( 'search_lru_length', SEARCH_PKG_NAME );;
		$search_lru_purge_rate = $gBitSystem->getConfig( 'search_lru_purge_rate', SEARCH_PKG_NAME );
		$ret = [];

		// delete from wordlist and lru list
		$this->mDb->query("delete from `".BIT_DB_PREFIX."search_words` where `syllable`=?", [ $syllable ],-1,-1);
		$this->mDb->query("delete from `".BIT_DB_PREFIX."search_syllable` where `syllable`=?", [ $syllable ],-1,-1);
		if (!isset($search_max_syllwords)) {
			$search_max_syllwords = 100;
		}
		// A prefix match can use the index on searchword; a contains match ('%syllable%') cannot, so it reads every row of search_index -
		// with the index at millions of rows that took over a minute and ran past the web server's time limit, and a lookup that never
		// finishes is never cached. STARTING WITH is used rather than LIKE 'syllable%' because Firebird only uses the index for LIKE when the
		// pattern is a literal, not a bound parameter; it also takes the word literally, with no wildcard characters.
		$query  = "SELECT `searchword`, SUM(`i_count`) AS `cnt` FROM `" . BIT_DB_PREFIX .
					"search_index` WHERE `searchword` STARTING WITH ? GROUP BY `searchword` ORDER BY 2 desc";
		$result = $this->mDb->query($query, [ $syllable ], $search_max_syllwords); // search_max_syllwords: how many different search_words that start with the syllable are taken into account?. Sortet by number of occurences.
		while ($res = $result->fetchRow()) {
			$ret[] = $res["searchword"];
		}
		// cache this long running query
		foreach($ret as $searchword) {
			$this->mDb->query("INSERT INTO `" . BIT_DB_PREFIX .
				"search_words` (`syllable`,`searchword`) VALUES (?,?)",
				[ $syllable, $searchword ], -1, -1, );
			}
		// set lru list parameters
		$now = time();
		$this->mDb->query("INSERT INTO `" . BIT_DB_PREFIX .
			"search_syllable`(`syllable`,`last_used`,`last_updated`) values (?,?,?)",
			[ $syllable, (int) $now, (int) $now ], );

		// at random rate: check length of lru list and purge these that
		// have not been used for long time. This is what a lru list
		// basically does
		list($usec, $sec) = explode(" ", microtime());
		srand (ceil($sec + 100 * $usec));
		if(rand(1, $search_lru_purge_rate) == 1) {
			$lrulength = $this->mDb->getOne("SELECT COUNT(*) FROM `" . BIT_DB_PREFIX .
				"search_syllable`", [], );
			if ($lrulength > $search_lru_length) { // only purge if lru list is too long.
				//purge oldest
				$oldwords = [];
				$diff   = $lrulength - $search_lru_length;
				$query  = "select `syllable` from `".BIT_DB_PREFIX."search_syllable` ORDER BY `last_used` asc";
				$result = $this->mDb->query($query, [], $diff);
				while ($res = $result->fetchRow()) {
					$oldwords[]=$res["syllable"];
				}
				foreach($oldwords as $oldword) {
					$this->mDb->query("delete from `" . BIT_DB_PREFIX .
						"search_words`    where `syllable`=?", [ $oldword ], -1, -1, );
					$this->mDb->query("delete from `" . BIT_DB_PREFIX .
						"search_syllable` where `syllable`=?", [ $oldword ], -1, -1, );
				}

			}
		}
		return $ret;
	}

	public function find_with_or($allowed, $selectSql, $joinSql, $whereSql, $bindVars,&$pParamHash) {
		// Putting in the below hack because mssql cannot select distinct on a text blob column.
		$qPlaceHolders1 = implode(',', array_fill(0, count($pParamHash['words']), '?'));
		$bindVars = array_merge( $pParamHash['words'], $allowed );
		LibertyContent::getServicesSql( 'search_extra_sql_function', $selectSql, $joinSql, $whereSql, $bindVars );
		// the filters every content list applies (an access-control service leaves out what this user may not see): a search result lists the
		// title and a piece of the text, so it must not show what the page would refuse
		LibertyContent::getServicesSql( 'content_list_sql_function', $selectSql, $joinSql, $whereSql, $bindVars );
		$ret = [];
		$query = "SELECT
						lc.`content_id`,
						lc.`title`,
						lc.`format_guid`,
						lc.`content_type_guid`,
						COALESCE(lch.`hits`,0) AS hits,
						lc.`created`,
						lc.`last_modified`,
						lc.`data`,
						COALESCE((
							SELECT SUM(i_count)
							FROM `" . BIT_DB_PREFIX . "search_index` si
							WHERE si.`content_id`=lc.`content_id` AND si.`searchword` IN (" . $qPlaceHolders1 . ")
						),0) AS relevancy
						$selectSql
					FROM `" . BIT_DB_PREFIX . "liberty_content` lc
					LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` lch ON (lc.`content_id` = lch.`content_id`)
					$joinSql
					WHERE (
						SELECT SUM(i_count)
						FROM `" . BIT_DB_PREFIX . "search_index` si
						WHERE si.`content_id`=lc.`content_id`
						AND si.`searchword` IN (" . $qPlaceHolders1 . ")
						GROUP BY
						si.`content_id`
						)>0 $whereSql
					ORDER BY 9 DESC, 5 DESC
					";
			$querycant = "SELECT
					COUNT(*)
					FROM `" . BIT_DB_PREFIX . "liberty_content` lc
					LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` lch ON (lc.`content_id` = lch.`content_id`)
					$joinSql
					WHERE (
						SELECT SUM(i_count)
						FROM `" . BIT_DB_PREFIX . "search_index` si
						WHERE si.`content_id`=lc.`content_id`
						AND si.`searchword` IN (" . $qPlaceHolders1 . ")
						GROUP BY
						si.`content_id`
						)>0 $whereSql";
			$result = $this->mDb->query( $query,  array_merge( $pParamHash['words'] ,$bindVars), $pParamHash['max_records'], $pParamHash['offset'] );
			$pParamHash['cant'] = $this->mDb->getOne( $querycant, $bindVars );
			while ($res = $result->fetchRow()) {
				$res['href'] = BIT_ROOT_URL . "index.php?content_id=" . $res['content_id'];
				$ret[] = $res;
			}
			return $ret;
	}

	public function find_with_and($allowed, $selectSql, $joinSql, $whereSql, $bindVars, &$pParamHash) {
		// Make a slot for the search words (filled per typed word below).
		$bindVars[0] = null;
		$bindVars = array_merge( $bindVars, $allowed );
		LibertyContent::getServicesSql( 'search_extra_sql_function', $selectSql, $joinSql, $whereSql, $bindVars );
		// the filters every content list applies (an access-control service leaves out what this user may not see): a search result lists the
		// title and a piece of the text, so it must not show what the page would refuse
		LibertyContent::getServicesSql( 'content_list_sql_function', $selectSql, $joinSql, $whereSql, $bindVars );

		$ret = [];
		$first = true;
		foreach( $pParamHash['terms'] as $variants ) {
			$wordPlaceHolders = implode( ',', array_fill( 0, count( $variants ), '?' ) );
			$query = "SELECT lc.`content_id` AS hash_key,
						lc.`content_id`,
						lc.`title`,
						lc.`format_guid`,
						lc.`content_type_guid`,
						COALESCE(lch.`hits`,0) AS hits,
						lc.`created`,
						lc.`last_modified`,
						lc.`data`,
						si.`i_count` AS relevancy
						$selectSql
					FROM `" . BIT_DB_PREFIX . "liberty_content` lc
					LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` lch ON (lc.`content_id` = lch.`content_id`)
					$joinSql
					INNER JOIN `".BIT_DB_PREFIX."search_index` si ON (si.`content_id`=lc.`content_id` AND si.`searchword` IN ( $wordPlaceHolders ) )
					WHERE si.`i_count` > 0 $whereSql
					ORDER BY relevancy DESC, hits DESC
					";
				// one row per (page, variant): add the variants' counts up so a page is listed once with its total relevancy
				$result = [];
				foreach( $this->mDb->getAll( $query, array_merge( $variants, array_slice( $bindVars, 1 ) ) ) ?: [] as $row ) {
					$id = $row['content_id'];
					if( isset( $result[$id] ) ) {
						$result[$id]['relevancy'] += $row['relevancy'];
					} else {
						$result[$id] = $row;
					}
				}
				if ($first) {
					$ret = $result;
					$first = false;
				}
				else {
					$this->mergeResults($ret, $result);
				}
			}
			/* count it */
			$pParamHash['cant'] = count($ret);

			/* Sort it */
			uasort($ret, function($a, $b) {
				$rel = $b['relevancy'] - $a['relevancy'];
				return $rel == 0 ? $b['hits'] - $a['hits'] : $rel;
			});

			/* slice it */
			$ret = array_slice($ret, $pParamHash['offset'], $pParamHash['offset'] + $pParamHash['max_records']);

			/* Set the hrefs. */
			foreach ($ret as $content_id => $data) {
				$ret[$content_id]['href'] = BIT_ROOT_URL . "index.php?content_id=" . $data['content_id'];
			}

			return $ret;
	}

	public function find_exact_generic( &$pParamHash ) {
		global $gPage, $gBitSystem, $gLibertySystem, $gBitDbType;
		$allowed = [];
		$ret    = [];
		// the Search in choice (scope[]), else the old single content_type_guid: null = every type, else the types wanted. A chosen scope that names content
		// ids is every type the person may search, limited below to those ids (and the types chosen beside them).
		$scope = self::resolveScopes( $pParamHash );
		$wanted = $scope ? ( $scope['ids'] ? null : $scope['types'] ) : self::contentTypesFor( $pParamHash['content_type_guid'] ?? '' );
		foreach( $gLibertySystem->mContentTypes as $contentType ) {
			if (( $wanted === null || in_array( $contentType["content_type_guid"], $wanted, true ) ) // pages ?
			and self::isTypeSearchable( $contentType["content_type_guid"] ) ) {
				$allowed[] = $contentType["content_type_guid"];
			}
		}

		if (count($allowed) > 0 && count($pParamHash['words']) > 0) {
			$selectSql = '';
			$joinSql = '';
			$whereSql = " AND  lc.`content_type_guid` IN (" . implode(',', array_fill(0, count($allowed), '?')) . ") ";
			if( $scope ) {
				$whereSql .= self::scopeWhereSql( $scope, $allowed );
			}
			$bindVars = [];

			$ret = isset($pParamHash['useAnd']) && $pParamHash['useAnd']
				? $this->find_with_and($allowed, $selectSql, $joinSql, $whereSql, $bindVars, $pParamHash)
				: $this->find_with_or($allowed, $selectSql, $joinSql, $whereSql, $bindVars, $pParamHash);
		} else {
			$pParamHash['cant'] = 0;
			$ret = [];
		}
		return $ret;
	}

	public function mergeResults(&$ret, $result) {
		// Remove those that don't overlap or update relevance
		foreach ($ret as $content_id => $data) {
			if (!isset($result[$content_id])) {
				unset($ret[$content_id]);
			}
			else {
				$ret[$content_id]['relevancy'] += $result[$content_id]['relevancy'];
			}
		}
	}

	/**
	 * The classes that contribute "scopes" to the Limit Search list (the service 'search_scopes_class'): a package that knows of things worth searching as a
	 * whole - a library's top-level galleries, say - names a class with searchScopes() and searchScopeIds(), so search itself never names a package.
	 *
	 * @return list<class-string>
	 */
	private static function scopeProviders(): array {
		global $gLibertySystem;
		return array_values( array_filter( (array)$gLibertySystem->getServiceValues( 'search_scopes_class' ),
			fn( $c ) => is_string( $c ) && class_exists( $c ) && method_exists( $c, 'searchScopes' ) && method_exists( $c, 'searchScopeIds' ) ) );
	}

	/** The content types the providers cover with scopes of their own (so the type groups below leave them out). */
	public static function claimedTypes(): array {
		static $claimed = null;
		if( $claimed === null ) {
			$claimed = [];
			foreach( self::scopeProviders() as $class ) {
				$claimed = array_merge( $claimed, (array)( $class::searchScopes()['claims'] ?? [] ) );
			}
		}
		return $claimed;
	}

	/**
	 * What the "Search in" list offers, as [ [ 'heading' => string, 'items' => [ key => label ] ], ... ]: each provider's scopes under its own heading, then
	 * one entry per package group of the content types no provider claimed. A key is opaque to the page: it goes back as scope[] and is resolved by
	 * resolveScopes().
	 */
	public static function getScopes(): array {
		static $cache = null;
		if( $cache !== null ) {
			return $cache;
		}
		$out = [];
		foreach( self::scopeProviders() as $class ) {
			$def = $class::searchScopes();
			if( !empty( $def['scopes'] ) ) {
				$out[] = [ 'heading' => (string)( $def['heading'] ?? '' ), 'items' => (array)$def['scopes'], 'own_list' => true ];
			}
		}
		// the rest as one flat list, in the menu's package order and the admin's type order - no headings, no "All <package>" (tick two boxes instead)
		$claimed = array_flip( self::claimedTypes() );
		$items = [];
		foreach( self::getSearchGroups() as $types ) {
			foreach( array_diff_key( $types, $claimed ) as $guid => $name ) {
				$items['type:'.$guid] = $name;
			}
		}
		if( $items ) {
			$out[] = [ 'heading' => '', 'items' => $items ];
		}
		return $cache = $out;
	}

	/**
	 * What the scope[] (or scopes, the same keys comma-joined, as the pagination links carry them) in a request stands for: [ 'types' => the content types
	 * chosen, 'ids' => the content ids chosen (a provider's scope), 'labels' => their names, 'keys' => the keys taken ], or null when nothing is chosen
	 * (all content). A key nobody recognises - or a provider will not give, a gallery the viewer cannot open - is ignored.
	 */
	public static function resolveScopes( array $pParamHash ): ?array {
		$keys = !empty( $pParamHash['scopes'] ) ? explode( ',', (string)$pParamHash['scopes'] ) : ( is_array( $pParamHash['scope'] ?? null ) ? $pParamHash['scope'] : [] );
		$keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ), fn( $k ) => $k !== '' && strlen( $k ) < 80 ) ) );
		$got = [ 'types' => [], 'ids' => [], 'labels' => [], 'keys' => [] ];
		foreach( $keys as $key ) {
			if( str_starts_with( $key, 'group:' ) ) {
				foreach( self::getSearchGroups() as $label => $types ) {
					if( self::groupValue( $label ) === $key ) {
						$got['types'] = array_merge( $got['types'], array_keys( array_diff_key( $types, array_flip( self::claimedTypes() ) ) ) );
						$got['labels'][] = $label;
						$got['keys'][] = $key;
					}
				}
				continue;
			}
			if( str_starts_with( $key, 'type:' ) ) {
				$guid = substr( $key, 5 );
				foreach( self::getSearchGroups() as $label => $types ) {
					if( isset( $types[$guid] ) && !in_array( $guid, self::claimedTypes(), true ) ) {
						$got['types'][] = $guid;
						$got['labels'][] = $types[$guid];
						$got['keys'][] = $key;
					}
				}
				continue;
			}
			foreach( self::scopeProviders() as $class ) {
				if( ( $one = $class::searchScopeIds( $key ) ) !== null ) {
					$got['ids'] = array_merge( $got['ids'], array_map( 'intval', $one['ids'] ) );
					$got['labels'][] = (string)$one['label'];
					$got['keys'][] = $key;
					break;
				}
			}
		}
		if( !$got['keys'] ) {
			return null;
		}
		$got['types'] = array_values( array_unique( $got['types'] ) );
		$got['ids'] = array_values( array_unique( $got['ids'] ) );
		return $got;
	}

	/**
	 * The WHERE condition for a resolved scope: a content type listed or a content id listed (ids in blocks of 1,000, as one IN list is limited). Types are
	 * taken only from those the person may search; ids are plain integers. Nothing in it needs binding.
	 */
	private static function scopeWhereSql( array $pScope, array $pAllowedTypes ): string {
		$parts = [];
		$types = array_values( array_intersect( $pScope['types'], $pAllowedTypes ) );
		if( $types ) {
			$parts[] = "lc.`content_type_guid` IN ( '".implode( "','", array_map( fn( $t ) => preg_replace( '/[^A-Za-z0-9_]/', '', $t ), $types ) )."' )";
		}
		foreach( array_chunk( $pScope['ids'], 1000 ) as $block ) {
			$parts[] = "lc.`content_id` IN ( ".implode( ',', array_map( 'intval', $block ) )." )";
		}
		return $parts ? " AND ( ".implode( ' OR ', $parts )." ) " : " AND 1 = 0 ";
	}

	/**
	 * The package a content type is listed under: its own, or - for a package that sits inside another's menu - that package. A package says so by
	 * contributing a section to the parent's menu through the generic '<parent>_menu_tpl' service, so the menu and search read the same fact and
	 * nothing is set here.
	 */
	public static function sectionOf( string $pGuid ): string {
		global $gLibertySystem;
		$package = strtolower( (string)( $gLibertySystem->mContentTypes[$pGuid]['handler_package'] ?? '' ) );
		foreach( array_keys( $gLibertySystem->mServices[$package]['services'] ?? [] ) as $key ) {
			if( str_ends_with( $key, '_menu_tpl' ) && ( $parent = substr( $key, 0, -9 ) ) !== $package && $parent !== '' ) {
				return $parent;
			}
		}
		return $package;
	}

	/**
	 * Is a package (section) searchable? The admin's search_on_<package> y/n; until it has been set a package follows its menu: searchable
	 * unless its menu is switched off. Packages with no menu entry are searchable.
	 */
	public static function isSectionSearchable( string $pSection ): bool {
		global $gBitSystem;
		$set = $gBitSystem->getConfig( 'search_on_'.$pSection );
		if( $set === 'y' || $set === 'n' ) {
			return $set === 'y';
		}
		return empty( $gBitSystem->mAppMenu['bar'][$pSection]['is_disabled'] );
	}

	/** Is one content type switched on? The admin's search_type_on_<guid> is only ever 'n'; a type is on until it is ticked off. */
	public static function isTypeOn( string $pGuid ): bool {
		global $gBitSystem;
		return $gBitSystem->getConfig( 'search_type_on_'.$pGuid ) !== 'n';
	}

	/** The one test for a content type: its package is active, the person may see the type (access rules), and its package and the type are on. */
	public static function isTypeSearchable( string $pGuid ): bool {
		global $gBitSystem, $gLibertySystem;
		$package = strtolower( (string)( $gLibertySystem->mContentTypes[$pGuid]['handler_package'] ?? '' ) );
		return $gBitSystem->isPackageActive( $package ) && self::has_permission( $pGuid ) && self::isSectionSearchable( self::sectionOf( $pGuid ) ) && self::isTypeOn( $pGuid );
	}

	/** The heading for a package: its menu title (the menu is the one source of package names), else the capitalised package name. */
	public static function sectionHeading( string $pSection ): string {
		global $gBitSystem;
		return (string)( $gBitSystem->mAppMenu['bar'][$pSection]['menu_title'] ?? '' ) ?: (string)$gBitSystem->getConfig( $pSection.'_menu_text' ) ?: ucfirst( $pSection );
	}

	/** The label of a content type in the search list: the admin's override, else its own name. */
	public static function typeLabel( string $pGuid ): string {
		global $gBitSystem, $gLibertySystem;
		return (string)$gBitSystem->getConfig( 'search_type_text_'.$pGuid ) ?: (string)$gLibertySystem->getContentTypeName( $pGuid );
	}

	/**
	 * The searchable types grouped for the "Limit Search" list: headings in the menu's order (packages outside the menu follow, alphabetically),
	 * types within a heading by the admin's position then name.
	 */
	public static function getSearchGroups(): array {
		global $gBitSystem, $gLibertySystem;
		static $cache = null;
		if( $cache !== null ) {
			return $cache;
		}
		$menuOrder = array_flip( array_keys( $gBitSystem->mAppMenu['bar'] ?? [] ) );
		$groups = $rank = $typeRank = [];
		foreach( $gLibertySystem->mContentTypes as $guid => $cType ) {
			if( !self::isTypeSearchable( $guid ) ) {
				continue;
			}
			$section = self::sectionOf( $guid );
			$heading = self::sectionHeading( $section );
			$groups[$heading][$guid] = self::typeLabel( $guid );
			$rank[$heading] = $menuOrder[$section] ?? PHP_INT_MAX;
			$typeRank[$guid] = (int)$gBitSystem->getConfig( 'search_type_position_'.$guid ) ?: PHP_INT_MAX;
		}
		uksort( $groups, function( $a, $b ) use ( $rank ) {
			return $rank[$a] <=> $rank[$b] ?: strcasecmp( $a, $b );
		} );
		foreach( $groups as &$types ) {
			$labels = $types;
			uksort( $types, function( $a, $b ) use ( $typeRank, $labels ) {
				return $typeRank[$a] <=> $typeRank[$b] ?: strcasecmp( $labels[$a], $labels[$b] );
			} );
		}
		unset( $types );
		return $cache = $groups;
	}

	/** The value a group's "All ..." option carries in the content_type_guid parameter. */
	public static function groupValue( string $pLabel ): string {
		return 'group:'.trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $pLabel ) ), '-' );
	}

	/**
	 * The "Limit Search" options for html_options: All Content, then an optgroup per group - its own "All <group>" option first when it holds more than
	 * one type, a single entry otherwise - so the list is short to scan and a whole kind of thing (all contacts, all TV) can be searched at once.
	 */
	public static function getContentTypeOptions(): array {
		$options = [ '' => KernelTools::tra( 'All Content' ) ];
		foreach( self::getSearchGroups() as $label => $types ) {
			if( count( $types ) > 1 ) {
				$options[$label] = [ self::groupValue( $label ) => KernelTools::tra( 'All' ).' '.$label ] + $types;
			} else {
				$options[array_key_first( $types )] = $label;   // a group of one is just an entry, named for the group
			}
		}
		return $options;
	}

	/** The content types a content_type_guid parameter stands for: null for all, the group's types for "group:...", else the one type. */
	public static function contentTypesFor( $pValue ): ?array {
		$pValue = (string)$pValue;
		if( $pValue === '' ) {
			return null;
		}
		if( str_starts_with( $pValue, 'group:' ) ) {
			foreach( self::getSearchGroups() as $label => $types ) {
				if( self::groupValue( $label ) === $pValue ) {
					return array_keys( $types );
				}
			}
			return [];
		}
		return [ $pValue ];
	}

	/** What to call the thing searched for a content_type_guid parameter: the group's label, the type's name, or null when it is unknown. */
	public static function labelFor( $pValue ): ?string {
		global $gLibertySystem;
		$pValue = (string)$pValue;
		if( str_starts_with( $pValue, 'group:' ) ) {
			foreach( self::getSearchGroups() as $label => $types ) {
				if( self::groupValue( $label ) === $pValue ) {
					return $label;
				}
			}
			return null;
		}
		return $gLibertySystem->getContentTypeName( $pValue );
	}

	public static function has_permission($pContentType = null) {
		global $gBitUser, $gLibertySystem;

		if ( ! empty( $pContentType ) ) {
			$object = LibertyBase::getLibertyObject(1, $pContentType, false);
			if ( ! empty( $object ) ) {
				// Note that we can't do verify access here because
				// we are using a generic object but we can at least get a
				// basic permission check here.
				return $object->hasViewPermission(false);
			}
		}

		return false;
	}

} # class SearchLib

