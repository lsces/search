{strip}
{if empty($moduleTitle)}
	{assign var=moduleTitle value=""}
{/if}
{if $gBitSystem->isPackageActive( 'search' )}
	{bitmodule title="$moduleTitle" name="search_new"}
		{include file="bitpackage:search/global_mini_search.tpl"}
	{/bitmodule}
{/if}
{/strip}