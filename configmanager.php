<?php
/**
 * Configuration Manager (com.cividesk.configmanager).
 */

if (!defined('CIVICRM_UF')) {
  return;
}

/**
 * Basic PSR-4 style autoloader for this extension.
 */
spl_autoload_register(function ($class) {
  $prefixes = [
    'Civi\\Api4\\Action\\ConfigManager\\' => __DIR__ . '/Civi/Api4/Action/ConfigManager/',
    'Civi\\Api4\\' => __DIR__ . '/Civi/Api4/',
    'Civi\\ConfigManager\\' => __DIR__ . '/Civi/ConfigManager/',
    'CRM_Configmanager_' => __DIR__ . '/CRM/Configmanager/',
  ];

  foreach ($prefixes as $prefix => $baseDir) {
    if (strpos($class, $prefix) !== 0) {
      continue;
    }
    $relative = substr($class, strlen($prefix));
    if ($prefix === 'CRM_Configmanager_') {
      $relative = str_replace('_', '/', $relative);
    }
    else {
      $relative = str_replace('\\', '/', $relative);
    }
    $file = $baseDir . $relative . '.php';
    if (is_file($file)) {
      require_once $file;
    }
  }
});

/**
 * Implements hook_civicrm_config().
 */
function configmanager_civicrm_config(&$config) {
  $templateDir = __DIR__ . '/templates';
  if (isset($config->templateCompileDir) && class_exists('CRM_Core_Smarty')) {
    CRM_Core_Smarty::singleton()->addTemplateDir($templateDir);
  }
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 */
function configmanager_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  $metaDataFolders[] = __DIR__ . '/settings';
}



/**
 * Implements hook_civicrm_permission().
 */
function configmanager_civicrm_permission(&$permissions) {
  foreach (\Civi\ConfigManager\UI\Permission::definitions() as $name => $definition) {
    $permissions[$name] = $definition;
  }
}

/**
 * Implements hook_civicrm_xmlMenu().
 */
function configmanager_civicrm_xmlMenu(&$files) {
  $files[] = __DIR__ . '/xml/Menu/configmanager.xml';
}

/**
 * Implements hook_civicrm_navigationMenu().
 */
function configmanager_civicrm_navigationMenu(&$menu) {
  _configmanager_insert_navigation_menu($menu, 'Administer/System Settings', [
    'label' => ts('Configuration Manager'),
    'name' => 'configuration_manager',
    'url' => 'civicrm/admin/config-manager?reset=1',
    'permission' => \Civi\ConfigManager\UI\Permission::ACCESS,
    'operator' => NULL,
    'separator' => FALSE,
    'active' => 1,
  ]);
}

/**
 * Implements hook_civicrm_managed().
 */
function configmanager_civicrm_managed(&$entities) {
  // Reserved for future managed entities.
}

/**
 * Insert navigation item into a path.
 */
function _configmanager_insert_navigation_menu(&$menu, $path, $item) {
  $parts = explode('/', $path);
  $current =& $menu;
  foreach ($parts as $part) {
    $found = NULL;
    foreach ($current as $key => &$entry) {
      if (!empty($entry['attributes']['name']) && $entry['attributes']['name'] === $part) {
        $found = $key;
        break;
      }
      if (!empty($entry['attributes']['label']) && $entry['attributes']['label'] === $part) {
        $found = $key;
        break;
      }
    }
    if ($found === NULL) {
      return;
    }
    if (!isset($current[$found]['child'])) {
      $current[$found]['child'] = [];
    }
    $current =& $current[$found]['child'];
  }

  $maxKey = empty($current) ? 0 : max(array_keys($current));
  $current[$maxKey + 1] = ['attributes' => $item];
}
