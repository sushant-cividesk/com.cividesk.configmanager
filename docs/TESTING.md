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
8. Apply Import and confirm the CiviCRM UI/database value is reverted to YAML.
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
4. Export all three related items.
5. Confirm the Search Display YAML declares the Saved Search dependency.
6. Confirm the Afform YAML declares the Search Display dependency where detectable.
7. Remove or move one dependency YAML file in a test copy of the sync directory.
8. Run Validate and confirm a dependency warning is shown.
9. Restore the dependency file before import.

## Large Text Diff Test

For Message Templates, edit only a short marker inside `msg_html` or `msg_text`.

Expected behavior:

- Synchronize should report only the changed field.
- The modal should not flood the page with the entire template body.
- Long preview values may be truncated in the UI, while the YAML file keeps the complete content.

## CMS Smoke Tests

Before wider release, run the standard flow on:

- Drupal
- WordPress
- CiviCRM Standalone

For each CMS, verify UI access, API4 commands, sync-directory resolution, export, import dry-run, import apply, validation, ZIP download/upload, and CiviCRM status report notices.
