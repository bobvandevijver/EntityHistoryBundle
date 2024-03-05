<?php

namespace Bobv\EntityHistoryBundle\Reader;

/**
 * @author BobV
 */
class HistoryCollection
{
  private array $revisions = [];

  /**
   * Add a revision to the collection. Take care to add them in the correct
   * order, as otherwise you will have to sort them.
   */
  public function addRevision(HistoryRevision $revision): self {
    if(!isset($this->revisions[$revision->getEntityId()])){
      $this->revisions[$revision->getEntityId()] = [];
    }

    $this->revisions[$revision->getEntityId()][] = $revision;

    return $this;
  }

  public function getRevisions($entityId): array {
    if (isset($this->revisions[$entityId])) {
      return $this->revisions[$entityId];
    }

    return [];
  }

  public function getAllRevisions(): array {
    return $this->revisions;
  }

  public function getRevisionCount($entityId = null): int {
    if($entityId === null){
      $count = 0;
      foreach ($this->revisions as $revisions) {
        $count += count($revisions);
      }
      return $count;
    }

    return count($this->revisions[$entityId]);
  }

  /**
   * Returns the amount of different entities in this collection
   */
  public function getEntityCount(): int {
    return count($this->revisions);
  }
}