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
 * This class is used to convert between camel case and snake case string
 * representations.
 */
class CbCaseConverter {
   /**
    * Converts snake_case to CamelCase.
    *
    * @param string String in snake_case
    * @return CamelCase string
    */
   public static function camelize($string) {
      return implode('', array_map('ucfirst', preg_split('/[_\.]/', $string)));
   }

   /**
    * Strips a string down to the very basic alphanumeric characters and tries
    * to create a snake_case representation of the string.
    *
    * @param string Target
    * @return Snakeified string
    */
   public static function snakeify($string) {
      // Convert any special characters to match snake_case.
      $string = preg_replace_callback('/([^a-z0-9])/', array(__CLASS__, 'snakeify_callback'), $string);

      // Make sure that there is no more than one underscore in a row.
      $string = preg_replace('/__+/', '_', $string);

      // Make sure that there are no underscores at beginning and end.
      $string = trim($string, '_');

      return $string;
   }

   private static function snakeify_callback($matches) {
      return sprintf('_%s', strtolower($matches[1]));
   }
}