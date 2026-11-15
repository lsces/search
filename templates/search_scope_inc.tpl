{* The "Search in" list: ticks for the areas to search, none ticked meaning all content. A native select takes one value, so this is a Bootstrap dropdown
   of tick boxes (scope[]), the same control the menu is. Needs $searchScopes (SearchLib::getScopes()) and $scopeSelected (keys already ticked). *}
{strip}
{* the content types in one list; a provider's scopes (the media galleries) are a list of their own, headed by the provider's name *}
{foreach $searchScopes as $group}
{* what the button says: the list's own name when nothing is ticked, the one ticked item, else how many *}
{assign var=tickedLabels value=[]}
{if !empty($group.own_list) && $group.heading}{assign var=manyWord value=$group.heading|escape}{else}{capture assign=manyWord}{tr}Content{/tr}{/capture}{/if}
{foreach $group.items as $scopeKey => $scopeLabel}{if !empty($scopeSelected) && in_array($scopeKey, $scopeSelected)}{$tickedLabels[] = $scopeLabel}{/if}{/foreach}
<div class="dropdown search-scope" style="display:inline-block;">
	<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-all="{if !empty($group.own_list) && $group.heading}{tr}All{/tr} {$group.heading|escape}{else}{tr}All Content{/tr}{/if}" data-none="{tr}No{/tr} {$manyWord}" data-many="{$manyWord}">
		<span class="scope-summary">{if !$tickedLabels}{if !empty($scopeSelected)}{tr}No{/tr} {$manyWord}{elseif !empty($group.own_list) && $group.heading}{tr}All{/tr} {$group.heading|escape}{else}{tr}All Content{/tr}{/if}{elseif count($tickedLabels) == 1}{$tickedLabels[0]|escape}{else}{count($tickedLabels)} {$manyWord}{/if}</span> <span class="caret"></span>
	</button>
	<ul class="dropdown-menu" style="padding:.4em 1em; max-height:26em; overflow:auto;">
		{foreach $group.items as $scopeKey => $scopeLabel}
			<li><label style="font-weight:normal; margin:.15em 0; white-space:nowrap;"><input type="checkbox" name="scope[]" value="{$scopeKey|escape}"{if !empty($scopeSelected) && in_array($scopeKey, $scopeSelected)} checked="checked"{/if} /> {$scopeLabel|escape}</label></li>
		{/foreach}
	</ul>
</div>
{/foreach}
{/strip}
{if empty($searchScopeScript)}
{assign var=searchScopeScript value=true scope=global}
<script>
(function () {ldelim}
	// a box with nothing ticked says "All ..." while nothing is ticked anywhere in its form, and "No ..." once something is ticked in another list
	function summarise(box) {ldelim}
		var form = box.closest('form') || document,
			any = form.querySelectorAll('.search-scope input[type=checkbox]:checked').length,
			ticked = box.querySelectorAll('input[type=checkbox]:checked'), btn = box.querySelector('.dropdown-toggle'), out = box.querySelector('.scope-summary');
		out.textContent = ticked.length === 0 ? btn.getAttribute(any === 0 ? 'data-all' : 'data-none') : (ticked.length === 1 ? ticked[0].parentNode.textContent.trim() : ticked.length + ' ' + btn.getAttribute('data-many'));
	{rdelim}
	// the button text is written by the template; this only keeps it right as boxes are ticked, in every box on the page (the search form and the search module can both be there, and this script is written once)
	function start() {ldelim}
		document.querySelectorAll('.search-scope').forEach(function (box) {ldelim}
			// ticking inside the list must not close it (a page click closes a Bootstrap dropdown)
			box.querySelector('.dropdown-menu').addEventListener('click', function (e) {ldelim} e.stopPropagation(); {rdelim});
			box.addEventListener('change', function () {ldelim} (box.closest('form') || document).querySelectorAll('.search-scope').forEach(summarise); {rdelim});
		{rdelim});
	{rdelim}
	if (document.readyState === 'loading') {ldelim} document.addEventListener('DOMContentLoaded', start); {rdelim} else {ldelim} start(); {rdelim}
{rdelim})();
</script>
{/if}
