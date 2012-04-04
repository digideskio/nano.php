<?php

/**
 * ConfServ
 *
 * Helps you build services which return information from a
 * Nano3 Conf registry in specific formats.
 *
 * We currently have JSON output as our natively supported format.
 *
 */

namespace Nano3\Plugins;
use Nano3\Exception;

class ConfServ
{
  // Get a section, as a PHP array/value. Decode any objects.
  public function getPath ($path, $full=False)
  {
    $nano = \Nano3\get_instance();
    $section = $nano->conf->getPath($path);
    if ($full)
    {
      if (is_object($section))
      {
        if (is_callable(array($section, 'to_array')))
        {
          return $section->to_array();
        }
        else
        {
          return Null;
        }
      }
      else
      {
        return $section;
      }
    }
    else
    { // Get a summary, consisting of the keys.
      $keys = Null;
      if (is_object($section))
      {
        if (is_callable(array($section, 'array_keys')))
        {
          $keys = $section->array_keys();
        }
        else
        {
          return Null;
        }
      }
      elseif (is_array($section))
      {
        $keys = array_keys($section);
      }
      elseif (is_string($section) || is_numeric($section))
      {
        return $section;
      }
      return $keys;
    }
  }

  // Process a request. PHP value to encode.
  // If you want to send it a pre-encoded JSON string, pass True as
  // the second parameter.
  public function sendJSON ($data, $encoded=False)
  {
    $nano = \Nano3\get_instance();
    $nano->pragma('no-cache');
    header('Content-Type: application/json');
    if ($encoded)
    {
      echo $data;
    }
    else
    {
      if (is_object($data) && is_callable(array($data, 'to_json')))
      {
        echo $data->to_json();
      }
      else
      {
        echo json_encode($data, true);
      }
    }
    exit;
  }

  // A simple JSON-based request. If you need anything more complex
  // than this, you'll need to do it yourself.
  public function jsonRequest ($path, $full=False)
  {
    $data = $this->getPath($path, $full);
    $this->sendJSON($data);
  }

}

