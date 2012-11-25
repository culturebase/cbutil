<?php
/* This file is part of cbutil.
 * Copyright © 2011-2012 stiftung kulturserver.de ggmbh <github@culturebase.org>
 *
 * cbutil is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * cbutil is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with cbutil.  If not, see <http://www.gnu.org/licenses/>.
 */

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
