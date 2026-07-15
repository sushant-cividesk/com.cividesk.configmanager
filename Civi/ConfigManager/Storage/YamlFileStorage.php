<?php
namespace Civi\ConfigManager\Storage;

use Civi\ConfigManager\Util\SimpleYaml;

class YamlFileStorage {
  private string $root;

  public function __construct(string $root) {
    $this->root = rtrim($root, DIRECTORY_SEPARATOR);
  }

  public function getRoot(): string {
    return $this->root;
  }

  public function ensureRoot(): void {
    if (!is_dir($this->root)) {
      if (!mkdir($this->root, 0775, TRUE) && !is_dir($this->root)) {
        throw new \RuntimeException('Could not create config directory: ' . $this->root);
      }
    }
    if (!is_writable($this->root)) {
      throw new \RuntimeException('Config directory is not writable: ' . $this->root);
    }
  }

  public function getPath(string $directory, string $filename): string {
    $directory = trim($directory, DIRECTORY_SEPARATOR);
    if ($directory === '') {
      return $this->root . DIRECTORY_SEPARATOR . $filename;
    }
    return $this->root . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $filename;
  }

  public function exists(string $directory, string $filename): bool {
    return is_file($this->getPath($directory, $filename));
  }

  public function dump(array $data): string {
    return SimpleYaml::dump($data);
  }

  public function isSame(string $directory, string $filename, array $data): bool {
    $path = $this->getPath($directory, $filename);
    if (!is_file($path)) {
      return FALSE;
    }
    $current = file_get_contents($path);
    $new = $this->dump($data);
    return $this->normalise($current) === $this->normalise($new);
  }

  public function write(string $directory, string $filename, array $data): string {
    $this->ensureRoot();
    $dir = $this->root . DIRECTORY_SEPARATOR . trim($directory, DIRECTORY_SEPARATOR);
    if (trim($directory, DIRECTORY_SEPARATOR) === '') {
      $dir = $this->root;
    }
    if (!is_dir($dir)) {
      mkdir($dir, 0775, TRUE);
    }
    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    $subdir = dirname($path);
    if (!is_dir($subdir)) {
      mkdir($subdir, 0775, TRUE);
    }
    file_put_contents($path, $this->dump($data));
    return $path;
  }


  public function readFile(string $relativePath): array {
    $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
    $path = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
      return [];
    }
    return SimpleYaml::parseFile($path);
  }

  public function readDirectory(string $directory): array {
    $path = $this->root . DIRECTORY_SEPARATOR . trim($directory, DIRECTORY_SEPARATOR);
    if (!is_dir($path)) {
      return [];
    }
    $items = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if ($file->isFile() && preg_match('/\.ya?ml$/', $file->getFilename())) {
        $relative = substr($file->getPathname(), strlen($path) + 1);
        $items[$relative] = SimpleYaml::parseFile($file->getPathname());
      }
    }
    ksort($items);
    return $items;
  }

  private function normalise(string $yaml): string {
    return rtrim(str_replace(["\r\n", "\r"], "\n", $yaml)) . "\n";
  }
}
