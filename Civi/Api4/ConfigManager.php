<?php
namespace Civi\Api4;

use Civi\Api4\Generic\AbstractEntity;
use Civi\ConfigManager\UI\Permission;

/**
 * Configuration Manager API4 facade.
 *
 * This is intentionally implemented as normal API4 actions so it works with
 * core cv commands, e.g. `cv api4 ConfigManager.status`.
 *
 * @package Civi\Api4
 */
class ConfigManager extends AbstractEntity {

  public static function permissions() {
    return [
      'default' => [Permission::ACCESS],
      'status' => [Permission::ACCESS],
      'listTypes' => [Permission::ACCESS],
      'diff' => [Permission::ACCESS],
      'validate' => [Permission::ACCESS],
      'export' => [Permission::EXPORT],
      'import' => [Permission::IMPORT],
    ];
  }

  public static function getFields($checkPermissions = TRUE) {
    return [];
  }

  public static function status($checkPermissions = TRUE) {
    return (new Action\ConfigManager\Status(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function listTypes($checkPermissions = TRUE) {
    return (new Action\ConfigManager\ListTypes(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function export($checkPermissions = TRUE) {
    return (new Action\ConfigManager\Export(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function diff($checkPermissions = TRUE) {
    return (new Action\ConfigManager\Diff(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function validate($checkPermissions = TRUE) {
    return (new Action\ConfigManager\Validate(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function import($checkPermissions = TRUE) {
    return (new Action\ConfigManager\Import(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

}
