# Configuration Manager

Configuration Manager is a CiviCRM extension that exports selected CiviCRM configuration to YAML, compares the active database with the YAML sync directory, and imports supported YAML changes back into CiviCRM.

- Extension key: `com.cividesk.configmanager`
- UI title: `Configuration Manager`
- Admin path: `civicrm/admin/config-manager`
- File format: YAML
- Current build: read from `info.xml`; this ZIP is `0.1.0-alpha41-core`
- Supported CiviCRM target: 5.x and 6.x

For release-by-release history, see `CHANGELOG.md`. For manual QA and round-trip checks, see `docs/TESTING.md`. Update the changelog and any affected current-behavior docs whenever a functional change is made.

## Purpose

The extension is intended to provide a Drupal-style configuration workflow for CiviCRM:

1. Export configuration from CiviCRM to YAML.
2. Review and commit YAML changes in Git.
3. Move the YAML directory between environments.
4. Preview and import supported YAML changes into CiviCRM.

The YAML directory is treated as the deployable source of truth for supported configuration types. This extension is still alpha software. Import can now create, update, and delete supported records, but only after preview and explicit confirmation.

## Current UI

The admin UI has four tabs.

### Synchronize

Shows the current difference between active CiviCRM configuration and YAML files.

Available actions:

- `Export` writes active CiviCRM changes to YAML. If a temporary type filter is active, related dependency-sensitive types are included automatically and the filter is cleared after export so the next Synchronize view shows the full managed status.
- `Import` opens an import preview for supported YAML-to-CiviCRM changes.
- `Validate` checks YAML structure and handler compatibility.
- `Diff` shows field-level details for a changed file.

### Import

Reviews YAML files in the sync directory and applies supported changes to CiviCRM.

Current import behavior:

- Supported handlers treat YAML as the source of truth.
- Import can create records that exist in YAML but not in CiviCRM.
- Import can update records that differ between YAML and CiviCRM.
- Import can delete supported records that exist in CiviCRM but not in YAML. Actual imports apply create/update first and then delete missing records in reverse dependency order where supported.
- The UI uses a confirmation modal before applying import changes. The user must review the warning and type `IMPORT`.
- CiviCRM may assign a new numeric ID when a deleted record is recreated from YAML; dependencies should rely on stable machine names wherever possible.
- Unsupported handlers are shown as not ready instead of applying partial changes.

The Import tab also supports uploading a single YAML file or a ZIP archive into the sync directory before previewing changes.

### Export

Exports active CiviCRM configuration to YAML.

Available options:

- Full export to the sync directory.
- ZIP download of the current sync directory.
- Single-file preview.
- Single-file YAML download.

### Settings

Controls the sync directory and the managed type filter.

Settings include:

- Sync Directory
- Managed Types
- Settings Allowlist
- Config Ignore

Config Ignore accepts one relative YAML path or wildcard per line. Ignored files are skipped during diff, validate, export, and import. `extensions/com.cividesk.configmanager.yml` is ignored by default to avoid self-management loops; remove it only if you intentionally want this extension to manage its own extension status.

Leaving Managed Types unchecked means all supported handlers are managed. If Managed Types is changed to a subset after YAML files already exist, the old YAML files are left on disk but ignored by status, diff, export, validate, and import until that type is enabled again. The extension does not delete those files automatically.

## Sync Directory

The Sync Directory must be a server-local filesystem path. It is not a URL and not a desktop/Finder path.

Recommended default:

```text
civicrm-config
```

Absolute path example:

```text
/var/www/html/civicrm-buildkit/build/drupal-civi/civicrm-config
```

Rules:

- Relative paths resolve from the CMS/project root where possible.
- `../civicrm-config` is treated as the legacy form of `civicrm-config`.
- Export creates the sync directory if the parent directory is writable by the web/PHP user.
- URL-style values such as `https://...` are rejected.
- Do not point the sync directory at a public upload directory containing live files or secrets.

### Code-owned Sync Directory

For environment-specific deployments, define the path in `civicrm.settings.php`:

```php
global $civicrm_setting;
$civicrm_setting['domain']['civicfg_sync_dir'] = '/var/www/html/civicrm-buildkit/build/drupal-civi/civicrm-config';
```

When this setting is present, the UI shows the Sync Directory as locked and does not allow UI edits to override the code-defined value.

