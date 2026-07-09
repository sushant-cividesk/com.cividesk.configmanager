<?php
namespace Civi\ConfigManager\Handler;

class OptionGroupHandler extends AbstractHandler {
  public function getType(): string { return 'option-groups'; }
  public function getLabel(): string { return 'Option Groups and Values'; }
  public function getDirectory(): string { return 'option-groups'; }
  public function getWeight(): int { return 20; }

  public function export(): array {
    $groups = $this->api4Get('OptionGroup', [], ['id', 'name', 'title', 'description', 'data_type', 'is_reserved', 'is_active'], ['name' => 'ASC']);
    $files = [];
    foreach ($groups as $group) {
      $values = $this->api4Get('OptionValue', [["option_group_id", "=", $group['id']]], ['name', 'label', 'value', 'description', 'weight', 'is_default', 'is_optgroup', 'is_reserved', 'is_active', 'component_id', 'domain_id', 'visibility_id'], ['weight' => 'ASC', 'name' => 'ASC']);
      unset($group['id']);
      foreach ($values as &$value) {
        unset($value['id'], $value['option_group_id']);
      }
      $files[] = [
        'filename' => $this->safeName($group['name']) . '.yml',
        'data' => [
          'schema_version' => 1,
          'type' => 'option_group',
          'name' => $group['name'],
          'dependencies' => [],
          'group' => $group,
          'values' => array_values($values),
        ],
      ];
    }
    return $files;
  }

  public function validate(array $items): array {
    $errors = [];
    $warnings = [];
    foreach ($items as $filename => $item) {
      if (($item['type'] ?? '') !== 'option_group') {
        $errors[] = [
          'file' => $filename,
          'message' => 'Invalid type. Expected option_group.',
        ];
        continue;
      }
      $groupName = (string) ($item['name'] ?? '');
      $groupArrayName = (string) ($item['group']['name'] ?? '');
      if ($groupName === '' || $groupArrayName === '') {
        $errors[] = [
          'file' => $filename,
          'message' => 'Missing option group machine name.',
        ];
      }
      elseif ($groupName !== $groupArrayName) {
        $errors[] = [
          'file' => $filename,
          'message' => 'Top-level option group name and group.name do not match.',
        ];
      }

      $names = [];
      $values = [];
      foreach (($item['values'] ?? []) as $index => $value) {
        $name = (string) ($value['name'] ?? '');
        $optionValue = array_key_exists('value', $value) ? (string) $value['value'] : '';
        if ($name === '') {
          $errors[] = [
            'file' => $filename,
            'message' => 'Option value at index ' . $index . ' is missing name.',
          ];
          continue;
        }
        if (isset($names[$name])) {
          $errors[] = [
            'file' => $filename,
            'message' => 'Duplicate option value machine name: ' . $name,
          ];
        }
        $names[$name] = TRUE;
        if ($optionValue !== '') {
          if (isset($values[$optionValue]) && $values[$optionValue] !== $name) {
            $warnings[] = [
              'file' => $filename,
              'message' => 'Two option values use the same value "' . $optionValue . '" (' . $values[$optionValue] . ' and ' . $name . '). This may indicate a machine-name rename and may not import safely.',
            ];
          }
          $values[$optionValue] = $name;
        }
      }
    }
    return [
      'type' => $this->getType(),
      'valid' => empty($errors),
      'warnings' => $warnings,
      'errors' => $errors,
      'count' => count($items),
    ];
  }

  public function import(array $items, bool $dryRun = TRUE): array {
    $summary = [
      'type' => $this->getType(),
      'status' => $dryRun ? 'dry_run' : 'applied',
      'dry_run' => $dryRun,
      'groups' => ['create' => 0, 'update' => 0, 'skip' => 0],
      'values' => ['create' => 0, 'update' => 0, 'delete' => 0, 'skip' => 0],
      'warnings' => [],
      'errors' => [],
    ];

    foreach ($items as $filename => $item) {
      if (($item['type'] ?? '') !== 'option_group') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Invalid type. Expected option_group.'];
        continue;
      }
      $group = $item['group'] ?? [];
      $groupName = (string) ($item['name'] ?? $group['name'] ?? '');
      if ($groupName === '') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Missing option group machine name.'];
        continue;
      }
      $group['name'] = $groupName;
      $desiredGroup = $this->cleanGroupValues($group);
      $existingGroup = $this->api4GetFirst('OptionGroup', [['name', '=', $groupName]], ['*']);

