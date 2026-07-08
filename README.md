# Configuration Manager

Configuration Manager is a CiviCRM extension that exports selected CiviCRM configuration to YAML, compares the active database with the YAML sync directory, and imports supported YAML changes back into CiviCRM.

- Extension key: `com.cividesk.configmanager`
- UI title: `Configuration Manager`
- Admin path: `civicrm/admin/config-manager`
- File format: YAML
- Current build: `0.1.0-alpha26-core`
- Supported CiviCRM target: 5.x and 6.x

For release-by-release history, see `CHANGELOG.md`.

## Purpose

The extension is intended to provide a Drupal-style configuration workflow for CiviCRM:

1. Export configuration from CiviCRM to YAML.
2. Review and commit YAML changes in Git.
3. Move the YAML directory between environments.
4. Preview and import supported YAML changes into CiviCRM.

The YAML directory is treated as the deployable source of truth for supported configuration types. This extension is still alpha software, so imports remain intentionally conservative.

## Current UI

The admin UI has four tabs.

### Synchronize

Shows the current difference between active CiviCRM configuration and YAML files.

Available actions:

- `Export` writes active CiviCRM changes to YAML.
- `Import` opens an import preview for supported YAML-to-CiviCRM changes.
- `Validate` checks YAML structure and handler compatibility.
- `Diff` shows field-level details for a changed file.

### Import

Reviews YAML files in the sync directory and applies supported create/update changes to CiviCRM.

Current import behavior:

- Imports are non-destructive in this alpha.
- Missing YAML does not delete existing CiviCRM records.
- Records that exist only in CiviCRM are left unchanged.
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

Leaving Managed Types unchecked means all supported handlers are managed.

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

- Extensions, conservative install/enable/disable only
- Option Groups and Values
- Contact Types
- Relationship Types
- Location Types
- Dedupe Rules
- Scheduled Jobs
- SearchKit Saved Searches
- SearchKit Displays
- FormBuilder Afforms

Other handlers may export and diff but still show as not ready for import.

## Safety rules

- Import does not delete records in the current alpha series.
- Machine names are treated as identities.
- Suspected machine-name renames are warned and skipped.
- Payment processor secrets are never exported.
- Live transactional data is never exported.
- ZIP upload only stages YAML files under the configured sync directory.

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