## API4 and CLI automation

The UI and CLI wrappers use the same API4 backend.

```bash
cv api4 ConfigManager.status
cv api4 ConfigManager.listTypes
cv api4 ConfigManager.diff
cv api4 ConfigManager.validate
cv api4 ConfigManager.export dryRun=1
cv api4 ConfigManager.export dryRun=0
cv api4 ConfigManager.import dryRun=1 type=option-groups
cv api4 ConfigManager.import dryRun=0 yes=1 type=option-groups
```

Preferred CLI commands are available under the extension `bin/` directory:

| Main command | Alias | Purpose |
|---|---|---|
| `bin/config-export` | `bin/ce` | Export active CiviCRM configuration to YAML. |
| `bin/config-import` | `bin/ci` | Preview or apply YAML configuration import. |
| `bin/config-diff` | `bin/cdf` | Show YAML vs active CiviCRM differences. |
| `bin/config-validate` | `bin/cval` | Validate YAML and dependency safety. |
| `bin/civicfg` | `bin/cvcfg` | General wrapper for all operations. |

Examples:

```bash
ext/com.cividesk.configmanager/bin/ce --write
ext/com.cividesk.configmanager/bin/ce --type searchkit-saved-searches --write
ext/com.cividesk.configmanager/bin/ci --dry-run
ext/com.cividesk.configmanager/bin/ci --yes
ext/com.cividesk.configmanager/bin/cdf
ext/com.cividesk.configmanager/bin/cval
ext/com.cividesk.configmanager/bin/civicfg ce --write
ext/com.cividesk.configmanager/bin/civicfg ci --yes
```

Future work: make `civicfg` globally available immediately after extension install.

## Managed configuration types

Current export/diff/validate support includes:

- Extensions
- Option Groups and Values
- Contact Types
- Relationship Types
- Location Types
- Financial Types
- Payment Processors, sanitized
- Custom Groups and Fields
- CiviCRM Settings Allowlist
- Message Templates
- Dedupe Rules
- Scheduled Jobs
- SearchKit Saved Searches
- SearchKit Displays
- FormBuilder Afforms
- Site Tokens, when `SiteToken` API4 exists
- Contributed/custom extension settings and extension-provided config, bundled under each extension YAML file when safely discoverable
- CiviRules, alpha support when CiviRules API4 entities exist

Current create/update import support includes:

- Extensions, conservative install/enable/disable only. Extension status changes exported from CiviCRM can be imported back from YAML, including disable, when the extension code is available. Uninstall/delete is not performed, and Configuration Manager skips disabling itself so the import can finish safely.
- Option Groups and Values
- Contact Types
- Relationship Types
- Location Types
- Financial Types
- Custom Groups and Fields
- CiviCRM Settings Allowlist
- Message Templates
- Dedupe Rules
- Scheduled Jobs
- SearchKit Saved Searches
- SearchKit Displays
- FormBuilder Afforms
- Site Tokens, when `SiteToken` API4 exists
- Contributed/custom extension settings and extension-provided config, bundled under each extension YAML file when safely discoverable
- CiviRules, alpha support when CiviRules API4 entities exist

Payment Processors remain export/diff only because exported data is sanitized and may omit environment-specific or secret values.

## YAML layout

Most stable config types are stored as collection files, for example `extensions/extensions.yml` or `option-groups/*.yml`. High-churn config types are stored as one YAML file per item:

- `searchkit/saved-searches/<name>.yml`
- `searchkit/displays/<saved-search>__<display>.yml` for new exports, with older `<display>.yml` files still accepted
- `formbuilder/afforms/<name>.yml`
- `scheduled-jobs/<name>.yml`
- `message-templates/system/<name>.yml`
- `message-templates/user/<name>.yml`
- `custom-data/groups/<name>.yml`
- `extensions/<extension-key>.yml`

Each split file uses `type: <handler>.item`, stores the editable record under `item`, and includes a `dependencies` section where dependencies are detectable. Extension-owned settings and extension-provided API config are bundled into `extensions/<extension-key>.yml` under `settings` and `config` instead of creating a separate file tree. Collection files use `type: <handler>.collection` and an `items` list. Existing collection files for these handlers are still accepted for import, but the current export format rewrites them as split files.

