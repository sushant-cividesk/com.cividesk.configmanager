# Architecture

Configuration Manager is structured around a small core engine, handlers, and a CiviCRM-compatible UI.

## Main layers

### API4 facade

`Civi/Api4/ConfigManager.php` exposes the stable automation interface:

- `ConfigManager.status`
- `ConfigManager.listTypes`
- `ConfigManager.export`
- `ConfigManager.diff`
- `ConfigManager.validate`
- `ConfigManager.import`

This keeps the CLI path compatible with core `cv api4`.

### Service layer

`Civi/ConfigManager/Service/ConfigManager.php` coordinates:

- sync directory discovery
- handler registry
- export
- diff
- validate
- import
- manifest writing

### Handler layer

Each supported config type has a handler under `Civi/ConfigManager/Handler`.

A handler knows how to:

- export active DB config into one or more YAML files
- diff active DB config against YAML files
- validate YAML files
- import supported create/update changes

### Storage layer

`YamlFileStorage` reads/writes YAML files under the sync directory.

`SimpleYaml` uses `ext-yaml` or Symfony YAML when available, with a fallback writer/parser for simple YAML.

### UI layer

The route class is intentionally thin:

- `CRM/Configmanager/Page/Main.php`

UI logic is split into:

- `Civi/ConfigManager/UI/MainPage` - main controller
- `Civi/ConfigManager/UI/Request` - request parsing
- `Civi/ConfigManager/UI/Presenter` - template rows/summaries/labels
- `Civi/ConfigManager/UI/FileTransfer` - upload/download/single preview
- `Civi/ConfigManager/UI/Permission` - permission names and checks
- `Civi/ConfigManager/UI/AssetLoader` - loads CSS/JS through CiviCRM resources

### Template

`templates/CRM/Configmanager/Page/Main.tpl` uses CiviCRM-compatible Smarty and includes smaller partial templates. CSS and JavaScript live in separate resource files loaded by `AssetLoader`.

No npm or third-party JavaScript dependency is required in phase 1.

## Extension points

Other extensions can register extra handlers through:

```php
hook_civicfg_configTypes(&$handlers)
```

Handlers should implement `Civi\ConfigManager\Handler\HandlerInterface`.


### Template and asset layer

The UI is intentionally split into maintainable pieces:

- `templates/CRM/Configmanager/Page/Main.tpl` is a small wrapper.
- `templates/CRM/Configmanager/Page/Partials/*.tpl` contains the individual page sections.
- `css/configmanager.css` contains scoped styles under `.crm-configmanager-block`.
- `js/configmanager.js` contains vanilla JavaScript for modals and AJAX single-file export preview.

Assets are loaded with `CRM_Core_Resources` via `Civi/ConfigManager/UI/AssetLoader`, which avoids inline style/script blocks and keeps the extension more compatible with CiviCRM themes.

## UI asset loading

The UI uses three layers:

- `css/configmanager-preload.css` is a tiny critical stylesheet rendered before the page block to prevent delayed unstyled rendering and hidden modal flash.
- `css/configmanager.css` contains the full scoped UI styling.
- `js/configmanager.js` contains vanilla JavaScript interactions.

The critical stylesheet must stay small and must not replace the full CSS file.


## UI Asset Maintenance

The extension intentionally avoids a Node/npm build step. Edit the runtime files directly:

- `css/configmanager.css` for full scoped UI styling.
- `css/configmanager-preload.css` for tiny critical styles only.
- `js/configmanager.js` for vanilla JavaScript interactions.

Keep these files dependency-free for CiviCRM 5.x/6.x compatibility.

## Code-Defined Settings

If `civicfg_sync_dir` is defined in `civicrm.settings.php`, the UI treats it as environment-owned configuration and locks the Sync Directory field. This avoids accidental UI changes to path configuration that should be controlled by code/deployment.


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

## 0.1.0-alpha25-core Notes

- The custom `cv civicfg:*` CLI wrapper is paused. Use `cv api4 ConfigManager.*` as the supported command/automation surface for now.
- The extension now declares the `scan-classes` mixin so APIv4 classes are discovered by the current scanner.
- CiviCRM system status now reports Configuration Manager health: initial export required, pending differences, or in sync.
- The status warning is intended to appear anywhere CiviCRM shows system-check notices, including the status report page and normal admin login notification flow.
