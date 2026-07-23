<?php
namespace Civi\ConfigManager\Service;

use Civi\ConfigManager\Version;

/**
 * Installs project-level CLI wrappers without requiring root access.
 * Existing non-managed files are never overwritten.
 */
class CliInstaller {
  private ConfigManager $manager;

  public function __construct(?ConfigManager $manager = NULL) {
    $this->manager = $manager ?: new ConfigManager();
  }

  public function install(): array {
    $result = ['ok' => TRUE, 'bin_dirs' => [], 'installed' => [], 'skipped' => [], 'errors' => []];
    foreach ($this->getBinDirs() as $binDir) {
      $result['bin_dirs'][] = $binDir;
      if (!is_dir($binDir) && !@mkdir($binDir, 0775, TRUE) && !is_dir($binDir)) {
        $result['ok'] = FALSE;
        $result['errors'][] = 'Could not create project bin directory: ' . $binDir;
        continue;
      }
      if (!is_writable($binDir)) {
        $result['ok'] = FALSE;
        $result['errors'][] = 'Project bin directory is not writable: ' . $binDir;
        continue;
      }
      foreach ($this->commands() as $name => $command) {
        $target = $binDir . DIRECTORY_SEPARATOR . $name;
        if (is_file($target) && !$this->isManagedWrapper($target)) {
          $result['skipped'][] = $target . ' (existing non-managed file)';
          continue;
        }
        $script = $this->buildWrapperScript($command);
        if (@file_put_contents($target, $script) === FALSE) {
          $result['ok'] = FALSE;
          $result['errors'][] = 'Could not write CLI wrapper: ' . $target;
          continue;
        }
        @chmod($target, 0775);
        $result['installed'][] = $target;
      }
    }
    return $result;
  }

  public function uninstall(): array {
    $result = ['ok' => TRUE, 'bin_dirs' => [], 'removed' => [], 'skipped' => [], 'errors' => []];
    foreach ($this->getBinDirs() as $binDir) {
      $result['bin_dirs'][] = $binDir;
      foreach (array_keys($this->commands()) as $name) {
        $target = $binDir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($target)) {
          continue;
        }
        if (!$this->isManagedWrapper($target)) {
          $result['skipped'][] = $target . ' (existing non-managed file)';
          continue;
        }
        if (!@unlink($target)) {
          $result['ok'] = FALSE;
          $result['errors'][] = 'Could not remove CLI wrapper: ' . $target;
          continue;
        }
        $result['removed'][] = $target;
      }
    }
    return $result;
  }

  public function ensureSiteIdentifier(): string {
    return $this->manager->getSiteIdentifier();
  }

  private function getBinDirs(): array {
    $root = rtrim($this->manager->getProjectRoot(), DIRECTORY_SEPARATOR);
    $dirs = [];
    if ($root !== '') {
      $dirs[] = $root . DIRECTORY_SEPARATOR . 'bin';
      // Drupal/Backdrop usually report the CMS docroot. Add the Composer project
      // root one level above /web so commands also work from the site root.
      if (basename($root) === 'web') {
        $dirs[] = dirname($root) . DIRECTORY_SEPARATOR . 'bin';
      }
    }
    // Buildkit/DDEV convenience: allow a shared /var/www/html/bin when writable.
    if (is_dir('/var/www/html') && is_writable('/var/www/html')) {
      $dirs[] = '/var/www/html/bin';
    }
    return array_values(array_unique($dirs));
  }

  private function commands(): array {
    return [
      'civicfg' => '',
      'cvcfg' => '',
      'config-export' => 'config-export',
      'ce' => 'ce',
      'config-import' => 'config-import',
      'ci' => 'ci',
      'config-diff' => 'config-diff',
      'cdf' => 'cdf',
      'config-validate' => 'config-validate',
      'cval' => 'cval',
    ];
  }

  private function buildWrapperScript(string $command): string {
    $commandLine = $command === ''
      ? 'exec "$extension_bin" "$@"'
      : 'exec "$extension_bin" ' . escapeshellarg($command) . ' "$@"';
    $template = <<<'BASH'
#!/usr/bin/env bash
# Managed by Configuration Manager extension. Do not edit manually.
set -euo pipefail
extension_bin=__EXTENSION_BIN__
extension_key=__EXTENSION_KEY__

if ! command -v cv >/dev/null 2>&1; then
  echo "Configuration Manager CLI requires the CiviCRM cv command in PATH." >&2
  exit 2
fi

status="$(cv ev 'try { $s = CRM_Extension_System::singleton()->getManager()->getStatus("__EXTENSION_KEY_RAW__"); echo $s ?: "missing"; } catch (Throwable $e) { echo "unknown"; }' 2>/dev/null || true)"
case "${status}" in
  installed|enabled) ;;
  disabled)
    echo "Configuration Manager extension is disabled. Enable ${extension_key} before running civicfg." >&2
    exit 2
    ;;
  *)
    echo "Configuration Manager extension is not installed/enabled on this site (status: ${status:-unknown})." >&2
    exit 2
    ;;
esac

if [[ ! -x "$extension_bin" ]]; then
  echo "Configuration Manager extension CLI is missing or not executable: $extension_bin" >&2
  exit 2
fi

__COMMAND_LINE__
BASH;
    return strtr($template, [
      '__EXTENSION_BIN__' => escapeshellarg($this->extensionRoot() . '/bin/civicfg'),
      '__EXTENSION_KEY__' => escapeshellarg(Version::EXTENSION_KEY),
      '__EXTENSION_KEY_RAW__' => addslashes(Version::EXTENSION_KEY),
      '__COMMAND_LINE__' => $commandLine,
    ]);
  }

  private function extensionRoot(): string {
    return dirname(__DIR__, 3);
  }

  private function isManagedWrapper(string $file): bool {
    $contents = @file_get_contents($file);
    return is_string($contents) && strpos($contents, 'Managed by Configuration Manager extension') !== FALSE;
  }
}
