# Architecture

Configuration Manager is organized around a small service layer, type-specific handlers, YAML storage, an API4 facade, and a CiviCRM-compatible UI.

Release history is maintained in `../CHANGELOG.md`. This document describes the current architecture only.

## Runtime flow

```text
UI / cv api4
  -> Civi\Api4\ConfigManager actions
  -> Civi\ConfigManager\Service\ConfigManager
  -> HandlerRegistry
  -> HandlerInterface implementations
  -> YamlFileStorage / SimpleYaml
```

## API4 facade

`Civi/Api4/ConfigManager.php` exposes the supported automation interface:

- `ConfigManager.status`
- `ConfigManager.listTypes`
- `ConfigManager.export`
- `ConfigManager.diff`
- `ConfigManager.validate`
- `ConfigManager.import`

The API4 actions live under `Civi/Api4/Action/ConfigManager`. They keep the command workflow compatible with normal `cv api4` usage.

The custom `cv civicfg:*` wrapper is intentionally paused. See `../README.md#api4-and-automation`.

## Service layer

`Civi/ConfigManager/Service/ConfigManager.php` coordinates the main operations:

- Sync directory resolution.
- Managed handler filtering.
- Full export and dry-run export.
- Diff calculation.
- YAML validation.
- Import preview and import apply.
- Manifest writing with version metadata read from `info.xml`.
- System-status health summary.

The service resolves relative sync directories from the CMS/project root where possible. The legacy value `../civicrm-config` is normalized to `civicrm-config`.

## Handler registry

`Civi/ConfigManager/Service/HandlerRegistry.php` defines the built-in config handlers and their order.

The current built-in handlers cover:

- Extensions
- Option Groups and Values
- Contact Types
- Relationship Types
- Location Types
- Financial Types
- Payment Processors
- Custom Groups and Fields
- CiviCRM Settings Allowlist
- Message Templates
- Dedupe Rules
- Scheduled Jobs
- SearchKit Saved Searches
- SearchKit Displays
- FormBuilder Afforms

Other extensions can alter the handler list with:

```php
hook_civicfg_configTypes(&$handlers)
```

Custom handlers should implement `Civi\ConfigManager\Handler\HandlerInterface`.

## Handler contract

Each handler is responsible for one config type and implements:

- `getType()` - machine name used in filters/API calls.
- `getLabel()` - human-readable label.
- `getDirectory()` - sync directory subdirectory.
- `getWeight()` - dependency/order priority.
- `export()` - returns YAML file definitions from active CiviCRM config.
- `diff()` - compares active config with YAML files.
- `validate()` - checks YAML format and identity requirements.
- `import()` - applies supported YAML changes.

`AbstractHandler` provides common diff and validation defaults. Import defaults to `not_implemented` unless a handler overrides it.

## YAML file strategy

Handlers can export either collection files or split item files. Collection files remain suitable for stable low-volume configuration. Split item files are used for high-churn or large records so Git diffs stay reviewable.

Current split-file handlers:

- Scheduled Jobs
- SearchKit Saved Searches
- SearchKit Displays
- FormBuilder Afforms
- Message Templates
- Custom Groups and Fields

Split files contain one record under `item`, an `identity_field`, and dependency metadata where detectable. The generic API4 handler continues to accept older collection files for import, but new exports use the split-file layout for the handlers listed above.

## Version metadata

`Civi/ConfigManager/Version.php` reads the extension version from `info.xml`. Export manifests use this runtime version, so generated YAML metadata is not hard-coded in service classes.

## Import model

Imports are conservative in the current alpha series.

- Create/update is supported only by specific handlers documented in `../README.md`.
- Import does not delete records.
- Machine names are treated as identities.
- YAML acts as the source of truth for supported create/update fields, so import can revert UI/database changes back to the exported YAML state.
- The UI asks for confirmation before applying imports.
- Dependency metadata is validated where available and missing managed YAML dependencies are reported as warnings.
- Suspected machine-name renames are warned and skipped.
- Unsupported handlers report `not_implemented` instead of partially applying changes.

## Storage layer

`Civi/ConfigManager/Storage/YamlFileStorage.php` reads and writes YAML under the configured sync directory. It supports nested directories so handlers can store either collection files or one file per config item.

`Civi/ConfigManager/Util/SimpleYaml.php` uses available YAML support when possible and includes a simple fallback for the extension's YAML structures.

## UI layer

The route/page wrapper is intentionally thin:

- `CRM/Configmanager/Page/Main.php`

UI logic is split into focused classes:

- `Civi/ConfigManager/UI/MainPage` - page controller.
- `Civi/ConfigManager/UI/Request` - request parsing.
- `Civi/ConfigManager/UI/Presenter` - display rows, labels, summaries, and diff view data.
- `Civi/ConfigManager/UI/FileTransfer` - upload, ZIP handling, preview, and download.
- `Civi/ConfigManager/UI/Permission` - permission constants and checks.
- `Civi/ConfigManager/UI/AssetLoader` - CiviCRM resource loading.

## Templates and assets

Templates are CiviCRM-compatible Smarty files:

- `templates/CRM/Configmanager/Page/Main.tpl`
- `templates/CRM/Configmanager/Page/Partials/*.tpl`

Assets are dependency-free runtime files:

- `css/configmanager-preload.css` - small critical CSS to prevent unstyled flash and modal flash.
- `css/configmanager.css` - full scoped styles under the Configuration Manager wrapper.
- `js/configmanager.js` - vanilla JavaScript for modals and AJAX preview.

There is no required Node/npm build step in the current alpha.

## Settings ownership

`civicfg_sync_dir` can be UI-managed or code-owned.

When defined in `civicrm.settings.php`, the UI treats the sync directory as environment-owned configuration and locks the field to avoid accidental changes.
