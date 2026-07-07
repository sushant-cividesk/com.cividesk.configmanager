# Implementation Plan

## Locked decisions

- Extension key: `com.cividesk.configmanager`
- UI name: `Configuration Manager`
- File format: YAML only
- Default config directory: `civicrm-config`
- Setting: `civicfg_sync_dir`
- CiviCRM support: 5.x and 6.x
- Import delete behavior: never delete in phase 1
- Financial types: export by default with dependencies, no delete
- Payment processors: export sanitized only, no secrets
- Existing `org.civicoop.configitems`: reference only, not dependency

## Current command strategy

Use core API4 as the stable CLI/automation path.

```bash
cv api4 ConfigManager.status
cv api4 ConfigManager.export dryRun=0
cv api4 ConfigManager.import dryRun=1 type=option-groups
```

Do not rely on custom `cv civicfg:*` commands yet.

## Dependency order

1. Extensions
2. Option groups and option values
3. Contact types / relationship types / location types
4. Financial types
5. Payment processors, sanitized
6. Custom groups
7. Custom fields
8. CiviCRM settings allowlist
9. Message templates
10. Dedupe rules
11. Scheduled jobs
12. SearchKit saved searches
13. SearchKit displays
14. FormBuilder / Afform
15. Contact summary layouts

## Near-term coding tasks

1. Complete custom group/custom field import.
2. Complete SearchKit saved search import.
3. Complete SearchKit display import with dependency resolution.
4. Complete Afform import.
5. Add message template import.
6. Add settings allowlist import.
7. Add dependency graph validation.
8. Add round-trip tests.
9. Add WP and Standalone compatibility smoke tests.

## Longer-term tasks

See `docs/ROADMAP.md`.


## Current refactor notes

The UI layer is now separated into controller, presenter, file-transfer, permission, asset-loader, partial templates, CSS, and JavaScript. Future UI changes should avoid adding large inline `<style>` or `<script>` blocks to Smarty templates.

## UI Maintainability

- Keep Smarty templates focused on markup only.
- Keep full UI CSS in `css/configmanager.css`.
- Keep vanilla JavaScript in `js/configmanager.js`.
- Do not require Node/npm or a frontend compiler for phase 1.
- Keep critical preload CSS small in `css/configmanager-preload.css`.

## Settings Ownership

- If `civicfg_sync_dir` is set in `civicrm.settings.php`, lock the UI field and treat the path as code-owned.

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
