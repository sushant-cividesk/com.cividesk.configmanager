<?php
namespace Civi\ConfigManager\Handler;

class SettingHandler extends AbstractHandler {
  public function getType(): string { return 'settings'; }
  public function getLabel(): string { return 'CiviCRM Settings Allowlist'; }
  public function getDirectory(): string { return 'settings'; }
  public function getWeight(): int { return 80; }

  public function export(): array {
    $allowlist = (array) \Civi::settings()->get('civicfg_settings_allowlist');
    sort($allowlist);
    $items = [];
    foreach ($allowlist as $name) {
      if (!is_string($name) || $name === '') {
        continue;
      }
      $items[$name] = \Civi::settings()->get($name);
    }
    ksort($items);
    return [[
      'filename' => 'civicrm.settings.yml',
      'data' => [
        'schema_version' => 1,
        'type' => 'settings.allowlist',
        'dependencies' => [],
        'allowlist' => array_values($allowlist),
        'items' => $items,
      ],
    ]];
  }
}
