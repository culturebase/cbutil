<?php

// Ensure that all required minifiers are available.
require_once '3rdparty/jsmin.php';
require_once 'class.cb_css_min.php';

/**
 * Generic combiner/compressor/minifier for JavaScript and CSS (including
 * automatic caching).
 *
 * @author Johannes Wüller <jw@heimat.de>
 */
class CbMin {

   // These represent the type of file that is about to be minified.
   const JS  = 0x1;
   const CSS = 0x2;

   // These are used for simple scanning of comment nesting.
   const TOKEN_COMMENT_START                 = 0x1;
   const TOKEN_COMMENT_END                   = 0x2;
   const TOKEN_CONDITIONAL_COMPILATION_START = 0x4;
   const TOKEN_CONDITIONAL_COMPILATION_END   = 0x8;

   /**
    * Stores the mime-types associated with the various minified files.
    */
   private static $mimeTypes = array(
      self::JS  => 'application/x-javascript',
      self::CSS => 'text/css'
   );

   /**
    * Stores the file extensions for the cached files that identify their type.
    */
   private static $extensions = array(
      self::JS  => 'js',
      self::CSS => 'css'
   );

   /**
    * Maps token values to token constants. WARNING: The tokens are ordered by
    * priority. The lower, the more important (they override in that order). Do
    * not mess with that without thinking about it!
    */
   private static $tokens = array(
      '/*'       => self::TOKEN_COMMENT_START,
      '*/'       => self::TOKEN_COMMENT_END,
      '/*@cc_on' => self::TOKEN_CONDITIONAL_COMPILATION_START,
      '/*@if'    => self::TOKEN_CONDITIONAL_COMPILATION_START,
      '/*@set'   => self::TOKEN_CONDITIONAL_COMPILATION_START,
      '@*/'      => self::TOKEN_CONDITIONAL_COMPILATION_END
   );

   /**
    * Stores the current configuration.
    */
   private $options;

   /**
    * Creates a minifier.
    *
    * The following properties are available:
    *    property ----- type --- default --- description -----------------------
    *    cache.enabled  boolean  true        wether caching should be used
    *                                        (should be disabled for debugging
    *                                        only)
    *    cache.create   boolean  true        wether to create the cache
    *                                        directory (recursively) if it does
    *                                        not exist
    *    cache.dir      string   /tmp        cache location (/tmp/cb_min is
    *                                        the default global cache location)
    *    minify         boolean  true        wether to minify the concatenated
    *                                        files (gets automatically disabled
    *                                        on development servers unless
    *                                        explicitly specified)
    *    debug          boolean  false       wether to include debug comments
    *                                        that identify each file after
    *                                        concatenation (gets automatically
    *                                        enabled on development servers
    *                                        unless explicitly specified)
    *
    * @param array $options list of properties
    */
   public function __construct(array $options = array()) {
      // Are we working on a development server?
      $isDev = substr(php_uname('n'), 0, 3) === 'dev';

      // Merge with defaults to obtain a complete configuration.
      $this->options = array_merge(array(
         'cache.enabled' => true,
         'cache.create'  => true,
         'cache.dir'     => '/tmp/cb_min',
         'minify'        => !$isDev,
         'debug'         => $isDev
      ), $options);

      // Remove trailing slashes.
      if ($this->options['cache.dir'] !== null) {
         $this->options['cache.dir'] = rtrim($this->options['cache.dir'], DIRECTORY_SEPARATOR);
      }

      // We definitely need a cache dir if caching is enabled.
      if ($this->options['cache.enabled'] && !is_dir($this->options['cache.dir'])) {
         // Create the cache directory if we are allowed to do so. Spit an
         // exception otherwise.
         if (!$this->options['cache.create'] || !mkdir($this->options['cache.dir'], 755, true)) {
            throw new CbMinException(sprintf("The caching directory `%s' does" .
                    " not exist or is unaccessible. Please check the file " .
                    "permissions.",
                    $this->options['cache.dir']));
         }
      }
   }

   /**
    * Helper for easy serving. The filenames (without directory and extension)
    * are fetched from the request URL. The suffix is automatically appended
    * based on the served type. More complex serving approaches should use the
    * regular interface. The URL should look like this:
    *
    *    requested_script.php/a,b,c,...
    *
    * The directory is added in front and the file extension at the back of
    * the names. The result could look like this:
    *
    *    styles/a.css
    *    styles/b.css
    *    styles/c.css
    *
    * All of these files are then served regularly.
    *
    * @param integer $type type of the files
    * @param string $directory
    * @param array $minOptions
    */
   public static function serveSimple($type, $directory = '', array $minOptions = array()) {
      if (!isset(self::$extensions[$type])) {
         throw new CbMinException("Unknown type.");
      }

      // Sanitize directory.
      $directory = rtrim($directory, DIRECTORY_SEPARATOR);

      if (!empty($directory)) {
         $directory .= DIRECTORY_SEPARATOR;
      }

      // Parse URL to determine requested files and convert them to actual
      // filenames.
      $files = array();

      foreach (explode(',', basename(rtrim($_SERVER['REQUEST_URI'], DIRECTORY_SEPARATOR))) as $filename) {
         $files[] = sprintf('%s%s.%s', $directory, trim($filename), self::$extensions[$type]);
      }

      // Serve it.
      $minifier = new CbMin($minOptions);
      $minifier->serve($files, $type);
   }

