{strip}
{if $gBitSystem->isPackageActive( 'search' )}
	{bitmodule title="$moduleTitle" name="search_new"}
		<input id="ajax_search_box" type="text" name="search" value="{tr}search{/tr}" />
		<div id="ajax_search_results"></div>
	{/bitmodule}
	<script src="{$smarty.const.UTIL_PKG_URL}javascript/live_search.js"></script>
	<script>
		new LiveSearch('#ajax_search_box', '#ajax_search_results', '{$smarty.const.SEARCH_PKG_URL}ajax_search.php');
	</script>
{/if}
{/strip}
