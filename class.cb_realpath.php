<?php

/**
 * Get real paths for files. Especially those in module from outside of module.
 */
class CbRealpath {

   static function getFromModules(array $files, $prefix = '') {
      return self::get($files, dirname(__FILE__)."/../../".$prefix.'/');
   }

   static function get(array $files, $prefix = '') {
      if ($prefix) $prefix .= '/'; // realpath strips double slashes
      $real = array();
      foreach($files as $file) {
         $real[] = realpath($prefix.$file);
      }
      return $real;
   }
}
