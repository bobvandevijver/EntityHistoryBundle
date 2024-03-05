<?php

namespace Bobv\EntityHistoryBundle\Configuration;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
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
  private array $changes = [];

  public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker) {
  }

  public function getClasses(): mixed {
    return $this->classes;
  }

  public function getPrefix(): mixed {
    return $this->prefix;
  }

  public function getRevisionFieldName(): mixed {
    return $this->revisionFieldName;
  }

  public function getRevisionTypeFieldName(): mixed {
    return $this->revisionTypeFieldName;
  }

  public function getSuffix(): mixed {
    return $this->suffix;
  }

  public function getTableName($class): string {
    return $this->prefix . $class . $this->suffix;
  }

  public function injectVars($prefix, $suffix, $revFieldName, $revTypeFieldName, $classes, $deletedAtField, $deletedByField, $deletedByMethod): void
  {
    $this->prefix                = $prefix;
    $this->suffix                = $suffix;
    $this->revisionFieldName     = $revFieldName;
    $this->revisionTypeFieldName = $revTypeFieldName;
    $this->classes               = array_flip($classes);
    $this->deletedAtField        = $deletedAtField;
    $this->deletedByField        = $deletedByField;
    $this->deletedByMethod       = $deletedByMethod;
  }

  public function isLogged($entityName): bool {
    return array_key_exists($entityName, $this->classes);
  }

  public function getDeletedAtField(): mixed {
    return $this->deletedAtField;
  }

  public function getDeletedByField(): mixed {
    return $this->deletedByField;
  }

  public function getDeletedByValue(): mixed {
    $method = $this->deletedByMethod;
    try {
      return $this->authorizationChecker->$method();
    } catch (\Exception $e) {
      throw new \LogicException(sprintf('The method "%s" could not be called on "%s" to generate the deleted by value', $method, get_class($this->authorizationChecker)));
    }
  }

  public function isReverted($className, $id): bool {
    if (isset($this->changes[$className]) && in_array($id, $this->changes[$className])) {
      unset($this->changes[$className][$id]);
      return true;
    }

    return false;
  }

  public function setReverted($className, $id): void {
    if (!isset($this->changes[$className])) {
      $this->changes[$className] = [];
    }
    $this->changes[$className][] = $id;
  }
}
