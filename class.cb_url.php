<?php

class CbUrl {
   // --------------------------------------------------------------------------
   // URLs
   // --------------------------------------------------------------------------

   /**
    * Returns the website's root directory. Trailing slashes are always omitted.
    *
    * @return string document root
    */
   public static function getDocumentRoot() {
      static $dr = null;
      if ($dr === null) $dr = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
      return $dr;
   }

   /**
    * Removes the root directory from a path (if present) to make it relative.
    *
    * @return string relative path
    */
   public static function stripDocumentRoot($path) {
      $dr = self::getDocumentRoot();
      $drLength = mb_strlen($dr);
      if (mb_substr($path, 0, $drLength) !== $dr) return $path;
      return ltrim(mb_substr($path, $drLength), '/');
   }

   /**
    * Returns the website's root directory including the current language.
    * Trailing slashes are always omitted.
    *
    * @return string link root
    */
   public static function getLinkRoot() {
      return self::getDocumentRoot() . '/' . CbMl::getCurrentLanguage();
   }

   /**
    * Returns the website's root url including the current language.
    * Trailing slashes are always omitted.
    *
    * @return string link root url
    */
   public static function getUrlRoot() {
      return 'http'.($_SERVER['HTTPS']?'s':'').'://'.$_SERVER['HTTP_HOST'].
              self::getDocumentRoot() . '/' . CbMl::getCurrentLanguage();
   }

   /**
    * Returns the website's root url without the current language.
    * Trailing slashes are always omitted.
    *
    * @return string link root url
    */
   public static function getUrlRootWithoutLocale() {
      return 'http'.($_SERVER['HTTPS']?'s':'').'://'.$_SERVER['HTTP_HOST'].
              self::getDocumentRoot();
   }

   /**
    * This regular expression is used to detect locals AT THE BEGINNING of URLs.
    * It is not designed to parse arbitrary URLs, because detecting locales in
    * those is way to error prone. Locale-like things are too common in regular
    * URLs. We only parse those at the beginning because thats where the locale
    * is in all of our URLs. At the moment, the following locale notations are
    * supported:
    *
    *  - de_DE
    *  - de
    *  - deu
    *
    * Add other ones as needed, but make sure that it still works for the cases
    * listed above. Also, it is important that the match group numbers in
    * CbUtil::getUrlWithLocale() get adjusted accordingly. Lastly, make sure
    * that your new locale format is listed above to ensure that future
    * maintainers are still able to figure out what this thing needs to do.
    */
   const LOCALE_REGEXP = '/^([a-z]{2}_[A-Z]{2}|[a-z]{2,3})(\/|$)/';

   /**
    * Changes the locale in a standard URL. The URL is expected to be in one
    * of the following formats (empty URLs are accepted as well):
    *
    *  - /project-name/locale/any-other-stuff
    *  - /project-name/locale
    *  - /project-name/any-other-stuff
    *  - /project-name
    *  - /locale/any-other-stuff
    *  - /locale
    *
    * All of the above variants also accept trailing slashes, but they will be
    * removed. If there is no locale present in the URL, the new one is inserted
    * at the end.
    *
    * @param string $locale the locale to be inserted
    * @param string $url if null, the current URL is used
    * @return string "translated" URL
    */
   public static function getUrlWithLocale($locale, $url = null) {
      // Default to the current URL if no other one is given.
      $url = $url !== null ? $url : $_SERVER['REQUEST_URI'];

      // Strip the document root.
      $url = trim(substr($url, strlen(self::getDocumentRoot())), '/');

      // Look for locales at the beginning of the URL.
      if (preg_match(self::LOCALE_REGEXP, $url)) {
         // Replace the old locale with the new one.
         $url = preg_replace(self::LOCALE_REGEXP, $locale . '$2', $url);
      } else {
         // Append the new locale.
         $url = rtrim($url, '/') . '/' . $locale;
      }

      // Re-prepend the document root.
      $url = self::getDocumentRoot() . '/' . ltrim($url, '/');

      return $url;
   }
}