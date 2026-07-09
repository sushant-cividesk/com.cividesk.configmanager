# Implementation Plan

This document records current implementation decisions and remaining work. Version history is maintained in `../CHANGELOG.md`.

## Locked decisions

- Extension key: `com.cividesk.configmanager`
- UI name: `Configuration Manager`
- File format: YAML
- Default sync directory: `civicrm-config`
- Sync directory setting: `civicfg_sync_dir`
- Target compatibility: CiviCRM 5.x and 6.x
- Current command strategy: API4 through `cv api4 ConfigManager.*`
- Current import delete behavior: no deletes in alpha
- Payment processors: export sanitized data only; never export secrets
- `org.civicoop.configitems`: reference only; not a dependency

## Current command strategy

Use API4 for command-line and automation flows:

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

The custom `cv civicfg:*` wrapper is paused. Do not document or build release processes around that wrapper until the API4/UI behavior is stable.

## Dependency order

Handlers should run in this order unless a new dependency requires adjustment:

1. Extensions
2. Option Groups and Values
3. Contact Types
4. Relationship Types
5. Location Types
6. Financial Types
7. Payment Processors, sanitized
8. Custom Groups and Fields
9. CiviCRM Settings Allowlist
10. Message Templates
11. Dedupe Rules
12. Scheduled Jobs
13. SearchKit Saved Searches
14. SearchKit Displays
15. FormBuilder Afforms
16. Contact Summary Layouts, planned

## File layout decisions

Use split item files for config types that are large, commonly edited, or dependency-sensitive:

- Scheduled Jobs
- SearchKit Saved Searches
- SearchKit Displays
- FormBuilder Afforms

Keep collection files for smaller stable types unless diffs become difficult to review. When adding a new split-file handler, include dependency metadata in each item file and document the dependency order here instead of duplicating release notes.

Temporary filtered exports are expanded by type for known dependency-sensitive groups:

- SearchKit Saved Searches, SearchKit Displays, and FormBuilder Afforms are exported together.
- Custom Groups and Fields can bring Option Groups and Contact Types.
- Relationship Types can bring Contact Types.

This is type-level expansion, not a full per-record dependency graph yet. It prevents the common broken-export case while keeping the current alpha implementation simple.

## Version maintenance

Update the release version in `info.xml`. Runtime export metadata reads that version through `Civi\ConfigManager\Version`, so service code should not hard-code the alpha version. Update `CHANGELOG.md` and current-behavior docs when behavior changes.

## Current supported import areas

Create/update import is currently implemented for:

- Extensions, conservative install/enable/disable only
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

Import can create, update, and delete supported records according to YAML. Payment Processors remain export/diff only because sanitized exports may omit required environment-specific values.

## Remaining phase 1 work

- Add round-trip tests for each handler on real CiviCRM builds.
- Add compatibility smoke tests for Drupal, WordPress, and Standalone.
- Expand dependency graph validation from type-level bundling to per-record dependency resolution, and decide which dependency warnings should become import blockers.
- Add clearer per-handler import readiness reporting based on real-world failures.
- Re-check whether sanitized Payment Processors should ever be importable by default.

## UI maintenance rules

- Keep page logic out of Smarty templates.
- Keep CSS in `css/configmanager.css` unless it must be critical preload CSS.
- Keep `css/configmanager-preload.css` small.
- Keep JavaScript dependency-free in `js/configmanager.js`.
- Do not reintroduce large inline `<style>` or `<script>` blocks.
- Do not add a required frontend build step for the current alpha.

## Sync directory rules

- Use `civicrm-config` as the default relative directory.
- Resolve relative paths from the CMS/project root where possible.
- Reject URL-style values.
- Lock the UI field when `civicfg_sync_dir` is defined in `civicrm.settings.php`.

## Status reporting rules

The CiviCRM status report should warn when:

- The sync directory is missing.
- The sync directory exists but no YAML files exist.
- CiviCRM and YAML have pending differences.

When there are no differences, the status check may show an informational in-sync notice.
