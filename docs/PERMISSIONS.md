# Permissions

Configuration Manager defines granular permissions so sites can separate read, export, import, and administration responsibilities.

## Permissions

### access CiviCRM configuration manager

Allows a user to open the Configuration Manager UI and view sync status, pending changes, and validation results.

Recommended roles:

- Developer
- Site administrator
- Release manager

### export CiviCRM configuration

Allows a user to export active CiviCRM configuration into YAML, download a ZIP archive, or download a single YAML file.

This permission can overwrite files in the sync directory.

Recommended roles:

- Developer
- Release manager

### import CiviCRM configuration

Allows a user to upload/stage YAML or ZIP files and apply supported create/update imports into CiviCRM.

This permission changes active CiviCRM configuration.

Recommended roles:

- Developer
- Release manager
- Senior site administrator

### administer CiviCRM configuration manager

Allows a user to change the sync directory, enabled config types, and settings allowlist.

Recommended roles:

- Technical administrator only

## Superuser behavior

Users with `administer CiviCRM` are treated as superusers for this extension and can perform all Configuration Manager actions.

## Route/API behavior

- The admin route requires `access CiviCRM configuration manager`.
- UI actions perform additional checks before export/import/settings operations.
- API4 actions use matching permissions.

## Safe default recommendation

For production, grant:

- Read/status access broadly to technical admins.
- Export permission to trusted developers/release managers.
- Import permission only to release managers or senior administrators.
- Administer permission only to the person/team managing deployment policy.

## Code-Owned Sync Directory

When `civicfg_sync_dir` is set in `civicrm.settings.php`, even users with Configuration Manager administration permission cannot change the sync directory from the UI. Other settings remain editable according to permission.

## 0.1.0-alpha22-core Notes

- Summary cards now always use the live configuration diff, so non-sync tabs do not show a false `In Sync` state.
- Pending Changes and Changed Files are collapsible.
- Changed Files use compact single-line rows.
- Import Preview only shows YAML-to-CiviCRM changes and skips export-only differences.
- Import remains non-destructive in this alpha when YAML files are missing.


## 0.1.0-alpha24-core Notes

- Sync Directory now defaults to `civicrm-config` and relative paths resolve from the CMS project root where possible.
- The legacy `../civicrm-config` value is treated as `civicrm-config`.
- Settings layout now uses the full available page width.

## 0.1.0-alpha25-core Notes

- The custom `cv civicfg:*` CLI wrapper is paused. Use `cv api4 ConfigManager.*` as the supported command/automation surface for now.
- The extension now declares the `scan-classes` mixin so APIv4 classes are discovered by the current scanner.
- CiviCRM system status now reports Configuration Manager health: initial export required, pending differences, or in sync.
- The status warning is intended to appear anywhere CiviCRM shows system-check notices, including the status report page and normal admin login notification flow.
