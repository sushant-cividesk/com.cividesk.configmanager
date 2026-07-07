<?php
namespace Civi\Api4\Action\ConfigManager;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\ConfigManager\Service\ConfigManager;

class ListTypes extends AbstractAction {
  public function _run(Result $result) {
    $items = [];
    foreach ((new ConfigManager())->getHandlers() as $handler) {
      $items[] = [
        'type' => $handler->getType(),
        'label' => $handler->getLabel(),
        'directory' => $handler->getDirectory(),
        'weight' => $handler->getWeight(),
      ];
    }
    $result[] = ['ok' => TRUE, 'types' => $items];
  }
}
