<?php
namespace Civi\ConfigManager\UI;

use Civi\ConfigManager\Service\ConfigManager;
use Exception;
use RuntimeException;

/**
 * Controller for the admin UI. Keeps the CRM page class thin and delegates
 * presentation/file-transfer details to focused helper classes.
 */
class MainPage {
  private \CRM_Core_Page $page;
  private ConfigManager $manager;
  private Request $request;
  private Presenter $presenter;
  private FileTransfer $files;
  private Permission $permission;

  public function __construct(\CRM_Core_Page $page, ?ConfigManager $manager = NULL) {
    $this->page = $page;
    $this->manager = $manager ?: new ConfigManager();
    $this->request = new Request();
    $this->presenter = new Presenter();
    $this->files = new FileTransfer();
    $this->permission = new Permission();
  }

  public function run(): void {
    \CRM_Utils_System::setTitle(ts('Configuration Manager'));

    $op = $this->request->getOperation();
    $postAction = $this->request->getPostAction();
    $types = $this->request->getSelectedTypes();
    $notice = NULL;
    $validationResult = NULL;
    $importResult = NULL;
    $result = [];

    $this->permission->requireForPage($op, $postAction);

    try {
      if ($op === 'single-export-json') {
        $this->files->jsonSingleExport($this->manager);
      }
      elseif ($op === 'download-archive') {
        $this->files->downloadArchive($this->manager);
      }
      elseif ($op === 'download-single') {
        $this->files->downloadSingleExport($this->manager);
      }
      elseif ($postAction === 'import_single_yaml') {
        $notice = $this->files->uploadSingleYaml($this->manager);
        $this->redirectWithNotice($notice, 'import', 'success');
      }
      elseif ($postAction === 'import_zip_archive') {
        $notice = $this->files->uploadZipArchive($this->manager);
        $this->redirectWithNotice($notice, 'import', 'success');
      }
      elseif ($postAction === 'save_settings') {
        $this->saveSettings();
        \CRM_Core_Session::setStatus(ts('Configuration Manager settings saved.'), ts('Saved'), 'success');
        \CRM_Utils_System::redirect(\CRM_Utils_System::url('civicrm/admin/config-manager', 'reset=1&op=settings'));
      }
      elseif ($postAction === 'export_write') {
        $requestedTypes = $types;
        $exportResult = $this->manager->export(FALSE, $requestedTypes);
        $written = !empty($exportResult['written']) ? count($exportResult['written']) : 0;
        $skipped = !empty($exportResult['skipped']) ? count($exportResult['skipped']) : 0;
        $dependencyTypes = (array) ($exportResult['dependency_types'] ?? []);
        $notice = $written
          ? ts('Export complete. %1 YAML file(s) updated. %2 unchanged file(s) skipped.', [1 => $written, 2 => $skipped])
          : ts('Nothing to export. YAML files already match active CiviCRM configuration.');
        if ($dependencyTypes) {
          $notice .= ' ' . ts('Related dependency types were included automatically: %1.', [1 => implode(', ', $dependencyTypes)]);
        }
        if ($requestedTypes) {
          $notice .= ' ' . ts('The temporary filter was cleared so the Synchronize tab now shows the full managed status.');
        }
        $this->redirectWithNotice($notice, 'sync', empty($exportResult['errors']) ? 'success' : 'error');
      }
      elseif ($postAction === 'import_apply') {
        $importTypes = $this->request->getSelectedTypes();
        $importResult = $this->manager->import(FALSE, TRUE, $importTypes ?: []);
        $afterDiff = $this->manager->diff([]);
        $remaining = $this->presenter->countDiffChanges($afterDiff);
        if (!empty($importResult['ok']) && $remaining === 0) {
          $notice = ts('Import complete. YAML files and active CiviCRM configuration now match.');
          $type = 'success';
        }
        elseif (!empty($importResult['ok'])) {
          $notice = ts('Import ran, but %1 pending change(s) remain. Review the remaining changes below.', [1 => $remaining]);
          $type = 'warning';
        }
        else {
          $notice = ts('Import found problems. Review the warnings or errors below.');
          $type = 'error';
        }
        $this->redirectWithNotice($notice, 'sync', $type);
      }
      elseif ($postAction === 'validate_files') {
        $validationResult = $this->manager->validate($types);
        $notice = !empty($validationResult['ok'])
          ? ts('Validation passed. No YAML format problems were found for the selected files.')
          : ts('Validation found problems. Review the validation details below.');
        $this->redirectWithNotice($notice, 'sync', !empty($validationResult['ok']) ? 'success' : 'warning');
      }
      elseif ($op === 'import') {
        $result = $this->manager->diff($types);
      }
      elseif ($op === 'export') {
        $result = $this->manager->export(TRUE, $types);
      }
      elseif ($op === 'settings') {
        $result = $this->manager->status();
      }
      else {
        $op = 'sync';
        $result = $this->manager->diff($types);
      }
    }
    catch (Exception $e) {
      $result = [
        'ok' => FALSE,
        'error' => $e->getMessage(),
      ];
    }

    $this->assignTemplate($op, $types, $result, $notice, $validationResult, $importResult);
  }


