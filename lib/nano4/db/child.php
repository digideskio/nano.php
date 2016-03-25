<?php

namespace Nano4\DB;

/**
 * Base child class for ORM style models.
 */
abstract class Child implements \ArrayAccess
{
  /**
   * The model instaqnce that spawned us.
   */
  public $parent;              

  /**
   * Do we want to auto-save changes?
   */
  public $auto_save = False;

  protected $data;             // The hash data returned from a query.
  protected $table;            // The database table we belong to.
  protected $modified_data;    // Fields we have modified.
  protected $save_value;       // Used in batch operations.

  protected $primary_key = 'id';

  /** 
   * To make aliases to the database field names, override the $aliases
   * member in your sub-classes. It should be an associative array, where
   * the key is the alias, and the value is the target database field.
   * NOTE: Aliases are not known about by the Model or ResultSet objects, 
   * so functions like getRowByField and manual SELECT statements must use 
   * the real database field names, not aliases!
   */
  protected $aliases = array();

  /**
   * Assume the primary key is automatically generated by the database.
   */
  protected $auto_generated_pk = True;

  /**
   * A list of virtual properties. 
   * These must be accessed using _set_$name and _get_$name
   */
  protected $virtuals = [];

  // Build a new object.
  public function __construct ($opts, $parent=null, $table=null, $pk=null)
  { 
    if (isset($opts, $opts['parent']) && !isset($parent))
    { // Using new-style constructor.
      $this->parent = $opts['parent'];
      if (isset($opts['data']))
        $this->data = $opts['data'];
      else
        $this->data = [];
      if (isset($opts['table']))
        $this->table = $opts['table'];
      if (isset($opts['pk']))
        $this->primary_key = $opts['pk'];
    }
    elseif (isset($opts, $parent))
    { // Using old-style constructor.
      $this->data   = $opts;
      $this->parent = $parent;
      if (isset($table))
        $this->table = $table;
      if (isset($pk))
        $this->primary_key = $pk;
    }
    else
    {
      throw new \Exception("Invalid constructor for DB\Item");
    }
    $this->modified_data = [];
  }

  /** 
   * Look for a field
   *
   * If the field exists in the database, it's returned unchanged.
   *
   * If an alias exists, the alias target field will be returned.
   *
   * If neither exists, and the field is not the primary key,
   * an exception will be thrown.
   */
  protected function db_field ($name, $strict=True)
  {
    if (array_key_exists($name, $this->data))
      return $name;
    elseif (array_key_exists($name, $this->aliases))
      return $this->aliases[$name];
    elseif ($name == $this->primary_key)
      return $name;
    elseif ($this->parent->is_known($name))
      return $name;
    elseif (in_array($name, $this->virtuals))
      return $name;
    else
      if ($strict)
        throw new Exception("Unknown field '$name' in ".json_encode($this->data));
      else
        return Null;
  }

  /** 
   * Set a database field.
   *
   * Unless $auto_generated_pk is set to False, this will throw an
   * exception if you try to set the value of the primary key.
   */
  public function __set ($field, $value)
  {
    $name = $this->db_field($field);

    if ($name == $this->primary_key)
    {
      if ($this->auto_generated_pk)
      {
        throw new Exception('Cannot overwrite primary key.');
      }
      elseif (!isset($this->data[$name]))
      {
        $this->data[$name] = true; // a defined initial value.
      }
    }

    if (!in_array($name, $this->virtuals))
      $this->modified_data[$name] = $this->data[$name];

    $meth = "_set_$field";
    if (is_callable(array($this, $meth)))
    {
      $this->$meth($value);
    }
    else
    {
      $this->data[$name] = $value;
    }
    if ($this->auto_save)
    {
      $this->save();
    }
  }

  /** 
   * Restore the previous value (we only store one.)
   * Does not work with auto_save turned on.
   */
  public function restore ($name)
  {
    $name = $this->db_field($name);
    if (isset($this->modified_data[$name]))
    {
      $this->data[$name] = $this->modified_data[$name];
      unset($this->modified_data[$name]);
    }
  }

  /** 
   * Undo all modifications.
   * Does not work with auto_save turned on.
   */
  public function undo ()
  {
    foreach ($this->modified_data as $name => $value)
    {
      $this->data[$name] = $value;
    }
    $this->modified_data = array();
  }

  /** 
   * Get a database field.
   */
  public function __get ($field)
  {
    $name = $this->db_field($field);
    $meth = "_get_$field";
    if (is_callable(array($this, $meth)))
    {
      return $this->$meth();
    }
    else
    {
      return $this->data[$name];
    }
  }

  /** 
   * See if a database field is set.
   * 
   * For the purposes of this method, '' is considered unset.
   */
  public function __isset ($name)
  {
    $name = $this->db_field($name, False);
    if (isset($name))
      return (isset($this->data[$name]) && $this->data[$name] != '');
    else
      return False;
  }

  /** 
   * Sets a field to null.
   */
  public function __unset ($name)
  {
    $name = $this->db_field($name);
    $this->modified_data[$name] = $this->data[$name];
    $this->data[$name] = null;
    if ($this->auto_save)
    {
      $this->save();
    }
  }

  /**
   * ArrayAccess interface, alias to __isset().
   */
  public function offsetExists ($name)
  {
    return $this->__isset($name);
  }

  /**
   * ArrayAccess interface, alias to __set().
   */
  public function offsetSet ($name, $value)
  {
    return $this->__set($name, $value);
  }

  /**
   * ArrayAccess interface, alias to __unset().
   */
  public function offsetUnset ($name)
  {
    return $this->__unset($name);
  }

  /**
   * ArrayAccess interface, alias to __get().
   */
  public function offsetGet ($name)
  {
    return $this->__get($name);
  }

  /**
   * Save our data back to the database.
   *
   * If the primary key is set, and has not been modified, this will
   * update the existing record with the new data.
   *
   * If the primary key has not been set, or has been modified, this will
   * insert a new record into the database, and in the case of auto-generated
   * primary keys, update our primary key field to point to the new record.
   */
  abstract public function save ($opts=[]);

  /** 
   * Delete this item from the database.
   */
  abstract public function delete ();

  /** 
   * Start a batch operation. 
   *
   * We disable the 'auto_save' feature, saving its value for later.
   */
  public function start_batch ()
  {
    $this->save_value = $this->auto_save;
    $this->auto_save = False;
  }

  /** 
   * Finish a batch operation.
   *
   * We restore the auto_save value, and if it was true, save the data.
   */
  public function end_batch ()
  {
    $this->auto_save = $this->save_value;
    if ($this->auto_save)
    {
      $this->save();
    }
  }

  /** 
   * Cancel a batch operation. 
   *
   * We run $this->undo() and then restore the auto_save value.
   */
  public function cancel_batch ()
  {
    $this->undo();
    $this->auto_save = $this->save_value;
  }

} // end class Child

