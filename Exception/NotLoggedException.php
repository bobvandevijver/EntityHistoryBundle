<?php

namespace Bobv\EntityHistoryBundle\Exception;

/**
 * @author BobV
 */
class NotLoggedException extends \Exception
{
  public function __construct(string $className) {
    parent::__construct(sprintf('Class "%s" is not logged by the HistoryBundle', $className));
  }
}