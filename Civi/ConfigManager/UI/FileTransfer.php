<?php
namespace Civi\ConfigManager\UI;

use Civi\ConfigManager\Service\ConfigManager;
use Civi\ConfigManager\Util\SimpleYaml;
use RuntimeException;
use Throwable;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

/**
 * Handles import/export file transfers and single-file preview/downloads.
 */
class FileTransfer {

  public function buildExportItems(ConfigManager $manager, array $typeFilter = []): array {
    $items = [];
    foreach ($manager->getHandlers() as $handler) {
      if ($typeFilter && !in_array($handler->getType(), $typeFilter, TRUE)) {
        continue;
      }
      try {
        foreach ($handler->export() as $file) {
          if (empty($file['filename'])) {
            continue;
          }
          $key = $handler->getType() . '::' . $file['filename'];
          $items[] = [
            'key' => $key,
            'type' => $handler->getType(),
            'label' => $handler->getLabel(),
            'directory' => $handler->getDirectory(),
            'file' => $file['filename'],
            'path' => trim($handler->getDirectory(), '/') . '/' . $file['filename'],
          ];
        }
      }
      catch (\Exception $e) {
        // Keep the export page available even if one handler has an error.
      }
    }
    usort($items, function($a, $b) {
      return strcmp($a['path'], $b['path']);
    });
    return $items;
  }

  public function loadSingleExport(ConfigManager $manager, string $key): array {
    [$type, $filename] = $this->splitExportKey($key);
    foreach ($manager->getHandlers() as $handler) {
      if ($handler->getType() !== $type) {
        continue;
      }
      foreach ($handler->export() as $file) {
        if (($file['filename'] ?? '') === $filename) {
          $yaml = SimpleYaml::dump($file['data'] ?? []);
          return [
            'key' => $key,
            'type' => $type,
            'label' => $handler->getLabel(),
            'directory' => $handler->getDirectory(),
            'file' => $filename,
            'path' => trim($handler->getDirectory(), '/') . '/' . $filename,
            'yaml' => $yaml,
            'download_url' => \CRM_Utils_System::url('civicrm/admin/config-manager', 'reset=1&op=download-single&export_item=' . rawurlencode($key)),
          ];
        }
      }
    }
    throw new RuntimeException('Selected export item was not found.');
  }

