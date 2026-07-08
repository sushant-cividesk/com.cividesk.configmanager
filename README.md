# Configuration Manager

CiviCRM Configuration Manager is a CiviCRM extension for Drupal-style configuration management.

- Extension key: `com.cividesk.configmanager`
- UI title: `Configuration Manager`
- Admin path: `civicrm/admin/config-manager`
- File format: YAML
- Phase 1 target: CiviCRM 5.x and 6.x

## What it does

The extension exports supported CiviCRM configuration from the active database into YAML files in a configured sync directory. The YAML directory can be committed to Git or moved between environments. The UI then compares the active database against the YAML files and lets an administrator export database changes or import supported YAML changes.

This follows the same workflow idea as Drupal configuration management:

1. Export configuration to files.
2. Review the file changes.
3. Move or commit the files.
4. Import the files into another environment.

## Current UI

The admin UI has four main tabs.

### Synchronize

Shows pending differences between the active CiviCRM database and YAML files.

Actions:

- `Export` writes active database changes to YAML.
- `Import` opens a preview of YAML changes that can be applied to CiviCRM.
- `Validate` checks YAML files for format/handler issues.
- `Diff` opens a field-level modal for changed files.

### Import

Supports safe staging and import flows.

Current support:

- Preview importable YAML changes.
- Apply supported create/update changes.
- Upload one YAML file into the sync directory.
- Upload a ZIP archive into the sync directory.

Phase 1 import never deletes or prunes records.

### Export

Supports:

- Full export to the sync directory.
- ZIP download of the current sync directory.
- Single file export preview without page reload.
- Single YAML download.

### Settings

Supports:

- Sync directory path.
- Enabled config types.
- CiviCRM settings allowlist.

## CLI/API4

The stable automation path is core API4 through `cv`:

```bash
./bin/cvdp api4 ConfigManager.status
./bin/cvdp api4 ConfigManager.listTypes
./bin/cvdp api4 ConfigManager.export dryRun=1
./bin/cvdp api4 ConfigManager.export dryRun=0
./bin/cvdp api4 ConfigManager.diff
./bin/cvdp api4 ConfigManager.validate
./bin/cvdp api4 ConfigManager.import dryRun=1 type=option-groups
./bin/cvdp api4 ConfigManager.import dryRun=0 yes=1 type=option-groups
```

Do not use `cv civicfg:*` yet. The custom CLI wrapper work is currently paused. Keep docs, tests, and deployment notes based on the API4 commands above until the API4 engine and UI workflows are stable enough for a thin CLI alias.

## Permissions

The extension defines granular permissions.

- `access CiviCRM configuration manager`
- `export CiviCRM configuration`
- `import CiviCRM configuration`
- `administer CiviCRM configuration manager`

Users with `administer CiviCRM` are treated as superusers and can perform all actions.

See `docs/PERMISSIONS.md` for details.

## Phase 1 handlers

Current export/diff support includes:

- Extensions
- Option groups and option values
- Contact types
- Relationship types
- Location types
- Financial types
- Payment processors, sanitized only
- Custom groups and fields
- CiviCRM settings allowlist
- Message templates
- Dedupe rules
- Scheduled jobs
- SearchKit saved searches
- SearchKit displays
- FormBuilder/Afform

Current import implementation:

- Extensions, conservative install/enable/disable only
- Option groups and option values
- Contact types
- Relationship types
- Location types
- Dedupe rules
- Scheduled jobs
- SearchKit saved searches
- SearchKit displays
- FormBuilder/Afform

Other types are exported/diffed but not yet importable.

## Safety rules

- Import never deletes records in phase 1.
- Payment processor secrets are never exported.
- Live data is never exported.
- Machine names are treated as identities.
- Suspected machine-name renames are warned and skipped.
- ZIP upload only stages safe YAML files under the sync directory.

## Development structure

Main areas:

