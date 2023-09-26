<?php

namespace Bobv\EntityHistoryBundle\Reader;

/**
 * Class HistoryCollection
 * @author BobV
 */
class HistoryCollection
{
  /** @var array */
  private $revisions;

  /**
   * HistoryCollection constructor.
   */
  public function __construct() {
    $this->revisions = array();
  }

  /**
   * Add a revision to the collection. Take care to add them in the correct
   * order, as otherwise you will have to sort them.
   *
   * @param HistoryRevision $revision
   *
   * @return $this
   */
  public function addRevision(HistoryRevision $revision) {
    if(!isset($this->revisions[$revision->getEntityId()])){
      $this->revisions[$revision->getEntityId()] = array();
    }

    $this->revisions[$revision->getEntityId()][] = $revision;

    return $this;
  }

  /**
   * @param $entityId
   *
   * @return array|mixed
   */
  public function getRevisions($entityId) {
    if (isset($this->revisions[$entityId])) {
      return $this->revisions[$entityId];
    }

    return array();
  }

  /**
   * @return array
   */
  public function getAllRevisions() {
    return $this->revisions;
  }

  /**
   * @param null $entityId
   *
   * @return int
   */
  public function getRevisionCount($entityId = null){
    if($entityId === null){
      $count = 0;
      foreach ($this->revisions as $revisions){
        $count += count($revisions);
      }
      return $count;
    }

    return count($this->revisions[$entityId]);
  }

  /**
   * Returns the amount of different entities in this collection
   *
   * @return mixed
   */
  public function getEntityCount() {
    return count($this->revisions);
  }

}