<?php
namespace Civi\ConfigManager\Handler;

class FinancialTypeHandler extends AbstractHandler {
  public function getType(): string { return 'financial-types'; }
  public function getLabel(): string { return 'Financial Types'; }
  public function getDirectory(): string { return 'financial'; }
  public function getWeight(): int { return 40; }

  public function export(): array {
    $rows = $this->api4Get('FinancialType', [], ['name', 'label', 'description', 'is_deductible', 'is_reserved', 'is_active'], ['name' => 'ASC']);
    return [[
      'filename' => 'financial-types.yml',
      'data' => [
        'schema_version' => 1,
        'type' => 'financial_type.collection',
        'dependencies' => [],
        'items' => $rows,
      ],
    ]];
  }

  public function import(array $items, bool $dryRun = TRUE): array {
    $summary = $this->baseImportSummary($dryRun);
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'financial_type.collection') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Invalid type. Expected financial_type.collection.'];
        continue;
      }
      foreach (($file['items'] ?? []) as $row) {
        $row = $this->cleanValues((array) $row);
        if (empty($row['name'])) {
          $summary['errors'][] = ['file' => $filename, 'message' => 'Financial type is missing name.'];
          continue;
        }
        try {
          $existing = $this->api4GetFirst('FinancialType', [['name', '=', (string) $row['name']]], ['*']);
          if ($existing) {
            if ($this->desiredDiffers($existing, $row)) {
              $summary['update']++;
              if (!$dryRun) {
                $this->api4Update('FinancialType', [['id', '=', $existing['id']]], $row);
              }
            }
            else {
              $summary['skip']++;
            }
          }
          else {
            $summary['create']++;
            if (!$dryRun) {
              $this->api4Create('FinancialType', $row);
            }
          }
        }
        catch (\Throwable $e) {
          $summary['errors'][] = ['file' => $filename, 'name' => $row['name'], 'message' => $e->getMessage()];
        }
      }
    }
    $summary['ok'] = empty($summary['errors']);
    return $summary;
  }
}