- `CRM/Configmanager/Page/Main.php` thin CiviCRM page wrapper.
- `Civi/ConfigManager/UI/*` UI request/presenter/file-transfer/permission services.
- `Civi/ConfigManager/Service/*` core orchestration and handler registry.
- `Civi/ConfigManager/Handler/*` config-type handlers.
- `Civi/Api4/*` core API4 facade/actions.
- `templates/CRM/Configmanager/Page/Main.tpl` CiviCRM-compatible Smarty wrapper.
- `templates/CRM/Configmanager/Page/Partials/*.tpl` smaller UI partials for tabs, filters, sync, import, export, settings, and modals.
- `css/configmanager.css` scoped UI styles loaded through CiviCRM resources.
- `js/configmanager.js` vanilla JavaScript for modals and AJAX single-file export preview.

## Buildkit install path

For the current DDEV/Buildkit setup, keep the extension source here:

```text
/Volumes/Data/www/civi-hub/civicrm-buildkit/extensions/com.cividesk.configmanager
```

The CiviCRM extension directory should be a symlink to that source folder.


## 0.1.0-alpha17-core

This build keeps the API4-first workflow and refactors the UI layer for maintainability:

- Smarty templates are split into partials.
- CSS and JavaScript are separate resource files.
- No inline UI assets are embedded in the main template.
- Assets are registered through the CiviCRM resource system.

## 0.1.0-alpha18-core

This build fixes the delayed style rendering seen after CSS/JS were split into asset files. A small critical stylesheet is loaded before page markup to stabilize layout and hide modal content immediately, while the full UI remains in `css/configmanager.css`.

## 0.1.0-alpha19-core

This build adds code-defined sync-directory locking.

### Code-Defined Sync Directory

To make the sync directory environment-specific and read-only in the UI, define it in `civicrm.settings.php`:

```php
global $civicrm_setting;
$civicrm_setting['domain']['civicfg_sync_dir'] = '/var/www/html/civicrm-buildkit/build/drupal-civi/civicrm-config';
```

When this value is present, the Settings page shows the Sync Directory field as locked and does not allow it to be edited from the UI.

## 0.1.0-alpha24-core

This build cleans up the Synchronize/Import UI and fixes the false `In Sync` status shown on some tabs.

Notes:

- All top summary cards now use the same live diff state on Synchronize, Import, Export, and Settings.
- Pending Changes and Changed Files are collapsible.
- Changed Files are shown as compact single-line rows with a Diff button.
- Diff wording now uses `In CiviCRM` and `In YAML` to make the direction clearer.
- Import Preview hides export-only differences, so a fresh install with no YAML does not look like it will remove CiviCRM data.
- Import remains non-destructive in this alpha; missing YAML does not delete existing CiviCRM records.



## 0.1.0-alpha24-core

This build fixes follow-up UI and sync-directory issues from alpha 22.

Notes:

- Restores the shorter diff labels `In CiviCRM` and `In YAML`.
- Changes the default Sync Directory value to `civicrm-config`.
- Treats the older default value `../civicrm-config` as `civicrm-config` for path resolution.
- Resolves relative Sync Directory values from the CMS project root where possible, instead of the private CiviCRM config directory.
- Keeps absolute Sync Directory values supported.
- Rejects URL-style Sync Directory values because the path must be a server-local filesystem path.
- Makes the Settings form use the full available page width.

### Sync Directory Rules

The Sync Directory may be either:

- A relative server path, for example `civicrm-config`.
- An absolute server path, for example `/var/www/html/civicrm-buildkit/build/drupal-civi/civicrm-config`.

Relative paths are resolved from the CMS project root where possible. The directory must be writable by the web/PHP user, or its parent directory must be writable so Export can create it. Do not use a URL, and do not point this setting at a public upload directory containing live files or secrets.

## 0.1.0-alpha25-core

This build adds CiviCRM status-report integration and updates the extension metadata/docs for the paused CLI work.

Notes:

- Adds the `scan-classes` mixin so APIv4 entities are discovered through the current scanner instead of the legacy entity scanner.
- Adds a CiviCRM system status check for Configuration Manager.
- Shows a warning when the initial YAML export has not been done or when CiviCRM/YAML have pending differences.
- Shows an informational in-sync notice when the active configuration matches YAML.
- The status check links back to the Configuration Manager synchronize page, so admins see the same warning from the CiviCRM status report and normal admin login notices.
- Confirms that the custom CLI wrapper is paused for now; API4 through `cv api4 ConfigManager.*` remains the supported automation path.
