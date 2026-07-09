  {if $op eq 'sync'}
    <div class="civicfg-actions">
      {if $canExport}<form method="post" action="{crmURL p='civicrm/admin/config-manager' q='reset=1&op=sync'}" {if $exportDependencyTypes|@count gt 0}data-civicfg-confirm-modal="1" data-civicfg-confirm-title="Export with Dependencies" data-civicfg-confirm-word="EXPORT" data-civicfg-confirm-button="Export" data-civicfg-confirm-message="The selected filter has related dependency types. Export will include those related YAML files too so the configuration can deploy safely." data-civicfg-confirm-warning="Export writes active CiviCRM configuration to YAML. Related dependency files will also be exported so the exported set stays deployable."{/if}>
        <input type="hidden" name="_action" value="export_write" />
        {foreach from=$selectedTypes item=type}<input type="hidden" name="type[]" value="{$type|escape}" />{/foreach}
        <button type="submit" class="button"><span>{ts}Export{/ts}</span></button>
      </form>{/if}
      {if $canImport}<a class="button" href="{crmURL p='civicrm/admin/config-manager' q='reset=1&op=import'}"><span>{ts}Import{/ts}</span></a>{/if}
      <form method="post" action="{crmURL p='civicrm/admin/config-manager' q='reset=1&op=sync'}">
        <input type="hidden" name="_action" value="validate_files" />
        {foreach from=$selectedTypes item=type}<input type="hidden" name="type[]" value="{$type|escape}" />{/foreach}
        <button type="submit" class="button"><span>{ts}Validate{/ts}</span></button>
      </form>
    </div>

    {if $validationResult}
      <details class="civicfg-panel civicfg-validation-panel" open="open">
        <summary>{ts}Validation Details{/ts}</summary>
        <div class="civicfg-panel-body">
          {if $validationResult.ok}
            <div class="messages status no-popup">{ts}YAML validation passed.{/ts}</div>
          {else}
            <div class="messages error no-popup">{ts}YAML validation found problems.{/ts}</div>
          {/if}

          {if $validationResult.errors|@count gt 0}
            <div class="messages error no-popup">
              <strong>{ts}Validation errors{/ts}</strong>
              <ul>
                {foreach from=$validationResult.errors item=error}
                  <li>
                    {if $error.type}<code>{$error.type|escape}</code>: {/if}
                    {$error.message|escape}
                  </li>
                {/foreach}
              </ul>
            </div>
          {/if}

          {foreach from=$validationResult.items item=item}
            {if $item.errors|@count gt 0 || $item.warnings|@count gt 0}
              <div class="civicfg-change-card">
                <h4>{$item.type|escape}</h4>

                {if $item.errors|@count gt 0}
                  <div class="messages error no-popup">
                    <strong>{ts}Errors{/ts}</strong>
                    <ul>
                      {foreach from=$item.errors item=error}
                        <li>
                          {if $error.file}<code>{$error.file|escape}</code>: {/if}
                          {$error.message|escape}
                        </li>
                      {/foreach}
                    </ul>
                  </div>
                {/if}

                {if $item.warnings|@count gt 0}
                  <div class="messages warning no-popup">
                    <strong>{ts}Warnings{/ts}</strong>
                    <ul>
                      {foreach from=$item.warnings item=warning}
                        <li>
                          {if $warning.file}<code>{$warning.file|escape}</code>: {/if}
                          {$warning.message|escape}
                        </li>
                      {/foreach}
                    </ul>
                  </div>
                {/if}
              </div>
            {/if}
          {/foreach}
        </div>
      </details>
    {/if}

    {if $summary.total_changes eq 0}
      <div class="messages status no-popup">{ts}No pending configuration changes.{/ts}</div>
    {else}
      <details class="civicfg-panel civicfg-summary-panel" open="open">
        <summary>{ts}Pending Changes{/ts}</summary>
        <div class="civicfg-panel-body">
          <p class="description">{ts}These are summary counts by config type. In CiviCRM means the active database has config not yet exported to YAML. In YAML means a YAML file exists for config that is missing from CiviCRM. Use Changed Files and Diff for field-level review.{/ts}</p>
          <div class="civicfg-type-lines">
            {foreach from=$allTypes item=row}
              {if $row.changedCount gt 0 || $row.newCount gt 0 || $row.missingCount gt 0}
                <div class="civicfg-type-line">
                  <strong>{$row.label|escape}</strong>
                  <code>{$row.type|escape}</code>
                  {if $row.changedCount gt 0}<span class="civicfg-badge warn">{$row.changedCount|escape} {ts}Changed{/ts}</span>{/if}
                  {if $row.newCount gt 0}<span class="civicfg-badge warn">{$row.newCount|escape} {ts}In CiviCRM{/ts}</span>{/if}
                  {if $row.missingCount gt 0}<span class="civicfg-badge warn">{$row.missingCount|escape} {ts}In YAML{/ts}</span>{/if}
                </div>
              {/if}
            {/foreach}
          </div>
        </div>
      </details>

      <details class="civicfg-panel civicfg-files-panel" open="open">
        <summary>{ts}Changed Files{/ts}</summary>
        <div class="civicfg-panel-body">
          <p class="description">{ts}Only files with differences are listed. Open Diff to review the changed fields before exporting or importing.{/ts}</p>
          <div class="civicfg-file-lines">
            {foreach from=$diffFiles item=file}
              <div class="civicfg-file-line">
                <code class="civicfg-file-code">{$file.path|escape}</code>
                <span class="civicfg-badge warn">{$file.status_label|escape}</span>
                <span>{$file.change_count|escape} {ts}Field Change(s){/ts}</span>
                <span class="civicfg-muted">{$file.type_label|escape}</span>
                <button type="button" class="button civicfg-line-button" data-civicfg-open="{$file.id|escape}"><span>{ts}Diff{/ts}</span></button>
              </div>
            {/foreach}
          </div>
        </div>
      </details>
    {/if}

    <div class="civicfg-actions">
      {if $canExport}<form method="post" action="{crmURL p='civicrm/admin/config-manager' q='reset=1&op=sync'}" {if $exportDependencyTypes|@count gt 0}data-civicfg-confirm-modal="1" data-civicfg-confirm-title="Export with Dependencies" data-civicfg-confirm-word="EXPORT" data-civicfg-confirm-button="Export" data-civicfg-confirm-message="The selected filter has related dependency types. Export will include those related YAML files too so the configuration can deploy safely." data-civicfg-confirm-warning="Export writes active CiviCRM configuration to YAML. Related dependency files will also be exported so the exported set stays deployable."{/if}>
        <input type="hidden" name="_action" value="export_write" />
        {foreach from=$selectedTypes item=type}<input type="hidden" name="type[]" value="{$type|escape}" />{/foreach}
        <button type="submit" class="button"><span>{ts}Export{/ts}</span></button>
      </form>{/if}
      {if $canImport}<a class="button" href="{crmURL p='civicrm/admin/config-manager' q='reset=1&op=import'}"><span>{ts}Import{/ts}</span></a>{/if}
    </div>
  {/if}
