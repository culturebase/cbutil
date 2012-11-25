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

require_once 'class.cb_transliterator.php';

class CbText {
   /**
    * Removes all known markup from the text.
    *
    * @param string $text
    * @return string plain text
    */
   public static function plain($text) {
      return self::removeMakeUrl($text);
   }

   /**
    * Removes the markup produced by the URL parser and replaces it with a plain
    * text version.
    *
    * @param string $text
    * @param string $format any combination of "{TEXT}" and "{URL}"
    * @return string processed text
    */
   public static function removeMakeUrl($text, $format = '{TEXT}') {
      $replacement = self::inject($format, array(
         'text' => '$1',
         'url'  => '$2'
      ));

      return preg_replace('/\[(.+?)(?:\.intern|)\](http[^\s]+)/', $replacement, $text);
   }

   /**
    * removes invalid UTF8 characters from the given string. The algorithm was
    * proposed on  http://webcollab.sourceforge.net/unicode.html in
    * section "Character Validation".
    *
    * @param string text corrupted by invalid UTF8 characters
    * @return string adjusted text
    */
   public static function removeInvalidUtf8($text) {
      $text = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' .
            '|(?<=^|[\x00-\x7F])[\x80-\xBF]+' .
            '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' .
            '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' .
            '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/', '', $text);

      return preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]' .
            '|\xED[\xA0-\xBF][\x80-\xBF]/S', '?', $text);
   }

   /**
    * Removes invalid XML 1.0 characters.
    * See http://en.wikipedia.org/wiki/Valid_characters_in_XML
    *
    * @param string $str
    * @return string replaced string
    */
   public static function removeInvalidXML1Characters($str) {
      return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x19\x1E]/', '', $str);
   }

   /**
    * Replaces uppercase keys enclosed in "{" and "}" with the provided values.
    *
    * @param string $text
    * @param array $injections map of keys and their replacements
    * @return string processed text
    */
   public static function inject($text, array $injections) {
      foreach ($injections as $key => $replacement) {
         $text = str_replace('{' . strtoupper($key) . '}', $replacement, $text);
      }

      return $text;
   }

   /**
    * Cuts a text off if it is longer than a given maximum and appends an
    * indicator for omission.
    *
    * @param string $text
    * @param int $length
    * @param string $omission
    * @return string truncated text
    */
   public static function truncate($text, $length, $omission = '...') {
      if (mb_strlen($text) > $length) {
         $text = rtrim(mb_substr($text, 0, $length)) . $omission;
      }

      return $text;
   }

   /**
    * Cuts a text off if it is longer than a given maximum and appends an
    * indicator for omission. Words are preserved.
    *
    * @param string $text
    * @param int $length
    * @param string $omission
    * @return string truncated text
    */
   public static function truncateWords($text, $length, $omission = ' ...') {
      if (mb_strlen($text) <= $length) return $text;

      $trailingWhitespaceRe = '/\s+$/';
      $leadingWordsRe = '/^[^\s-]*/';
      $trailingInsignificantCharsRe = '/[,]+$/';

      $start = mb_substr($text, 0, $length);
      $result = null;

      if (preg_match($trailingWhitespaceRe, $start)) {
         $result = preg_replace($trailingWhitespaceRe, '', $start);
      } else {
         $matches = array();
         preg_match($leadingWordsRe, mb_substr($text, $length), $matches);
         $result = $start . $matches[0];
      }

      return preg_replace($trailingInsignificantCharsRe, '', $result) . $omission;
   }

   /**
    * Creates a slug representation of the string. It is meant to be used in
    * plain text ASCII, unicode-hostile environments like URLs, CSS rules and
    * cb-ml labels.
    *
    * @param string $text
    * @param array $optionalParams
    * @return string URL-safe representation
    */
   public static function slugify($text, $optionalParams = null) {
      // Handle the legacy invocation where the second parameter was the
      // delimiter and no maximum length existed.
      if ($optionalParams === null) {
         $optionalParams = array();
      } else if (!is_array($optionalParams)) {
         $optionalParams = array(
            'delimiter'  => $optionalParams,

            // Alternative delimiters were mainly used for cb-ml-labels, which
            // is why we disable the length in that case, as it is important
            // that they do not get truncated in older projects. Otherwise, some
            // things could break.
            'max_length' => null
         );
      }

      // Merge with defaults.
      $optionalParams = array_merge(array(
         'delimiter'  => '-',
         'max_length' => 42 // interestingly, this seems to be a common default
      ), $optionalParams);

      // Resolve HTML entities.
      $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

      // Make sure that no special chars are in there.
      $text = CbTransliterator::translit($text);

      // Remove all characters that cannot be represented unescaped.
      $text = preg_replace(array('/[^a-z\d\s]/i', '/\s+/'), array(' ', $optionalParams['delimiter']), strtolower($text));

      // Truncate the result if needed. Try to truncate by words. If that does
      // not work due to the nature of the string, truncate by characters.
      if ($optionalParams['max_length'] !== null && $optionalParams['max_length'] > 0) {
         $truncatedText = self::truncateWords($text, $optionalParams['max_length'], '');

         if (mb_strlen($truncatedText) > $optionalParams['max_length']) {
            $truncatedText = self::truncate($text, $optionalParams['max_length'], '');
         }

         $text = $truncatedText;
      }

      // Make sure that there are no delimiters at beginning and end.
      return trim($text, $optionalParams['delimiter']);
   }
}