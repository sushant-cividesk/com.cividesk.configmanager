<?php
namespace Civi\ConfigManager\Handler;

use Civi\ConfigManager\Util\SimpleYaml;

abstract class AbstractHandler implements HandlerInterface {

  public function import(array $items, bool $dryRun = TRUE): array {
    return [
      'type' => $this->getType(),
      'status' => 'not_implemented',
      'dry_run' => $dryRun,
      'message' => 'Import handler is planned but not implemented for this config type yet.',
      'count' => count($items),
      'create' => 0,
      'update' => 0,
      'skip' => count($items),
      'warnings' => [],
      'errors' => [],
    ];
  }

  public function diff(array $items): array {
    $exported = $this->export();
    $dbItems = [];
    foreach ($exported as $file) {
      if (!empty($file['filename'])) {
        $dbItems[$file['filename']] = $file['data'] ?? [];
      }
    }
    ksort($dbItems);
    ksort($items);

    $newInDb = array_values(array_diff(array_keys($dbItems), array_keys($items)));
    $missingInDb = array_values(array_diff(array_keys($items), array_keys($dbItems)));
    $changed = [];
    $files = [];

    foreach (array_intersect(array_keys($dbItems), array_keys($items)) as $filename) {
      if ($this->fingerprint($dbItems[$filename]) !== $this->fingerprint($items[$filename])) {
        $fieldChanges = $this->structuredChanges($items[$filename], $dbItems[$filename]);
        if ($fieldChanges) {
          $changed[] = $filename;
          $files[] = $this->buildDiffFile($filename, 'changed', $items[$filename], $dbItems[$filename], $fieldChanges);
        }
      }
    }

    foreach ($newInDb as $filename) {
      $files[] = $this->buildDiffFile($filename, 'new_in_db', [], $dbItems[$filename], $this->structuredChanges([], $dbItems[$filename]));
    }

    foreach ($missingInDb as $filename) {
      $files[] = $this->buildDiffFile($filename, 'missing_in_db', $items[$filename], [], $this->structuredChanges($items[$filename], []));
    }

    $status = 'in_sync';
    if ($newInDb || $missingInDb || $changed) {
      $status = 'changed';
    }

    return [
      'type' => $this->getType(),
      'label' => $this->getLabel(),
      'db_count' => count($dbItems),
      'file_count' => count($items),
      'status' => $status,
      'changed' => $changed,
      'new_in_db' => $newInDb,
      'missing_in_db' => $missingInDb,
      'files' => $files,
    ];
  }

  public function validate(array $items): array {
    return [
      'type' => $this->getType(),
      'valid' => TRUE,
      'warnings' => [],
      'errors' => [],
      'count' => count($items),
    ];
  }

  protected function fingerprint(array $data): string {
    $normalised = $this->normaliseData($data);
    return sha1(json_encode($normalised));
  }

  protected function normaliseData($data) {
    if (is_array($data)) {
      $isList = array_keys($data) === range(0, count($data) - 1);
      $normalised = [];
      foreach ($data as $key => $value) {
        $normalised[$key] = $this->normaliseData($value);
      }
      if (!$isList) {
        ksort($normalised);
      }
      return $normalised;
    }
    if ($data === NULL || $data === '') {
      return '';
    }
    if (is_bool($data)) {
      return $data ? '1' : '0';
    }
    if (is_int($data) || is_float($data)) {
      return (string) $data;
    }
    return (string) $data;
  }

  /**
   * Build focused, field-level changes. Lists of records with a 'name' key are
   * compared by that machine name so the UI shows only meaningful changed fields.
   */
  protected function structuredChanges($old, $new, string $path = ''): array {
    $changes = [];
    $old = $this->normaliseStructuredValue($old);
    $new = $this->normaliseStructuredValue($new);

    if (is_array($old) && is_array($new)) {
      if ($this->isNamedList($old) || $this->isNamedList($new)) {
        $oldMap = $this->namedListToMap($old);
        $newMap = $this->namedListToMap($new);
        $keys = array_values(array_unique(array_merge(array_keys($oldMap), array_keys($newMap))));
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($keys as $key) {
          $childPath = $path . '[' . $key . ']';
          if (!array_key_exists($key, $oldMap)) {
            $changes[] = [
              'path' => $childPath,
              'type' => 'added',
              'old' => NULL,
              'new' => $newMap[$key],
            ];
          }
          elseif (!array_key_exists($key, $newMap)) {
            $changes[] = [
              'path' => $childPath,
              'type' => 'removed',
              'old' => $oldMap[$key],
              'new' => NULL,
            ];
          }
          else {
            $changes = array_merge($changes, $this->structuredChanges($oldMap[$key], $newMap[$key], $childPath));
          }
        }
        return $changes;
      }

      $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
      sort($keys, SORT_NATURAL | SORT_FLAG_CASE);
      foreach ($keys as $key) {
        $childPath = $path === '' ? (string) $key : $path . '.' . $key;
        if (!array_key_exists($key, $old)) {
          $changes[] = [
            'path' => $childPath,
            'type' => 'added',
            'old' => NULL,
            'new' => $new[$key],
          ];
        }
        elseif (!array_key_exists($key, $new)) {
          $changes[] = [
            'path' => $childPath,
            'type' => 'removed',
            'old' => $old[$key],
            'new' => NULL,
          ];
        }
        else {
          $changes = array_merge($changes, $this->structuredChanges($old[$key], $new[$key], $childPath));
        }
      }
      return $changes;
    }

    if ($this->normaliseScalar($old) !== $this->normaliseScalar($new)) {
      $changes[] = [
        'path' => $path === '' ? 'value' : $path,
        'type' => 'changed',
        'old' => $old,
        'new' => $new,
      ];
    }
    return $changes;
  }

