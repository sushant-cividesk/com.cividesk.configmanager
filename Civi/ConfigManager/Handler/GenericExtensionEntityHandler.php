<?php
namespace Civi\ConfigManager\Handler;

/**
 * Generic handler for configuration-like API entities provided by extensions.
 *
 * This is intentionally extension-agnostic. It discovers API4 entity classes and
 * APIv3 entity files from installed extension directories, then exports records
 * that have a stable identity field such as name/title/label. It gives every
 * exported item an extension dependency so filtered exports/imports keep the
 * providing extension with its config.
 */
class GenericExtensionEntityHandler extends AbstractHandler {
  private bool $importWritesEnabled = TRUE;
  private bool $deleteMissingEnabled = TRUE;
  private ?array $discovered = NULL;

  public function getType(): string { return 'extension-config'; }
  public function getLabel(): string { return 'Extension Entity Config'; }
  public function getDirectory(): string { return 'extension-config'; }
  public function getWeight(): int { return 150; }

  public function setImportWriteEnabled(bool $enabled): self { $this->importWritesEnabled = $enabled; return $this; }
  public function setDeleteMissingEnabled(bool $enabled): self { $this->deleteMissingEnabled = $enabled; return $this; }

  public function export(): array {
    $files = [];
    $used = [];
    foreach ($this->discoverEntities() as $definition) {
      foreach ($this->fetchRows($definition) as $row) {
        $row = $this->cleanExportRow((array) $row, $definition);
        $identityField = $this->identityField($row);
        if ($identityField === NULL) {
          continue;
        }
        $identity = (string) $row[$identityField];
        $relative = $this->fileNameFor($definition, $identity, $used);
        $files[] = [
          'filename' => $relative,
          'data' => [
            'schema_version' => 1,
            'type' => 'extension_config.item',
            'extension' => (string) $definition['extension'],
            'api' => (string) $definition['api'],
            'entity' => (string) $definition['entity'],
            'name' => $identity,
            'identity_field' => $identityField,
            'dependencies' => $this->dependenciesForRow($row, $definition),
            'item' => $row,
          ],
        ];
      }
    }
    usort($files, fn($a, $b) => strcmp($a['filename'], $b['filename']));
    return $files;
  }

