<?php
namespace Civi\Api4\Action\ConfigManager;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\ConfigManager\Service\ConfigManager;

class Import extends AbstractAction {
  /**
   * Preview only. Set false to apply import.
   *
   * @var bool
   */
  protected $dryRun = TRUE;

  /**
   * Required to apply writes when dryRun=false.
   *
   * @var bool
   */
  protected $yes = FALSE;

  /**
   * Optional type filter.
   *
   * @var array
   */
  protected $type = [];

  public function _run(Result $result) {
    $effectiveDryRun = (bool) $this->dryRun || !(bool) $this->yes;
    $result[] = (new ConfigManager())->import($effectiveDryRun, (bool) $this->yes, (array) $this->type);
  }
}
