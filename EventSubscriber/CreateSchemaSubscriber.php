<?php

namespace Bobv\EntityHistoryBundle\EventSubscriber;

use Bobv\EntityHistoryBundle\Configuration\HistoryConfiguration;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class CreateSchemaSubscriber implements EventSubscriber
{
  public function __construct(private readonly HistoryConfiguration $configuration)
  {
  }

  public function getSubscribedEvents(): array
  {
    return [
        ToolEvents::postGenerateSchemaTable
    ];
  }

  public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs): void
  {
    $cm = $eventArgs->getClassMetadata();

    // Check if the entity is logged
    if ($this->configuration->isLogged($cm->getName())) {

      // Get needed vars
      $schema      = $eventArgs->getSchema();
      $entityTable = $eventArgs->getClassTable();

      // Create table
      $revisionTable = $schema->createTable(
          $this->configuration->getTableName($entityTable->getName())
      );

      // Get id column (if any)
      if ($entityTable->hasColumn('id')) {
        /* @var $column Column */
        $column = $entityTable->getColumn('id');
        $revisionTable->addColumn($column->getName(), $column->getType()->getName(), array_merge(
            $this->getColumnOptions($column),
            array('notnull' => false, 'autoincrement' => false)
        ));
      }

      // Add revision info
      $revisionTable->addColumn($this->configuration->getRevisionFieldName(), 'integer');
      $revisionTable->addColumn($this->configuration->getRevisionTypeFieldName(), 'string', array('length' => 4));

      // Get each column (except id) and add it to the table
      foreach ($entityTable->getColumns() AS $column) {
        if ($column->getName() == 'id') continue;
        /* @var $column Column */
        $revisionTable->addColumn($column->getName(), $column->getType()->getName(), array_merge(
            $this->getColumnOptions($column),
            array('notnull' => false, 'autoincrement' => false)
        ));
      }

      // Get the primary keys
      $pkColumns   = $entityTable->getPrimaryKey()->getColumns();
      $pkColumns[] = $this->configuration->getRevisionFieldName();
      $revisionTable->setPrimaryKey($pkColumns);
    }
  }

  /**
   * Get all the options for a column, uses the toArray method and removes the keys that are not relevant
   */
  private function getColumnOptions(Column $column): array
  {
    $columnArray = $column->toArray();
    unset($columnArray['name']);
    unset($columnArray['type']);
    unset($columnArray['version']);

    return $columnArray;
  }
}