  public function jsonSingleExport(ConfigManager $manager): void {
    try {
      $key = isset($_REQUEST['export_item']) ? trim((string) $_REQUEST['export_item']) : '';
      if ($key === '') {
        throw new RuntimeException('Choose a configuration item to preview.');
      }
      $item = $this->loadSingleExport($manager, $key);
      $payload = [
        'ok' => TRUE,
        'key' => $item['key'],
        'type' => $item['type'],
        'label' => $item['label'],
        'file' => $item['file'],
        'path' => $item['path'],
        'yaml' => $item['yaml'],
        'download_url' => $item['download_url'],
      ];
    }
    catch (Throwable $e) {
      $payload = [
        'ok' => FALSE,
        'error' => $e->getMessage(),
      ];
    }

    \CRM_Utils_System::setHttpHeader('Content-Type', 'application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    \CRM_Utils_System::civiExit();
  }

  public function downloadSingleExport(ConfigManager $manager): void {
    $key = isset($_REQUEST['export_item']) ? trim((string) $_REQUEST['export_item']) : '';
    if ($key === '') {
      throw new RuntimeException('Choose a configuration item before downloading a single YAML file.');
    }
    $item = $this->loadSingleExport($manager, $key);
    \CRM_Utils_System::setHttpHeader('Content-Type', 'text/yaml; charset=utf-8');
    \CRM_Utils_System::setHttpHeader('Content-Disposition', 'attachment; filename="' . basename($item['file']) . '"');
    echo $item['yaml'];
    \CRM_Utils_System::civiExit();
  }

  public function uploadSingleYaml(ConfigManager $manager): string {
    $type = trim((string) ($_POST['single_type'] ?? ''));
    $filename = trim((string) ($_POST['single_filename'] ?? ''));
    if ($type === '') {
      throw new RuntimeException('Choose a configuration type before uploading a YAML file.');
    }
    if (empty($_FILES['single_yaml']['tmp_name']) || !is_uploaded_file($_FILES['single_yaml']['tmp_name'])) {
      throw new RuntimeException('Choose a YAML file to upload.');
    }
    if ($filename === '') {
      $filename = basename((string) ($_FILES['single_yaml']['name'] ?? ''));
    }
    if (!$this->isSafeRelativeYamlPath($filename)) {
      throw new RuntimeException('The YAML filename must be a relative .yml or .yaml path without .. segments.');
    }
    $handler = $this->getHandlerByType($manager, $type);
    if (!$handler) {
      throw new RuntimeException('Unknown configuration type: ' . $type);
    }
    $parsed = SimpleYaml::parseFile($_FILES['single_yaml']['tmp_name']);
    if (!is_array($parsed) || !$parsed) {
      throw new RuntimeException('The uploaded YAML file could not be parsed.');
    }
    $targetRoot = rtrim($manager->getSyncDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($handler->getDirectory(), DIRECTORY_SEPARATOR);
    $target = $targetRoot . DIRECTORY_SEPARATOR . $filename;
    $this->safeWriteUploadedFile($_FILES['single_yaml']['tmp_name'], $target, $manager->getSyncDir());
    return ts('YAML file uploaded to %1. Review Synchronize before importing.', [1 => trim($handler->getDirectory(), '/') . '/' . $filename]);
  }

  public function uploadZipArchive(ConfigManager $manager): string {
    if (!class_exists('ZipArchive')) {
      throw new RuntimeException('ZipArchive is not available in PHP.');
    }
    if (empty($_FILES['zip_archive']['tmp_name']) || !is_uploaded_file($_FILES['zip_archive']['tmp_name'])) {
      throw new RuntimeException('Choose a ZIP archive to upload.');
    }
    $zip = new ZipArchive();
    if ($zip->open($_FILES['zip_archive']['tmp_name']) !== TRUE) {
      throw new RuntimeException('Could not open the uploaded ZIP archive.');
    }
    $syncRoot = rtrim($manager->getSyncDir(), DIRECTORY_SEPARATOR);
    $written = 0;
    $skipped = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $name = $zip->getNameIndex($i);
      if (substr($name, -1) === '/') {
        continue;
      }
      if (!$this->isSafeRelativeYamlPath($name)) {
        $skipped++;
        continue;
      }
      $stream = $zip->getStream($name);
      if (!$stream) {
        $skipped++;
        continue;
      }
      $contents = stream_get_contents($stream);
      fclose($stream);
      $tmp = tempnam(sys_get_temp_dir(), 'civicfg-yml-');
      file_put_contents($tmp, $contents);
      try {
        $parsed = SimpleYaml::parseFile($tmp);
        if (!is_array($parsed) || !$parsed) {
          $skipped++;
          @unlink($tmp);
          continue;
        }
      }
      catch (Throwable $e) {
        $skipped++;
        @unlink($tmp);
        continue;
      }
      $target = $syncRoot . DIRECTORY_SEPARATOR . $name;
      $this->safeWriteContents($contents, $target, $syncRoot);
      @unlink($tmp);
      $written++;
    }
    $zip->close();
    if ($written === 0) {
      throw new RuntimeException('No YAML files were imported from the ZIP archive.');
    }
    return ts('Archive uploaded. %1 YAML file(s) staged; %2 file(s) skipped. Review Synchronize before importing.', [1 => $written, 2 => $skipped]);
  }

  public function downloadArchive(ConfigManager $manager): void {
    $dir = $manager->getSyncDir();
    if (!class_exists('ZipArchive')) {
      throw new RuntimeException('ZipArchive is not available in PHP.');
    }
    if (!is_dir($dir)) {
      throw new RuntimeException('Sync directory does not exist. Export files first.');
    }
    $zipPath = tempnam(sys_get_temp_dir(), 'civicfg-') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
      throw new RuntimeException('Could not create archive.');
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if ($file->isFile()) {
        $relative = substr($file->getPathname(), strlen($dir) + 1);
        $zip->addFile($file->getPathname(), $relative);
      }
    }
    $zip->close();
    \CRM_Utils_System::setHttpHeader('Content-Type', 'application/zip');
    \CRM_Utils_System::setHttpHeader('Content-Disposition', 'attachment; filename="civicrm-config.zip"');
    \CRM_Utils_System::setHttpHeader('Content-Length', (string) filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    \CRM_Utils_System::civiExit();
  }

  private function splitExportKey(string $key): array {
    $parts = explode('::', $key, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
      throw new RuntimeException('Invalid export item selection.');
    }
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $parts[0]) || !$this->isSafeRelativeYamlPath($parts[1])) {
      throw new RuntimeException('Invalid export item path.');
    }
    return $parts;
  }

  private function getHandlerByType(ConfigManager $manager, string $type) {
    foreach ($manager->getAllHandlers() as $handler) {
      if ($handler->getType() === $type) {
        return $handler;
      }
    }
    return NULL;
  }

  private function isSafeRelativeYamlPath(string $path): bool {
    $path = str_replace('\\', '/', trim($path));
    if ($path === '' || $path[0] === '/' || strpos($path, '..') !== FALSE) {
      return FALSE;
    }
    if (!preg_match('/\.ya?ml$/i', $path)) {
      return FALSE;
    }
    return (bool) preg_match('/^[A-Za-z0-9_.\/-]+$/', $path);
  }

  private function safeWriteUploadedFile(string $tmp, string $target, string $root): void {
    $contents = file_get_contents($tmp);
    $this->safeWriteContents($contents, $target, $root);
  }

  private function safeWriteContents(string $contents, string $target, string $root): void {
    $root = rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);
    $dir = dirname($target);
    if (!is_dir($dir)) {
      mkdir($dir, 0775, TRUE);
    }
    $realDir = realpath($dir);
    if (!$realDir || strpos($realDir, $root) !== 0) {
      throw new RuntimeException('Refusing to write outside the sync directory.');
    }
    file_put_contents($target, $contents);
  }
}
