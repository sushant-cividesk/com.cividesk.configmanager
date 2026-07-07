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
}
