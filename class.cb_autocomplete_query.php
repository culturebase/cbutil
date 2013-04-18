<?php
/* This file is part of cbutil.
 * Copyright © 2011-2013 stiftung kulturserver.de ggmbh <github@culturebase.org>
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

class CbAutocompleteQuery {
   /**
    * get autocompletion data from a generic propel query.
    * @param query A query implementing filterForAutocomplete($input)
    * @param input string to be fed into the query. If this is missing $_REQUEST['actualInput'] is assumed.
    * @param filter array of propel column name => array of values as additional filter. If this is missing $_REQUEST['filter'] is assumed.
    * @return a json-encoded 'results' array of id, value and info to be fed into the autocomplete box
    */
   public static function find($query, $input = false, $filter = false) {
      if (!$input) $input = $_REQUEST['actualInput'];
      if (!$filter) $filter = $_REQUEST['filter'];
      $query = $query->distinct()->filterForAutocomplete($input);
      if ($filter) foreach($filter as $column => $values) {
         $query = $query->filterBy($column, $values, Criteria::IN);
      }
      $objects = $query->find();
      $ret = array();
      while($object = $objects->pop()) {
         $ret[] = $object->toIdValueInfo();
      }

      return array("results" => $ret);
   }
}