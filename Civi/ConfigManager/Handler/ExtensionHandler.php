<?php
namespace Civi\ConfigManager\Handler;

use Civi\ConfigManager\Version;

class ExtensionHandler extends AbstractHandler {
  public function getType(): string { return 'extensions'; }
  public function getLabel(): string { return 'Extensions'; }
  public function getDirectory(): string { return 'extensions'; }
  public function getWeight(): int { return 10; }

  public function export(): array {
    $manager = \CRM_Extension_System::singleton()->getManager();
    $rows = [];
    foreach ($manager->getStatuses() as $key => $status) {
      $rows[] = [
        'schema_version' => 1,
        'type' => 'extension',
        'key' => $key,
        'status' => $status,
      ];
    }
    usort($rows, function($a, $b) {
      return strcmp($a['key'], $b['key']);
    });
    return [[
      'filename' => 'extensions.yml',
      'data' => [
        'schema_version' => 1,
        'type' => 'extensions.collection',
        'items' => $rows,
      ],
    ]];
  }

  public function validate(array $items): array {
    $errors = [];
    foreach ($items as $filename => $item) {
      if (($item['type'] ?? '') !== 'extensions.collection') {
        $errors[] = ['file' => $filename, 'message' => 'Invalid type. Expected extensions.collection.'];
        continue;
      }
      foreach (($item['items'] ?? []) as $index => $extension) {
        if (empty($extension['key'])) {
          $errors[] = ['file' => $filename, 'message' => 'Extension row ' . $index . ' is missing key.'];
        }
      }
    }
    return [
      'type' => $this->getType(),
      'valid' => empty($errors),
      'errors' => $errors,
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
      'skip' => 0,
      'warnings' => [],
      'errors' => [],
    ];

    $manager = \CRM_Extension_System::singleton()->getManager();
    $current = (array) $manager->getStatuses();

    foreach ($items as $filename => $collection) {
      foreach (($collection['items'] ?? []) as $extension) {
        $key = (string) ($extension['key'] ?? '');
        $desired = strtolower((string) ($extension['status'] ?? ''));
        if ($key === '') {
          $summary['errors'][] = ['file' => $filename, 'message' => 'Extension key missing.'];
          continue;
        }
        $actual = strtolower((string) ($current[$key] ?? 'missing'));
        if ($actual === $desired) {
          $summary['skip']++;
          continue;
        }

        try {
          if (in_array($desired, ['installed', 'enabled'], TRUE)) {
            if (in_array($actual, ['missing'], TRUE)) {
              $summary['errors'][] = ['extension' => $key, 'message' => 'Extension code is not available on this site.'];
              continue;
            }
            if (in_array($actual, ['uninstalled', 'not installed', 'not_installed'], TRUE)) {
              $summary['install']++;
              if (!$dryRun) {
                $this->callManager($manager, 'install', [$key]);
              }
            }
            else {
              $summary['enable']++;
              if (!$dryRun) {
                $this->callManager($manager, 'enable', [$key]);
              }
            }
          }
          elseif ($desired === 'disabled') {
            if ($key === Version::EXTENSION_KEY) {
              $summary['skip']++;
              $summary['warnings'][] = [
                'extension' => $key,
                'message' => 'Self-disable is skipped so Configuration Manager can finish the import safely.',
              ];
              continue;
            }
            $summary['disable']++;
            if (!$dryRun) {
              $this->callManager($manager, 'disable', [$key]);
            }
          }
          elseif (in_array($desired, ['uninstalled', 'not installed', 'not_installed'], TRUE)) {
            $summary['skip']++;
            $summary['warnings'][] = [
              'extension' => $key,
              'message' => 'Uninstall is skipped in phase 1 because imports never delete or prune config.',
            ];
          }
          else {
            $summary['skip']++;
            $summary['warnings'][] = [
              'extension' => $key,
              'message' => 'Unknown target status: ' . $desired,
            ];
          }
        }
        catch (\Throwable $e) {
          $summary['errors'][] = ['extension' => $key, 'message' => $e->getMessage()];
        }
      }
    }

    $summary['ok'] = empty($summary['errors']);
    return $summary;
  }

  private function callManager($manager, string $method, array $keys): void {
    if (!method_exists($manager, $method)) {
      throw new \RuntimeException('Extension manager does not support method ' . $method . ' on this CiviCRM version.');
    }
    $manager->{$method}($keys);
  }
}
