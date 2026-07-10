<?php
namespace Civi\ConfigManager\Service;

use Civi\ConfigManager\Storage\YamlFileStorage;
use Civi\ConfigManager\Version;

class ConfigManager {
  private HandlerRegistry $registry;

  public function __construct(?HandlerRegistry $registry = NULL) {
    $this->registry = $registry ?: new HandlerRegistry();
  }

  public function getSyncDir(): string {
    $dir = trim((string) \Civi::settings()->get('civicfg_sync_dir'));
    if ($dir === '' || $dir === '../civicrm-config') {
      $dir = 'civicrm-config';
    }

    if ($this->isUrlPath($dir)) {
      throw new \RuntimeException('Sync Directory Must Be A Server File Path, Not A URL.');
    }

    if ($dir[0] !== DIRECTORY_SEPARATOR) {
      $dir = rtrim($this->getProjectRoot(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dir;
    }

    return $this->normalizePath($dir);
  }

  public function getDefaultSyncDirSetting(): string {
    return 'civicrm-config';
  }

  public function getProjectRoot(): string {
    foreach ($this->getProjectRootCandidates() as $candidate) {
      if ($candidate !== '' && is_dir($candidate)) {
        return $candidate;
      }
    }

    $config = \CRM_Core_Config::singleton();
    if (!empty($config->configAndLogDir)) {
      return dirname((string) $config->configAndLogDir);
    }

    return (string) getcwd();
  }

  private function getProjectRootCandidates(): array {
    $candidates = [];

    if (defined('DRUPAL_ROOT')) {
      $candidates[] = DRUPAL_ROOT;
    }

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
      $candidates[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);
    }

    foreach ($this->getSettingsFileCandidates() as $settingsFile) {
      $dir = dirname($settingsFile);
      if (basename($dir) === 'default' && basename(dirname($dir)) === 'sites') {
        $candidates[] = dirname($dir, 2);
      }
      $candidates[] = dirname($settingsFile);
      $candidates[] = dirname($settingsFile, 2);
    }

    try {
      $config = \CRM_Core_Config::singleton();
      if (!empty($config->userFrameworkResourceURL)) {
        // No-op. Referencing config here keeps the method safe across CMS variants.
      }
      if (!empty($config->configAndLogDir)) {
        $candidates[] = dirname((string) $config->configAndLogDir);
        $candidates[] = dirname((string) $config->configAndLogDir, 2);
        $candidates[] = dirname((string) $config->configAndLogDir, 3);
      }
    }
    catch (\Throwable $e) {
      // Ignore config discovery errors; other candidates may still work.
    }

    $candidates[] = (string) getcwd();

    return array_values(array_unique(array_filter($candidates, 'is_string')));
  }

  private function getSettingsFileCandidates(): array {
    $candidates = [];
    if (defined('CIVICRM_SETTINGS_PATH')) {
      $candidates[] = CIVICRM_SETTINGS_PATH;
    }
    if (!empty($_SERVER['CIVICRM_SETTINGS'])) {
      $candidates[] = $_SERVER['CIVICRM_SETTINGS'];
    }
    if (!empty($_ENV['CIVICRM_SETTINGS'])) {
      $candidates[] = $_ENV['CIVICRM_SETTINGS'];
    }
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
      $candidates[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . '/sites/default/civicrm.settings.php';
    }
    return array_values(array_unique(array_filter($candidates, 'is_string')));
  }

  private function isUrlPath(string $path): bool {
    return (bool) preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $path);
  }

  private function normalizePath(string $path): string {
    $prefix = '';
    if (strpos($path, DIRECTORY_SEPARATOR) === 0) {
      $prefix = DIRECTORY_SEPARATOR;
    }

    $parts = [];
    foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
      if ($part === '' || $part === '.') {
        continue;
      }
      if ($part === '..') {
        array_pop($parts);
        continue;
      }
      $parts[] = $part;
    }

