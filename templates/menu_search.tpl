{strip}
{if !empty($packageMenuTitle)}<a class="dropdown-toggle" data-toggle="dropdown" href="#"> {tr}{$packageMenuTitle}{/tr} <b class="caret"></b></a>{/if}
<ul class="{$packageMenuClass}">
<li><a class="item" href="{$smarty.const.SEARCH_PKG_URL}index.php">{biticon ipackage="icons" iexplain=Search iname="edit-find" ilocation=menu}</a></li>

</ul>
{/strip}