  private function redirectWithNotice(string $message, string $op = 'sync', string $type = 'success'): void {
    \CRM_Core_Session::setStatus($message, ts('Configuration Manager'), $type);
    \CRM_Utils_System::redirect(\CRM_Utils_System::url('civicrm/admin/config-manager', 'reset=1&op=' . $op));
  }

  private function getCodeDefinedSyncDir(): ?string {
    global $civicrm_setting;

    $fromGlobal = $this->readSyncDirFromSettingsArray($civicrm_setting ?? []);
    if ($fromGlobal !== NULL) {
      return $fromGlobal;
    }

    foreach ($this->getSettingsFileCandidates() as $file) {
      $fromFile = $this->readSyncDirFromSettingsFile($file);
      if ($fromFile !== NULL) {
        return $fromFile;
      }
    }

    return NULL;
  }

  private function readSyncDirFromSettingsArray($settings): ?string {
    if (!is_array($settings)) {
      return NULL;
    }
    foreach (['domain', 'Domain', 'CiviCRM Preferences'] as $group) {
      if (isset($settings[$group]['civicfg_sync_dir']) && trim((string) $settings[$group]['civicfg_sync_dir']) !== '') {
        return (string) $settings[$group]['civicfg_sync_dir'];
      }
    }
    return NULL;
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

    try {
      $config = \CRM_Core_Config::singleton();
      if (!empty($config->configAndLogDir)) {
        $dir = rtrim((string) $config->configAndLogDir, DIRECTORY_SEPARATOR);
        $candidates[] = dirname($dir, 3) . DIRECTORY_SEPARATOR . 'civicrm.settings.php';
        $candidates[] = dirname($dir, 2) . DIRECTORY_SEPARATOR . 'civicrm.settings.php';
      }
    }
    catch (\Throwable $e) {
      // Ignore config discovery errors; other candidates may still work.
    }

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
      $candidates[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . '/sites/default/civicrm.settings.php';
    }

    return array_values(array_unique(array_filter($candidates, 'is_string')));
  }

  private function readSyncDirFromSettingsFile(string $file): ?string {
    if ($file === '' || !is_file($file) || !is_readable($file)) {
      return NULL;
    }
    $contents = (string) file_get_contents($file);
    if (strpos($contents, 'civicfg_sync_dir') === FALSE) {
      return NULL;
    }
    $pattern = '/\$civicrm_setting\s*\[\s*[\"\']domain[\"\']\s*\]\s*\[\s*[\"\']civicfg_sync_dir[\"\']\s*\]\s*=\s*([\"\'])(.*?)\1\s*;/s';
    if (preg_match($pattern, $contents, $matches) && trim($matches[2]) !== '') {
      return stripcslashes($matches[2]);
    }
    return NULL;
  }

