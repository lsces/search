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
			{foreach from=$formSearchTypeToggles key=item item=output}
				<div class="form-group">
					{formlabel label=$output.label for=$item}
					{forminput}
						{html_checkboxes name="$item" values="y" checked=$gBitSystem->getConfig($item) labels=false id=$item}
						{formhelp note=$output.note page=$output.page|default:''}
					{/forminput}
				</div>
			{/foreach}

			<input type="hidden" name="page" value="{$page|default:''}" />
			<div class="form-group">
				{formlabel label="Searchable Content"}
				{forminput}
					{html_checkboxes options=$formSearchable.guids value=y name=searchable_content separator="<br />" checked=$formSearchable.checked|default:false}
					{formhelp note="Here you can select what content can be searched."}
				{/forminput}
			</div>

			<div class="form-group">
				{formlabel label="Group types by package"}
				{forminput}
					<p class="help-block">{tr}In the Limit Search list each package's types are listed under a heading, the package's menu name. A package that adds its types to another package's menu can be listed under that package's heading instead.{/tr}</p>
					<table class="table table-condensed">
						<thead><tr><th>{tr}Package{/tr}</th><th>{tr}List its types under{/tr}</th></tr></thead>
						<tbody>
							{foreach from=$searchPackages key=pkg item=label}
								<tr>
									<td>{$label|escape} <small class="text-muted">{$pkg}</small></td>
									<td>
										<select name="search_under[{$pkg}]">
											<option value="">{tr}its own heading{/tr}</option>
											{foreach from=$searchPackages key=other item=otherLabel}
												{if $other ne $pkg}<option value="{$other}"{if $searchUnder.$pkg eq $other} selected="selected"{/if}>{$otherLabel|escape}</option>{/if}
											{/foreach}
										</select>
									</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				{/forminput}
			</div>

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
