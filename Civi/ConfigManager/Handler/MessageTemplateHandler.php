<?php
namespace Civi\ConfigManager\Handler;

class MessageTemplateHandler extends AbstractHandler {
  public function getType(): string { return 'message-templates'; }
  public function getLabel(): string { return 'Message Templates'; }
  public function getDirectory(): string { return 'message-templates'; }
  public function getWeight(): int { return 90; }

  public function export(): array {
    $rows = $this->api4Get('MessageTemplate', [], ['id', 'msg_title', 'msg_subject', 'msg_text', 'msg_html', 'workflow_name', 'is_default', 'is_reserved', 'is_active'], ['workflow_name' => 'ASC', 'msg_title' => 'ASC', 'id' => 'ASC']);
    $files = [];
    $used = [];
    foreach ($rows as $row) {
      $folder = !empty($row['workflow_name']) || !empty($row['is_reserved']) ? 'system' : 'user';
      $name = $row['workflow_name'] ?: $row['msg_title'];
      $base = $name;
      if (!empty($row['workflow_name']) && array_key_exists('is_default', $row)) {
        $base .= !empty($row['is_default']) ? '_default' : '_custom';
      }
      unset($row['id']);
      $filename = $folder . '/' . $this->uniqueFileName($base, $used) . '.yml';
      $files[] = [
        'filename' => $filename,
        'data' => [
          'schema_version' => 1,
          'type' => 'message_template',
          'name' => $name,
          'dependencies' => [],
          'template' => $row,
        ],
      ];
    }
    return $files;
  }

  private function uniqueFileName(string $name, array &$used): string {
    $base = $this->safeName($name);
    $candidate = $base;
    $i = 2;
    while (isset($used[$candidate])) {
      $candidate = $base . '_' . $i;
      $i++;
    }
    $used[$candidate] = TRUE;
    return $candidate;
  }

  private function safeName(string $name): string {
    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', strtolower($name));
    return trim($safe, '_') ?: 'message_template';
  }
}
