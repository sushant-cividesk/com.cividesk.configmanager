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

  public function validate(array $items): array {
    $errors = [];
    $warnings = [];
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'message_template') {
        $errors[] = ['file' => $filename, 'message' => 'Invalid type. Expected message_template.'];
        continue;
      }
      $template = (array) ($file['template'] ?? []);
      if (empty($template['workflow_name']) && empty($template['msg_title'])) {
        $errors[] = ['file' => $filename, 'message' => 'Message template needs workflow_name or msg_title.'];
      }
      if (!array_key_exists('msg_html', $template) && !array_key_exists('msg_text', $template)) {
        $warnings[] = ['file' => $filename, 'message' => 'Message template has no msg_html or msg_text body.'];
      }
    }
    return ['type' => $this->getType(), 'valid' => empty($errors), 'warnings' => $warnings, 'errors' => $errors, 'count' => count($items)];
  }

  public function import(array $items, bool $dryRun = TRUE): array {
    $summary = $this->baseImportSummary($dryRun);
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'message_template') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Invalid type. Expected message_template.'];
        continue;
      }
      $template = $this->cleanValues((array) ($file['template'] ?? []));
      if (!$template) {
        $summary['errors'][] = ['file' => $filename, 'message' => 'No template data found.'];
        continue;
      }
      $where = $this->identityWhere($template);
      if (!$where) {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Message template needs workflow_name or msg_title.'];
        continue;
      }

      try {
        $existing = $this->api4GetFirst('MessageTemplate', $where, ['*']);
        if ($existing) {
          if ($this->desiredDiffers($existing, $template)) {
            $summary['update']++;
            if (!$dryRun) {
              $this->api4Update('MessageTemplate', [['id', '=', $existing['id']]], $template);
            }
          }
          else {
            $summary['skip']++;
          }
        }
        else {
          $summary['create']++;
          if (!$dryRun) {
            $this->api4Create('MessageTemplate', $template);
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

  private function identityWhere(array $template): array {
    if (!empty($template['workflow_name'])) {
      $where = [['workflow_name', '=', (string) $template['workflow_name']]];
      if (array_key_exists('is_default', $template)) {
        $where[] = ['is_default', '=', !empty($template['is_default'])];
      }
      return $where;
    }
    if (!empty($template['msg_title'])) {
      return [['msg_title', '=', (string) $template['msg_title']]];
    }
    return [];
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
