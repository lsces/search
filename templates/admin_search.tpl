{strip}
{jstabs}
	{jstab title="Search Settings"}
		{formfeedback hash=$feedback}

		{form legend="Search Settings"}
			<input type="hidden" name="page" value="{$page}" />

			{foreach from=$formSearchToggles key=item item=output}
				<div class="form-group">
					{formlabel label=$output.label for=$item}
					{forminput}
						{html_checkboxes name="$item" values="y" checked=$gBitSystem->getConfig($item) labels=false id=$item}
						{formhelp note=$output.note page=$output.page|default:''}
					{/forminput}
				</div>
			{/foreach}

			{foreach from=$formSearchInts key=item item=output}
				<div class="form-group">
					{formlabel label=$output.label for=$item}
					{forminput}
						<input size="5" type="text" name="{$item}" id="{$item}" value="{$output.value|escape}" />
						{formhelp note=$output.note page=$output.page|default:''}
					{/forminput}
				</div>
			{/foreach}

			<div class="form-group submit">
				<input type="submit" name="store_prefs" value="{tr}Change preferences{/tr}" />
			</div>
		{/form}
	{/jstab}

	{jstab title="Searchable Content"}
		{form legend="Searchable Content"}
			<input type="hidden" name="page" value="{$page|default:''}" />
			<p class="help-block">{tr}Packages are listed in the order and with the names set on the Themes Menus page. Tick a package to make its content searchable; a package whose menu is hidden is listed too, and is off until ticked. Inside a package each type of content has its own tick (a blog site may only want its posts searched), in the order and with the name given here (blank position = alphabetical). Which items a person may see is still decided by the access rules, so a type they cannot view never appears in their search.{/tr}</p>
			<table class="table table-condensed">
				<thead><tr><th>{tr}Package{/tr}</th><th>{tr}Searchable{/tr}</th><th>{tr}Content type{/tr}</th><th>{tr}Shown in search as{/tr}</th><th>{tr}Position{/tr}</th></tr></thead>
				{foreach from=$searchSections key=section item=data}
					<tbody>
						<tr class="active">
							<td colspan="5">
								<input type="hidden" name="search_section[{$section}]" value="1" />
								<label><input type="checkbox" name="search_on[{$section}]" value="y"{if $data.searchable} checked="checked"{/if} /> <strong>{$data.title|escape}</strong></label>
								<small class="text-muted">{$section}{if $data.menu_hidden} &mdash; {tr}menu hidden{/tr}{/if}{if !empty($data.by_scope)} &mdash; {tr}searched by gallery, from its own list in the search box{/tr}{/if}</small>
							</td>
						</tr>
						{foreach from=$data.types key=guid item=type}
							<tr>
								<td></td>
								<td><input type="checkbox" name="search_type_on[{$guid}]" value="y"{if $type.on} checked="checked"{/if} /></td>
								<td>{$type.name|escape} <small class="text-muted">{$guid}</small></td>
								<td><input type="text" size="30" name="search_type_text[{$guid}]" value="{$type.text|escape}" /></td>
								<td><input type="text" size="3" name="search_type_position[{$guid}]" value="{$type.position|escape}" /></td>
							</tr>
						{/foreach}
					</tbody>
				{/foreach}
			</table>

			<div class="form-group submit">
				<input type="submit" name="store_content" value="{tr}Change preferences{/tr}" />
			</div>
		{/form}

	{/jstab}

	{jstab title="Delete / Rebuild Index"}
		{formfeedback hash=$feedback}

		{form legend="Delete / Rebuild Index"}
			<input type="hidden" name="page" value="{$page}" />
			<div class="form-group">
				{formlabel label="Clear Searchwords:" for="clearss"}
				{forminput}
					<input type="submit" name="del_searchwords" value="{tr}Clear Searchwords{/tr}"/>
					{formhelp note="This clears out the cache of recently searched for terms and the syllables derived from those search terms."}
				{/forminput}
			</div>
			<div class="form-group">
				{formfeedback warning='Deleting the index will render search useless until content is reindexed.'}
				{formfeedback warning='Rebuilding the Index <strong>could take a long time</strong> depending on how much content you have. If this is a large site, you may want to do this during off-peak hours.'}
				{formfeedback warning='Note: timeout setting will automatically be set to 5 minutes for reindexing operations.'}
			</div>
			<div class="form-group">
				{formlabel label="Perform action on:" for="where"}
				{forminput}
					<select name="where" id="where">
						{html_options options=$contentTypes}
					</select>
					{formhelp note="Limit indexing action to the selected Liberty package"}
				{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Index what is missing:" for="index_unindexed"}
				{forminput}
					<input type="submit" name="index_unindexed" id="index_unindexed" value="{tr}Index Unindexed Only{/tr}"/>
					{formhelp note="Indexes only the selected items that are not in the index yet, and deletes nothing. Use this for content stored with its text added afterwards, such as an uploaded PDF."}
				{/forminput}
			</div>
			<div class="form-group submit">
				<input type="submit" name="del_index" value="{tr}Delete Index Only{/tr}"/>
				
				<input type="submit" name="del_index_reindex" value="{tr}Delete and Rebuild Index{/tr}"/>
			</div>
		{/form}
	{/jstab}
{/jstabs}
{/strip}
