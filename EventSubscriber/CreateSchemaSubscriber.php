<?php

namespace BobV\EntityHistoryBundle\EventSubscriber;

use BobV\EntityHistoryBundle\Configuration\HistoryConfiguration;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Class CreateSchemaSubscriber
 *
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class CreateSchemaSubscriber implements EventSubscriber
{

  /**
   * @var HistoryConfiguration
   */
  private $config;

  /**
   * @param HistoryConfiguration $configuration
   */
  public function __construct(HistoryConfiguration $configuration)
  {
    $this->config = $configuration;
  }

  /**
   * @return array
   */
  public function getSubscribedEvents()
  {
    return array(
        ToolEvents::postGenerateSchemaTable
    );
  }

  /**
   * @param GenerateSchemaTableEventArgs $eventArgs
   */
  public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs)
  {
    $cm = $eventArgs->getClassMetadata();

    // Check if the entity is logged
    if ($this->config->isLogged($cm->getName())) {

      // Get needed vars
      $schema      = $eventArgs->getSchema();
      $entityTable = $eventArgs->getClassTable();

      // Create table
      $revisionTable = $schema->createTable(
          $this->config->getTableName($entityTable->getName())
      );

      // Get id column (if any)
      if ($entityTable->hasColumn('id')) {
        /* @var $column Column */
        $column = $entityTable->getColumn('id');
        $revisionTable->addColumn($column->getName(), $column->getType()->getName(), array_merge(
            $column->toArray(),
            array('notnull' => false, 'autoincrement' => false)
        ));
      }

      // Add revision info
      $revisionTable->addColumn($this->config->getRevisionFieldName(), 'integer');
      $revisionTable->addColumn($this->config->getRevisionTypeFieldName(), 'string', array('length' => 4));

      // Get each column (except id) and add it to the table
      foreach ($entityTable->getColumns() AS $column) {
        if ($column->getName() == 'id') continue;
        /* @var $column Column */
        $revisionTable->addColumn($column->getName(), $column->getType()->getName(), array_merge(
            $column->toArray(),
            array('notnull' => false, 'autoincrement' => false)
        ));
      }

      // Get the primary keys
      $pkColumns   = $entityTable->getPrimaryKey()->getColumns();
      $pkColumns[] = $this->config->getRevisionFieldName();
      $revisionTable->setPrimaryKey($pkColumns);
    }
  }
}
