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

  public function validate(array $items): array {
    $errors = [];
    $warnings = [];
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'settings.allowlist') {
        $errors[] = ['file' => $filename, 'message' => 'Invalid type. Expected settings.allowlist.'];
        continue;
      }
      foreach (($file['items'] ?? []) as $name => $value) {
        if (!$this->isSafeSettingName((string) $name)) {
          $errors[] = ['file' => $filename, 'message' => 'Unsafe setting name: ' . $name];
        }
        if (preg_match('/(password|secret|key|token|credential)/i', (string) $name)) {
          $warnings[] = ['file' => $filename, 'message' => 'Setting looks sensitive and should normally not be managed in git: ' . $name];
        }
      }
    }
    return ['type' => $this->getType(), 'valid' => empty($errors), 'warnings' => $warnings, 'errors' => $errors, 'count' => count($items)];
  }

  public function import(array $items, bool $dryRun = TRUE): array {
    $summary = $this->baseImportSummary($dryRun);
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'settings.allowlist') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Invalid type. Expected settings.allowlist.'];
        continue;
      }
      $settings = (array) ($file['items'] ?? []);
      foreach ($settings as $name => $value) {
        $name = (string) $name;
        if (!$this->isSafeSettingName($name)) {
          $summary['errors'][] = ['file' => $filename, 'message' => 'Unsafe setting name: ' . $name];
          continue;
        }
        $current = \Civi::settings()->get($name);
        if ($this->normaliseComparableValue($current) !== $this->normaliseComparableValue($value)) {
          $summary['update']++;
          if (!$dryRun) {
            \Civi::settings()->set($name, $value);
          }
        }
        else {
          $summary['skip']++;
        }
      }
      if (array_key_exists('allowlist', $file) && is_array($file['allowlist'])) {
        $allowlist = array_values(array_filter(array_map('strval', $file['allowlist'])));
        $currentAllowlist = (array) \Civi::settings()->get('civicfg_settings_allowlist');
        sort($allowlist);
        sort($currentAllowlist);
        if ($allowlist !== $currentAllowlist) {
          $summary['update']++;
          if (!$dryRun) {
            \Civi::settings()->set('civicfg_settings_allowlist', $allowlist);
          }
        }
      }
    }
    $summary['ok'] = empty($summary['errors']);
    return $summary;
  }

  private function isSafeSettingName(string $name): bool {
    return (bool) preg_match('/^[A-Za-z0-9_.:-]+$/', $name);
  }
}
