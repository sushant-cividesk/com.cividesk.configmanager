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
          'dependencies' => [],
          'group' => $group,
          'fields' => $fields,
        ],
      ];
    }
    return $files;
  }

  private function safeName(string $name): string {
    return preg_replace('/[^A-Za-z0-9_.-]+/', '_', $name);
  }
}