The export manifest is written to `manifest.yml`. Its `exported_with` value is read from `info.xml` at runtime, so the extension version only needs to be changed in `info.xml` for generated export metadata.

## Safety rules

- Import can delete supported records that are present in CiviCRM but missing from YAML. Delete actions are shown as destructive actions in the import preview. Review the import preview before applying.
- Machine names are treated as identities.
- Suspected machine-name renames are warned and skipped.
- Dependency metadata is validated where available. Missing managed YAML dependencies are treated as import-blocking errors to avoid broken relationships.
- Large scalar values such as HTML message-template bodies are truncated in UI previews; the YAML and field-level diff still carry the complete value.
- Payment processor secrets are never exported.
- Live transactional data is never exported.
- ZIP upload only stages YAML files under the configured sync directory.
- SearchKit Saved Searches, SearchKit Displays, FormBuilder Afforms, and Scheduled Jobs are exported as one YAML file per item so small changes are easier to review.
- Split item files include dependency metadata where the extension can detect it. SearchDisplay files declare their SavedSearch dependency; SavedSearch files declare related SearchDisplays; Afform files declare referenced SearchKit displays where detectable.
- Custom field exports store `option_group_name` instead of numeric `option_group_id` where possible, so YAML is safer across environments. Legacy YAML with numeric option group IDs is still accepted during validation/import.
- Option values are validated using the full option value entry, not just the `name` field, because some core CiviCRM option groups legitimately reuse option value names with different stored values.
- Config Ignore can be used to intentionally leave environment-specific YAML files unmanaged.
- Temporary filtered exports include related dependency-sensitive config types automatically. For example, SearchKit Saved Searches, SearchKit Displays, and FormBuilder Afforms are exported together because they commonly reference each other. Custom Groups and Fields can include Option Groups and Contact Types. Relationship Types can include Contact Types. The UI warns before exporting a filtered set when dependency types will be added, and the confirmation uses `EXPORT` to distinguish it from destructive imports.
- After a filtered export, the UI clears the temporary filter and reloads the full managed diff to avoid showing a misleading In Sync state for only the filtered subset. POST actions redirect after completion, so browser refresh does not resubmit export/import forms.

## System status integration

The extension implements a CiviCRM status check.

The status report warns when:

- The initial YAML export has not been done.
- The sync directory exists but has no YAML files.
- CiviCRM and YAML have pending differences.

When the database and YAML are in sync, the status check reports an informational in-sync notice. CiviCRM displays these checks anywhere normal system-check notices are shown, including the status report page and admin login notification flow.

## Permissions

The extension defines granular permissions:

- `access CiviCRM configuration manager`
- `export CiviCRM configuration`
- `import CiviCRM configuration`
- `administer CiviCRM configuration manager`

Users with `administer CiviCRM` are treated as superusers for this extension.

See `docs/PERMISSIONS.md` for details.

## Development notes

Important source areas:

- `CRM/Configmanager/Page/Main.php` - thin CiviCRM page wrapper.
- `Civi/Api4/*` - API4 facade and actions.
- `Civi/ConfigManager/Service/*` - orchestration and handler registry.
- `Civi/ConfigManager/Handler/*` - config-type handlers.
- `Civi/ConfigManager/Storage/YamlFileStorage.php` - YAML file storage.
- `Civi/ConfigManager/UI/*` - UI request, presenter, transfer, permissions, assets.
- `templates/CRM/Configmanager/Page/*.tpl` - Smarty templates and partials.
- `css/configmanager.css` - scoped UI styles.
- `css/configmanager-preload.css` - tiny critical preload stylesheet.
- `js/configmanager.js` - vanilla JavaScript interactions.

See `docs/ARCHITECTURE.md` for the implementation structure and `docs/IMPLEMENTATION_PLAN.md` for current technical decisions.


## Alpha37 Notes

- Added Config Ignore to skip selected YAML files from diff, validate, export, and import. This is useful for environment-specific configuration and for avoiding self-management of this extension.
- `extensions/com.cividesk.configmanager.yml` is ignored by default. Exporting this extension's own status can create a circular dependency because the extension must stay enabled to finish imports.
- SearchDisplay import now uses `saved_search_id.name + name` as the stable identity. This avoids duplicate `Table` display failures when a target site already has extension-provided SearchKit displays.
- New SearchDisplay exports include the SavedSearch name in the filename to avoid collisions. Older display filenames are still read.
- Already-existing records are treated as warnings when they can be matched safely instead of as hard errors.

