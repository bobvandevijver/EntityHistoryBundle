<?php

namespace Bobv\EntityHistoryBundle\Exception;

/**
 * @author BobV
 */
class NotFoundException extends \Exception
{
  public function __construct(string $objectId, int $revision) {
    parent::__construct(sprintf('No revision found?! (block: %d, revision: %d)', $objectId, $revision));
  }
}