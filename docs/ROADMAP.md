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

- Round-trip tests for all phase 1 handlers on real CiviCRM builds.
- Drupal, WordPress, and Standalone smoke tests.
- More handler-specific import readiness messages based on real-world failures.
- Decide whether sanitized Payment Processors should ever be importable by default.
- Final decision on sanitized Payment Processors import support.

## Phase 1.1 hardening

- Improve diff summaries for large files.
- Add more handler-specific validation.
- Expand dependency detection for SearchKit, Afform, custom fields, option values, and future CiviRules.
- Improve status report wording after real-world testing.
- Add documentation for deployment workflows between dev/stage/prod.

## Phase 2 candidates

- CiviRules.
- Mosaico templates.
- SQL query definitions.
- Contact summary layouts.
- Extension-specific configuration handlers.
- Environment override support.
- Further harden destructive import dependency checks and per-record dependency ordering.
- Optional CLI aliases after API4 stabilizes.

## Deferred CLI wrapper

The custom `cv civicfg:*` wrapper is paused. API4 remains the supported command surface for now.

A future wrapper should be thin and should call the existing API4 actions instead of duplicating business logic.

## Asset tooling

No Node/npm build step is required today.

Optional Stylelint/ESLint can be considered later, but runtime CSS and JavaScript should remain simple and dependency-free for CiviCRM 5.x/6.x compatibility.