  protected function normaliseStructuredValue($value) {
    if (is_array($value)) {
      $result = [];
      foreach ($value as $key => $child) {
        $result[$key] = $this->normaliseStructuredValue($child);
      }
      return $result;
    }
    if ($value === NULL || $value === '') {
      return NULL;
    }
    if (is_bool($value) || is_int($value) || is_float($value)) {
      return $value;
    }
    return (string) $value;
  }

  private function isNamedList($value): bool {
    if (!is_array($value) || array_keys($value) !== range(0, count($value) - 1)) {
      return FALSE;
    }
    if (!$value) {
      return FALSE;
    }
    foreach ($value as $item) {
      if (!is_array($item) || !array_key_exists('name', $item)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function namedListToMap(array $list): array {
    $map = [];
    foreach ($list as $index => $item) {
      if (is_array($item) && array_key_exists('name', $item)) {
        $name = (string) $item['name'];
        $key = $name !== '' ? $name : 'index:' . $index;
        $map[$key] = $item;
      }
      else {
        $map['index:' . $index] = $item;
      }
    }
    return $map;
  }

  private function normaliseScalar($value): string {
    if ($value === NULL || $value === '') {
      return '';
    }
    if (is_bool($value)) {
      return $value ? '1' : '0';
    }
    if (is_array($value)) {
      return json_encode($this->normaliseData($value));
    }
    return (string) $value;
  }

  protected function buildDiffFile(string $filename, string $status, array $fileData, array $dbData, array $fieldChanges = []): array {
    $relative = trim($this->getDirectory(), '/') . '/' . $filename;
    return [
      'file' => $filename,
      'path' => $relative,
      'status' => $status,
      'change_count' => count($fieldChanges),
      'changes' => $fieldChanges,
      'diff' => $this->fieldDiff($relative, $fieldChanges),
    ];
  }

  protected function fieldDiff(string $relative, array $changes): string {
    $diff = [];
    $diff[] = 'diff --git a/' . $relative . ' b/' . $relative;
    $diff[] = '--- a/' . $relative;
    $diff[] = '+++ b/' . $relative;
    if (!$changes) {
      $diff[] = '@@ no field-level differences @@';
      return implode("\n", $diff);
    }
    $maxChanges = 120;
    $shown = 0;
    foreach ($changes as $change) {
      if ($shown >= $maxChanges) {
        $diff[] = '... diff truncated for UI preview ...';
        break;
      }
      $path = (string) ($change['path'] ?? 'value');
      $diff[] = '@@ ' . $path . ' @@';
      if (($change['type'] ?? '') === 'added') {
        $diff[] = '+ ' . $path . ': ' . $this->formatDiffValue($change['new'] ?? NULL);
      }
      elseif (($change['type'] ?? '') === 'removed') {
        $diff[] = '- ' . $path . ': ' . $this->formatDiffValue($change['old'] ?? NULL);
      }
      else {
        $old = $change['old'] ?? NULL;
        $new = $change['new'] ?? NULL;
        if (is_string($old) && is_string($new) && ($this->isLargeText($old) || $this->isLargeText($new))) {
          foreach ($this->formatFocusedTextDiff($path, $old, $new) as $line) {
            $diff[] = $line;
          }
        }
        else {
          $diff[] = '- ' . $path . ': ' . $this->formatDiffValue($old);
          $diff[] = '+ ' . $path . ': ' . $this->formatDiffValue($new);
        }
      }
      $shown++;
    }
    return implode("\n", $diff);
  }

  protected function isLargeText(string $value): bool {
    return strlen($value) > 800 || substr_count($value, "\n") > 12;
  }

  protected function formatFocusedTextDiff(string $path, string $old, string $new): array {
    [$oldStart, $oldEnd, $newStart, $newEnd] = $this->changedRanges($old, $new);
    $oldExcerpt = $this->excerptForDiff($old, $oldStart, $oldEnd);
    $newExcerpt = $this->excerptForDiff($new, $newStart, $newEnd);
    return [
      '- ' . $path . ': ' . $oldExcerpt,
      '+ ' . $path . ': ' . $newExcerpt,
    ];
  }

  protected function excerptForDiff(string $value, int $start, int $end, int $context = 240): string {
    $from = max(0, $start - $context);
    $to = min(strlen($value), $end + $context);
    $excerpt = substr($value, $from, max(0, $to - $from));
    $prefix = $from > 0 ? "...\n" : '';
    $suffix = $to < strlen($value) ? "\n..." : '';
    if ($excerpt === '') {
      return '[empty at changed position]';
    }
    return $prefix . $excerpt . $suffix;
  }

  protected function changedRanges(string $old, string $new): array {
    $oldLen = strlen($old);
    $newLen = strlen($new);
    $start = 0;
    $maxStart = min($oldLen, $newLen);
    while ($start < $maxStart && $old[$start] === $new[$start]) {
      $start++;
    }
    $oldEnd = $oldLen;
    $newEnd = $newLen;
    while ($oldEnd > $start && $newEnd > $start && $old[$oldEnd - 1] === $new[$newEnd - 1]) {
      $oldEnd--;
      $newEnd--;
    }
    return [$start, $oldEnd, $start, $newEnd];
  }

  protected function formatDiffValue($value): string {
    if (is_array($value)) {
      $yaml = trim(SimpleYaml::dump($value));
      $lines = explode("\n", $yaml);
      if (count($lines) > 12) {
        $lines = array_slice($lines, 0, 12);
        $lines[] = '...';
      }
      return implode("\n  ", $lines);
    }
    if ($value === NULL || $value === '') {
      return "''";
    }
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    $value = (string) $value;
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $lines = explode("\n", $value);
    if (count($lines) > 20) {
      $value = implode("\n", array_slice($lines, 0, 20)) . "\n... (diff value truncated for preview)";
    }
    if (strlen($value) > 2400) {
      $value = substr($value, 0, 2400) . "\n... (diff value truncated for preview)";
    }
    return $value;
  }

  protected function api4Get(string $entity, array $where = [], array $select = ['*'], array $orderBy = []): array {
    $class = 'Civi\\Api4\\' . $entity;
    if (!class_exists($class)) {
      return [];
    }
    $action = $class::get(FALSE)->addSelect(...$select);
    foreach ($where as $condition) {
      $action->addWhere(...$condition);
    }
    foreach ($orderBy as $field => $direction) {
      $action->addOrderBy($field, $direction);
    }
    return (array) $action->execute();
  }

  protected function api4GetFirst(string $entity, array $where, array $select = ['*']): ?array {
    $rows = $this->api4Get($entity, $where, $select);
    return $rows[0] ?? NULL;
  }

  protected function api4Create(string $entity, array $values): array {
    $class = 'Civi\\Api4\\' . $entity;
    if (!class_exists($class)) {
      throw new \RuntimeException("API4 entity not available: {$entity}");
    }
    $action = $class::create(FALSE);
    foreach ($values as $field => $value) {
      $action->addValue($field, $value);
    }
    $result = (array) $action->execute();
    return $result[0] ?? [];
  }

  protected function api4Update(string $entity, array $where, array $values): array {
    $class = 'Civi\\Api4\\' . $entity;
    if (!class_exists($class)) {
      throw new \RuntimeException("API4 entity not available: {$entity}");
    }
    $action = $class::update(FALSE);
    foreach ($where as $condition) {
      $action->addWhere(...$condition);
    }
    foreach ($values as $field => $value) {
      $action->addValue($field, $value);
    }
    return (array) $action->execute();
  }

  protected function api4Delete(string $entity, array $where): array {
    $class = 'Civi\\Api4\\' . $entity;
    if (!class_exists($class)) {
      throw new \RuntimeException("API4 entity not available: {$entity}");
    }
    $action = $class::delete(FALSE);
    foreach ($where as $condition) {
      $action->addWhere(...$condition);
    }
    return (array) $action->execute();
  }

  protected function cleanValues(array $values, array $remove = ['id']): array {
    foreach ($remove as $field) {
      unset($values[$field]);
    }
    return $values;
  }

  protected function baseImportSummary(bool $dryRun): array {
    return [
      'type' => $this->getType(),
      'status' => $dryRun ? 'dry_run' : 'applied',
      'dry_run' => $dryRun,
      'create' => 0,
      'update' => 0,
      'skip' => 0,
      'warnings' => [],
      'errors' => [],
    ];
  }

  protected function desiredDiffers(array $existing, array $desired): bool {
    foreach ($desired as $key => $value) {
      if (!array_key_exists($key, $existing)) {
        continue;
      }
      if ($this->normaliseComparableValue($existing[$key]) !== $this->normaliseComparableValue($value)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  protected function normaliseComparableValue($value) {
    if ($value === NULL || $value === '') {
      return '';
    }
    if (is_bool($value)) {
      return $value ? '1' : '0';
    }
    if (is_array($value)) {
      ksort($value);
      return json_encode($value);
    }
    return (string) $value;
  }

}
