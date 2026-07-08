# Changelog

All notable ZIP/test builds for `com.cividesk.configmanager` are tracked here. Other docs describe current behavior only and should reference this file instead of repeating release notes.

## 0.1.0-alpha26-core

- Reworked project documentation so current behavior, architecture, permissions, roadmap, and release history are clearly separated.
- Removed repeated per-release notes from secondary docs and kept version history centralized in this changelog.
- Updated docs to accurately reflect the paused custom CLI wrapper and the current API4-first automation path.
- Updated docs to reflect current import safety behavior, sync-directory rules, system-status checks, and supported handlers.

## 0.1.0-alpha25-core

- Added `scan-classes@1.0.0` mixin to avoid APIv4 legacy entity scanner warnings.
- Added CiviCRM status-report integration for Configuration Manager sync health.
- Status check warns when the initial export is missing or when pending export/import differences exist.
- Status check shows an informational in-sync notice when YAML and CiviCRM match.
- Updated docs to state that the custom CLI wrapper is paused and API4 commands remain the current supported automation path.

## 0.1.0-alpha24-core

- Added create/update import support for generic API4 collection handlers, including SearchKit Saved Searches, SearchKit Displays, and FormBuilder Afforms.
- Kept import non-destructive: records that exist only in CiviCRM are not deleted during import.
- Simplified the Synchronize tab changed-files rows by removing field-name previews from the row. Use Diff for field-level details.
- Restored normal sentence case for help and warning text while keeping short UI labels readable.

## 0.1.0-alpha23-core

- Restored diff wording to `In CiviCRM` and `In YAML`.
- Changed the default Sync Directory from `../civicrm-config` to `civicrm-config`.
- Added legacy handling so existing `../civicrm-config` settings resolve to the new project-root `civicrm-config` directory.
- Resolved relative Sync Directory values from the CMS project root where possible.
- Added Sync Directory validation for URL-style values.
- Made the Settings page layout use the full available page width.
- Clarified Sync Directory rules in the UI and README.

## 0.1.0-alpha22-core

- Fixed top summary cards so Synchronize, Import, Export, and Settings all use the same live diff state instead of showing a false In Sync status on non-sync tabs.
- Made Pending Changes and Changed Files sections collapsible.
- Simplified Changed Files into compact single-line rows with the file path, status, change count, type, field preview, and Diff button.
- Renamed confusing diff labels to In CiviCRM and In YAML.
- Hid export-only differences from the Import Preview so a fresh install with no YAML does not look like it will remove CiviCRM data.
- Kept imports non-destructive in this alpha; import does not delete existing records.

## 0.1.0-alpha21-core

- Renamed the extension key from `org.cividesk.configmanager` to `com.cividesk.configmanager`.
- Hardened Sync Directory locking when `civicfg_sync_dir` is defined in `civicrm.settings.php`; the UI now treats the value as code-owned and does not save UI changes to it.
- Added Drupal-style import behavior for supported option-value removals.
- Kept import conservative for unsupported config types and whole missing option-group files.
- Made Import Preview, Upload Single YAML, Upload ZIP Archive, Full Archive export, and Single File export sections collapsible.
- Kept the Raw API Result panel removed and kept the Node-based asset compiler reverted.

## 0.1.0-alpha19-core

- Disabled Sync Directory editing in the UI when `civicfg_sync_dir` is defined in `civicrm.settings.php` through `$civicrm_setting['domain']['civicfg_sync_dir']`.
- Updated short extension UI labels and button text toward Title Case for a more consistent admin experience.
- Documented settings override behavior.

## 0.1.0-alpha18-core

- Fixed delayed style rendering / FOUC after the UI asset refactor.
- Added a tiny critical stylesheet rendered before the Configuration Manager markup.
- Kept full UI styling in `css/configmanager.css`.
- Added hidden modal markup so diff modal contents cannot flash before CSS loads.
- Updated JavaScript to open/close modals by toggling both `hidden` and `is-open`.

## 0.1.0-alpha17-core

- Separated UI assets from Smarty templates.
- Added `css/configmanager.css` for all scoped UI styling.
- Added `js/configmanager.js` for modal and single-export preview behavior.
- Split the main UI template into smaller partial templates under `templates/CRM/Configmanager/Page/Partials`.
- Added `Civi\ConfigManager\UI\AssetLoader` to register assets through the CiviCRM resource system.
- Kept the existing synchronize/import/export/settings behavior and single-file AJAX export preview.
- Removed inline `<style>` and `<script>` blocks from the main Smarty template.
- Kept maintainer metadata and removed the unused `.gitkeep` file.

## 0.1.0-alpha16-core

- Kept maintainer update to `Sushant Paste <sushant@cividesk.com>`.
- Removed the unnecessary `.gitkeep` file from the extension source.
- Added granular CiviCRM permissions for access, export, import, and administration.
- Refactored the UI code into focused classes:
  - `Civi\ConfigManager\UI\MainPage`
  - `Civi\ConfigManager\UI\Presenter`
  - `Civi\ConfigManager\UI\FileTransfer`
  - `Civi\ConfigManager\UI\Request`
  - `Civi\ConfigManager\UI\Permission`
- Reduced `CRM_Configmanager_Page_Main` to a thin route/page wrapper.
- Added permission checks for UI actions and API4 actions.
- Kept the AJAX single-file export preview behavior.
- Updated README and added architecture, permissions, and roadmap docs.

## 0.1.0-alpha15-core

- Added no-reload single-file export preview with vanilla JavaScript.
- Kept UI wording/label changes requested in template.

## 0.1.0-alpha14-core

- Fixed empty single export selection error.
- Changed YAML null output from `~` to `null`.

## 0.1.0-alpha13-core

- Added single YAML upload.
- Added ZIP archive upload.
- Added single-file export preview and download.
- Removed fixed-width UI container.

## 0.1.0-alpha12-core

- Simplified import preview to show actual importable changes.
- Removed noisy developer labels from UI output.

## 0.1.0-alpha11-core

- Improved diff modal with side-by-side field-level changes.
- Added clearer labels for option value fields.

## 0.1.0-alpha10-core

- Reworked UI tabs toward Drupal-style synchronize/import/export/settings flow.
- Improved option value import matching and machine-name warnings.

## 0.1.0-alpha9-core and earlier

- Established API4-only core workflow.
- Added export, diff, validation, and first import support for option groups/values.
