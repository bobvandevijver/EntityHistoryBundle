<?php

namespace Bobv\EntityHistoryBundle\Exception;

/**
 * Class IncorrectCriteriaException
 * @author BobV
 */
class IncorrectCriteriaException extends \Exception
{

  /**
   * IncorrectCriteriaException constructor.
   *
   * @param string $criteria
   * @param string $className
   */
  public function __construct($criteria, $className) {
    parent::__construct(sprintf('Field "%s" is available in object of type "%s"', $criteria, $className));
  }

}