# Changelog

## 0.1.0-alpha39-core

- Hardened Config Ignore so ignored DB-only records are hidden from Synchronize/import previews when their generated YAML path matches an ignore rule.
- Added dependency-risk warnings after saving Config Ignore when non-ignored YAML depends on ignored YAML.
- Added requested CLI aliases `cdf`, `cval`, and `cvcfg`; kept `ce`, `ci`, and main command wrappers.
- Updated CLI help to document `-y`, `-h`, and `--help`.
- Added cross-theme UI compatibility styles for CiviCRM core themes.

## 0.1.0-alpha38-core

- Added dedicated CLI wrapper scripts under `bin/`:
  - `bin/civicfg ce` / `bin/config-export`
  - `bin/civicfg ci` / `bin/config-import`
  - `bin/civicfg cd` / `bin/config-diff`
  - `bin/civicfg config-validate`
- Improved Config Ignore behavior so ignored YAML files are hidden from diff, validate, import, export, single-file preview, and ZIP download.
- Added clearer warnings when ignored files may hide dependencies needed by non-ignored YAML.
- Filtered ignored DB-only diff entries, including the default self-ignore for `extensions/com.cividesk.configmanager.yml`.
- Documented that Configuration Manager is intended to work smoothly for the same site codebase across dev/stage/prod, while cross-site imports may still need careful review.


## 0.1.0-alpha37-core

- Added Config Ignore settings for relative YAML paths/wildcards, similar to Drupal config ignore. Ignored files are skipped during diff, validate, export, and import.
- Ignored `extensions/com.cividesk.configmanager.yml` by default to avoid self-management loops when Configuration Manager exports extension status.
- Improved SearchDisplay import matching with composite identity `saved_search_id.name + name`, so extension-provided displays like `Table` can be matched instead of causing duplicate/already-exists failures.
- SearchDisplay split exports now use `SavedSearch__Display.yml` filenames for new exports to avoid collisions where multiple searches have a display with the same name.
- Downgraded already-exists create conflicts to warnings when the target record can be matched safely after the conflict.
- Improved relationship type matching fallback by labels when machine names differ, with warnings for review.
- Updated import result handling so a non-blocking issue does not leave a scary error state when no pending diff remains.

## 0.1.0-alpha36-core

- Exported extensions as one YAML file per extension key to prepare for future extension-specific config grouping.
- Added full-page progress overlay for import, export, validate, upload, and settings form submissions to reduce double-click/resubmission risk.
- Preserved import result details across redirect so failure notices can name the first handler/file error and the page can list warnings/errors.
- Added optional Site Tokens handler for sites exposing a `SiteToken` API4 entity.
- Improved Custom Groups and Fields import with YAML-source delete support for missing fields and non-reserved missing groups, plus stronger dependency metadata.
- Added alpha CiviRules handler for common CiviRules API4 entities when available.
- Updated documentation and testing notes for alpha36 behavior.


## 0.1.0-alpha35-core

- Fixed cross-site diff comparison to normalize runtime fields before deciding a file is changed. Numeric database IDs should no longer appear as import/update-only differences after deploying YAML from another database.
- Improved list comparison identities so rows keyed by `key`, `name`, `name_a_b`, `title`, or duplicate `name + value` are compared safely. This avoids false option-value and extension-status diffs.
- Fixed the delete phase of generic imports so it does not resolve create/update-only dependencies while it is only calculating missing-record deletes.
- Kept the Export page full archive UI fix so it does not imply that the ZIP contains only the files changed by the preview.

## 0.1.0-alpha34-core

- Ignored runtime numeric database IDs in generic API4 diff/export comparison so YAML exported from one database can be compared safely against another database.
- Normalized SearchDisplay diff/export comparison to use `saved_search_id.name` instead of source database `saved_search_id` where available.
- Removed the misleading planned-file list from the Export tab full archive panel; ZIP download represents the full current sync directory, while Export writes pending YAML changes.
- Kept validation/import behavior from alpha33, including safer option value identity handling.

## 0.1.0-alpha33-core

- Fixed validation noise for core option groups where CiviCRM legitimately reuses option value names with different stored values.
- Updated option value validation/import identity handling so duplicate names are matched by name plus value where needed instead of failing validation.
- Updated Custom Groups and Fields export to write option group references as stable `option_group_name` values where possible.
- Kept legacy custom field YAML with numeric `option_group_id` validation-compatible so older alpha exports do not fail validation unnecessarily.
- Updated docs to clarify option value identity handling and environment-safe custom field option group dependencies.

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
