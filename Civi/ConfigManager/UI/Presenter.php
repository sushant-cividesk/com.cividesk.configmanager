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


  public function labelsForTypes(ConfigManager $manager, array $types): array {
    $wanted = array_fill_keys(array_map('strval', $types), TRUE);
    $labels = [];
    foreach ($manager->getAllHandlers() as $handler) {
      if (isset($wanted[$handler->getType()])) {
        $labels[$handler->getType()] = $handler->getLabel();
      }
    }
    return $labels;
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
            'old' => $this->formatChangeValue($change['old'] ?? NULL, $change['new'] ?? NULL),
            'new' => $this->formatChangeValue($change['new'] ?? NULL, $change['old'] ?? NULL),
            'old_html' => $this->formatChangeValueHtml($change['old'] ?? NULL, $change['new'] ?? NULL),
            'new_html' => $this->formatChangeValueHtml($change['new'] ?? NULL, $change['old'] ?? NULL),
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
      return ts('Delete from CiviCRM');
    }
    return ts('Update CiviCRM');
  }

  private function importActionNote(string $status, bool $importable): string {
    if (!$importable) {
      return ts('Import for this config type is not available yet.');
    }
    if ($status === 'new_in_db') {
      return ts('This exists in CiviCRM but not in YAML. Import treats YAML as the source of truth and will delete this record after confirmation. Export first if you want to keep it.');
    }
    if ($status === 'missing_in_db') {
      return ts('This exists in YAML but not in CiviCRM. Import will recreate it. CiviCRM may assign a new numeric database ID.');
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


  public function firstImportProblem(?array $importResult): string {
    if (!$importResult) {
      return '';
    }
    if (!empty($importResult['validation']['items'])) {
      foreach ($importResult['validation']['items'] as $item) {
        foreach (($item['errors'] ?? []) as $error) {
          $file = !empty($error['file']) ? ((string) $error['file'] . ': ') : '';
          return $this->humanizeType((string) ($item['type'] ?? 'validation')) . ': ' . $file . (string) ($error['message'] ?? json_encode($error));
        }
      }
    }
    foreach (($importResult['items'] ?? []) as $item) {
      foreach (($item['errors'] ?? []) as $error) {
        $file = !empty($error['file']) ? ((string) $error['file'] . ': ') : '';
        return $this->humanizeType((string) ($item['type'] ?? 'import')) . ': ' . $file . (string) ($error['message'] ?? json_encode($error));
      }
    }
    return '';
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
    if (!empty($importResult['validation']['items']) && is_array($importResult['validation']['items'])) {
      foreach ($importResult['validation']['items'] as $item) {
        foreach (($item['warnings'] ?? []) as $warning) {
          $messages[] = [
            'type' => 'warning',
            'title' => $this->humanizeType((string) ($item['type'] ?? 'validation')),
            'message' => (string) ($warning['message'] ?? json_encode($warning)),
          ];
        }
        foreach (($item['errors'] ?? []) as $error) {
          $messages[] = [
            'type' => 'error',
            'title' => $this->humanizeType((string) ($item['type'] ?? 'validation')),
            'message' => (string) ($error['message'] ?? json_encode($error)),
          ];
        }
      }
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
    return ['extensions', 'option-groups', 'contact-types', 'relationship-types', 'location-types', 'financial-types', 'custom-data', 'settings', 'site-tokens', 'message-templates', 'dedupe-rules', 'scheduled-jobs', 'searchkit-saved-searches', 'searchkit-displays', 'formbuilder-afforms', 'civirules'];
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

  private function formatChangeValue($value, $other = NULL): string {
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
    $value = (string) $value;
    return $this->truncateLongValue($value, $other);
  }

  private function formatChangeValueHtml($value, $other = NULL): string {
    $text = $this->formatChangeValue($value, $other);
    if (!is_string($value) || !is_string($other) || $value === $other || strlen($value) < 200) {
      return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return $this->highlightChangedText($value, $other);
  }

  private function truncateLongValue(string $value, $other = NULL): string {
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $lines = explode("\n", $value);
    $maxLines = 18;
    $maxChars = 1800;
    if (is_string($other) && $other !== '' && $value !== $other && (count($lines) > $maxLines || strlen($value) > $maxChars)) {
      return $this->focusedTextExcerpt($value, $other);
    }
    if (count($lines) > $maxLines) {
      $value = implode("\n", array_slice($lines, 0, $maxLines)) . "\n... (preview truncated; use Show Diff Text or open the YAML file for full content)";
    }
    if (strlen($value) > $maxChars) {
      $value = substr($value, 0, $maxChars) . "\n... (preview truncated)";
    }
    return $value;
  }

  private function focusedTextExcerpt(string $value, string $other, int $context = 220): string {
    [$start, $endValue] = $this->changedRange($value, $other);
    $from = max(0, $start - $context);
    $length = min(strlen($value), $endValue + $context) - $from;
    $excerpt = substr($value, $from, $length);
    $prefix = $from > 0 ? "...\n" : '';
    $suffix = ($from + $length) < strlen($value) ? "\n..." : '';
    if ($excerpt === '') {
      return '[empty at changed position]';
    }
    return $prefix . $excerpt . $suffix;
  }

  private function highlightChangedText(string $value, string $other, int $context = 220): string {
    [$start, $endValue] = $this->changedRange($value, $other);
    $from = max(0, $start - $context);
    $to = min(strlen($value), $endValue + $context);
    $before = substr($value, $from, $start - $from);
    $changed = substr($value, $start, max(0, $endValue - $start));
    $after = substr($value, $endValue, $to - $endValue);
    $html = '';
    if ($from > 0) {
      $html .= htmlspecialchars("...\n", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    $html .= htmlspecialchars($before, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($changed === '') {
      $html .= '<mark class="civicfg-diff-empty">[missing here]</mark>';
    }
    else {
      $html .= '<mark>' . htmlspecialchars($changed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</mark>';
    }
    $html .= htmlspecialchars($after, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($to < strlen($value)) {
      $html .= htmlspecialchars("\n...", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return $html;
  }

  private function changedRange(string $value, string $other): array {
    $valueLen = strlen($value);
    $otherLen = strlen($other);
    $start = 0;
    $maxStart = min($valueLen, $otherLen);
    while ($start < $maxStart && $value[$start] === $other[$start]) {
      $start++;
    }
    $valueEnd = $valueLen;
    $otherEnd = $otherLen;
    while ($valueEnd > $start && $otherEnd > $start && $value[$valueEnd - 1] === $other[$otherEnd - 1]) {
      $valueEnd--;
      $otherEnd--;
    }
    return [$start, $valueEnd];
  }
}

