<?php
namespace Civi\ConfigManager\Handler;

class PaymentProcessorHandler extends AbstractHandler {
  public function getType(): string { return 'payment-processors'; }
  public function getLabel(): string { return 'Payment Processors'; }
  public function getDirectory(): string { return 'payment-processors'; }
  public function getWeight(): int { return 50; }

  public function export(): array {
    $rows = $this->api4Get('PaymentProcessor', [], ['name', 'title', 'description', 'payment_processor_type_id', 'is_active', 'is_default', 'is_test', 'user_name', 'url_site', 'url_api', 'url_recur', 'url_button', 'class_name', 'billing_mode', 'financial_account_id', 'payment_instrument_id'], ['name' => 'ASC']);
    foreach ($rows as &$row) {
      foreach (['password', 'signature', 'subject'] as $secret) {
        if (array_key_exists($secret, $row)) {
          $row[$secret] = '__SECRET_NOT_EXPORTED__';
        }
      }
      $row['secrets_exported'] = FALSE;
    }
    return [[
      'filename' => 'processors.yml',
      'data' => [
        'schema_version' => 1,
        'type' => 'payment_processor.collection',
        'dependencies' => [
          'option_groups' => ['payment_instrument'],
          'financial_types' => [],
        ],
        'items' => $rows,
      ],
    ]];
  }
}
