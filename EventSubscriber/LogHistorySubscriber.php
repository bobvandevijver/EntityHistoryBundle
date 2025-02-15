<?php

namespace Bobv\EntityHistoryBundle\EventSubscriber;

use Bobv\EntityHistoryBundle\Configuration\HistoryConfiguration;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\ORM\Utility\PersisterHelper;
use LogicException;

/**
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class LogHistorySubscriber
{
  /**
   * @var Connection
   */
  private $conn;
  /**
   * @var EntityManager
   */
  private $em;
  /**
   * @var array
   */
  private $insertRevisionSQL = array();
  /**
   * @var AbstractPlatform
   */
  private $platform;
  /**
   * @var UnitOfWork
   */
  private $uow;

  public function __construct(private readonly HistoryConfiguration $config) {
  }

  public function onFlush(OnFlushEventArgs $eventArgs): void {
    $this->em       = $eventArgs->getObjectManager();
    $this->conn     = $this->em->getConnection();
    $this->uow      = $this->em->getUnitOfWork();
    $this->platform = $this->conn->getDatabasePlatform();

    foreach ($this->uow->getScheduledEntityDeletions() AS $entity) {
      $class = $this->em->getClassMetadata(get_class($entity));
      if (!$this->config->isLogged($class->name)) {
        continue;
      }

      // Get the original data
      $entityData = array_merge($this->getOriginalEntityData($entity), $this->uow->getEntityIdentifier($entity));

      // Check if there is a deletedAt field configured which we can set
      if (null !== ($deletedAtField = $this->config->getDeletedAtField())) {
        $deletedAtValue                  = [];
        $deletedAtValue[$deletedAtField] = new DateTime();
        $entityData                      = array_merge($entityData, $deletedAtValue);
      }

      // Check if there is a deletedBy field configured which we can set
      if (null !== ($deletedByField = $this->config->getDeletedByField())) {
        $deletedByValue                  = [];
        $deletedByValue[$deletedByField] = $this->config->getDeletedByValue();
        $entityData                      = array_merge($entityData, $deletedByValue);
      }

      // Save the update
      $this->saveRevisionEntityData($class, $entityData, 'DEL');
    }
  }

  public function postPersist(PostPersistEventArgs $eventArgs): void {
    // onFlush was executed before, everything already initialized
    $entity = $eventArgs->getObject();

    $class = $this->em->getClassMetadata(get_class($entity));
    if (!$this->config->isLogged($class->name)) {
      return;
    }

    $this->saveRevisionEntityData($class, $this->getOriginalEntityData($entity), 'INS');
  }

  public function postUpdate(PostUpdateEventArgs $eventArgs): void {
    // onFlush was executed before, everything already initialized
    $entity = $eventArgs->getObject();

    $class = $this->em->getClassMetadata(get_class($entity));
    if (!$this->config->isLogged($class->name)) {
      return;
    }

    $entityData = array_merge($this->getOriginalEntityData($entity), $this->uow->getEntityIdentifier($entity));
    // Check if the deleted field was set before, if so, it if and REVERT
    if ($this->config->isReverted($class->name, $entityData['id'])) {
      $this->saveRevisionEntityData($class, $entityData, 'REV');
    } else {
      $this->saveRevisionEntityData($class, $entityData, 'UPD');
    }
  }

  private function getInsertRevisionSQL(ClassMetadata $class): string {
    if (!isset($this->insertRevisionSQL[$class->name])) {
      $placeholders = ['?', '?'];
      $tableName    = $this->config->getTableName($class->table['name']);

      $sql = "INSERT INTO " . $tableName . " (" .
          $this->config->getRevisionFieldName() . ", " . $this->config->getRevisionTypeFieldName();

      $fields = [];

      // Find associations and copy the data
      foreach ($class->associationMappings AS $assoc) {
        if (($assoc['type'] & ClassMetadata::TO_ONE) > 0 && $assoc['isOwningSide']) {
          foreach ($assoc['targetToSourceKeyColumns'] as $sourceCol) {
            $fields[$sourceCol] = true;
            $sql .= ', ' . $sourceCol;
            $placeholders[] = '?';
          }
        }
      }

      // Find the normal fields and copy the data
      foreach ($class->fieldNames AS $field) {
        if (array_key_exists($field, $fields)) {
          continue;
        }
        $type           = Type::getType($class->fieldMappings[$field]['type']);
        $placeholders[] = (!empty($class->fieldMappings[$field]['requireSQLConversion']))
            ? $type->convertToDatabaseValueSQL('?', $this->platform)
            : '?';
        $sql .= ', ' . $this->em->getConfiguration()->getQuoteStrategy()->getColumnName($field, $class, $this->platform);
      }

      $sql .= ") VALUES (" . implode(", ", $placeholders) . ")";
      $this->insertRevisionSQL[$class->name] = $sql;
    }

    return $this->insertRevisionSQL[$class->name];
  }

  /**
   * Get original entity data, including versioned field, if "version" constraint is used
   */
  private function getOriginalEntityData(mixed $entity): array {
    $class = $this->em->getClassMetadata(get_class($entity));
    $data  = $this->uow->getOriginalEntityData($entity);
    if ($class->isVersioned) {
      $versionField        = $class->versionField;
      $data[$versionField] = $class->reflFields[$versionField]->getValue($entity);
    }

    return $data;
  }

  /**
   * Find the new revision id for the current entity
   */
  private function getRevisionId(ClassMetadata $class, $entityData, $revType): int{
    if ($revType === "INS") {
      return 1;
    }

    $tableName   = $this->config->getTableName($class->getTableName());
    $identifiers = $class->getIdentifier();

    // Get identifier info and use it in the select query
    $count = 1;
    $where = ' WHERE ';
    foreach ($identifiers as $identifier) {
      if ($count > 1) {
        $where .= ' AND ';
      }
      $where .= '`' . $identifier . '` = ' . $entityData[$identifier];
      $count++;
    }

    $sql = 'SELECT ' . $this->config->getRevisionFieldName()
        . ' FROM ' . $tableName
        . $where
        . ' ORDER BY ' . $this->config->getRevisionFieldName() . ' DESC '
        . ' LIMIT 1';

    $result = $this->conn->executeQuery($sql);

    if ($result->rowCount() == 1) {
      return $result->fetchAssociative()[$this->config->getRevisionFieldName()] + 1;
    } elseif ($result->rowCount() > 1) {
      throw new LogicException('Error while selecting new rev number');
    } else {
      return 1;
    }
  }

  private function saveRevisionEntityData(ClassMetadata $class, array $entityData, string $revType): void {
    $params = [$this->getRevisionId($class, $entityData, $revType), $revType];
    $types  = [Types::INTEGER, Types::STRING];

    $fields = [];

    foreach ($class->associationMappings AS $field => $assoc) {
      if (($assoc['type'] & ClassMetadata::TO_ONE) > 0 && $assoc['isOwningSide']) {

        $relatedId = NULL;
        if ($entityData[$field] !== NULL) {
          $relatedId = $this->uow->getEntityIdentifier($entityData[$field]);
        }

        $targetClass = $this->em->getClassMetadata($assoc['targetEntity']);

        foreach ($assoc['sourceToTargetKeyColumns'] as $sourceColumn => $targetColumn) {
          $fields[$sourceColumn] = true;
          if ($entityData[$field] === NULL) {
            $params[] = NULL;
            $types[]  = Types::STRING;
          } else {
            $params[] = $relatedId[$targetClass->fieldNames[$targetColumn]];
            $types[]  = PersisterHelper::getTypeOfColumn($targetColumn, $targetClass, $this->em);
          }
        }
      }
    }

    foreach ($class->fieldNames AS $field) {
      if (array_key_exists($field, $fields)) {
        continue;
      }
      $params[] = $entityData[$field];
      $types[]  = $class->fieldMappings[$field]['type'];
    }

    $this->conn->executeStatement($this->getInsertRevisionSQL($class), $params, $types);
  }
}
