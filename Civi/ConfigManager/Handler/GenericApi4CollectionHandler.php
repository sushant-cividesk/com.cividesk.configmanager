<?php
namespace Civi\ConfigManager\Handler;

class GenericApi4CollectionHandler extends AbstractHandler {
  private string $type;
  private string $label;
  private string $directory;
  private string $entity;
  private array $select;
  private array $orderBy;
  private int $weight;
  private string $fileName;

  public function __construct(string $type, string $label, string $directory, string $entity, array $select, array $orderBy, int $weight, string $fileName) {
    $this->type = $type;
    $this->label = $label;
    $this->directory = $directory;
    $this->entity = $entity;
    $this->select = $select;
    $this->orderBy = $orderBy;
    $this->weight = $weight;
    $this->fileName = $fileName;
  }

  public function getType(): string { return $this->type; }
  public function getLabel(): string { return $this->label; }
  public function getDirectory(): string { return $this->directory; }
  public function getWeight(): int { return $this->weight; }

  public function export(): array {
    $rows = $this->api4Get($this->entity, [], $this->select, $this->orderBy);
    return [[
      'filename' => $this->fileName,
      'data' => [
        'schema_version' => 1,
        'type' => $this->type . '.collection',
        'entity' => $this->entity,
        'dependencies' => [],
        'items' => $rows,
      ],
    ]];
  }

  public function validate(array $items): array {
    $errors = [];
    foreach ($items as $filename => $item) {
      if (($item['type'] ?? '') !== $this->type . '.collection') {
        $errors[] = [
          'file' => $filename,
          'message' => 'Invalid type. Expected ' . $this->type . '.collection.',
        ];
        continue;
      }
      if (($item['entity'] ?? '') !== $this->entity) {
        $errors[] = [
          'file' => $filename,
          'message' => 'Invalid entity. Expected ' . $this->entity . '.',
        ];
      }
      foreach (($item['items'] ?? []) as $index => $row) {
        if (!$this->getIdentityField((array) $row)) {
          $errors[] = [
            'file' => $filename,
            'message' => 'Item at index ' . $index . ' is missing a supported identity field.',
          ];
        }
      }
    }
    return [
      'type' => $this->getType(),
      'valid' => empty($errors),
      'warnings' => [],
      'errors' => $errors,
      'count' => count($items),
    ];
  }

  public function import(array $items, bool $dryRun = TRUE): array {
    $summary = [
      'type' => $this->getType(),
      'status' => $dryRun ? 'dry_run' : 'applied',
      'dry_run' => $dryRun,
      'create' => 0,
      'update' => 0,
      'skip' => 0,
      'warnings' => [],
      'errors' => [],
    ];

    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== $this->type . '.collection') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Invalid type. Expected ' . $this->type . '.collection.'];
        continue;
      }

      foreach (($file['items'] ?? []) as $row) {
        $row = (array) $row;
        $identityField = $this->getIdentityField($row);
        if (!$identityField) {
          $summary['errors'][] = ['file' => $filename, 'message' => 'Item is missing a supported identity field.'];
          continue;
        }

        $identityValue = (string) $row[$identityField];
        $desired = $this->cleanImportValues($row);
        $existing = $this->api4GetFirst($this->entity, [[$identityField, '=', $identityValue]], ['*']);

        try {
          if ($existing) {
            if ($this->desiredDiffers($existing, $desired)) {
              $summary['update']++;
              if (!$dryRun) {
                $this->api4Update($this->entity, [['id', '=', $existing['id']]], $desired);
              }
            }
            else {
              $summary['skip']++;
            }
          }
          else {
            $summary['create']++;
            if (!$dryRun) {
              $this->api4Create($this->entity, $desired);
            }
          }
        }
        catch (\Throwable $e) {
          $summary['errors'][] = [
            'file' => $filename,
            'name' => $identityValue,
            'message' => $e->getMessage(),
          ];
        }
      }
    }

    $summary['ok'] = empty($summary['errors']);
    return $summary;
  }

  private function getIdentityField(array $row): ?string {
    foreach (['name', 'name_a_b', 'title'] as $field) {
      if (!empty($row[$field])) {
        return $field;
      }
    }
    return NULL;
  }

  private function cleanImportValues(array $row): array {
    unset($row['id']);
    return $row;
  }

  private function desiredDiffers(array $existing, array $desired): bool {
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
}
