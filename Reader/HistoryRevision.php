<?php

namespace Bobv\EntityHistoryBundle\Reader;

/**
 * Class HistoryRevision
 *
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class HistoryRevision
{

  /** @var int */
  private $id;

  /** @var string */
  private $type;

  /** @var int */
  private $entityId;

  /** @var Object */
  private $entity;

  /**
   * HistoryRevision constructor.
   *
   * @param $revisionId
   * @param $revisionType
   * @param $entityId
   * @param $entity
   */
  public function __construct($revisionId, $revisionType, $entityId, $entity) {
    $this->id       = $revisionId;
    $this->type     = $revisionType;
    $this->entityId = $entityId;
    $this->entity   = $entity;
  }

  /**
   * @return int
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  /**
   * @return int
   */
  public function getEntityId() {
    return $this->entityId;
  }

  /**
   * @return Object
   */
  public function getEntity() {
    return $this->entity;
  }

}