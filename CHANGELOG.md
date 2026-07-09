# Changelog

## 0.1.0-alpha32-core

- Fixed the export dependency confirmation modal so export asks for `EXPORT` and shows export-specific warning text instead of import text.
- Added stronger dependency metadata for SearchKit SavedSearch exports so related SearchDisplay files are declared and validated.
- Improved dependency validation messages to name the file and missing dependency that blocks import.
- Improved SearchDisplay import safety by resolving `saved_search_id` from `saved_search_id.name` on the target site instead of trusting source database IDs.
- Changed destructive imports to apply create/update first and then delete missing records in reverse dependency order, so child SearchDisplay records are deleted before their parent SavedSearch.
- Kept delete actions visually dangerous in the import preview using the red badge style.
- Fixed a syntax issue in the UI page fallback error handling.

## 0.1.0-alpha31-core

- Added the alpha29/alpha30 hotfixes into the versioned build, including the API4 metadata fix and Smarty undefined-key warning fix.
- Changed import behavior for supported handlers so YAML is now the source of truth for create, update, and delete operations after explicit confirmation.
- Added delete support for records that exist in CiviCRM but not in YAML for Message Templates and generic split/collection API4 handlers such as SearchKit Saved Searches, SearchKit Displays, FormBuilder Afforms, Scheduled Jobs, Contact Types, Relationship Types, Location Types, and Dedupe Rules.
- Import preview now includes CiviCRM-only records as importable delete actions instead of hiding them.
- Missing managed YAML dependencies are now import-blocking validation errors instead of warnings.
- Added dependency notices and confirmation for filtered exports when related types are automatically included.
- Converted export, import, upload, and validate actions to post/redirect/get so browser refresh does not trigger form resubmission.
- Updated current-behavior docs for destructive import safeguards, dependency handling, and filtered export behavior.

All notable ZIP/test builds for `com.cividesk.configmanager` are tracked here. Other docs describe current behavior only and should reference this file instead of repeating release notes.

## 0.1.0-alpha30-core

- Included the alpha29 hotfixes for API4 `getFields()` metadata and Smarty undefined-key warning prevention.
- Added dependency-aware type expansion for temporary filtered export/import operations. SearchKit Saved Searches, SearchKit Displays, and FormBuilder Afforms are bundled together; Custom Groups can include Option Groups and Contact Types; Relationship Types can include Contact Types.
- Cleared temporary type filters after filtered export so the Synchronize tab shows the full managed status instead of a filtered In Sync result.
- Updated docs to clarify the difference between temporary filters and the Settings > Managed Types scope.


## 0.1.0-alpha29-core

- Improved Import Preview layout so each changed field shows the current CiviCRM value beside the YAML value to import.
- Added focused large-text previews with highlighted changed text for message-template HTML/text and other long scalar values.
- Updated modal diff rows to highlight the changed substring instead of showing only the beginning of long content.
- Replaced the browser confirm dialog with an in-page confirmation modal that requires review acknowledgement and typing `IMPORT` before applying YAML changes.
- Documented that recreating a deleted CiviCRM record from YAML can create a new database ID, so dependency-safe imports should rely on stable machine names where available.
- Updated current-behavior docs for the safer import confirmation and focused diff review workflow.

## 0.1.0-alpha28-core

- Added create/update import support for Message Templates, CiviCRM Settings Allowlist, Custom Groups and Fields, and Financial Types.
- Made YAML-to-CiviCRM import usable for reverting supported UI/database changes back to the exported YAML source of truth.
- Added an import confirmation prompt before applying YAML changes to active CiviCRM configuration.
- Added cross-file dependency warnings where exported YAML declares dependencies on other managed YAML items.
- Improved large text diff previews so message-template HTML bodies no longer flood the modal or import preview; long values are truncated in the UI while the underlying YAML/diff remains complete.
- Updated documentation to reflect current import support, dependency warnings, alpha safety behavior, and manual round-trip test expectations.

## 0.1.0-alpha27-core

- Added runtime version lookup from `info.xml` and removed the hard-coded `exported_with` version from the export manifest service.
- Split high-churn config exports into one YAML file per item for Scheduled Jobs, SearchKit Saved Searches, SearchKit Displays, and FormBuilder Afforms.
- Added dependency metadata to split item files where dependencies can be detected, including SearchDisplay to SavedSearch, FormBuilder layout SearchKit references, and Scheduled Job API entity usage.
- Kept backward-compatible import support for older collection files for the split handlers.
- Clarified extension status behavior: export captures current CiviCRM extension status, import can install/enable/disable when code exists, uninstall remains skipped, and self-disable is skipped for safety.
- Updated current-behavior docs to reflect the split YAML layout, dependency metadata, version maintenance rule, and documentation maintenance expectation.

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
