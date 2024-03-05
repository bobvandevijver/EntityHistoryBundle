<?php

namespace Bobv\EntityHistoryBundle\Exception;

/**
 * @author BobV
 */
class TooManyFoundException extends \Exception
{
  public function __construct(string $objectId, int $revision) {
    parent::__construct(sprintf('To many revisions found?! (block: %d, revision: %d)', $objectId, $revision));
  }
}