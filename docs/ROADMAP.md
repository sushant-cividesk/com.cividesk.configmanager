# Roadmap

This roadmap describes planned work. Completed release history is maintained in `../CHANGELOG.md`.

## Current alpha scope

The current alpha focuses on a safe, reviewable configuration workflow:

- API4 automation surface.
- YAML export, diff, and validation.
- UI synchronize/import/export/settings tabs.
- Non-destructive import for supported handlers.
- Single YAML and ZIP staging.
- Single-file export preview and download.
- Granular permissions.
- CiviCRM status report integration.
- Dependency-free UI assets.

## Phase 1 completion

Before treating phase 1 as complete, finish:

- Custom Groups and Fields import.
- Message Templates import.
- CiviCRM Settings Allowlist import.
- Dependency graph validation.
- Round-trip tests for all phase 1 handlers.
- Drupal, WordPress, and Standalone smoke tests.
- Clearer import readiness and warning messages per handler.
- Final decision on Financial Types import support.
- Final decision on sanitized Payment Processors import support.

## Phase 1.1 hardening

- Improve diff summaries for large files.
- Add more handler-specific validation.
- Add safer dependency detection for SearchKit, Afform, custom fields, and option values.
- Improve status report wording after real-world testing.
- Add documentation for deployment workflows between dev/stage/prod.

## Phase 2 candidates

- CiviRules.
- Mosaico templates.
- SQL query definitions.
- Contact summary layouts.
- Extension-specific configuration handlers.
- Environment override support.
- Optional prune/delete mode with strict safeguards and explicit confirmation.
- Optional CLI aliases after API4 stabilizes.

## Deferred CLI wrapper

The custom `cv civicfg:*` wrapper is paused. API4 remains the supported command surface for now.

A future wrapper should be thin and should call the existing API4 actions instead of duplicating business logic.

## Asset tooling

No Node/npm build step is required today.

Optional Stylelint/ESLint can be considered later, but runtime CSS and JavaScript should remain simple and dependency-free for CiviCRM 5.x/6.x compatibility.