  private function saveSettings(): void {
    if ($this->getCodeDefinedSyncDir() === NULL) {
      $syncDir = trim((string) ($_POST['sync_dir'] ?? ''));
      if ($syncDir === '') {
        throw new RuntimeException('Sync Directory Is Required.');
      }
      if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $syncDir)) {
        throw new RuntimeException('Sync Directory Must Be A Server File Path, Not A URL.');
      }
      \Civi::settings()->set('civicfg_sync_dir', $syncDir);
    }

    $enabled = $_POST['enabled_types'] ?? [];
    if (!is_array($enabled)) {
      $enabled = [];
    }
    $valid = [];
    foreach ($this->manager->getAllHandlers() as $handler) {
      $valid[] = $handler->getType();
    }
    $enabled = array_values(array_intersect($valid, array_map('strval', $enabled)));
    \Civi::settings()->set('civicfg_enabled_types', $enabled);

    $allowlistRaw = (string) ($_POST['settings_allowlist'] ?? '');
    $allowlist = preg_split('/[\r\n,]+/', $allowlistRaw);
    $allowlist = array_values(array_unique(array_filter(array_map('trim', $allowlist))));
    \Civi::settings()->set('civicfg_settings_allowlist', $allowlist);
  }


  private function getSyncDirLockMessage(): string {
    return ts('This value is set in civicrm.settings.php and cannot be edited from the UI.');
  }

  private function assignTemplate(string $op, array $types, array $result, $notice, $validationResult, $importResult): void {
    $status = $this->manager->status();
    $diffResult = $result;
    if (empty($diffResult['items']) || !is_array($diffResult['items'])) {
      try {
        $diffResult = $this->manager->diff($types);
      }
      catch (Exception $e) {
        $diffResult = [
          'ok' => FALSE,
          'error' => $e->getMessage(),
          'items' => [],
        ];
      }
    }
    $allTypes = $this->presenter->buildTypeRows($this->manager, $diffResult);
    $enabledTypes = (array) \Civi::settings()->get('civicfg_enabled_types');
    $settingsAllowlist = (array) \Civi::settings()->get('civicfg_settings_allowlist');
    $diffFiles = $this->presenter->extractDiffFiles($diffResult);
    $importPlan = $this->presenter->buildImportPlan($diffFiles);
    $importApplyTypes = $this->presenter->getImportApplyTypes($importPlan);

    if ($op === 'import' && $importResult === NULL && $importApplyTypes) {
      try {
        $importResult = $this->manager->import(TRUE, FALSE, $importApplyTypes);
      }
      catch (Exception $e) {
        $importResult = [
          'ok' => FALSE,
          'error' => $e->getMessage(),
        ];
      }
    }

    $effectiveExportTypes = $this->manager->getEffectiveExportTypeFilter($types);
    $exportDependencyTypes = $types ? array_values(array_diff($effectiveExportTypes, $types)) : [];
    $exportItems = $this->files->buildExportItems($this->manager, $types);
    $selectedExportItem = $this->request->getSingleExportKey();
    $singleExport = NULL;
    if ($selectedExportItem !== '') {
      try {
        $singleExport = $this->files->loadSingleExport($this->manager, $selectedExportItem);
        $singleExport['has_value'] = TRUE;
      }
      catch (Exception $e) {
        $singleExport = ['error' => $e->getMessage(), 'has_value' => FALSE];
      }
    }

    $allTypeKeys = array_map(fn($row) => (string) $row['type'], $allTypes);
    $selectedTypesMap = array_fill_keys($allTypeKeys, FALSE);
    foreach ($types as $type) {
      $selectedTypesMap[(string) $type] = TRUE;
    }
    $enabledTypesMap = array_fill_keys($allTypeKeys, FALSE);
    foreach ($enabledTypes as $type) {
      $enabledTypesMap[(string) $type] = TRUE;
    }
    $importApplyTypesMap = array_fill_keys($allTypeKeys, FALSE);
    foreach ($importApplyTypes as $type) {
      $importApplyTypesMap[(string) $type] = TRUE;
    }
    $result += [
      'error' => NULL,
      'errors' => [],
      'items' => [],
      'planned' => [],
      'written' => [],
      'skipped' => [],
      'requested_types' => [],
      'effective_types' => [],
      'dependency_types' => [],
    ];
    $singleExportDefaults = [
      'error' => NULL,
      'has_value' => FALSE,
      'key' => '',
      'type' => '',
      'label' => '',
      'directory' => '',
      'file' => '',
      'path' => '',
      'yaml' => '',
      'download_url' => '',
    ];
    $singleExport = is_array($singleExport) ? ($singleExport + $singleExportDefaults) : $singleExportDefaults;

    $assetLoader = new AssetLoader();
    $assetLoader->addResources();

    $this->page->assign('criticalCss', $assetLoader->getCriticalCss());
    $this->page->assign('op', $op);
    $this->page->assign('notice', $notice);
    $this->page->assign('result', $result);
    $this->page->assign('importResult', $importResult);
    $this->page->assign('importMessages', $this->presenter->extractImportMessages($importResult));
    $this->page->assign('validationResult', $validationResult);
    $this->page->assign('status', $status);
    $this->page->assign('allTypes', $allTypes);
    $this->page->assign('selectedTypes', $types);
    $this->page->assign('selectedTypesMap', $selectedTypesMap);
    $this->page->assign('enabledTypes', $enabledTypes);
    $this->page->assign('enabledTypesMap', $enabledTypesMap);
    $this->page->assign('settingsAllowlist', implode("\n", $settingsAllowlist));
    $codeDefinedSyncDir = $this->getCodeDefinedSyncDir();
    $savedSyncDir = trim((string) \Civi::settings()->get('civicfg_sync_dir'));
    if ($savedSyncDir === '' || $savedSyncDir === '../civicrm-config') {
      $savedSyncDir = $this->manager->getDefaultSyncDirSetting();
    }
    $this->page->assign('syncDir', $codeDefinedSyncDir ?: $savedSyncDir);
    $this->page->assign('syncDirLocked', $codeDefinedSyncDir !== NULL);
    $this->page->assign('syncDirLockValue', $codeDefinedSyncDir ?: '');
    $this->page->assign('syncDirLockMessage', $this->getSyncDirLockMessage());
    $this->page->assign('tabs', $this->presenter->buildTabs($op));
    $this->page->assign('summary', $this->presenter->buildSummary($diffResult, $status, $op));
    $this->page->assign('diffResult', $diffResult);
    $this->page->assign('diffFiles', $diffFiles);
    $this->page->assign('importPlan', $importPlan);
    $this->page->assign('importApplyTypes', $importApplyTypes);
    $this->page->assign('importApplyTypesMap', $importApplyTypesMap);
    $this->page->assign('effectiveExportTypes', $effectiveExportTypes);
    $this->page->assign('exportDependencyTypes', $exportDependencyTypes);
    $this->page->assign('exportDependencyTypeLabels', $this->presenter->labelsForTypes($this->manager, $exportDependencyTypes));
    $this->page->assign('exportItems', $exportItems);
    $this->page->assign('selectedExportItem', $selectedExportItem);
    $this->page->assign('singleExport', $singleExport);
    $this->page->assign('zipAvailable', class_exists('ZipArchive'));
    $this->page->assign('canExport', Permission::has(Permission::EXPORT));
    $this->page->assign('canImport', Permission::has(Permission::IMPORT));
    $this->page->assign('canAdminister', Permission::has(Permission::ADMINISTER));
  }
}
