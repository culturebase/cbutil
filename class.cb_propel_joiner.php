<?php

/**
 * Joins up nested queries in order to reduce DB overhead in get* methods later.
 */
class CbPropelJoiner {
   protected static $joinTypes = array(
         Criteria::LEFT_JOIN => 'left',
         Criteria::RIGHT_JOIN => 'right',
         Criteria::INNER_JOIN => 'inner'
   );
   
   /**
    * Accepts an array of classes/tables to be joined and extends the given
    * query so that the results are automatically hydrated in the desired object
    * hierarchy.
    * @param ModelCriteria $query Base query to be extended with joins.
    * @param array $tables Hierarchical list of tables in the form of nested
    *    arrays of Propel PhpNames. Tables without further joins can be given as
    *    simple strings, i.e. this is OK: array('Cb3Tuser' => 'Cb3Taccount').
    * @param string $joinType One of Criteria::LEFT_JOIN, Criteria::RIGHT_JOIN,
    *    Criteria::INNER_JOIN.
    * @return ModelCriteria The query extended with joins.
    */
	public static function join($query, $tables, $joinType = Criteria::LEFT_JOIN)
	{
      if (!is_array($tables)) $tables = array($tables);
		foreach ($tables as $name => $children) {
         if (is_numeric($name)) {
            $name = $children;
            $children = null;
         }
         
			$join = self::$joinTypes[$joinType].'JoinWith'.$name;
			$query = $query->$join();
         
         if ($children) {
            $use = 'use'.$name.'Query';
            $query = self::join($query->$use('', $joinType), $children, $joinType)->endUse();
         }
		}
      return $query;
	}
}