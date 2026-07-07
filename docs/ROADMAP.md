# Roadmap

## Phase 1 - current alpha

- CiviCRM 5.x/6.x compatible extension scaffold.
- API4 core automation path.
- YAML export/diff/validate.
- UI synchronize/import/export/settings flow.
- Safe no-delete import for initial supported handlers.
- ZIP and single YAML staging.
- Single file export preview/download.
- Granular permissions.

## Phase 1.1

- Keep UI assets and templates separated as features grow.

- Complete import handlers for:
  - Custom groups and fields
  - SearchKit saved searches
  - SearchKit displays
  - FormBuilder/Afform
  - Message templates
  - Settings allowlist
  - Scheduled jobs
  - Dedupe rules
  - Financial types
  - Payment processors, sanitized

## Phase 1.2

- Round-trip tests for every phase 1 handler.
- Compatibility smoke tests on Drupal, WordPress, and Standalone.
- Better dependency metadata and validation.
- More detailed import preview per handler.

## Phase 2

- CiviRules.
- Mosaico templates.
- SQL queries.
- Extension-specific config handlers.
- Optional prune/delete mode with strict safeguards.
- Environment override support.
- Optional CLI aliases after API4 stabilizes.

## Asset Build Improvements

- Consider adding optional Stylelint/ESLint in a future build once the UI stabilizes.
- Keep runtime CSS/JavaScript dependency-free and avoid a required Node/npm compiler for CiviCRM 5.x/6.x compatibility.

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
