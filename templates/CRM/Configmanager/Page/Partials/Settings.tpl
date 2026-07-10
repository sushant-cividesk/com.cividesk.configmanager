  {if $op eq 'settings'}
    <h3>{ts}Settings{/ts}</h3>
    <form class="civicfg-settings-form" method="post" action="{crmURL p='civicrm/admin/config-manager' q='reset=1&op=settings'}">
      <input type="hidden" name="_action" value="save_settings" />
      <table class="form-layout-compressed">
        <tr>
          <td class="label"><label for="sync_dir">{ts}Sync Directory{/ts}</label></td>
          <td>
            {if $syncDirLocked}
              <input type="text" class="crm-form-text huge" size="90" id="sync_dir" name="sync_dir_display" value="{$syncDir|escape}" disabled="disabled" />
              <input type="hidden" name="sync_dir" value="{$syncDir|escape}" />
              <div class="messages status no-popup civicfg-inline-message">{$syncDirLockMessage|escape}</div>
            {else}
              <input type="text" class="crm-form-text huge" size="90" id="sync_dir" name="sync_dir" value="{$syncDir|escape}" />
            {/if}
            <p class="description">{ts}Use one directory per CiviCRM build. Relative paths are resolved from the CMS project root. Absolute paths are accepted. The path must be a server-local directory writable by the web/PHP user; URLs are not accepted. Export creates the directory when the parent is writable. Example: civicrm-config or /var/www/html/civicrm-buildkit/build/drupal-civi/civicrm-config{/ts}</p>
          </td>
        </tr>
        <tr>
          <td class="label">{ts}Managed Types{/ts}</td>
          <td>
            <p class="description">{ts}Leave all unchecked to manage all supported types. Select types only if this site should manage a subset.{/ts}</p>
            <div class="civicfg-checkbox-grid">
              {foreach from=$allTypes item=row}
                <label><input type="checkbox" name="enabled_types[]" value="{$row.type|escape}" {if $enabledTypesMap[$row.type]}checked="checked"{/if} /> {$row.label|escape}</label>
              {/foreach}
            </div>
          </td>
        </tr>
        <tr>
          <td class="label"><label for="settings_allowlist">{ts}Settings Allowlist{/ts}</label></td>
          <td>
            <textarea id="settings_allowlist" name="settings_allowlist" class="crm-form-textarea">{$settingsAllowlist|escape}</textarea>
            <p class="description">{ts}Only these CiviCRM settings are exported. Add one setting name per line. Do not add secrets.{/ts}</p>
          </td>
        </tr>
        <tr>
          <td class="label"><label for="ignore_paths">{ts}Config Ignore{/ts}</label></td>
          <td>
            <textarea id="ignore_paths" name="ignore_paths" class="crm-form-textarea">{$ignorePaths|escape}</textarea>
            <p class="description">{ts}One relative YAML path or wildcard per line. Ignored files are skipped during diff, validate, export, and import. The Configuration Manager extension YAML is ignored by default to avoid self-management loops; remove that line only if you intentionally want to manage this extension state from YAML.{/ts}</p>
          </td>
        </tr>
      </table>
      <div class="crm-submit-buttons"><button type="submit" class="button"><span>{ts}Save{/ts}</span></button></div>
    </form>
  {/if}
