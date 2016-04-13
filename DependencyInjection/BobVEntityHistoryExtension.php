<?php

namespace BobV\EntityHistoryBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class BobVEntityHistoryExtension
 *
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class BobVEntityHistoryExtension extends Extension
{
  public function load(array $configs, ContainerBuilder $container)
  {
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

  }
}
