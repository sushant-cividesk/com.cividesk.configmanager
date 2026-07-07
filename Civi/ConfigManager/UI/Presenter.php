<?php
namespace Civi\ConfigManager\UI;

use Civi\ConfigManager\Service\ConfigManager;

/**
 * Converts service/API data into template-friendly rows, labels, and summaries.
 */
class Presenter {

  public function buildTabs(string $op): array {
    $tabs = [
      'sync' => ts('Synchronize'),
      'import' => ts('Import'),
      'export' => ts('Export'),
      'settings' => ts('Settings'),
    ];
    $rows = [];
    foreach ($tabs as $key => $label) {
      if ($key === 'import' && !Permission::has(Permission::IMPORT)) {
        continue;
      }
      if ($key === 'export' && !Permission::has(Permission::EXPORT)) {
        continue;
      }
      if ($key === 'settings' && !Permission::has(Permission::ADMINISTER)) {
        continue;
      }
      $rows[] = [
        'key' => $key,
        'label' => $label,
        'active' => $op === $key,
        'url' => \CRM_Utils_System::url('civicrm/admin/config-manager', 'reset=1&op=' . $key),
      ];
    }
    return $rows;
  }

  public function buildTypeRows(ConfigManager $manager, array $result): array {
    $diffByType = [];
    if (!empty($result['items']) && is_array($result['items'])) {
      foreach ($result['items'] as $item) {
        if (!empty($item['type'])) {
          $diffByType[$item['type']] = $item;
        }
      }
    }

    $rows = [];
    foreach ($manager->getAllHandlers() as $handler) {
      $type = $handler->getType();
      $rows[] = [
        'type' => $type,
        'label' => $handler->getLabel(),
        'directory' => $handler->getDirectory(),
        'weight' => $handler->getWeight(),
        'status' => $diffByType[$type]['status'] ?? NULL,
        'dbCount' => $diffByType[$type]['db_count'] ?? NULL,
        'fileCount' => $diffByType[$type]['file_count'] ?? ($diffByType[$type]['count'] ?? NULL),
        'changedCount' => !empty($diffByType[$type]['changed']) ? count($diffByType[$type]['changed']) : 0,
        'newCount' => !empty($diffByType[$type]['new_in_db']) ? count($diffByType[$type]['new_in_db']) : 0,
        'missingCount' => !empty($diffByType[$type]['missing_in_db']) ? count($diffByType[$type]['missing_in_db']) : 0,
        'valid' => $diffByType[$type]['valid'] ?? NULL,
        'statusUrl' => \CRM_Utils_System::url('civicrm/admin/config-manager', 'reset=1&op=sync&type=' . rawurlencode($type)),
      ];
    }
    return $rows;
  }

  public function buildSummary(array $result, array $status, string $op): array {
    $changed = 0;
    $new = 0;
    $missing = 0;
    $warnings = 0;
    if (!empty($result['items']) && is_array($result['items'])) {
      foreach ($result['items'] as $item) {
        $changed += !empty($item['changed']) ? count($item['changed']) : 0;
        $new += !empty($item['new_in_db']) ? count($item['new_in_db']) : 0;
        $missing += !empty($item['missing_in_db']) ? count($item['missing_in_db']) : 0;
        $warnings += !empty($item['warnings']) ? count($item['warnings']) : 0;
      }
    }

    return [
      'ok' => ($result['ok'] ?? FALSE) && ($status['ok'] ?? FALSE),
      'sync_dir' => $status['sync_dir'] ?? ($result['sync_dir'] ?? NULL),
      'planned_count' => !empty($result['planned']) && is_array($result['planned']) ? count($result['planned']) : 0,
      'written_count' => !empty($result['written']) && is_array($result['written']) ? count($result['written']) : 0,
      'skipped_count' => !empty($result['skipped']) && is_array($result['skipped']) ? count($result['skipped']) : 0,
      'error_count' => !empty($result['errors']) && is_array($result['errors']) ? count($result['errors']) : 0,
      'warning_count' => $warnings,
      'item_count' => !empty($result['items']) && is_array($result['items']) ? count($result['items']) : 0,
      'changed_count' => $changed,
      'new_count' => $new,
      'missing_count' => $missing,
      'total_changes' => $changed + $new + $missing,
      'exists' => $status['exists'] ?? NULL,
      'writable' => $status['writable'] ?? NULL,
      'op' => $op,
    ];
  }

  public function extractDiffFiles(array $result): array {
    $files = [];
    if (empty($result['items']) || !is_array($result['items'])) {
      return $files;
    }
    $i = 0;
    foreach ($result['items'] as $item) {
      foreach (($item['files'] ?? []) as $file) {
        $file['id'] = 'civicfg-diff-' . (++$i);
        $file['type'] = $item['type'] ?? '';
        $file['type_label'] = $item['label'] ?? ($item['type'] ?? '');
        $file['status_label'] = $this->statusLabel((string) ($file['status'] ?? 'changed'));
        $file['rows'] = [];
        foreach (($file['changes'] ?? []) as $change) {
          $file['rows'][] = [
            'label' => $this->humanizeChangePath((string) ($change['path'] ?? 'value')),
            'path' => (string) ($change['path'] ?? 'value'),
            'old' => $this->formatChangeValue($change['old'] ?? NULL),
            'new' => $this->formatChangeValue($change['new'] ?? NULL),
            'type' => (string) ($change['type'] ?? 'changed'),
          ];
        }
        $files[] = $file;
      }
    }
    return $files;
  }