      try {
        if ($existingGroup) {
          if ($this->desiredDiffers($existingGroup, $desiredGroup)) {
            $summary['groups']['update']++;
            if (!$dryRun) {
              $this->api4Update('OptionGroup', [['id', '=', $existingGroup['id']]], $desiredGroup);
            }
          }
          else {
            $summary['groups']['skip']++;
          }
          $groupId = $existingGroup['id'];
        }
        else {
          $summary['groups']['create']++;
          if (!$dryRun) {
            $created = $this->api4Create('OptionGroup', $desiredGroup);
            $groupId = $created['id'] ?? NULL;
          }
          else {
            $groupId = NULL;
          }
        }

        $desiredValueNames = [];
        foreach (($item['values'] ?? []) as $value) {
          $valueName = (string) ($value['name'] ?? '');
          if ($valueName === '') {
            $summary['errors'][] = ['file' => $filename, 'message' => 'Option value is missing name.'];
            continue;
          }
          $desiredValueNames[$valueName] = TRUE;

          $desiredValue = $this->cleanOptionValueValues($value);
          $existingValue = NULL;
          $machineNameConflict = NULL;
          if ($existingGroup) {
            $existingValue = $this->api4GetFirst('OptionValue', [
              ['option_group_id', '=', $existingGroup['id']],
              ['name', '=', $valueName],
            ], ['*']);

            if (!$existingValue && array_key_exists('value', $desiredValue) && $desiredValue['value'] !== NULL && $desiredValue['value'] !== '') {
              $machineNameConflict = $this->api4GetFirst('OptionValue', [
                ['option_group_id', '=', $existingGroup['id']],
                ['value', '=', (string) $desiredValue['value']],
              ], ['id', 'name', 'label', 'value']);
              if ($machineNameConflict && ($machineNameConflict['name'] ?? '') !== $valueName) {
                $summary['warnings'][] = [
                  'file' => $filename,
                  'name' => $valueName,
                  'message' => 'Machine name appears to have changed from "' . $machineNameConflict['name'] . '" to "' . $valueName . '" for option value "' . $desiredValue['value'] . '". Machine names are identities and are not renamed automatically. Revert the name, or create a new option value with a new unique value.',
                ];
                $summary['values']['skip']++;
                continue;
              }
            }
          }

          if ($existingValue) {
            if ($this->desiredDiffers($existingValue, $desiredValue)) {
              $summary['values']['update']++;
              if (!$dryRun) {
                $this->api4Update('OptionValue', [['id', '=', $existingValue['id']]], $desiredValue);
              }
            }
            else {
              $summary['values']['skip']++;
            }
          }
          else {
            $summary['values']['create']++;
            if (!$dryRun) {
              if (empty($groupId)) {
                $summary['errors'][] = ['file' => $filename, 'message' => 'Could not resolve option_group_id for option value ' . $valueName . '.'];
                continue;
              }
              $desiredValue['option_group_id'] = $groupId;
              $this->api4Create('OptionValue', $desiredValue);
            }
          }
        }

        if ($existingGroup) {
          $this->warnExtraOptionValues($existingGroup, $desiredValueNames, $filename, $summary);
        }
      }
      catch (\Throwable $e) {
        $summary['errors'][] = [
          'file' => $filename,
          'name' => $groupName,
          'message' => $e->getMessage(),
        ];
      }
    }

    $summary['ok'] = empty($summary['errors']);
    return $summary;
  }

  private function warnExtraOptionValues(array $existingGroup, array $desiredValueNames, string $filename, array &$summary): void {
    if (empty($existingGroup['id'])) {
      return;
    }

    $existingValues = $this->api4Get('OptionValue', [
      ['option_group_id', '=', $existingGroup['id']],
    ], ['id', 'name', 'label', 'value']);

    foreach ($existingValues as $existingValue) {
      $existingName = (string) ($existingValue['name'] ?? '');
      if ($existingName === '' || isset($desiredValueNames[$existingName])) {
        continue;
      }

      $summary['warnings'][] = [
        'file' => $filename,
        'name' => $existingName,
        'message' => 'Option value exists in CiviCRM but not in YAML. It was left unchanged by this handler; delete support for individual option values is still conservative.',
      ];
    }
  }

  protected function desiredDiffers(array $existing, array $desired): bool {
    foreach ($desired as $key => $value) {
      if (!array_key_exists($key, $existing)) {
        continue;
      }
      if ($this->normaliseComparable($existing[$key]) !== $this->normaliseComparable($value)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function normaliseComparable($value) {
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

  private function cleanGroupValues(array $group): array {
    return $this->cleanValues($group, ['id']);
  }

  private function cleanOptionValueValues(array $value): array {
    return $this->cleanValues($value, ['id']);
  }

  private function safeName(string $name): string {
    return preg_replace('/[^A-Za-z0-9_.-]+/', '_', $name);
  }
}
