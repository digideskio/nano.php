<?php

namespace Nano4\DB\Mongo;

/**
 * A base class for SQL database models.
 */
class Item extends \Nano\DB\Child
{
  protected $primary_key = '_id';

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
  public function save ($opts=[])
  {
    if (isset($opts['pk']))
      $pk = $opts['pk'];
    else
      $pk = $this->primary_key;

    if (is_callable([$this, 'to_bson']))
      $data = $this->to_bson($opts);
    else
      $data = $this->data;

    if (isset($this->data[$pk]) && !isset($this->modified_data[$pk]))
    { // Update an existing row.
      if (count($this->modified_data)==0) return;
      $fields = array_keys($this->modified_data);
      $cdata  = [];
      $fc = count($fields);
      for ($i=0; $i< $fc; $i++)
      {
        $field = $fields[$i];
        if ($field == $pk) continue; // Sanity check.
        $cdata[$field] = $data[$field];
      }
      $id = $data[$pk];
      return $this->parent->save($id, ['$set'=>$cdata]);
    }
    else
    { // Insert a new row.
      // Clear the modified data.
      $this->modified_data = [];
      return $this->parent->save($data);
    }
  }

  /** 
   * Delete this item from the database.
   */
  public function delete ()
  {
    $pk = $this->primary_key;
    $id = $this->data[$pk];
    return $this->parent->deleteId($id);
  }

} // end class Item