    return $prefix . implode(DIRECTORY_SEPARATOR, $parts);
  }

  public function getHandlers(): array {
    $handlers = $this->registry->getHandlers();
    $enabled = (array) \Civi::settings()->get('civicfg_enabled_types');
    $enabled = array_values(array_filter(array_map('strval', $enabled)));
    if (!$enabled) {
      return $handlers;
    }
    return array_values(array_filter($handlers, function($handler) use ($enabled) {
      return in_array($handler->getType(), $enabled, TRUE);
    }));
  }

  public function getAllHandlers(): array {
    return $this->registry->getHandlers();
  }

  public function status(): array {
    $dir = $this->getSyncDir();
    $exists = is_dir($dir);
    $parent = dirname($dir);
    $writable = $exists ? is_writable($dir) : (is_dir($parent) && is_writable($parent));
    $types = [];
    foreach ($this->getHandlers() as $handler) {
      $types[] = [
        'type' => $handler->getType(),
        'label' => $handler->getLabel(),
        'directory' => $handler->getDirectory(),
        'weight' => $handler->getWeight(),
      ];
    }
    return [
      'ok' => TRUE,
      'sync_dir' => $dir,
      'exists' => $exists,
      'writable' => $writable,
      'types' => $types,
    ];
  }

  public function getEffectiveExportTypeFilter(array $typeFilter = []): array {
    $requested = $this->normaliseTypeFilter($typeFilter);
    if (!$requested) {
      return [];
    }

    $available = [];
    foreach ($this->getHandlers() as $handler) {
      $available[$handler->getType()] = TRUE;
    }

    $expanded = [];
    foreach ($requested as $type) {
      if (isset($available[$type])) {
        $expanded[$type] = TRUE;
      }
    }

    $map = $this->getExportRelatedTypeMap();
    $changed = TRUE;
    while ($changed) {
      $changed = FALSE;
      foreach (array_keys($expanded) as $type) {
        foreach (($map[$type] ?? []) as $relatedType) {
          if (isset($available[$relatedType]) && !isset($expanded[$relatedType])) {
            $expanded[$relatedType] = TRUE;
            $changed = TRUE;
          }
        }
      }
    }

    $ordered = [];
    foreach ($this->getHandlers() as $handler) {
      $type = $handler->getType();
      if (isset($expanded[$type])) {
        $ordered[] = $type;
      }
    }
    return $ordered;
  }

  private function normaliseTypeFilter(array $typeFilter): array {
    $typeFilter = array_values(array_unique(array_filter(array_map('strval', $typeFilter))));
    if (!$typeFilter) {
      return [];
    }

    $valid = [];
    foreach ($this->getHandlers() as $handler) {
      $valid[$handler->getType()] = TRUE;
    }

    return array_values(array_filter($typeFilter, fn($type) => isset($valid[$type])));
  }

  private function getExportRelatedTypeMap(): array {
    return [
      // A SearchKit saved search is normally deployed with its displays, and
      // FormBuilder afforms may embed those displays. Export the set together.
      'searchkit-saved-searches' => ['searchkit-displays', 'formbuilder-afforms'],
      'searchkit-displays' => ['searchkit-saved-searches', 'formbuilder-afforms'],
      'formbuilder-afforms' => ['searchkit-displays', 'searchkit-saved-searches'],

      // Custom fields can depend on option groups and the contact type scope.
      'custom-data' => ['option-groups', 'contact-types', 'site-tokens'],

      // Relationship types can depend on contact/sub-contact types.
      'relationship-types' => ['contact-types'],
      'civirules' => ['extensions'],
      'site-tokens' => ['extensions'],
    ];
  }

  public function export(bool $dryRun = TRUE, array $typeFilter = []): array {
    $storage = new YamlFileStorage($this->getSyncDir());
    $requestedTypes = $this->normaliseTypeFilter($typeFilter);
    $effectiveTypes = $this->getEffectiveExportTypeFilter($requestedTypes);
    $dependencyTypes = $requestedTypes ? array_values(array_diff($effectiveTypes, $requestedTypes)) : [];
    $summary = [
      'ok' => TRUE,
      'dry_run' => $dryRun,
      'sync_dir' => $storage->getRoot(),
      'requested_types' => $requestedTypes,
      'effective_types' => $effectiveTypes,
      'dependency_types' => $dependencyTypes,
      'written' => [],
      'planned' => [],
      'skipped' => [],
      'errors' => [],
      'message' => NULL,
    ];

    if (!$dryRun) {
      $manifest = $this->getManifestData();
      if (!$storage->isSame('', 'manifest.yml', $manifest)) {
        $summary['written'][] = $storage->write('', 'manifest.yml', $manifest);
      }
      else {
        $summary['skipped'][] = 'manifest.yml';
      }
    }

    foreach ($this->getHandlers() as $handler) {
      if ($effectiveTypes && !in_array($handler->getType(), $effectiveTypes, TRUE)) {
        continue;
      }
      try {
        foreach ($handler->export() as $file) {
          $relative = trim($handler->getDirectory(), '/') . '/' . $file['filename'];
          $isSame = $storage->isSame($handler->getDirectory(), $file['filename'], $file['data']);
          if ($dryRun) {
            if (!$isSame) {
              $summary['planned'][] = $relative;
            }
            else {
              $summary['skipped'][] = $relative;
            }
          }
          else {
            if ($isSame) {
              $summary['skipped'][] = $relative;
            }
            else {
              $summary['written'][] = $storage->write($handler->getDirectory(), $file['filename'], $file['data']);
            }
          }
        }
      }
      catch (\Throwable $e) {
        $summary['errors'][] = [
          'type' => $handler->getType(),
          'message' => $e->getMessage(),
        ];
      }
    }
    $summary['ok'] = empty($summary['errors']);
    if ($dryRun && !$summary['planned'] && !$summary['errors']) {
      $summary['message'] = 'No export changes. YAML files already match the active database configuration.';
    }
    elseif (!$dryRun && !$summary['written'] && !$summary['errors']) {
      $summary['message'] = 'No files written. YAML files already match the active database configuration.';
    }
    return $summary;
  }

  public function diff(array $typeFilter = []): array {
    $storage = new YamlFileStorage($this->getSyncDir());
    $result = ['ok' => TRUE, 'sync_dir' => $storage->getRoot(), 'items' => [], 'errors' => []];
    foreach ($this->getHandlers() as $handler) {
      if ($typeFilter && !in_array($handler->getType(), $typeFilter, TRUE)) {
        continue;
      }
      try {
        $files = $storage->readDirectory($handler->getDirectory());
        $result['items'][] = $handler->diff($files);
      }
      catch (\Throwable $e) {
        $result['errors'][] = ['type' => $handler->getType(), 'message' => $e->getMessage()];
      }
    }
    $result['ok'] = empty($result['errors']);
    return $result;
  }

  public function validate(array $typeFilter = []): array {
    $storage = new YamlFileStorage($this->getSyncDir());
    $result = ['ok' => TRUE, 'sync_dir' => $storage->getRoot(), 'items' => [], 'errors' => []];
    $yamlByType = [];
    foreach ($this->getHandlers() as $handler) {
      if ($typeFilter && !in_array($handler->getType(), $typeFilter, TRUE)) {
        continue;
      }
      try {
        $files = $storage->readDirectory($handler->getDirectory());
        $yamlByType[$handler->getType()] = $files;
        $validation = $handler->validate($files);
        $result['items'][] = $validation;
        if (empty($validation['valid'])) {
          $result['ok'] = FALSE;
        }
      }
      catch (\Throwable $e) {
        $result['errors'][] = ['type' => $handler->getType(), 'message' => $e->getMessage()];
      }
    }
    $this->addDependencyWarnings($result, $yamlByType);
    $result['ok'] = $result['ok'] && empty($result['errors']);
    return $result;
  }

  private function addDependencyWarnings(array &$result, array $yamlByType): void {
    $available = $this->collectManagedYamlNames($yamlByType);
    $managedTypes = [];
    foreach ($this->getHandlers() as $handler) {
      $managedTypes[$handler->getType()] = $handler->getLabel();
    }
    $itemIndex = [];
    foreach ($result['items'] as $index => $item) {
      if (!empty($item['type'])) {
        $itemIndex[(string) $item['type']] = $index;
      }
    }

    foreach ($yamlByType as $type => $files) {
      if (!isset($itemIndex[$type])) {
        continue;
      }
      foreach ($files as $filename => $file) {
        foreach ($this->extractDependenciesFromYamlFile((array) $file) as $dependency) {
          $dependencyType = (string) ($dependency['type'] ?? '');
          $dependencyName = (string) ($dependency['name'] ?? '');
          if ($dependencyType === '' || $dependencyName === '') {
            continue;
          }
          if (!isset($managedTypes[$dependencyType])) {
            // Non-managed runtime dependencies such as api-entity are informational.
            continue;
          }
          if (!isset($available[$dependencyType][$dependencyName])) {
            $result['ok'] = FALSE;
            $reason = (string) ($dependency['reason'] ?? 'This YAML item references another managed config item.');
            $result['items'][$itemIndex[$type]]['errors'][] = [
              'file' => $filename,
              'message' => sprintf('Cannot import %s because required dependency %s "%s" is missing from YAML. %s Re-export the related items together, or restore the missing YAML file before importing.', $filename, $dependencyType, $dependencyName, $reason),
            ];
          }
        }
      }
    }
  }

  private function collectManagedYamlNames(array $yamlByType): array {
    $available = [];
    foreach ($yamlByType as $type => $files) {
      foreach ($files as $file) {
        $file = (array) $file;
        foreach ($this->namesFromYamlFile($file) as $name) {
          $available[$type][(string) $name] = TRUE;
        }
      }
    }
    return $available;
  }

  private function namesFromYamlFile(array $file): array {
    $names = [];
    if (!empty($file['name'])) {
      $names[] = (string) $file['name'];
    }
    if (!empty($file['item']) && is_array($file['item'])) {
      foreach (['name', 'title', 'name_a_b'] as $key) {
        if (!empty($file['item'][$key])) {
          $names[] = (string) $file['item'][$key];
        }
      }
    }
    foreach (($file['items'] ?? []) as $row) {
      if (is_array($row)) {
        foreach (['name', 'title', 'name_a_b'] as $key) {
          if (!empty($row[$key])) {
            $names[] = (string) $row[$key];
          }
        }
      }
    }
    return array_values(array_unique($names));
  }

  private function extractDependenciesFromYamlFile(array $file): array {
    $dependencies = [];
    foreach (($file['dependencies'] ?? []) as $dependency) {
      if (is_array($dependency)) {
        $dependencies[] = $dependency;
      }
    }
    foreach (($file['item']['dependencies'] ?? []) as $dependency) {
      if (is_array($dependency)) {
        $dependencies[] = $dependency;
      }
    }
    return $dependencies;
  }

  public function import(bool $dryRun = TRUE, bool $yes = FALSE, array $typeFilter = []): array {
    $storage = new YamlFileStorage($this->getSyncDir());
    $effectiveTypes = $this->getEffectiveExportTypeFilter($typeFilter);
    $validation = $this->validate($effectiveTypes);
    if (!$validation['ok']) {
      return [
        'ok' => FALSE,
        'dry_run' => $dryRun,
        'message' => 'Import stopped because validation failed.',
        'validation' => $validation,
      ];
    }
    $result = ['ok' => TRUE, 'dry_run' => $dryRun, 'applied' => !$dryRun && $yes, 'items' => []];
    $handlers = [];
    foreach ($this->getHandlers() as $handler) {
      if ($effectiveTypes && !in_array($handler->getType(), $effectiveTypes, TRUE)) {
        continue;
      }
      $handlers[] = $handler;
    }

    if (!$dryRun && $yes) {
      // Apply create/update first for all types, then delete missing records in
      // reverse order. This avoids deleting a parent SavedSearch while a child
      // SearchDisplay still exists and is scheduled for deletion in the same run.
      foreach ($handlers as $handler) {
        $this->setHandlerImportPhase($handler, TRUE, FALSE);
        $files = $storage->readDirectory($handler->getDirectory());
        $item = $handler->import($files, FALSE);
        $item['phase'] = 'create_update';
        $result['items'][] = $item;
        if (!empty($item['errors'])) {
          $result['ok'] = FALSE;
        }
      }
      foreach (array_reverse($handlers) as $handler) {
        $this->setHandlerImportPhase($handler, FALSE, TRUE);
        $files = $storage->readDirectory($handler->getDirectory());
        $item = $handler->import($files, FALSE);
        $item['phase'] = 'delete_missing';
        $result['items'][] = $item;
        if (!empty($item['errors'])) {
          $result['ok'] = FALSE;
        }
        $this->setHandlerImportPhase($handler, TRUE, TRUE);
      }
      $result['summary_message'] = $this->buildImportSummaryMessage($result);
      return $result;
    }

    foreach ($handlers as $handler) {
      $this->setHandlerImportPhase($handler, TRUE, TRUE);
      $files = $storage->readDirectory($handler->getDirectory());
      $item = $handler->import($files, $dryRun || !$yes);
      $result['items'][] = $item;
      if (!empty($item['errors'])) {
        $result['ok'] = FALSE;
      }
    }
    $result['summary_message'] = $this->buildImportSummaryMessage($result);
    return $result;
  }

  private function buildImportSummaryMessage(array $result): string {
    $create = $update = $delete = $skip = $errors = $warnings = 0;
    foreach (($result['items'] ?? []) as $item) {
      $create += (int) ($item['create'] ?? 0);
      $update += (int) ($item['update'] ?? 0);
      $delete += (int) ($item['delete'] ?? 0);
      $skip += (int) ($item['skip'] ?? 0);
      $errors += !empty($item['errors']) ? count($item['errors']) : 0;
      $warnings += !empty($item['warnings']) ? count($item['warnings']) : 0;
    }
    return sprintf('Import result: %d created, %d updated, %d deleted, %d skipped, %d warning(s), %d error(s).', $create, $update, $delete, $skip, $warnings, $errors);
  }

  private function setHandlerImportPhase($handler, bool $writeEnabled, bool $deleteEnabled): void {
    if (method_exists($handler, 'setImportWriteEnabled')) {
      $handler->setImportWriteEnabled($writeEnabled);
    }
    if (method_exists($handler, 'setDeleteMissingEnabled')) {
      $handler->setDeleteMissingEnabled($deleteEnabled);
    }
  }


  public function getHealth(): array {
    $status = $this->status();
    $syncDir = (string) ($status['sync_dir'] ?? $this->getSyncDir());
    $exists = !empty($status['exists']);
    $hasYaml = $exists && $this->hasYamlFiles($syncDir);

    if (!$exists) {
      return [
        'level' => 'warning',
        'title' => 'Configuration Manager: Initial export required',
        'message' => 'The sync directory does not exist yet. Run an export from Configuration Manager to create the initial YAML source files.',
        'sync_dir' => $syncDir,
        'changed' => 0,
        'in_civicrm' => 0,
        'in_yaml' => 0,
      ];
    }

    if (!$hasYaml) {
      return [
        'level' => 'warning',
        'title' => 'Configuration Manager: Initial export required',
        'message' => 'The sync directory exists but no YAML files were found. Run an export from Configuration Manager before using import as a source of truth.',
        'sync_dir' => $syncDir,
        'changed' => 0,
        'in_civicrm' => 0,
        'in_yaml' => 0,
      ];
    }

    $diff = $this->diff();
    $changed = 0;
    $inCivicrm = 0;
    $inYaml = 0;
    foreach (($diff['items'] ?? []) as $item) {
      $changed += !empty($item['changed']) ? count($item['changed']) : 0;
      $inCivicrm += !empty($item['new_in_db']) ? count($item['new_in_db']) : 0;
      $inYaml += !empty($item['missing_in_db']) ? count($item['missing_in_db']) : 0;
    }

    $total = $changed + $inCivicrm + $inYaml;
    if ($total > 0) {
      return [
        'level' => 'warning',
        'title' => 'Configuration Manager: Pending export/import changes',
        'message' => sprintf('There are %d pending configuration difference(s): %d changed, %d in CiviCRM, and %d in YAML. Review Configuration Manager and either export the CiviCRM changes to YAML or import YAML to CiviCRM.', $total, $changed, $inCivicrm, $inYaml),
        'sync_dir' => $syncDir,
        'changed' => $changed,
        'in_civicrm' => $inCivicrm,
        'in_yaml' => $inYaml,
      ];
    }

    return [
      'level' => 'info',
      'title' => 'Configuration Manager: In sync',
      'message' => 'CiviCRM configuration matches the YAML files in the sync directory.',
      'sync_dir' => $syncDir,
      'changed' => 0,
      'in_civicrm' => 0,
      'in_yaml' => 0,
    ];
  }

  private function hasYamlFiles(string $dir): bool {
    if (!is_dir($dir)) {
      return FALSE;
    }
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
      if ($file->isFile() && preg_match('/\.ya?ml$/i', $file->getFilename())) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function getManifestData(): array {
    return [
      'schema_version' => 1,
      'extension' => Version::EXTENSION_KEY,
      'format' => 'yaml',
      'exported_with' => Version::get(),
      'civicrm_min_version' => '5.0',
      'created_by' => 'Configuration Manager',
    ];
  }
}