   /**
    * Delivers the minified files at once.
    *
    * @param array $files list of files
    * @param integer $type type of the files
    */
   public function serve(array $files, $type) {
      // Remove all files that do not have the right suffix to prevent attacks.
      foreach ($files as $key => $filename) {
         $extension = strtolower(preg_replace('/^.*\.([a-z])$/i', '$1', $filename));

         if (in_array($extension, array_values(self::$extensions))) {
            unset($files[$key]);
         }
      }

      // Ensure that we are not using a sparse array after cleanup.
      $files = array_values($files);

      // We need a valid type.
      if (!isset(self::$mimeTypes[$type])) {
         throw new CbMinException("Unknown type.");
      }

      // Serve the whole thing using the correct mime-type.
      header('Content-Type: ' . self::$mimeTypes[$type] . ';charset=utf-8');

      // Is there anything to do?
      if (!empty($files)) {
         $content = null;

         // Do we have a cached version available?
         if ($this->options['cache.enabled']) {
            // Build identifier.
            $cacheFile = $this->options['cache.dir'] . DIRECTORY_SEPARATOR . $this->id($files, $type);

            // Check if the cache needs to be invalidated (this needs to be done
            // if any of the combined files has been modified since the cache
            // has been generated).
            if (file_exists($cacheFile)) {
               $cacheAge = filemtime($cacheFile);

               foreach ($files as $filename) {
                  if (filemtime($filename) > $cacheAge) {
                     // Invalidate the cache (i.e. delete the cache file).
                     unlink($cacheFile);
                     break;
                  }
               }
            }

            // Do not send anything (304) if the client has a cached version of
            // the file (identified by etag). This is ignored if the file does
            // not exist, since it does not make sense to cache a non-existant
            // resource on the client-side.
            if (file_exists($cacheFile)) {
               $requestHeaders = apache_request_headers();

               if (isset($requestHeaders['If-None-Match']) && $requestHeaders['If-None-Match'] == $this->etag($cacheFile)) {
                  header("HTTP/1.1 304 Not Modified");

                  // Abort any further action to prevent any output or further
                  // IO operations.
                  exit;
               }
            }

            // Generate the cache if it is missing (or has been invalidated).
            if (!file_exists($cacheFile)) {
               // Process the input files.
               $content = $this->build($files, $type);

               // Save the processed files to the disc.
               if (file_put_contents($cacheFile, $content) === false) {
                  throw new CbMinException(sprintf("Could not write caching " .
                          "file `%s'. Please check the file permissions.",
                          $cacheFile));
               }

               // Prepend the cache file path if we are debugging.
               if ($this->options['debug']) {
                  $content = sprintf("/* created cache file: %s */\n\n", $cacheFile) . $content;
               }
            } else {
               // There is a cached version. Let's use that.
               $content = file_get_contents($cacheFile);

               if ($this->options['debug']) {
                  $content = sprintf("/* loaded cache file: %s */\n\n", $cacheFile) . $content;
               }
            }

            // Send an etag for caching.
            if (!$this->options['debug']) {
               header('ETag: ' . $this->etag($cacheFile));
            }
         } else {
            // Caching is disabled. We need to do everything on the fly.
            $content = $this->build($files, $type);

            if ($this->options['debug']) {
               $content = "/* built on the fly */\n\n" . $content;
            }
         }

         // Deliver the whole thing.
         echo $content;
         exit;
      }
   }

   /**
    * Does the actual concatenation/compression/minification.
    *
    * @param array $files list of files
    * @param integer $type type of the files
    * @return processed file content
    */
   private function build(array $files, $type) {
      $content = '';

      // Determine the absolute filesystem path for all files.
      foreach ($files as $key => $filename) {
         $absoluteFilename = realpath($filename);

         if ($absoluteFilename === false) {
            throw new CbMinException(sprintf("Could not determine absolute " .
                    "path for file `%s'. It probably does not exist.",
                    $filename));
         }

         $files[$key] = $absoluteFilename;
      }

      // Prepend content information if we are debugging.
      if ($this->options['debug']) {
         $content .= "/* \n" .
                     " * Combined files (in this order):\n";

         // Find the length of the longest file path. We need this to be able to
         // align all filenames properly in the next step.
         $filenameLength = 0;
         foreach ($files as $filename) {
            $filenameLength = max($filenameLength, mb_strlen($filename));
         }

         // List all filenames in a table-structure.
         foreach ($files as $filename) {
            $content .= sprintf(" *    %-" . $filenameLength . "s [%s]\n", $filename, date('d.m.Y H:i:s', filemtime($filename)));
         }

         $content .= " */\n";
      }

      // Concat (and minify) the content of all files.
      foreach ($files as $filename) {
         if ($this->options['debug']) {
            $content .= sprintf("\n/* --- %s %s */\n", $filename, str_repeat('-', max(69 - strlen($filename), 3)));
         }

         $fileContent = file_get_contents($filename);

         if ($fileContent === false) {
            throw new CbMinException(sprintf("Could not read file `%s'. " .
                    "Please check the file permissions.",
                    $filename));
         }

         if ($this->options['minify']) {
            // Do the actual minification.
            switch ($type) {
               case self::JS:  $content .= trim(JSMin::minify($fileContent)); break;
               case self::CSS: $content .= CbCssMin::minify($fileContent);    break;
            }
         } else {
            // No minification, just append.

            // We may need to include line numbers in front of each line to make
            // sure that they are easy to find.
            if ($this->options['debug']) {
               $content .= $this->addLineNumbers($fileContent);
            } else {
               // Nope, only plain files.
               $content .= $fileContent;
            }
         }

         $content .= "\n";
      }

      return $content;
   }

