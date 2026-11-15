{* The "Search in" list: ticks for the areas to search, none ticked meaning all content. A native select takes one value, so this is a Bootstrap dropdown
   of tick boxes (scope[]), the same control the menu is. Needs $searchScopes (SearchLib::getScopes()) and $scopeSelected (keys already ticked). *}
{strip}
{* the content types in one list; a provider's scopes (the media galleries) are a list of their own, headed by the provider's name *}
{foreach $searchScopes as $group}
<div class="dropdown search-scope" style="display:inline-block;">
	<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-all="{if !empty($group.own_list) && $group.heading}{tr}All{/tr} {$group.heading|escape}{else}{tr}All Content{/tr}{/if}" data-many="{tr}areas{/tr}">
		<span class="scope-summary">{tr}All Content{/tr}</span> <span class="caret"></span>
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
	function summarise(box) {ldelim}
		var ticked = box.querySelectorAll('input[type=checkbox]:checked'), btn = box.querySelector('.dropdown-toggle'), out = box.querySelector('.scope-summary');
		out.textContent = ticked.length === 0 ? btn.getAttribute('data-all') : (ticked.length === 1 ? ticked[0].parentNode.textContent.trim() : ticked.length + ' ' + btn.getAttribute('data-many'));
	{rdelim}
	document.querySelectorAll('.search-scope').forEach(function (box) {ldelim}
		// ticking inside the list must not close it (a page click closes a Bootstrap dropdown)
		box.querySelector('.dropdown-menu').addEventListener('click', function (e) {ldelim} e.stopPropagation(); {rdelim});
		box.addEventListener('change', function () {ldelim} summarise(box); {rdelim});
		summarise(box);
	{rdelim});
{rdelim})();
</script>
{/if}
