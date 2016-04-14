<?php

namespace BobV\EntityHistoryBundle\Exception;

/**
 * Class NotLoggedException
 * @author BobV
 */
class NotLoggedException extends \Exception
{

  /**
   * NotLoggedException constructor.
   *
   * @param string $className
   */
  public function __construct($className) {
    parent::__construct(sprintf('Class "%s" is not logged by the HistoryBundle', $className));
  }

}