<?php

namespace BobV\EntityHistoryBundle\Exception;

/**
 * Class NotFoundException
 * @author BobV
 */
class TooManyFoundException extends \Exception
{

  /**
   * TooManyFoundException constructor.
   *
   * @param string $objectId
   * @param int    $revision
   */
  public function __construct($objectId, $revision) {
    parent::__construct(sprintf('To many revisions found?! (block: %d, revision: %d)', $objectId, $revision));
  }

}