<?php

// we need the case converter for resolving file names to be included. So we
// have to include it the messy way here.
require_once dirname(__FILE__).'/class.cb_case_converter.php';

class CbAutoload {

   private static $registered = false;

   /**
    * add our autoloader to the queue (there may already be a Zend or Propel autoloader)
    */
   public static function register()
   {
      if (!self::$registered) {
         spl_autoload_register(array('CbAutoload', 'autoload'));
         self::$registered = true;
      }
      self::addIncludePath(dirname(__FILE__));
      self::addIncludePath('.');
   }

   public static function addIncludePath($path) {
      set_include_path($path.PATH_SEPARATOR.get_include_path());
   }

   /**
    * just a more natural name for autoload.
    *
    * And easier usage as well. You may load as many classes as you like.
    *
    * usage:
    * <pre>
    * Cb::import('CbSomeClass');
    * Cb::import('CbSomeClass', 'CbSomeOtherClass');
    * </pre>
    *
    * @param string class_name
    * @param ...
    */
   public static function import()
   {
      $a = func_get_args();
      foreach ($a as $c) self::autoload($c);
   }

   /**
    * This method returns the filename where a given class can be found.
    */
   public static function fileNameFromClass($class_name)
   {
      $m = array();
      if (preg_match('/(\w+)(Interface|Test|Suite)$/', $class_name, $m)) {
         return lcfirst($m[2]).'.'.CbCaseConverter::snakeify($m[1]).'.php';
      } else {
         return 'class.'.CbCaseConverter::snakeify($class_name).'.php';
      }
   }

   /**
    * Returns classname of class which is defined in given file.
    *
    * @param string filename
    * @return string classname
    */
   public static function classFromFileName($filename)
   {
      $m = array();
      if (preg_match('/^(\w+)\.(\w+)\.php$/', basename($filename), $m)) {
         $class = CbCaseConverter::camelize($m[2]);
         if ($m[1] !== 'class') $class .= ucfirst($m[1]);
         return $class;
      } else {
         throw InvalidArgumentException("$filename is not a valid filename");
      }
   }

   public static function handleAutoloadNotFoundWarning($a, $b) {}

   /**
    * This is more like to be used in __autoload() function.
    *
    * imports file where given class is defined.
    *
    * @param string a class's name
    * @return boolean true, if require_once() is successful, nothing is returned
    *                 otherwise because a fatal error would occurr anyway
    */
   public static function autoload($class_name)
   {
      set_error_handler(array('CbAutoload', 'handleAutoloadNotFoundWarning'),
            E_WARNING);
      $found = include_once(self::fileNameFromClass($class_name));
      restore_error_handler();
      return $found;
   }
}