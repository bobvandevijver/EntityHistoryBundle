<?php

namespace Bobv\EntityHistoryBundle\Reader;

use Bobv\EntityHistoryBundle\Configuration\HistoryConfiguration;
use Bobv\EntityHistoryBundle\Exception\IncorrectCriteriaException;
use Bobv\EntityHistoryBundle\Exception\NotFoundException;
use Bobv\EntityHistoryBundle\Exception\NotLoggedException;
use Bobv\EntityHistoryBundle\Exception\TooManyFoundException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Persisters\Entity\EntityPersister;

/**
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class HistoryReader
{
  private array $entityCache = [];
  private readonly AbstractPlatform $platform;

  public function __construct(private readonly EntityManager $em, private readonly HistoryConfiguration $config) {
    $this->platform = $this->em->getConnection()->getDatabasePlatform();
  }

  public function findRevisions(string $className, int $id): HistoryCollection {
    if (false === ($metadata = $this->getMetadata($className))) {
      throw new NotLoggedException($className);
    }

    $tableName = $this->config->getTableName($metadata->getTableName());
    $query     = 'SELECT * FROM ' . $tableName . ' h WHERE h.id = ?';

    // Execute query
    $revisions = $this->em->getConnection()->fetchAllAssociative($query, $id);

    // Create history revisions
    return $this->createHistoryCollection($className, $revisions);
  }

  public function findRevision(string $className, int $id, int $revision): HistoryRevision {
    if (false === ($metadata = $this->getMetadata($className))) {
      throw new NotLoggedException($className);
    }

    // Create query
    $tableName = $this->config->getTableName($metadata->getTableName());
    $query     = 'SELECT * FROM ' . $tableName . ' h WHERE h.id = ? AND h.rev = ?';

    // Execute query
    $revisions = $this->em->getConnection()->fetchAllAssociative($query, array($id, $revision));

    // Create history revisions
    $history = $this->createHistoryCollection($className, $revisions);

    /** @var Object $dbObject */
    if ($history->getRevisionCount($id) == 0) {
      throw new NotFoundException($id, $revision);
    } elseif ($history->getRevisionCount($id) != 1) {
      throw new TooManyFoundException($id, $revision);
    }

    return $history->getRevisions($id)[0];
  }

  public function findRevisionsByCriteria(string $className, array $criteria): HistoryCollection {
    if (false === ($metadata = $this->getMetadata($className))) {
      throw new NotLoggedException($className);
    }

    // Check if the given criteria are indeed available in the object
    // and create the actual where query
    $whereSql = "";
    foreach ($criteria as $criterium => $data) {
      if ($whereSql) {
        $whereSql .= " AND ";
      }
      if ($metadata->hasField($criterium)) {
        $whereSql .= "h." . $metadata->getFieldMapping($criterium)['columnName'] . " = ?";
      } else if ($metadata->hasAssociation($criterium)) {
        $whereSql .= "h." . $metadata->getAssociationMapping($criterium)['joinColumns'][0]['name'] . " = ?";
      } else {
        throw new IncorrectCriteriaException($criterium, $className);
      }
    }

    // Create the query with the where statement
    $tableName = $this->config->getTableName($metadata->getTableName());
    $query     = 'SELECT * FROM ' . $tableName . ' h WHERE ' . $whereSql . " ORDER BY h.id DESC";

    // Execute query
    $revisions = $this->em->getConnection()->fetchAllAssociative($query, array_values($criteria));

    // Create history revisions
    return $this->createHistoryCollection($className, $revisions);
  }

  public function restoreObject(string $className, &$dbObject, HistoryRevision $revisionData): void {
    if (false === ($metadata = $this->getMetadata($className))) {
      throw new NotLoggedException($className);
    }

    $uow       = $this->em->getUnitOfWork();
    $changeset = array();

    // Check fields
    foreach ($metadata->getFieldNames() as $fieldName) {
      $oldValue = $metadata->getFieldValue($dbObject, $fieldName);
      $newValue = $metadata->getFieldValue($revisionData->getEntity(), $fieldName);
      if ($oldValue != $newValue) {
        $metadata->setFieldValue($dbObject, $fieldName, $newValue);
        $uow->propertyChanged($dbObject, $fieldName, $oldValue, $newValue);
        $changeset[$fieldName] = array($oldValue, $newValue);
      }
    }

    // Check associations
    foreach ($metadata->getAssociationNames() as $associationName) {
      $oldValue = $metadata->getFieldValue($dbObject, $associationName);
      $newValue = $metadata->getFieldValue($revisionData->getEntity(), $associationName);
      if ($oldValue != $newValue) {
        $metadata->setFieldValue($dbObject, $associationName, $newValue);
        $uow->propertyChanged($dbObject, $associationName, $oldValue, $newValue);
        $changeset[$associationName] = array($oldValue, $newValue);
      }
    }

    // If we try to revert a deleted object with the last known status (which has the deleted flags zet)
    if ($revisionData->getType() == 'DEL') {

      // Check if there is a deletedAt field configured which we can clear
      if (null !== ($deletedAtField = $this->config->getDeletedAtField())) {
        $oldValue = $metadata->getFieldValue($dbObject, $this->config->getDeletedAtField());
        $metadata->setFieldValue($dbObject, $this->config->getDeletedAtField(), null);
        $uow->propertyChanged($dbObject, $this->config->getDeletedAtField(), $oldValue, null);
        $changeset[$this->config->getDeletedAtField()] = array($oldValue, null);
      }

      // Check if there is a deletedBy field configured which we can clear
      if (null !== ($deletedByField = $this->config->getDeletedByField())) {
        $oldValue = $metadata->getFieldValue($dbObject, $this->config->getDeletedByField());
        $metadata->setFieldValue($dbObject, $this->config->getDeletedByField(), null);
        $uow->propertyChanged($dbObject, $this->config->getDeletedByField(), $oldValue, null);
        $changeset[$this->config->getDeletedByField()] = array($oldValue, null);
      }
    }

    /** @var Object $dbObject */
    $uow->scheduleExtraUpdate($dbObject, $changeset);
    $this->config->setReverted($className, $dbObject->getId());
  }

  private function createHistoryCollection(string $className, array &$revisions): HistoryCollection {
    // Loop the revisions and create object from them
    $historyCollection = new HistoryCollection();
    foreach ($revisions as $revision) {
      $historyCollection->addRevision(new HistoryRevision(
          $revision['rev'],
          $revision['revtype'],
          $revision['id'],
          $this->createObjectFromRevision($className, $revision)
      ));
    }

    $this->em->clear($className);

    return $historyCollection;
  }

  /**
   * Create an entity from a revision
   */
  private function createObjectFromRevision($className, $revision): object {
    // Remove revision fields
    $revId = $revision['rev'];
    unset($revision['rev']);
    unset($revision['revtype']);

    return $this->createEntity($className, $revision, $revId);
  }

  private function getMetadata(string $className): false|ClassMetadata {
    if (!$this->config->isLogged($className)) {
      return false;
    }

    return $this->em->getClassMetadata($className);
  }

  /**
   * Simplified and stolen code from UnitOfWork::createEntity.
   */
  private function createEntity(string $className, array $data, $revision): object {
    /** @var ClassMetadataInfo|ClassMetadata $class */
    $class = $this->em->getClassMetadata($className);
    //lookup revisioned entity cache
    $keyParts = [];
    foreach ($class->getIdentifierFieldNames() as $name) {
      $keyParts[] = $data[$name];
    }
    $key = implode(':', $keyParts);
    if (isset($this->entityCache[$className][$key][$revision])) {
      return $this->entityCache[$className][$key][$revision];
    }

    if (!$class->isInheritanceTypeNone()) {
      if (!isset($data[$class->discriminatorColumn['name']])) {
        throw new \RuntimeException('Expecting discriminator value in data set.');
      }
      $discriminator = $data[$class->discriminatorColumn['name']];
      if (!isset($class->discriminatorMap[$discriminator])) {
        throw new \RuntimeException("No mapping found for [{$discriminator}].");
      }
      if ($class->discriminatorValue) {
        $entity = $this->em->getClassMetadata($class->discriminatorMap[$discriminator])->newInstance();
      } else {
        //a complex case when ToOne binding is against AbstractEntity having no discriminator
        $pk = array();
        foreach ($class->identifier as $field) {
          $pk[$class->getColumnName($field)] = $data[$field];
        }
        //        return $this->find($class->discriminatorMap[$discriminator], $pk, $revision);
        throw new \RuntimeException("This is not supported");
      }
    } else {
      $entity = $class->newInstance();
    }
    //cache the entity to prevent circular references
    $this->entityCache[$className][$key][$revision] = $entity;
    foreach ($data as $field => $value) {
      if (isset($class->fieldMappings[$field])) {
        $type  = Type::getType($class->fieldMappings[$field]['type']);
        $value = $type->convertToPHPValue($value, $this->platform);
        $class->reflFields[$field]->setValue($entity, $value);
      }
    }
    foreach ($class->associationMappings as $field => $assoc) {
      // Check if the association is not among the fetch-joined associations already.
      if (isset($hints['fetched'][$className][$field])) {
        continue;
      }
      /** @var ClassMetadataInfo|ClassMetadata $targetClass */
      $targetClass = $this->em->getClassMetadata($assoc['targetEntity']);
      if ($assoc['type'] & ClassMetadataInfo::TO_ONE) {
        if ($assoc['isOwningSide']) {
          $associatedId = array();
          foreach ($assoc['targetToSourceKeyColumns'] as $targetColumn => $srcColumn) {
            $joinColumnValue = $data[$srcColumn] ?? null;
            if ($joinColumnValue !== null) {
              $associatedId[$targetClass->fieldNames[$targetColumn]] = $joinColumnValue;
            }
          }
          if (!$associatedId) {
            // Foreign key is NULL
            $class->reflFields[$field]->setValue($entity, null);
          } else {
            $associatedEntity = $this->em->getReference($targetClass->name, $associatedId);
            $class->reflFields[$field]->setValue($entity, $associatedEntity);
          }
        } else {
          // Inverse side of x-to-one can never be lazy
          $class->reflFields[$field]->setValue($entity, $this->getEntityPersister($assoc['targetEntity'])
              ->loadOneToOneEntity($assoc, $entity));
        }
      } elseif ($assoc['type'] & ClassMetadataInfo::ONE_TO_MANY) {
        $collection = new PersistentCollection($this->em, $targetClass, new ArrayCollection());
        $this->getEntityPersister($assoc['targetEntity'])
            ->loadOneToManyCollection($assoc, $entity, $collection);
        $class->reflFields[$assoc['fieldName']]->setValue($entity, $collection);
      } else {
        // Inject collection
        $reflField = $class->reflFields[$field];
        $reflField->setValue($entity, new ArrayCollection());
      }
    }
    return $entity;
  }

  protected function getEntityPersister($entity): EntityPersister {
    $uow = $this->em->getUnitOfWork();
    return $uow->getEntityPersister($entity);
  }

}