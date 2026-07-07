  {foreach from=$diffFiles item=file}
    <div class="civicfg-modal" id="{$file.id|escape}" aria-hidden="true" hidden="hidden">
      <div class="civicfg-modal-box">
        <div class="civicfg-modal-header">
          <strong>{$file.path|escape}</strong>
          <button type="button" class="civicfg-close" data-civicfg-close="1" aria-label="{ts}Close{/ts}">×</button>
        </div>
        <div class="civicfg-modal-body">
          <p>{ts}YAML file is the intended configuration from disk. Active CiviCRM is the current database state. Only changed fields are shown.{/ts}</p>
          {if $file.rows}
            <table class="civicfg-diff-table">
              <thead><tr><th>{ts}Field{/ts}</th><th>{ts}YAML File{/ts}</th><th>{ts}Active CiviCRM{/ts}</th></tr></thead>
              <tbody>
                {foreach from=$file.rows item=row}
                  <tr>
                    <td><strong>{$row.label|escape}</strong><br /><code>{$row.path|escape}</code></td>
                    <td class="civicfg-diff-old"><div class="civicfg-diff-value">{$row.old|escape}</div></td>
                    <td class="civicfg-diff-new"><div class="civicfg-diff-value">{$row.new|escape}</div></td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          {/if}
          <details>
            <summary>{ts}Show Diff Text{/ts}</summary>
            <pre class="civicfg-diff">{$file.diff|escape}</pre>
          </details>
        </div>
      </div>
    </div>
  {/foreach}
