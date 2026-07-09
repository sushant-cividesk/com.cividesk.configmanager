  {if $op neq 'settings'}
    <details class="civicfg-filter" {if $selectedTypes|@count gt 0}open="open"{/if}>
      <summary>{ts}Filter Config Types{/ts}</summary>
      <div class="civicfg-filter-body">
        <form method="get" action="{crmURL p='civicrm/admin/config-manager'}">
          <input type="hidden" name="reset" value="1" />
          <input type="hidden" name="op" value="{$op|escape}" />
          <p class="description">{ts}Leave all unchecked for all managed types. Select one or more types to narrow the preview.{/ts}</p>
          {if $selectedTypes|@count gt 0 and $exportDependencyTypes|@count gt 0}
            <div class="messages warning no-popup">
              {ts}Dependency-aware export/import will also include related config types:{/ts}
              {foreach from=$exportDependencyTypeLabels key=depType item=depLabel}<code>{$depLabel|escape}</code> {/foreach}
            </div>
          {/if}
          <div class="civicfg-checkbox-grid">
            {foreach from=$allTypes item=row}
              <label><input type="checkbox" name="type[]" value="{$row.type|escape}" {if $selectedTypesMap[$row.type]}checked="checked"{/if} /> {$row.label|escape}</label>
            {/foreach}
          </div>
          <div class="civicfg-actions">
            <button type="submit" class="button"><span>{ts}Apply{/ts}</span></button>
            <a class="button" href="{crmURL p='civicrm/admin/config-manager' q="reset=1&op=$op"}"><span>{ts}Clear{/ts}</span></a>
          </div>
        </form>
      </div>
    </details>
  {/if}
