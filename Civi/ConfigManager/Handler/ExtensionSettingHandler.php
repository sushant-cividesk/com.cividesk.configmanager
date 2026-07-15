<?php
namespace Civi\ConfigManager\Handler;

/**
 * Manages non-secret settings that can be attributed to installed extensions.
 *
 * This handler is generic. It does not hard-code Mosaico, Contact Layout,
 * CiviRules, or any other extension. It discovers setting names from CiviCRM's
 * Setting.getfields metadata and maps them back to installed extension keys
 * where metadata or a safe namespace match is available.
 */
class ExtensionSettingHandler extends AbstractHandler {
  public function getType(): string { return 'extension-settings'; }
  public function getLabel(): string { return 'Extension-specific Settings'; }
  public function getDirectory(): string { return 'extension-settings'; }
  public function getWeight(): int { return 75; }

  public function export(): array {
    $groups = [];
    foreach ($this->discoverSettingMetadata() as $name => $meta) {
      $name = (string) $name;
      if (!$this->isSafeSettingName($name) || $this->isSensitiveSettingName($name)) {
        continue;
      }
      $extensionKey = $this->extensionKeyForSetting($name, (array) $meta);
      if ($extensionKey === '') {
        continue;
      }
      $groups[$extensionKey][$name] = \Civi::settings()->get($name);
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

    $files = [];
    foreach ($groups as $extensionKey => $settings) {
      ksort($settings, SORT_NATURAL | SORT_FLAG_CASE);
      $files[] = [
        'filename' => $this->safeName($extensionKey) . '.yml',
        'data' => [
          'schema_version' => 1,
          'type' => 'extension_settings.collection',
          'extension' => $extensionKey,
          'dependencies' => [[
            'type' => 'extensions',
            'entity' => 'Extension',
            'name' => $extensionKey,
            'reason' => 'These settings are associated with this installed extension.',
          ]],
          'items' => $settings,
        ],
      ];
    }
    return $files;
  }

  public function validate(array $items): array {
    $errors = [];
    $warnings = [];
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'extension_settings.collection') {
        $errors[] = ['file' => $filename, 'message' => 'Invalid type. Expected extension_settings.collection.'];
        continue;
      }
      $extensionKey = (string) ($file['extension'] ?? '');
      if ($extensionKey === '') {
        $warnings[] = ['file' => $filename, 'message' => 'Extension key is missing. Settings will still be checked by name, but re-export is recommended.'];
      }
      elseif (!$this->extensionCodeExists($extensionKey)) {
        $errors[] = ['file' => $filename, 'message' => 'Extension code is not available for settings owner: ' . $extensionKey];
      }
      foreach ((array) ($file['items'] ?? []) as $name => $value) {
        $name = (string) $name;
        if (!$this->isSafeSettingName($name)) {
          $errors[] = ['file' => $filename, 'message' => 'Unsafe setting name: ' . $name];
        }
        if ($this->isSensitiveSettingName($name)) {
          $errors[] = ['file' => $filename, 'message' => 'Sensitive setting names are not importable through extension settings: ' . $name];
        }
        if ($extensionKey !== '' && $this->extensionKeyForSetting($name, []) === '' && !$this->settingNameLooksRelatedToExtension($name, $extensionKey)) {
          $warnings[] = ['file' => $filename, 'message' => 'Setting name does not clearly match the extension namespace; review before importing: ' . $name];
        }
      }
    }
    return ['type' => $this->getType(), 'valid' => empty($errors), 'warnings' => $warnings, 'errors' => $errors, 'count' => count($items)];
  }

  public function import(array $items, bool $dryRun = TRUE): array {
    $summary = $this->baseImportSummary($dryRun);
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'extension_settings.collection') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Invalid type. Expected extension_settings.collection.'];
        continue;
      }
      $extensionKey = (string) ($file['extension'] ?? '');
      if ($extensionKey !== '' && !$this->extensionCodeExists($extensionKey)) {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Extension code is not available for settings owner: ' . $extensionKey];
        continue;
      }
      foreach ((array) ($file['items'] ?? []) as $name => $value) {
        $name = (string) $name;
        if (!$this->isSafeSettingName($name) || $this->isSensitiveSettingName($name)) {
          $summary['errors'][] = ['file' => $filename, 'message' => 'Unsafe or sensitive setting skipped: ' . $name];
          continue;
        }
        if ($extensionKey !== '' && !$this->settingNameLooksRelatedToExtension($name, $extensionKey)) {
          $summary['warnings'][] = ['file' => $filename, 'message' => 'Setting namespace is not clearly tied to ' . $extensionKey . '; skipped for safety: ' . $name];
          $summary['skip']++;
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
    }
    $summary['ok'] = empty($summary['errors']);
    return $summary;
  }

  private function discoverSettingMetadata(): array {
    $settings = [];
    if (!function_exists('civicrm_api3')) {
      return $settings;
    }
    try {
      $result = civicrm_api3('Setting', 'getfields', ['sequential' => 1]);
      foreach ((array) ($result['values'] ?? []) as $key => $meta) {
        $meta = (array) $meta;
        $name = (string) ($meta['name'] ?? (is_string($key) ? $key : ''));
        if ($name !== '') {
          $settings[$name] = $meta;
        }
      }
    }
    catch (\Throwable $e) {
      // Optional handler. No settings are exported if metadata is unavailable.
    }
    ksort($settings, SORT_NATURAL | SORT_FLAG_CASE);
    return $settings;
  }

  private function extensionKeyForSetting(string $name, array $meta): string {
    $installed = $this->installedExtensionKeys();
    foreach (['extension', 'extension_key', 'component', 'module', 'group', 'group_name'] as $field) {
      $candidate = (string) ($meta[$field] ?? '');
      if ($candidate !== '' && isset($installed[$candidate])) {
        return $candidate;
      }
    }
    foreach (array_keys($installed) as $extensionKey) {
      if ($this->settingNameLooksRelatedToExtension($name, $extensionKey)) {
        return $extensionKey;
      }
    }
    return '';
  }

  private function settingNameLooksRelatedToExtension(string $settingName, string $extensionKey): bool {
    $setting = strtolower($settingName);
    foreach ($this->extensionTokens($extensionKey) as $token) {
      if (preg_match('/^' . preg_quote($token, '/') . '([_.:-]|$)/', $setting)) {
        return TRUE;
      }
      if (preg_match('/(^|[_.:-])' . preg_quote($token, '/') . '([_.:-]|$)/', $setting)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function extensionTokens(string $extensionKey): array {
    $parts = preg_split('/[^A-Za-z0-9]+/', strtolower($extensionKey));
    $tokens = [];
    foreach ((array) $parts as $part) {
      if (strlen($part) >= 4 && !in_array($part, ['civi', 'civicrm', 'org', 'com', 'net', 'co', 'uk', 'info', 'extension'], TRUE)) {
        $tokens[] = $part;
      }
    }
    $last = end($parts);
    if (is_string($last) && strlen($last) >= 3) {
      $tokens[] = $last;
    }
    $compact = preg_replace('/[^A-Za-z0-9]+/', '', strtolower($extensionKey));
    if ($compact !== '') {
      $tokens[] = $compact;
    }
    return array_values(array_unique($tokens));
  }

  private function installedExtensionKeys(): array {
    $keys = [];
    try {
      $statuses = (array) \CRM_Extension_System::singleton()->getManager()->getStatuses();
      foreach ($statuses as $key => $status) {
        if (in_array(strtolower((string) $status), ['installed', 'enabled', 'disabled'], TRUE)) {
          $keys[(string) $key] = TRUE;
        }
      }
    }
    catch (\Throwable $e) {
      // Leave empty.
    }
    return $keys;
  }

  private function extensionCodeExists(string $extensionKey): bool {
    try {
      $statuses = (array) \CRM_Extension_System::singleton()->getManager()->getStatuses();
      return array_key_exists($extensionKey, $statuses);
    }
    catch (\Throwable $e) {
      return TRUE;
    }
  }

  private function isSafeSettingName(string $name): bool {
    return (bool) preg_match('/^[A-Za-z0-9_.:-]+$/', $name);
  }

  private function isSensitiveSettingName(string $name): bool {
    return (bool) preg_match('/(password|passwd|secret|credential|private|token|api[_-]?key|key)$/i', $name);
  }

  private function safeName(string $name): string {
    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name);
    return trim((string) $safe, '-') ?: sha1($name);
  }
}
