<?php
namespace Civi\ConfigManager\Handler;

use Civi\ConfigManager\Version;

class ExtensionHandler extends AbstractHandler {
  private bool $importWritesEnabled = TRUE;
  private bool $deleteMissingEnabled = TRUE;

  public function getType(): string { return 'extensions'; }
  public function getLabel(): string { return 'Extensions'; }
  public function getDirectory(): string { return 'extensions'; }
  public function getWeight(): int { return 10; }

  public function setImportWriteEnabled(bool $enabled): self {
    $this->importWritesEnabled = $enabled;
    return $this;
  }

  public function setDeleteMissingEnabled(bool $enabled): self {
    $this->deleteMissingEnabled = $enabled;
    return $this;
  }

  public function export(): array {
    $manager = \CRM_Extension_System::singleton()->getManager();
    $files = [];
    foreach ($manager->getStatuses() as $key => $status) {
      $files[] = [
        'filename' => $this->safeName((string) $key) . '.yml',
        'data' => [
          'schema_version' => 1,
          'type' => 'extension.item',
          'key' => (string) $key,
          'dependencies' => $this->dependenciesForExtension((string) $key),
          'extension' => [
            'key' => (string) $key,
            'status' => (string) $status,
          ],
        ],
      ];
    }
    usort($files, fn($a, $b) => strcmp($a['filename'], $b['filename']));
    return $files;
  }

  public function validate(array $items): array {
    $errors = [];
    foreach ($items as $filename => $item) {
      $type = (string) ($item['type'] ?? '');
      if ($type === 'extensions.collection') {
        foreach (($item['items'] ?? []) as $index => $extension) {
          if (empty($extension['key'])) {
            $errors[] = ['file' => $filename, 'message' => 'Extension row ' . $index . ' is missing key.'];
          }
        }
        continue;
      }
      if ($type === 'extension.item') {
        $extension = (array) ($item['extension'] ?? []);
        if (empty($extension['key']) && empty($item['key'])) {
          $errors[] = ['file' => $filename, 'message' => 'Extension item is missing extension.key.'];
        }
        continue;
      }
      $errors[] = ['file' => $filename, 'message' => 'Invalid type. Expected extension.item.'];
    }
    return [
      'type' => $this->getType(),
      'valid' => empty($errors),
      'errors' => $errors,
      'warnings' => [],
      'count' => count($items),
    ];
  }

  public function import(array $items, bool $dryRun = TRUE): array {
    $summary = [
      'type' => $this->getType(),
      'status' => $dryRun ? 'dry_run' : 'applied',
      'dry_run' => $dryRun,
      'install' => 0,
      'enable' => 0,
      'disable' => 0,
      'delete' => 0,
      'skip' => 0,
      'warnings' => [],
      'errors' => [],
    ];

    $manager = \CRM_Extension_System::singleton()->getManager();
    $current = (array) $manager->getStatuses();
    $desiredKeys = [];

    foreach ($this->expandItems($items, $summary) as $entry) {
      $filename = $entry['filename'];
      $extension = (array) $entry['extension'];
      $key = (string) ($extension['key'] ?? '');
      $desired = strtolower((string) ($extension['status'] ?? ''));
      if ($key === '') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Extension key missing.'];
        continue;
      }
      $desiredKeys[$key] = TRUE;
      if (!$this->importWritesEnabled) {
        continue;
      }
      $actual = strtolower((string) ($current[$key] ?? 'missing'));
      if ($actual === $desired) {
        $summary['skip']++;
        continue;
      }

      try {
        if (in_array($desired, ['installed', 'enabled'], TRUE)) {
          if ($actual === 'missing') {
            $summary['errors'][] = ['file' => $filename, 'extension' => $key, 'message' => 'Extension code is not available on this site: ' . $key];
            continue;
          }
          if (in_array($actual, ['uninstalled', 'not installed', 'not_installed'], TRUE)) {
            $summary['install']++;
            $summary['warnings'][] = ['file' => $filename, 'extension' => $key, 'message' => 'Extension will be installed: ' . $key];
            if (!$dryRun) {
              $this->callManager($manager, 'install', [$key]);
            }
          }
          else {
            $summary['enable']++;
            $summary['warnings'][] = ['file' => $filename, 'extension' => $key, 'message' => 'Extension will be enabled: ' . $key];
            if (!$dryRun) {
              $this->callManager($manager, 'enable', [$key]);
            }
          }
        }
        elseif ($desired === 'disabled') {
          if ($key === Version::EXTENSION_KEY) {
            $summary['skip']++;
            $summary['warnings'][] = ['file' => $filename, 'extension' => $key, 'message' => 'Self-disable is skipped so Configuration Manager can finish the import safely.'];
            continue;
          }
          $summary['disable']++;
          $summary['warnings'][] = ['file' => $filename, 'extension' => $key, 'message' => 'Extension will be disabled: ' . $key];
          if (!$dryRun) {
            $this->callManager($manager, 'disable', [$key]);
          }
        }
        elseif (in_array($desired, ['uninstalled', 'not installed', 'not_installed'], TRUE)) {
          $summary['skip']++;
          $summary['warnings'][] = ['file' => $filename, 'extension' => $key, 'message' => 'Uninstall is skipped for safety: ' . $key];
        }
        else {
          $summary['skip']++;
          $summary['warnings'][] = ['file' => $filename, 'extension' => $key, 'message' => 'Unknown target status for ' . $key . ': ' . $desired];
        }
      }
      catch (\Throwable $e) {
        $summary['errors'][] = ['file' => $filename, 'extension' => $key, 'message' => $e->getMessage()];
      }
    }

    if ($this->deleteMissingEnabled) {
      foreach ($current as $key => $status) {
        if (isset($desiredKeys[(string) $key]) || (string) $key === Version::EXTENSION_KEY) {
          continue;
        }
        $summary['skip']++;
        $summary['warnings'][] = ['extension' => (string) $key, 'message' => 'Extension exists in CiviCRM but not YAML. It is not uninstalled automatically for safety: ' . (string) $key];
      }
    }

    $summary['ok'] = empty($summary['errors']);
    return $summary;
  }

  protected function normaliseDataForDiff(array $data): array {
    // Backward compatibility for old collection files.
    if (($data['type'] ?? '') === 'extensions.collection') {
      return $data;
    }
    return $data;
  }

  private function expandItems(array $items, array &$summary): array {
    $rows = [];
    foreach ($items as $filename => $item) {
      $type = (string) ($item['type'] ?? '');
      if ($type === 'extensions.collection') {
        foreach (($item['items'] ?? []) as $extension) {
          $rows[] = ['filename' => $filename, 'extension' => (array) $extension];
        }
      }
      elseif ($type === 'extension.item') {
        $extension = (array) ($item['extension'] ?? []);
        if (empty($extension['key']) && !empty($item['key'])) {
          $extension['key'] = $item['key'];
        }
        $rows[] = ['filename' => $filename, 'extension' => $extension];
      }
      else {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Invalid extension YAML type. Expected extension.item.'];
      }
    }
    return $rows;
  }

  private function dependenciesForExtension(string $key): array {
    return [];
  }

  private function callManager($manager, string $method, array $keys): void {
    if (!method_exists($manager, $method)) {
      throw new \RuntimeException('Extension manager does not support method ' . $method . ' on this CiviCRM version.');
    }
    $manager->{$method}($keys);
  }

  private function safeName(string $name): string {
    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name);
    return trim((string) $safe, '-') ?: sha1($name);
  }
}
