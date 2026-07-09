# Testing

This document records the current test expectations for Configuration Manager. Release history is maintained in `../CHANGELOG.md`.

## Standard Round-Trip Test

Use this flow for every handler that supports import:

1. Export YAML from a clean CiviCRM state.
2. Confirm Synchronize reports no pending changes.
3. Change one safe field in the CiviCRM UI or database.
4. Confirm Synchronize shows a focused field-level diff.
5. Use Export to write the CiviCRM change into YAML, then confirm the site is back in sync.
6. Revert the YAML field manually or from Git.
7. Use Import preview to confirm YAML will update CiviCRM.
8. Apply Import, complete the confirmation modal by acknowledging the warning and typing `IMPORT`, and confirm the CiviCRM UI/database value is reverted to YAML.
9. Confirm Synchronize reports no pending changes.

## Phase 1 Handler Matrix

Run the standard test for:

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

Extensions need a separate test because extension enable/disable affects runtime code. Never test disabling Configuration Manager itself; the handler intentionally skips self-disable.

Payment Processors remain export/diff only and should not be tested as importable unless a future explicit import policy is added.

## SearchKit/FormBuilder Dependency Test

1. Create or select a Saved Search.
2. Create or select a Search Display for that Saved Search.
3. Create or select a FormBuilder Afform that references the Search Display.
4. Apply a temporary filter for only SearchKit Saved Searches and run Export.
5. Confirm related SearchKit Displays and FormBuilder Afforms are included in the export when available, and that the filter is cleared after export.
6. Confirm the Search Display YAML declares the Saved Search dependency.
7. Confirm the Afform YAML declares the Search Display dependency where detectable.
8. Remove or move one dependency YAML file in a test copy of the sync directory.
9. Run Validate and confirm a dependency warning is shown.
10. Restore the dependency file before import.

## Large Text Diff Test

For Message Templates, edit only a short marker inside `msg_html` or `msg_text`.

Expected behavior:

- Synchronize should report only the changed field.
- The modal should not flood the page with the entire template body.
- The changed text should be visible near the center of the preview and highlighted.
- Long preview values may be focused/truncated in the UI, while the YAML file keeps the complete content.

## CMS Smoke Tests

Before wider release, run the standard flow on:

- Drupal
- WordPress
- CiviCRM Standalone

For each CMS, verify UI access, API4 commands, sync-directory resolution, export, import dry-run, import apply, validation, ZIP download/upload, and CiviCRM status report notices.

## Recreate From YAML Test

For handlers that support create/update import, delete a non-critical test record from CiviCRM after export and then import it from YAML. Confirm the record is recreated with the YAML values. Note that CiviCRM may assign a new numeric database ID; dependency checks should rely on stable names/keys where available.
