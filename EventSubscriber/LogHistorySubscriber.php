<?php

namespace BobV\EntityHistoryBundle\EventSubscriber;

use BobV\EntityHistoryBundle\Configuration\HistoryConfiguration;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\AnsiQuoteStrategy;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Doctrine\ORM\UnitOfWork;

/**
 * Class LogHistorySubscriber
 * 
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class LogHistorySubscriber implements EventSubscriber
{

  /**
   * @var HistoryConfiguration
   */
  private $config;
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

  /**
   * @param HistoryConfiguration $history
   */
  public function __construct(HistoryConfiguration $history)
  {
    $this->config = $history;
  }

  /**
   * @return array
   */
  public function getSubscribedEvents()
  {
    return array(Events::onFlush, Events::postPersist, Events::postUpdate);
  }

  /**
   * @param OnFlushEventArgs $eventArgs
   */
  public function onFlush(OnFlushEventArgs $eventArgs)
  {
    $this->em       = $eventArgs->getEntityManager();
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
      if(null !== ($deletedAtField = $this->config->getDeletedAtField())){
        $deletedAtValue = [];
        $deletedAtValue[$deletedAtField] = new \DateTime();
        $entityData = array_merge($entityData, $deletedAtValue);
      }

      // Check if there is a deletedBy field configured which we can set
      if(null !== ($deletedByField = $this->config->getDeletedByField())){
        $deletedByValue = [];
        $deletedByValue[$deletedByField] = $this->config->getDeletedByValue();
        $entityData = array_merge($entityData, $deletedByValue);
      }

      // Save the update
      $this->saveRevisionEntityData($class, $entityData, 'DEL');
    }
  }

  /**
   * @param LifecycleEventArgs $eventArgs
   */
  public function postPersist(LifecycleEventArgs $eventArgs)
  {
    // onFlush was executed before, everything already initialized
    $entity = $eventArgs->getEntity();

    $class = $this->em->getClassMetadata(get_class($entity));
    if (!$this->config->isLogged($class->name)) {
      return;
    }

    $this->saveRevisionEntityData($class, $this->getOriginalEntityData($entity), 'INS');
  }

  /**
   * @param LifecycleEventArgs $eventArgs
   */
  public function postUpdate(LifecycleEventArgs $eventArgs)
  {
    // onFlush was executed before, everything already initialized
    $entity = $eventArgs->getEntity();

    $class = $this->em->getClassMetadata(get_class($entity));
    if (!$this->config->isLogged($class->name)) {
      return;
    }

    $entityData = array_merge($this->getOriginalEntityData($entity), $this->uow->getEntityIdentifier($entity));
    $this->saveRevisionEntityData($class, $entityData, 'UPD');
  }

  /**
   * @param ClassMetadata $class
   *
   * @return string
   * @throws DBALException
   */
  private function getInsertRevisionSQL($class)
  {
    if (!isset($this->insertRevisionSQL[$class->name])) {
      $placeholders = array('?', '?');
      $tableName    = $this->config->getTableName($class->table['name']);

      $sql = "INSERT INTO " . $tableName . " (" .
          $this->config->getRevisionFieldName() . ", " . $this->config->getRevisionTypeFieldName();

      $fields = array();

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
   *
   * @param mixed $entity
   *
   * @return array
   */
  private function getOriginalEntityData($entity)
  {
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
   *
   * @return int|string
   */
  private function getRevisionId(ClassMetadata $class, $entityData, $revType)
  {
    if ($revType === "INS") {
      return 1;
    }

    $tableName   = $this->config->getTableName($class->getTableName());
    $identifiers = $class->getIdentifier();

    // Get identifier info and use it in the select query
    $count = 1;
    $where = " WHERE ";
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
      return $result->fetch()[$this->config->getRevisionFieldName()] + 1;
    } elseif ($result->rowCount() > 1) {
      throw new \LogicException('Error while selecting new rev number');
    } else {
      return 1;
    }
  }

  /**
   * @param ClassMetadata $class
   * @param array         $entityData
   * @param string        $revType
   */
  private function saveRevisionEntityData($class, $entityData, $revType)
  {
    $params = array($this->getRevisionId($class, $entityData, $revType), $revType);
    $types  = array(\PDO::PARAM_INT, \PDO::PARAM_STR);

    $fields = array();

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
            $types[]  = \PDO::PARAM_STR;
          } else {
            $params[] = $relatedId[$targetClass->fieldNames[$targetColumn]];
            $types[]  = $targetClass->getTypeOfColumn($targetColumn);
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

    $this->conn->executeUpdate($this->getInsertRevisionSQL($class), $params, $types);
  }
}