   /**
    * Generates a unique identifier based on used files, type, minification and
    * debugging settings. The identifier can be used as a filename.
    *
    * @param array $files list of files
    * @param integer $type type of the files
    * @return unique identifier
    */
   private function id(array $files, $type) {
      // Collect additional attributes.
      $attributes = array();
      if ($this->options['minify']) $attributes[] = 'min';
      if ($this->options['debug'])  $attributes[] = 'dbg';

      // Build identifier.
      return sprintf('%s%s.%s',
              $this->hash($files),
              !empty($attributes) ? '.' . implode('.', $attributes) : '',
              self::$extensions[$type]);
   }

   /**
    * Builds a unique identifier for the used input files. Order is important,
    * so different order creates different identifiers.
    *
    * @param array $files list of files
    * @return identifier
    */
   private function hash(array $files) {
      return md5(implode(';', array_map('realpath', $files)));
   }

   /**
    * Generates a standard etag (like the one apache uses) for the given file.
    * It consists out of inode, size and mtime.
    *
    * @param type $filename
    */
   private function etag($filename) {
      $stat = stat($filename);

      return sprintf('"%x-%x-%s"',
              $stat['ino'],
              $stat['size'],
              base_convert(sprintf('%016d', $stat['mtime']), 10, 16));
   }

   /**
    * Adds line numbers to a code snippet.
    *
    * @param string $code
    * @return string code
    */
   private function addLineNumbers($code) {
      $lines = explode("\n", $code);
      $lineNumberDigitCount = mb_strlen(sprintf('%d', count($lines)));
      $isInComment = false;
      $isInConditionalCompilation = false;

      foreach ($lines as $index => $line) {
         // We only need to include a comment if we are not already in a
         // comment. We use a similar visual indicator to avoid interrupting the
         // visual flow. This makes it easier to scan the file.
         $format = sprintf($isInComment ? '/+ %s +/' : '/* %s */', '%' . $lineNumberDigitCount . 'd');
         $prefix = sprintf($format, $index + 1);

         // Of course, there is a special case for IE: Conditional compilation.
         // It allows for IE-only JavaScript (again, inside comments... who
         // thinks of this kind of stuff?). Thus, it does not support our visual
         // aid for file scanning in what looks like a comment. We need to
         // completely disable non-whitespace additions in that case.
         if ($isInConditionalCompilation) {
            $prefix = str_repeat(' ', mb_strlen($prefix));
         }

         // Put everything together.
         $lines[$index] = $prefix . '   ' . $line;

         // Determine wether the tokens are balanced and remember it for the
         // following lines. Luckily, JavaScript does not support multi-line
         // strings. THAT would be tricky to parse safely. Comments are easy,
         // due to their simple nature (no nesting, etc). Conditional
         // compilation is a special case for IE, though.
         foreach ($this->tokenize($line) as $token) {
            switch ($token) {
               case self::TOKEN_CONDITIONAL_COMPILATION_START: $isInConditionalCompilation = true;  break;
               case self::TOKEN_CONDITIONAL_COMPILATION_END:   $isInConditionalCompilation = false; break;
               case self::TOKEN_COMMENT_START:                 $isInComment = true;                 break;
               case self::TOKEN_COMMENT_END:                   $isInComment = false;                break;
            }
         }
      }

      return implode("\n", $lines);
   }

   /**
    * Converts a string into a token list.
    *
    * @param string $string
    * @return array tokens
    */
   private function tokenize($string) {
      $collectedTokens = array();

      // Look out for all the tokens and store them by position.
      foreach (self::$tokens as $value => $token) {
         $pos = 0;
         while (($pos = strpos($string, $value, $pos)) !== false) {
            $collectedTokens[$pos] = $token;
            $pos += mb_strlen($value);
         }
      }

      // Get them in the correct order.
      ksort($collectedTokens);

      return array_values($collectedTokens);
   }

}

/**
 * Represents an exception that can occur
 */
class CbMinException extends Exception {}
