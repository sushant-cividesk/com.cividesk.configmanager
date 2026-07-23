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
          <td class="label">{ts}Site Identifier{/ts}</td>
          <td>
            <code class="civicfg-site-id">{$siteId|escape}</code>
            <p class="description">{ts}Generated automatically and stored in CiviCRM settings. A cloned dev/stage/prod database keeps the same identifier, so normal same-site environment sync works without manual setup. A separate site receives a different identifier and is blocked from import unless experimental cross-site import is enabled below.{/ts}</p>
          </td>
        </tr>
        <tr>
          <td class="label">{ts}Cross-site Import{/ts}</td>
          <td>
            <label class="civicfg-experimental"><input type="checkbox" name="allow_cross_site_import" value="1" {if $allowCrossSiteImport}checked="checked"{/if} /> {ts}Experimental: allow reviewed cross-site import when the manifest site identifier does not match this site{/ts}</label>
            <p class="description">{ts}Keep this disabled for normal dev/stage/prod sync. Enable only when intentionally migrating reviewed YAML between different sites. Validation still runs before import, and import remains manual.{/ts}</p>
          </td>
        </tr>
        <tr>
          <td class="label">{ts}Managed Types{/ts}</td>
          <td>
            <p class="description">{ts}Leave all unchecked to manage all supported types. Select types only if this site should manage a subset.{/ts}</p>
            <div class="civicfg-type-group">
              <h4>{ts}Standard managed types{/ts}</h4>
              <div class="civicfg-checkbox-grid">
                {foreach from=$allTypes item=row}
                  {if !$row.virtual}
                    <label class="civicfg-type-option"><input type="checkbox" name="enabled_types[]" value="{$row.type|escape}" {if $enabledTypesMap[$row.type]}checked="checked"{/if} /> <span class="civicfg-type-text"><span class="civicfg-type-name">{$row.label|escape}</span><small>{$row.directory|escape}</small></span></label>
                  {/if}
                {/foreach}
              </div>
            </div>
            <div class="civicfg-type-group civicfg-type-group-extension">
              <h4>{ts}Extension-owned managed config{/ts}</h4>
              <p class="description">{ts}Generated automatically for enabled contrib/custom extensions when their API records can be exported and imported safely.{/ts}</p>
              <div class="civicfg-checkbox-grid">
                {foreach from=$allTypes item=row}
                  {if $row.virtual}
                    <label class="civicfg-type-option civicfg-type-option-virtual"><input type="checkbox" name="enabled_types[]" value="{$row.type|escape}" {if $enabledTypesMap[$row.type]}checked="checked"{/if} /> <span class="civicfg-type-text"><span class="civicfg-type-name">{$row.label|escape}</span>{if $row.provider}<small>{$row.provider|escape}</small>{/if}</span></label>
                  {/if}
                {/foreach}
              </div>
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
          <td class="label"><label for="ignore_values">{ts}Config Ignore Values{/ts}</label></td>
          <td>
            <textarea id="ignore_values" name="ignore_values" class="crm-form-textarea">{$ignoreValues|escape}</textarea>
            <p class="description">{ts}Optional field-level ignore rules. Use one rule per line in the format path/to/file.yml:dot.path. Wildcards are allowed in the YAML path and * is allowed as a path segment. Example: settings/civicrm.settings.yml:items.theme_backend or extensions/*.yml:settings.environment_color. Ignored values are removed before diff, export, import, and single-file preview so each environment can keep local values while the rest of the file remains managed.{/ts}</p>
          </td>
        </tr>
        <tr>
          <td class="label"><label for="ignore_paths">{ts}Config Ignore{/ts}</label></td>
          <td>
            <textarea id="ignore_paths" name="ignore_paths" class="crm-form-textarea">{$ignorePaths|escape}</textarea>
            <p class="description">{ts}One relative YAML path or wildcard per line. Ignored files are skipped during diff, validate, export, import, single-file preview, and ZIP download. The Configuration Manager extension YAML is ignored by default to avoid self-management loops; remove that line only if you intentionally want to manage this extension state from YAML. Do not ignore files that are required dependencies of non-ignored YAML files; validation will warn/block when it can detect that risk.{/ts}</p>
          </td>
        </tr>
      </table>
      <div class="crm-submit-buttons"><button type="submit" class="button"><span>{ts}Save{/ts}</span></button></div>
    </form>
  {/if}