## Alpha36 Notes

- Extension status is now exported as one YAML file per extension key under `extensions/`, instead of one large collection file. This makes future extension-specific config files easier to review and group.
- Import/export/validate/settings forms show a full-page progress overlay and disable controls while the request is running, which helps prevent double submits.
- Import failures are saved across the post/redirect/get flow so the next page can show the exact handler/file error instead of only a generic toast.
- Site Tokens now have an optional handler. It exports/imports `SiteToken` API4 records when that API4 entity is available and clearly blocks import when the target site lacks the provider.
- Custom Groups and Fields now support YAML-source deletes for missing custom fields and non-reserved missing custom groups. Option group references are resolved by `option_group_name` where possible.
- CiviRules has an alpha handler for common CiviRules API4 entities when the CiviRules extension exposes them. This still needs real-world testing with rule triggers, conditions, actions, and extension-provided rule components.


### CLI wrappers

The extension includes dedicated command wrappers in `bin/` for teams that prefer short commands over raw API4 calls. Run them from a bootstrapped CiviCRM project where `cv` is available.

```bash
ext/com.cividesk.configmanager/bin/config-export --write
ext/com.cividesk.configmanager/bin/ce --type searchkit-saved-searches --write
ext/com.cividesk.configmanager/bin/config-import --dry-run
ext/com.cividesk.configmanager/bin/ci --yes
ext/com.cividesk.configmanager/bin/config-diff
ext/com.cividesk.configmanager/bin/config-validate
```

The wrappers call the same API4 backend as the UI, so dependency expansion, Config Ignore, validation, and import safety rules are shared.

### Config Ignore

Config Ignore accepts one relative YAML path or wildcard per line. Ignored files are skipped during diff, validate, export, import, single-file preview, and ZIP download. Do not ignore a YAML file that is a dependency of a non-ignored YAML file. Validation will show a dependency warning or error when it can detect this situation.

`extensions/com.cividesk.configmanager.yml` is ignored by default to avoid self-management loops while the extension is running an import.

### Environment workflow

The safest target workflow is one site codebase moving configuration between its own environments: dev, stage, and production. Cross-site imports are possible but require extra review because extensions, sample data, IDs, and contributed-extension defaults can differ between sites.



## Alpha 41 Notes

- Removed the separate `extension-config` and `extension-settings` managed types from the registry to prevent hundreds of duplicate YAML files.
- Bundled safely discoverable contributed/custom extension settings and extension API config under each `extensions/<extension-key>.yml` file.
- Generic extension config discovery now skips CiviCRM core component extensions and already-managed core handlers so operational data such as line items, events, financial accounts, SearchKit, and FormBuilder is not duplicated.
- Added option-value delete/revert support for non-reserved option values that exist in CiviCRM but are missing from YAML. Reserved option values are still skipped with a warning.
- Import summary counts now include nested option value, bundled extension setting, and bundled extension config create/update/delete results.

## Alpha 40 Notes

- Added generic contributed/custom extension support instead of hard-coded handlers for individual extensions.
- Generic Extension Entity Config discovers installed extension API4/APIv3 entities and exports records with stable identities under `extension-config/<extension>/<api>/<entity>/<item>.yml`.
- Generic Extension-specific Settings discovers non-secret settings from Setting metadata and installed-extension namespaces; password/secret/token/API-key style names are blocked.
- Dependency validation now gives clearer messages when required YAML is missing or when old YAML still contains local numeric IDs.
- If a provider extension/API entity is unavailable on the target site, validation/import reports the missing provider instead of fataling.

## Alpha 39 Notes

- Config Ignore is applied consistently to diff, validate, import, export, single-file preview, and ZIP download. Ignored DB-only records are hidden from Synchronize when their generated YAML path matches an ignore rule.
- Saving Config Ignore now checks for detectable non-ignored YAML files that depend on ignored YAML files and warns the administrator.
- CLI aliases are available as `config-export`/`ce`, `config-import`/`ci`, `config-diff`/`cdf`, `config-validate`/`cval`, and `civicfg`/`cvcfg`. Use `-h` or `--help` for usage, and `-y`/`--yes` for import apply.
- UI compatibility styles were adjusted so buttons and panels render more consistently across CiviCRM core themes.
