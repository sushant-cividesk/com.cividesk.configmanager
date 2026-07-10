# Configuration Manager

Configuration Manager is a CiviCRM extension that exports selected CiviCRM configuration to YAML, compares the active database with the YAML sync directory, and imports supported YAML changes back into CiviCRM.

- Extension key: `com.cividesk.configmanager`
- UI title: `Configuration Manager`
- Admin path: `civicrm/admin/config-manager`
- File format: YAML
- Current build: read from `info.xml`; this ZIP is `0.1.0-alpha34-core`
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

## API4 and automation

The supported command/automation surface is API4 through `cv`.

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

The custom `cv civicfg:*` CLI wrapper is paused for the current alpha series. Keep operational workflows on `cv api4 ConfigManager.*` until the API4 engine and UI behavior are stable enough for a thin CLI alias.

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

Payment Processors remain export/diff only because exported data is sanitized and may omit environment-specific or secret values.

## YAML layout

Most stable config types are stored as collection files, for example `extensions/extensions.yml` or `option-groups/*.yml`. High-churn config types are stored as one YAML file per item:

- `searchkit/saved-searches/<name>.yml`
- `searchkit/displays/<name>.yml`
- `formbuilder/afforms/<name>.yml`
- `scheduled-jobs/<name>.yml`
- `message-templates/system/<name>.yml`
- `message-templates/user/<name>.yml`
- `custom-data/groups/<name>.yml`

Each split file uses `type: <handler>.item`, stores the editable record under `item`, and includes a `dependencies` section where dependencies are detectable. Collection files use `type: <handler>.collection` and an `items` list. Existing collection files for these handlers are still accepted for import, but the current export format rewrites them as split files.

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
