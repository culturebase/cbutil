<?php

/**
 * Get real paths for files. Especially those in module from outside of module.
 */
class CbRealpath {

   /**
    * @deprecated Use getFromHere.
    */
   static function getFromModules($files, $prefix = '')
   {
      return self::getFromHere($files, $prefix);
   }

   /**
    * Get the absolute paths of the given files which are located relative to
    * this one. You can use that to find e.g. locations of JS or CSS files in
    * projects included using PHP's inclusion mechanism without actually knowing
    * the filesystem path of the included project. Unfortunately it doesn't
    * scale so well, as you can not redeclare CbRealpath...
    *
    * @param array $files Files to be resolved.
    * @param string $prefix Path prefix to be added when looking for them.
    * @param int $levels_up Number of '../' to be added to escape from the
    *                       subfolder this file is in.
    * @return array Absolute paths to the given files.
    */
   static function getFromHere($files, $prefix = '', $levels_up = 2)
   {
      $parts = array_fill(0, $levels_up, '..');
      $parts[] = $prefix;
      return self::get($files, dirname(__FILE__).'/'.implode('/', $parts).'/');
   }

   static function get(array $files, $prefix = '')
   {
      if ($prefix) $prefix .= '/'; // realpath strips double slashes
      $real = array();
      foreach ($files as $file) $real[] = realpath($prefix . $file);
      return $real;
   }

}
