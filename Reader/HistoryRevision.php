<?php

namespace Bobv\EntityHistoryBundle\Reader;

/**
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class HistoryRevision
{
  public function __construct(
      private readonly int $revisionId,
      private readonly string $revisionType,
      private readonly int $entityId,
      private readonly object $entity) {
  }

  public function getId(): int {
    return $this->revisionId;
  }

  public function getType(): string {
    return $this->revisionType;
  }

  public function getEntityId(): int {
    return $this->entityId;
  }

  public function getEntity(): object {
    return $this->entity;
  }

}