<?php
namespace Civi\ConfigManager\Handler;

class CustomGroupHandler extends AbstractHandler {
  public function getType(): string { return 'custom-data'; }
  public function getLabel(): string { return 'Custom Groups and Fields'; }
  public function getDirectory(): string { return 'custom-data'; }
  public function getWeight(): int { return 60; }

  public function export(): array {
    $groups = $this->api4Get('CustomGroup', [], ['id', 'name', 'title', 'extends', 'extends_entity_column_id', 'extends_entity_column_value', 'style', 'collapse_display', 'help_pre', 'help_post', 'weight', 'is_active', 'is_multiple', 'min_multiple', 'max_multiple', 'collapse_adv_display', 'is_reserved', 'is_public'], ['name' => 'ASC']);
    $files = [];
    foreach ($groups as $group) {
      $fields = $this->api4Get('CustomField', [["custom_group_id", "=", $group['id']]], ['name', 'label', 'data_type', 'html_type', 'default_value', 'is_required', 'is_searchable', 'is_search_range', 'weight', 'help_pre', 'help_post', 'attributes', 'is_active', 'is_view', 'options_per_line', 'text_length', 'start_date_years', 'end_date_years', 'date_format', 'time_format', 'note_columns', 'note_rows', 'column_name', 'option_group_id'], ['weight' => 'ASC']);
      unset($group['id']);
      foreach ($fields as &$field) {
        unset($field['id'], $field['custom_group_id']);
      }
      $files[] = [
        'filename' => 'groups/' . $this->safeName($group['name']) . '.yml',
        'data' => [
          'schema_version' => 1,
          'type' => 'custom_group',
          'name' => $group['name'],
          'dependencies' => $this->dependenciesForGroup((array) $group, $fields),
          'group' => $group,
          'fields' => $fields,
        ],
      ];
    }
    return $files;
  }

  public function validate(array $items): array {
    $errors = [];
    $warnings = [];
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'custom_group') {
        $errors[] = ['file' => $filename, 'message' => 'Invalid type. Expected custom_group.'];
        continue;
      }
      $group = (array) ($file['group'] ?? []);
      if (empty($group['name'])) {
        $errors[] = ['file' => $filename, 'message' => 'Custom group is missing group.name.'];
      }
      foreach (($file['fields'] ?? []) as $field) {
        $field = (array) $field;
        if (empty($field['name'])) {
          $errors[] = ['file' => $filename, 'message' => 'Custom field is missing name.'];
        }
        if (!empty($field['option_group_id']) && is_numeric($field['option_group_id'])) {
          $warnings[] = ['file' => $filename, 'message' => 'Custom field ' . ($field['name'] ?? '') . ' references option_group_id by numeric id. Re-export after option groups are stable.'];
        }
      }
    }
    return ['type' => $this->getType(), 'valid' => empty($errors), 'warnings' => $warnings, 'errors' => $errors, 'count' => count($items)];
  }

  public function import(array $items, bool $dryRun = TRUE): array {
    $summary = $this->baseImportSummary($dryRun);
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'custom_group') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Invalid type. Expected custom_group.'];
        continue;
      }
      $group = $this->cleanValues((array) ($file['group'] ?? []));
      if (empty($group['name'])) {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Custom group is missing group.name.'];
        continue;
      }

      try {
        $existingGroup = $this->api4GetFirst('CustomGroup', [['name', '=', (string) $group['name']]], ['*']);
        $groupId = $existingGroup['id'] ?? NULL;
        if ($existingGroup) {
          if ($this->desiredDiffers($existingGroup, $group)) {
            $summary['update']++;
            if (!$dryRun) {
              $this->api4Update('CustomGroup', [['id', '=', $existingGroup['id']]], $group);
            }
          }
          else {
            $summary['skip']++;
          }
        }
        else {
          $summary['create']++;
          if (!$dryRun) {
            $created = $this->api4Create('CustomGroup', $group);
            $groupId = $created['id'] ?? NULL;
          }
        }

        if (!$dryRun && !$groupId) {
          $existingGroup = $this->api4GetFirst('CustomGroup', [['name', '=', (string) $group['name']]], ['id']);
          $groupId = $existingGroup['id'] ?? NULL;
        }

        foreach (($file['fields'] ?? []) as $field) {
          $field = $this->cleanValues((array) $field);
          if (empty($field['name'])) {
            $summary['errors'][] = ['file' => $filename, 'message' => 'Custom field is missing name.'];
            continue;
          }
          $existingField = $groupId ? $this->api4GetFirst('CustomField', [['custom_group_id', '=', $groupId], ['name', '=', (string) $field['name']]], ['*']) : NULL;
          if (!$dryRun && $groupId) {
            $field['custom_group_id'] = $groupId;
          }
          if ($existingField) {
            if ($this->desiredDiffers($existingField, $field)) {
              $summary['update']++;
              if (!$dryRun) {
                $this->api4Update('CustomField', [['id', '=', $existingField['id']]], $field);
              }
            }
            else {
              $summary['skip']++;
            }
          }
          else {
            $summary['create']++;
            if (!$dryRun) {
              if (!$groupId) {
                throw new \RuntimeException('Cannot create custom field without custom group id.');
              }
              $this->api4Create('CustomField', $field);
            }
          }
        }
      }
      catch (\Throwable $e) {
        $summary['errors'][] = ['file' => $filename, 'message' => $e->getMessage()];
      }
    }
    $summary['ok'] = empty($summary['errors']);
    return $summary;
  }

  private function dependenciesForGroup(array $group, array $fields): array {
    $dependencies = [];
    foreach ($fields as $field) {
      $field = (array) $field;
      if (!empty($field['option_group_id'])) {
        $dependencies[] = [
          'type' => 'option-groups',
          'entity' => 'OptionGroup',
          'id' => $field['option_group_id'],
          'reason' => 'Custom field uses this option group for choices.',
        ];
      }
    }
    return $dependencies;
  }

  private function safeName(string $name): string {
    return preg_replace('/[^A-Za-z0-9_.-]+/', '_', $name);
  }
}
