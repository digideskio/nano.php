<?php

/**
 * A base class representing individual items from the model.
 * For use in an ORM-style model.
 * You can call $item->save(); to update the database.
 * The constructor requires the hash results from a query,
 * the DBModel object which created it, and the table to save results to.
 */

namespace Nano4\DB;

class Item implements \ArrayAccess
{
  public $parent;              // The DBModel object that created us.
  protected $data;             // The hash data returned from a query.
  protected $table;            // The database table to update with save().
  protected $modified_data;    // Fields we have modified.
  protected $save_value;       // Used in batch operations.
  public $auto_save = False;   // Do we want to auto-save changes?

  protected $primary_key = 'id';  // The key for our identifier (default 'id'.)

  // To make aliases to the database field names, override the $aliases
  // member in your sub-classes. It should be an associative array, where
  // the key is the alias, and the value is the target database field.
  // NOTE: Aliases are not known about by the Model or ResultSet objects, 
  // so functions like getRowByField and manual SELECT statements must use 
  // the real database field names, not aliases! 
  protected $aliases = array();

  // Assume the primary key is automatically generated by the database.
  protected $auto_generated_pk = True;

  // If set to an array of field names, only those fields will be
  // used when finding the id of a newly created row.
  public $new_query_fields;

  // Can't get much easier than this.
  public function __construct ($data, $parent, $table, $primary_key=Null)
  { 
    $this->data        = $data;
    $this->parent      = $parent;
    $this->table       = $table;
    if (isset($primary_key))
      $this->primary_key = $primary_key;
    $this->modified_data = array();
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
  public function save ()
  {
    $pk = $this->primary_key;
    if (isset($this->data[$pk]) && !isset($this->modified_data[$pk]))
    { // Update an existing row.
      if (count($this->modified_data)==0) return;
      $sql = "UPDATE {$this->table} SET ";
      $data = array($pk=>$this->data[$pk]);
      $fields = array_keys($this->modified_data);
      $fc = count($fields);
      for ($i=0; $i< $fc; $i++)
      {
        $field = $fields[$i];
        if ($field == $pk) continue; // Sanity check.
        $data[$field] = $this->data[$field];
        $sql .= "$field = :$field";
        if ($i != $fc -1)
        {
          $sql .= ', ';
        }
      }
      $sql .= " WHERE $pk = :$pk";
      $query = $this->parent->query($sql);
      $query->execute($data);
      $this->modified_data = [];
      return True;
    }
    else
    { // Insert a new row.
      $model = $this->parent;
      $opts = array('return'=>$model::return_key);
      if ($this->auto_generated_pk)
        $setpk = False;
      else
        $setpk = True;

      if ($setpk)
        $opts['allowpk'] = True;

      if (isset($this->new_query_fields))
        $opts['columns'] = $this->new_query_fields;

      // Insert the row and get the new primary key.
      $newpk = $this->parent->newRow($this->data, $opts);

      // Clear the modified data.
      $this->modified_data = [];

      if ($setpk && isset($this->data[$pk])) return True; // We're done.
      elseif ($newpk)
      {
        $this->data[$pk] = $newpk;
        return True;
      }
      return False;
    }
  }

  /** 
   * Delete this item from the database.
   */
  public function delete ()
  {
    $pk = $this->primary_key;
    $sql = "DELETE FROM {$this->table} WHERE $pk = :$pk";
    $query = $this->parent->query($sql);
    $data = array ($pk => $this->data[$pk]);
    $query->execute($data);
  }

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

} // end class Item

// End of file.

