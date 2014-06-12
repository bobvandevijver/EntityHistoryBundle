<?php

namespace BobV\EntityHistoryBundle\Configuration;

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
  protected $classes;
  protected $prefix;
  protected $revisionFieldName;
  protected $revisionTypeFieldName;
  protected $suffix;

  /**
   * @return mixed
   */
  public function getClasses()
  {
    return $this->classes;
  }

  /**
   * @return mixed
   */
  public function getPrefix()
  {
    return $this->prefix;
  }

  /**
   * @return mixed
   */
  public function getRevisionFieldName()
  {
    return $this->revisionFieldName;
  }

  /**
   * @return mixed
   */
  public function getRevisionTypeFieldName()
  {
    return $this->revisionTypeFieldName;
  }

  /**
   * @return mixed
   */
  public function getSuffix()
  {
    return $this->suffix;
  }

  public function getTableName($class)
  {
    return $this->prefix . $class . $this->suffix;
  }

  public function injectVars($prefix, $suffix, $revFieldName, $revTypeFieldName, $classes)
  {
    $this->prefix                = $prefix;
    $this->suffix                = $suffix;
    $this->revisionFieldName     = $revFieldName;
    $this->revisionTypeFieldName = $revTypeFieldName;
    $this->classes               = array_flip($classes);
  }

  public function isLogged($entityName)
  {
    return array_key_exists($entityName, $this->classes);
  }

}
