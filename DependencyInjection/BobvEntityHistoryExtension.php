<?php

namespace Bobv\EntityHistoryBundle\DependencyInjection;

use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\ToolEvents;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class BobvEntityHistoryExtension extends Extension
{
  public function load(array $configs, ContainerBuilder $container): void {
    $config = $this->processConfiguration(new Configuration(), $configs);

    $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
    $loader->load('services.yml');

    $configurables = array(
        'table_prefix',
        'table_suffix',
        'revision_field_name',
        'revision_type_field_name',
        'entities',
        'deleted_at_field',
        'deleted_by_field',
        'deleted_by_method',
    );

    foreach ($configurables as $key) {
      $container->setParameter("bobv.entityhistory." . $key, $config[$key]);
    }

    // Create the service tags
    foreach ($config['connections'] as $connection) {
      $container->findDefinition('bobv.entityhistory.create_schema_subscriber')
          ->addTag('doctrine.event_listener', ['event' => ToolEvents::postGenerateSchemaTable, 'connection' => $connection]);
      $container->findDefinition('bobv.entityhistory.log_history_subscriber')
          ->addTag('doctrine.event_listener', ['event' => Events::onFlush, 'connection' => $connection])
          ->addTag('doctrine.event_listener', ['event' => Events::postPersist, 'connection' => $connection])
          ->addTag('doctrine.event_listener', ['event' => Events::postUpdate, 'connection' => $connection]);
    }

  }
}
