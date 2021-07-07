<?php

namespace BobV\EntityHistoryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 *
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder('bobv_entity_history');

    if (method_exists($treeBuilder, 'getRootNode')) {
      $rootNode = $treeBuilder->getRootNode();
    } else {
      // for symfony/config 4.1 and older
      $rootNode = $treeBuilder->root('bobv_entity_history');
    }
    $rootNode
        ->children()
          ->arrayNode('entities')
            ->prototype('scalar')->end()
            ->defaultValue(array())
          ->end()
          ->arrayNode('connections')
            ->prototype('scalar')->end()
            ->defaultValue(array('default'))
          ->end()
          ->scalarNode('table_prefix')
            ->defaultValue('_HISTORY_')
            ->end()
          ->scalarNode('table_suffix')
            ->defaultValue('')
            ->end()
          ->scalarNode('revision_field_name')
            ->defaultValue('rev')
          ->end()
          ->scalarNode('revision_type_field_name')
            ->defaultValue('revtype')
            ->end()
          ->scalarNode('deleted_at_field')
            ->defaultValue(NULL)
            ->end()
          ->scalarNode('deleted_by_field')
            ->defaultValue(NULL)
            ->end()
          ->scalarNode('deleted_by_method')
            ->defaultValue(NULL)
            ->end()
        ->end();

    return $treeBuilder;
  }
}
