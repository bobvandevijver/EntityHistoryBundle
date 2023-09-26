<?php

namespace Bobv\EntityHistoryBundle\Configuration;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class HistoryConfiguration
 *
 * Based on the work of
 *  SimpleThings\EntityAudit
 *  Benjamin Eberlei <eberlei@simplethings.de>
 *  http://www.simplethings.de
 *
 * @author BobV
 */
class HistoryConfiguration
{

  // Configuration variables
  protected $classes;
  protected $prefix;
  protected $revisionFieldName;
  protected $revisionTypeFieldName;
  protected $suffix;
  protected $deletedAtField;
  protected $deletedByField;
  protected $deletedByMethod;

  /**
   * @var ContainerInterface
   */
  private $container;

  /**
   * @var array
   */
  private $changes = array();

  /**
   * HistoryConfiguration constructor.
   *
   * @param ContainerInterface $container
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * @return mixed
   */
  public function getClasses() {
    return $this->classes;
  }

  /**
   * @return mixed
   */
  public function getPrefix() {
    return $this->prefix;
  }

  /**
   * @return mixed
   */
  public function getRevisionFieldName() {
    return $this->revisionFieldName;
  }

  /**
   * @return mixed
   */
  public function getRevisionTypeFieldName() {
    return $this->revisionTypeFieldName;
  }

  /**
   * @return mixed
   */
  public function getSuffix() {
    return $this->suffix;
  }

  /**
   * @param $class
   *
   * @return string
   */
  public function getTableName($class) {
    return $this->prefix . $class . $this->suffix;
  }

  /**
   * @param $prefix
   * @param $suffix
   * @param $revFieldName
   * @param $revTypeFieldName
   * @param $classes
   * @param $deletedAtField
   * @param $deletedByField
   * @param $deletedByMethod
   */
  public function injectVars($prefix, $suffix, $revFieldName, $revTypeFieldName, $classes, $deletedAtField, $deletedByField, $deletedByMethod) {
    $this->prefix                = $prefix;
    $this->suffix                = $suffix;
    $this->revisionFieldName     = $revFieldName;
    $this->revisionTypeFieldName = $revTypeFieldName;
    $this->classes               = array_flip($classes);
    $this->deletedAtField        = $deletedAtField;
    $this->deletedByField        = $deletedByField;
    $this->deletedByMethod       = $deletedByMethod;
  }

  /**
   * @param $entityName
   *
   * @return mixed
   */
  public function isLogged($entityName) {
    return array_key_exists($entityName, $this->classes);
  }

  /**
   * @return mixed
   */
  public function getDeletedAtField() {
    return $this->deletedAtField;
  }

  /**
   * @return mixed
   */
  public function getDeletedByField() {
    return $this->deletedByField;
  }

  /**
   * @return mixed
   */
  public function getDeletedByValue() {
    $method = $this->deletedByMethod;
    try {
      return $this->container->get('security.authorization_checker')->$method();
    } catch (\Exception $e) {
      throw new \LogicException(sprintf('The method "%s" could not be called on "%s" to generate the deleted by value', $method, get_class($this->container->get('security.authorization_checker'))));
    }
  }

  /**
   * @param $className
   * @param $id
   *
   * @return bool
   */
  public function isReverted($className, $id) {
    if (isset($this->changes[$className]) && in_array($id, $this->changes[$className])) {
      unset($this->changes[$className][$id]);
      return true;
    }

    return false;
  }

  /**
   * @param $className
   * @param $id
   */
  public function setReverted($className, $id) {
    if (!isset($this->changes[$className])) {
      $this->changes[$className] = [];
    }
    $this->changes[$className][] = $id;
  }

}
