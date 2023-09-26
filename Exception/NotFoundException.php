<?php

namespace Bobv\EntityHistoryBundle\Exception;

/**
 * Class NotFoundException
 * @author BobV
 */
class NotFoundException extends \Exception
{

  /**
   * NotLoggedException constructor.
   *
   * @param string $objectId
   * @param int    $revision
   */
  public function __construct($objectId, $revision) {
    parent::__construct(sprintf('No revision found?! (block: %d, revision: %d)', $objectId, $revision));
  }

}