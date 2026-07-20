<?php

/**
 * Upgrade lifecycle for Configuration Manager.
 *
 * Keep release changes idempotent because this extension is now tested across
 * multiple development sites and should not assume a fresh install.
 */
class CRM_Configmanager_Upgrader extends CRM_Extension_Upgrader_Base {

  public function install() {
    $this->runLifecycle(TRUE, FALSE);
  }

  public function enable() {
    $this->runLifecycle(TRUE, FALSE);
  }

  public function uninstall() {
    $this->runLifecycle(FALSE, TRUE);
  }

  public function upgrade_1043() {
    $this->ctx->log->info('Applying Configuration Manager alpha43 lifecycle checks.');
    $this->runLifecycle(TRUE, FALSE);
    return TRUE;
  }

  public function upgrade_1044() {
    $this->ctx->log->info('Applying Configuration Manager alpha44 lifecycle checks.');
    $this->runLifecycle(TRUE, FALSE);
    return TRUE;
  }

  public function upgrade_1045() {
    $this->ctx->log->info('Applying Configuration Manager alpha45 lifecycle checks.');
    $this->runLifecycle(TRUE, FALSE);
    return TRUE;
  }

  private function runLifecycle(bool $installCli, bool $removeCli): void {
    if (function_exists('_configmanager_lifecycle')) {
      _configmanager_lifecycle($installCli, $removeCli);
    }
  }

}
