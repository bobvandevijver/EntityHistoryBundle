<?php

namespace Bobv\EntityHistoryBundle\Exception;

/**
 * @author BobV
 */
class IncorrectCriteriaException extends \Exception
{
  public function __construct(string $criteria, string $className) {
    parent::__construct(sprintf('Field "%s" is available in object of type "%s"', $criteria, $className));
  }
}