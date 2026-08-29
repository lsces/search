<?php
/**
 * @package search
 */

global $gBitInstaller;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'     => 'search',
		'version'     => '5.0.1',
		'description' => 'Widen search_index.last_update and search_syllable.last_used/last_updated from I4 to I8 — I4 is a 32-bit signed integer, max value 19 January 2038. Lower risk than blogs\' user-set future dates (these are always stamped "now"), but I8 matches the convention used everywhere else in this stack.',
	],
	[
		[ 'QUERY' => [
			'SQL92' => [
				"ALTER TABLE search_index ALTER COLUMN last_update TYPE BIGINT",
				"ALTER TABLE search_syllable ALTER COLUMN last_used TYPE BIGINT",
				"ALTER TABLE search_syllable ALTER COLUMN last_updated TYPE BIGINT",
			],
		]],
	]
);