  public function validate(array $items): array {
    $errors = [];
    $warnings = [];
    $definitions = $this->definitionsByKey();
    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'extension_config.item') {
        $errors[] = ['file' => $filename, 'message' => 'Invalid type. Expected extension_config.item.'];
        continue;
      }
      $extension = (string) ($file['extension'] ?? '');
      $api = (string) ($file['api'] ?? '');
      $entity = (string) ($file['entity'] ?? '');
      $key = $this->definitionKey($extension, $api, $entity);
      if ($extension === '' || $api === '' || $entity === '') {
        $errors[] = ['file' => $filename, 'message' => 'Extension config item must include extension, api, and entity.'];
        continue;
      }
      if (!isset($definitions[$key])) {
        $errors[] = ['file' => $filename, 'message' => sprintf('Provider API entity is not available: extension %s, %s entity %s. Install/enable the providing extension before import.', $extension, $api, $entity)];
        continue;
      }
      $row = (array) ($file['item'] ?? []);
      $identityField = (string) ($file['identity_field'] ?? '');
      if ($identityField === '' || empty($row[$identityField])) {
        $identityField = (string) ($this->identityField($row) ?? '');
      }
      if ($identityField === '') {
        $errors[] = ['file' => $filename, 'message' => 'Extension config item is missing a stable identity field such as name, title, label, or workflow_name. Re-export with a supported provider API.'];
      }
      if (array_key_exists('id', $row)) {
        $warnings[] = ['file' => $filename, 'message' => 'Runtime id is ignored for extension config. Re-export to remove local numeric IDs from YAML.'];
      }
    }
    return ['type' => $this->getType(), 'valid' => empty($errors), 'warnings' => $warnings, 'errors' => $errors, 'count' => count($items)];
  }

  public function import(array $items, bool $dryRun = TRUE): array {
    $summary = $this->baseImportSummary($dryRun);
    $definitions = $this->definitionsByKey();
    $desiredByEntity = [];

    foreach ($items as $filename => $file) {
      if (($file['type'] ?? '') !== 'extension_config.item') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Invalid type. Expected extension_config.item.'];
        continue;
      }
      $extension = (string) ($file['extension'] ?? '');
      $api = (string) ($file['api'] ?? '');
      $entity = (string) ($file['entity'] ?? '');
      $key = $this->definitionKey($extension, $api, $entity);
      if (!isset($definitions[$key])) {
        $summary['errors'][] = ['file' => $filename, 'message' => sprintf('Provider API entity is not available: extension %s, %s entity %s.', $extension, $api, $entity)];
        continue;
      }
      $definition = $definitions[$key];
      $row = (array) ($file['item'] ?? []);
      $identityField = (string) ($file['identity_field'] ?? '');
      if ($identityField === '' || empty($row[$identityField])) {
        $identityField = (string) ($this->identityField($row) ?? '');
      }
      if ($identityField === '') {
        $summary['errors'][] = ['file' => $filename, 'message' => 'Extension config item is missing a stable identity field.'];
        continue;
      }
      $identity = (string) $row[$identityField];
      $desiredByEntity[$key][$this->identityKey($identityField, $identity)] = TRUE;

      if (!$this->importWritesEnabled) {
        continue;
      }

      try {
        $desired = $this->cleanImportRow($row, $definition);
        $existing = $this->findExisting($definition, $identityField, $identity);
        if ($existing) {
          if ($this->desiredDiffers($existing, $desired)) {
            $summary['update']++;
            if (!$dryRun) {
              $this->updateRow($definition, (array) $existing, $desired);
            }
          }
          else {
            $summary['skip']++;
          }
        }
        else {
          $summary['create']++;
          if (!$dryRun) {
            $this->createRow($definition, $desired);
          }
        }
      }
      catch (\Throwable $e) {
        $summary['errors'][] = ['file' => $filename, 'name' => $identity, 'message' => $this->formatImportException($e, $definition, $identity)];
      }
    }

    if ($this->deleteMissingEnabled) {
      foreach ($definitions as $key => $definition) {
        $desiredKeys = $desiredByEntity[$key] ?? NULL;
        if ($desiredKeys === NULL) {
          continue;
        }
        $this->deleteMissingRows($definition, $desiredKeys, $dryRun, $summary);
      }
    }

    $summary['ok'] = empty($summary['errors']);
    return $summary;
  }

  protected function normaliseDataForDiff(array $data): array {
    return $this->stripRuntime($data);
  }

  private function discoverEntities(): array {
    if ($this->discovered !== NULL) {
      return $this->discovered;
    }
    $definitions = [];
    foreach ($this->extensionBasePaths() as $extensionKey => $basePath) {
      foreach ($this->discoverApi4Entities($extensionKey, $basePath) as $definition) {
        $definitions[$this->definitionKey($definition['extension'], $definition['api'], $definition['entity'])] = $definition;
      }
      foreach ($this->discoverApi3Entities($extensionKey, $basePath) as $definition) {
        $definitions[$this->definitionKey($definition['extension'], $definition['api'], $definition['entity'])] = $definition;
      }
    }
    uasort($definitions, function($a, $b) {
      return strcmp($a['extension'] . ':' . $a['api'] . ':' . $a['entity'], $b['extension'] . ':' . $b['api'] . ':' . $b['entity']);
    });
    $this->discovered = array_values($definitions);
    return $this->discovered;
  }

  private function definitionsByKey(): array {
    $definitions = [];
    foreach ($this->discoverEntities() as $definition) {
      $definitions[$this->definitionKey($definition['extension'], $definition['api'], $definition['entity'])] = $definition;
    }
    return $definitions;
  }

  private function extensionBasePaths(): array {
    $paths = [];
    try {
      $system = \CRM_Extension_System::singleton();
      $manager = $system->getManager();
      $mapper = method_exists($system, 'getMapper') ? $system->getMapper() : NULL;
      foreach ((array) $manager->getStatuses() as $key => $status) {
        $status = strtolower((string) $status);
        if (!in_array($status, ['installed', 'enabled'], TRUE)) {
          continue;
        }
        $base = '';
        if ($mapper && method_exists($mapper, 'keyToBasePath')) {
          $base = (string) $mapper->keyToBasePath((string) $key);
        }
        elseif ($mapper && method_exists($mapper, 'getBasePath')) {
          $base = (string) $mapper->getBasePath((string) $key);
        }
        if ($base !== '' && is_dir($base)) {
          $paths[(string) $key] = rtrim($base, DIRECTORY_SEPARATOR);
        }
      }
    }
    catch (\Throwable $e) {
      // Discovery is best-effort.
    }
    ksort($paths, SORT_NATURAL | SORT_FLAG_CASE);
    return $paths;
  }

  private function discoverApi4Entities(string $extensionKey, string $basePath): array {
    $dir = $basePath . '/Civi/Api4';
    if (!is_dir($dir)) {
      return [];
    }
    $definitions = [];
    foreach (glob($dir . '/*.php') ?: [] as $file) {
      $entity = basename($file, '.php');
      if ($entity === 'ConfigManager') {
        continue;
      }
      $class = 'Civi\\Api4\\' . $entity;
      if (!class_exists($class)) {
        continue;
      }
      if (!$this->api4EntityUsable($entity)) {
        continue;
      }
      $definitions[] = [
        'extension' => $extensionKey,
        'api' => 'api4',
        'entity' => $entity,
        'class' => $class,
        'fields' => $this->api4Fields($entity),
      ];
    }
    return $definitions;
  }

  private function discoverApi3Entities(string $extensionKey, string $basePath): array {
    $dir = $basePath . '/api/v3';
    if (!is_dir($dir) || !function_exists('civicrm_api3')) {
      return [];
    }
    $definitions = [];
    foreach (glob($dir . '/*.php') ?: [] as $file) {
      $entity = basename($file, '.php');
      if ($entity === '' || in_array(strtolower($entity), ['utils'], TRUE)) {
        continue;
      }
      if (!$this->api3EntityUsable($entity)) {
        continue;
      }
      $definitions[] = [
        'extension' => $extensionKey,
        'api' => 'api3',
        'entity' => $entity,
        'fields' => [],
      ];
    }
    return $definitions;
  }

  private function api4EntityUsable(string $entity): bool {
    $class = 'Civi\\Api4\\' . $entity;
    if (!class_exists($class) || !method_exists($class, 'get')) {
      return FALSE;
    }
    try {
      $class::get(FALSE)->setLimit(1)->execute();
      return TRUE;
    }
    catch (\Throwable $e) {
      return FALSE;
    }
  }

  private function api3EntityUsable(string $entity): bool {
    try {
      civicrm_api3($entity, 'get', ['sequential' => 1, 'options' => ['limit' => 1]]);
      return TRUE;
    }
    catch (\Throwable $e) {
      return FALSE;
    }
  }

  private function api4Fields(string $entity): array {
    $class = 'Civi\\Api4\\' . $entity;
    if (!class_exists($class) || !method_exists($class, 'getFields')) {
      return [];
    }
    try {
      $rows = (array) $class::getFields(FALSE)->execute();
      $fields = [];
      foreach ($rows as $row) {
        $row = (array) $row;
        if (!empty($row['name'])) {
          $fields[(string) $row['name']] = $row;
        }
      }
      return $fields;
    }
    catch (\Throwable $e) {
      return [];
    }
  }

  private function fetchRows(array $definition): array {
    if ($definition['api'] === 'api4') {
      $class = (string) $definition['class'];
      try {
        return (array) $class::get(FALSE)->addSelect('*')->execute();
      }
      catch (\Throwable $e) {
        return [];
      }
    }
    try {
      $result = civicrm_api3((string) $definition['entity'], 'get', ['sequential' => 1, 'options' => ['limit' => 0]]);
      return array_values((array) ($result['values'] ?? []));
    }
    catch (\Throwable $e) {
      return [];
    }
  }

  private function cleanExportRow(array $row, array $definition): array {
    $row = $this->stripRuntime($row);
    if ($definition['api'] === 'api4') {
      $row = $this->stripReadOnlyFields($row, (array) ($definition['fields'] ?? []));
    }
    ksort($row, SORT_NATURAL | SORT_FLAG_CASE);
    return $row;
  }

  private function cleanImportRow(array $row, array $definition): array {
    $row = $this->stripRuntime($row);
    if ($definition['api'] === 'api4') {
      $row = $this->stripReadOnlyFields($row, (array) ($definition['fields'] ?? []));
    }
    return $row;
  }

  private function stripRuntime(array $row): array {
    unset($row['id'], $row['created_date'], $row['modified_date'], $row['created_id'], $row['modified_id']);
    foreach ($row as $key => $value) {
      if (is_array($value)) {
        $row[$key] = $this->stripRuntime($value);
      }
    }
    return $row;
  }

  private function stripReadOnlyFields(array $row, array $fields): array {
    foreach ($fields as $name => $field) {
      $field = (array) $field;
      if (!empty($field['readonly']) || !empty($field['read_only']) || (($field['type'] ?? '') === 'Extra')) {
        unset($row[$name]);
      }
    }
    return $row;
  }

  private function identityField(array $row): ?string {
    foreach (['name', 'title', 'label', 'workflow_name', 'machine_name', 'key'] as $field) {
      if (!empty($row[$field]) && is_scalar($row[$field])) {
        return $field;
      }
    }
    return NULL;
  }

  private function findExisting(array $definition, string $identityField, string $identity): ?array {
    if ($definition['api'] === 'api4') {
      return $this->api4GetFirst((string) $definition['entity'], [[$identityField, '=', $identity]], ['*']);
    }
    try {
      $result = civicrm_api3((string) $definition['entity'], 'get', ['sequential' => 1, $identityField => $identity, 'options' => ['limit' => 1]]);
      $values = array_values((array) ($result['values'] ?? []));
      return $values[0] ?? NULL;
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }

  private function createRow(array $definition, array $values): array {
    if ($definition['api'] === 'api4') {
      return $this->api4Create((string) $definition['entity'], $values);
    }
    $result = civicrm_api3((string) $definition['entity'], 'create', $values + ['sequential' => 1]);
    $values = array_values((array) ($result['values'] ?? []));
    return $values[0] ?? [];
  }

  private function updateRow(array $definition, array $existing, array $values): array {
    if ($definition['api'] === 'api4') {
      return $this->api4Update((string) $definition['entity'], [['id', '=', (int) $existing['id']]], $values);
    }
    if (empty($existing['id'])) {
      throw new \RuntimeException('Existing APIv3 row has no id, so it cannot be updated safely.');
    }
    $values['id'] = (int) $existing['id'];
    $result = civicrm_api3((string) $definition['entity'], 'create', $values + ['sequential' => 1]);
    $rows = array_values((array) ($result['values'] ?? []));
    return $rows[0] ?? [];
  }

  private function deleteMissingRows(array $definition, array $desiredKeys, bool $dryRun, array &$summary): void {
    foreach ($this->fetchRows($definition) as $existing) {
      $existing = (array) $existing;
      if (empty($existing['id'])) {
        continue;
      }
      $identityField = $this->identityField($existing);
      if ($identityField === NULL) {
        continue;
      }
      $identity = (string) $existing[$identityField];
      if (isset($desiredKeys[$this->identityKey($identityField, $identity)])) {
        continue;
      }
      $summary['delete']++;
      $summary['warnings'][] = [
        'name' => $identity,
        'message' => sprintf('%s %s exists in CiviCRM but not in YAML and will be deleted when import is applied: %s', $definition['api'], $definition['entity'], $identity),
      ];
      if (!$dryRun) {
        try {
          $this->deleteRow($definition, (int) $existing['id']);
        }
        catch (\Throwable $e) {
          $summary['errors'][] = ['name' => $identity, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
      }
    }
  }

  private function deleteRow(array $definition, int $id): void {
    if ($definition['api'] === 'api4') {
      $this->api4Delete((string) $definition['entity'], [['id', '=', $id]]);
      return;
    }
    civicrm_api3((string) $definition['entity'], 'delete', ['id' => $id]);
  }

  private function dependenciesForRow(array $row, array $definition): array {
    $dependencies = [[
      'type' => 'extensions',
      'entity' => 'Extension',
      'name' => (string) $definition['extension'],
      'reason' => 'This configuration is provided by this extension API entity.',
    ]];

    $json = json_encode($row);
    if ($json) {
      preg_match_all('/afsearch[A-Za-z0-9_:-]+/', $json, $matches);
      foreach (array_values(array_unique($matches[0] ?? [])) as $name) {
        $dependencies[] = [
          'type' => 'searchkit-displays',
          'entity' => 'SearchDisplay',
          'name' => $name,
          'reason' => 'Extension configuration references this SearchKit display.',
        ];
      }
    }
    return $this->uniqueDependencies($dependencies);
  }

  private function fileNameFor(array $definition, string $identity, array &$used): string {
    $base = $this->safePart((string) $definition['extension']) . '/' . $this->safePart((string) $definition['api']) . '/' . $this->safePart((string) $definition['entity']) . '/' . $this->safePart($identity);
    $candidate = $base;
    $i = 2;
    while (isset($used[$candidate])) {
      $candidate = $base . '_' . $i;
      $i++;
    }
    $used[$candidate] = TRUE;
    return $candidate . '.yml';
  }

  private function definitionKey(string $extension, string $api, string $entity): string {
    return strtolower($extension . '|' . $api . '|' . $entity);
  }

  private function identityKey(string $field, string $value): string {
    return $field . ':' . $value;
  }

  private function safePart(string $value): string {
    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $value);
    return trim((string) $safe, '-') ?: sha1($value);
  }

  private function uniqueDependencies(array $dependencies): array {
    $seen = [];
    $unique = [];
    foreach ($dependencies as $dependency) {
      $key = json_encode($dependency);
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      $unique[] = $dependency;
    }
    return $unique;
  }

  private function formatImportException(\Throwable $e, array $definition, string $identity): string {
    $message = $e->getMessage();
    if (stripos($message, 'already exists') !== FALSE || stripos($message, 'duplicate') !== FALSE) {
      return sprintf('Target already has a conflicting %s %s record for %s. Import skipped this item to avoid creating a duplicate. Original error: %s', $definition['api'], $definition['entity'], $identity, $message);
    }
    return $message;
  }
}