  public function buildImportPlan(array $diffFiles): array {
    $plan = [];
    foreach ($diffFiles as $file) {
      $status = (string) ($file['status'] ?? 'changed');
      // Import is YAML -> CiviCRM. Items which only exist in CiviCRM are export-only
      // differences and must not be presented as importable delete/removal work.
      if ($status === 'new_in_db') {
        continue;
      }
      $type = (string) ($file['type'] ?? '');
      $importable = in_array($type, $this->getImportableTypes(), TRUE);
      $plan[] = [
        'file' => $file['file'] ?? '',
        'path' => $file['path'] ?? '',
        'type' => $type,
        'type_label' => $file['type_label'] ?? $type,
        'status' => $status,
        'change_count' => $file['change_count'] ?? 0,
        'rows' => $file['rows'] ?? [],
        'importable' => $importable,
        'action' => $this->importActionLabel($status),
        'status_label' => $this->statusLabel($status),
        'note' => $this->importActionNote($status, $importable),
      ];
    }
    return $plan;
  }

  public function statusLabel(string $status): string {
    if ($status === 'missing_in_db') {
      return ts('In YAML');
    }
    if ($status === 'new_in_db') {
      return ts('In CiviCRM');
    }
    if ($status === 'changed') {
      return ts('Changed');
    }
    return ts('In Sync');
  }

  private function importActionLabel(string $status): string {
    if ($status === 'missing_in_db') {
      return ts('Create in CiviCRM');
    }
    if ($status === 'new_in_db') {
      return ts('In CiviCRM');
    }
    return ts('Update CiviCRM');
  }

  private function importActionNote(string $status, bool $importable): string {
    if (!$importable) {
      return ts('Import for this config type is not available yet.');
    }
    if ($status === 'new_in_db') {
      return ts('This exists in CiviCRM but not in YAML. Export writes it to YAML. Import will not delete it in this alpha.');
    }
    return '';
  }

  public function getImportApplyTypes(array $importPlan): array {
    $types = [];
    foreach ($importPlan as $item) {
      if (!empty($item['importable']) && !empty($item['type'])) {
        $types[] = (string) $item['type'];
      }
    }
    return array_values(array_unique($types));
  }

  public function countDiffChanges(array $result): int {
    $count = 0;
    foreach (($result['items'] ?? []) as $item) {
      $count += !empty($item['changed']) ? count($item['changed']) : 0;
      $count += !empty($item['new_in_db']) ? count($item['new_in_db']) : 0;
      $count += !empty($item['missing_in_db']) ? count($item['missing_in_db']) : 0;
    }
    return $count;
  }

  public function extractImportMessages(?array $importResult): array {
    $messages = [];
    if (!$importResult) {
      return $messages;
    }
    if (!empty($importResult['error'])) {
      $messages[] = [
        'type' => 'error',
        'title' => ts('Import'),
        'message' => (string) $importResult['error'],
      ];
    }
    if (empty($importResult['items']) || !is_array($importResult['items'])) {
      return $messages;
    }
    foreach ($importResult['items'] as $item) {
      foreach (($item['warnings'] ?? []) as $warning) {
        $messages[] = [
          'type' => 'warning',
          'title' => $this->humanizeType((string) ($item['type'] ?? 'import')),
          'message' => (string) ($warning['message'] ?? json_encode($warning)),
        ];
      }
      foreach (($item['errors'] ?? []) as $error) {
        $messages[] = [
          'type' => 'error',
          'title' => $this->humanizeType((string) ($item['type'] ?? 'import')),
          'message' => (string) ($error['message'] ?? json_encode($error)),
        ];
      }
    }
    return $messages;
  }

  public function humanizeType(string $type): string {
    return ucwords(str_replace(['-', '_'], ' ', $type));
  }

  private function getImportableTypes(): array {
    return ['extensions', 'option-groups', 'contact-types', 'relationship-types', 'location-types', 'dedupe-rules', 'scheduled-jobs', 'searchkit-saved-searches', 'searchkit-displays', 'formbuilder-afforms'];
  }

  private function humanizeChangePath(string $path): string {
    $label = $path;
    $label = preg_replace('/^values\[([^\]]+)\]\./', 'Values > $1 > ', $label);
    $label = preg_replace('/^values\[([^\]]+)\]$/', 'Values > $1', $label);
    $label = preg_replace('/^items\[([^\]]+)\]\./', 'Items > $1 > ', $label);
    $label = preg_replace('/^items\[([^\]]+)\]$/', 'Items > $1', $label);
    $label = preg_replace('/^group\./', 'Group > ', $label);
    $map = [
      'name' => 'Machine Name',
      'label' => 'Label',
      'title' => 'Title',
      'description' => 'Description',
      'value' => 'Value',
      'weight' => 'Order / Weight',
      'is_active' => 'Enabled',
      'is_reserved' => 'Reserved',
      'is_default' => 'Default',
      'is_optgroup' => 'Option Group Marker',
      'component_id' => 'Component',
      'domain_id' => 'Domain',
      'visibility_id' => 'Visibility',
      'data_type' => 'Data Type',
    ];
    foreach ($map as $machine => $human) {
      if (preg_match('/(^| > |\.)' . preg_quote($machine, '/') . '$/', $label)) {
        $label = preg_replace('/' . preg_quote($machine, '/') . '$/', $human, $label);
        break;
      }
    }
    return str_replace(['.', '>'], [' > ', '›'], $label);
  }

  private function formatChangeValue($value): string {
    if ($value === NULL || $value === '') {
      return '—';
    }
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    if (is_array($value)) {
      $parts = [];
      foreach (['name', 'label', 'title', 'value', 'weight', 'is_active'] as $key) {
        if (array_key_exists($key, $value)) {
          $parts[] = $key . ': ' . $this->formatChangeValue($value[$key]);
        }
      }
      if ($parts) {
        return implode("\n", $parts);
      }
      return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return (string) $value;
  }
}
