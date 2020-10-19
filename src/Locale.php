<?php

namespace CorbeauPerdu\i18n;

use Exception;
use ParseError;

/**
 * Locale class wrapper to set locale environment
 * and then either use *nix gettext(), or JSON files for translations
 *
 * Copyright (C) 2020, Patrick Roy
 * This file may be used under the terms of the GNU Lesser General Public License, version 3.
 * For more details see: https://www.gnu.org/licenses/lgpl-3.0.html
 * This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This class was inspired by *nix's gettext, thus all terminology and functions are the same as gexttext(),
 * but works just as well if using JSON files for translation messages
 *
 * <pre>
 * Notes about using JSON files:
 * Since this script is inspired by gettext, when using a JSON file, it will actually fetch a 'nplurals' value,
 * along with a 'plural' ternary conditions value in order to determine the right plural array element to use,
 *
 * You can find a list of locales plural-forms here:
 * http://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html
 *
 * NOTES!!! The lather list is for Gettext, thus it's C-Style ternary conditions! You'll need to modify it to be valid in PHP!
 *
 * Example C-Style (NOT valid for PHP!):
 * (n==1 || n==11) ? 0 : (n==2 || n==12) ? 1 : (n > 2 && n < 20) ? 2 : 3
 *
 * would become in PHP:
 * (n==1 || n==11) ? 0 : ((n==2 || n==12) ? 1 : ((n > 2 && n < 20) ? 2 : 3))
 *
 * Example JSON French file: <locales_dir>/fr_FR/prestadesk.json
 * {
 *   "": {
 *     "domain": "prestadesk",
 *     "language": "fr_FR",
 *     "nplurals": "1",
 *     "plural": "(n > 1)"
 *   },

 *   "Welcome, %s!": "Bienvenu, %s!",
 *   "This page will show the dashboard": "Cette page affichera le tableau de bord",

 *   "I wrote a line of code": "J'ai écris une ligne de code",
 *   "I wrote %d lines of code": "J'ai un écris %d lignes de code"
 * }
 *
 * Example JSON Serbian file: <locales_dir>/sr_CS/prestadesk.json
 * {
 *   "": {
 *     "domain": "prestadesk",
 *     "language": "sr_CS",
 *     "nplurals": "3",
 *     "plural": "((n%10==1 && n%100!=11) ? 0 : ((n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20)) ? 1 : 2))"
 *   },

 *   "Welcome, %s!": "Dobrodosli, %s!",
 *   "This page will show the dashboard": "Ova stranica ce prikazati kontrolnu tablu",

 *   "I wrote a line of code": "Napisao sam liniju koda",
 *   "I wrote %d lines of code": [
 *     "Napisao sam %d liniju koda",
 *     "Napisao sam %d linije koda",
 *     "Napisao sam %d linija koda"
 *   ]
 * }
 *
 * If you are too lazy to convert the Gettext's C-Style plural value to a valid PHP ternary condition :P, set the constructor's $usePluralFormsFromGettext to TRUE,
 * and then you can drop the 'plural' and 'nplurals' values above, and replace with gettext's plural-forms like so in the JSON file (COPY/PASTE FROM GETTEXT's DOCS!!!):
 *
 *   "plural-forms": "nplurals=2; plural=(n > 1);"
 *   "plural-forms": "nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);"
 *
 * You'll find proper gettext plural-forms here: http://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html
 *
 * The script will attempt to parse the plural-forms using regex and rebuild an array of test conditions.
 * But really, this is a stupid approach: it's prone to errors (though I think I'm properly validating 'plural-forms'!), AND it'll be slower to process translations!
 * </pre>
 *
 * Notes about $locales array:
 * <pre>
 * Your supported $locales should be in the form of [ 'desired lang' => 'real installed lang mapping on server' ]
 * Example: [ 'en' => 'en_US', 'fr' => 'fr_FR' ]
 *
 * In this case, 'en_US' and 'fr_FR' folders are expected to exist in your specified locales directory!
 *
 * Even if you use 'phrases' as your language keys (say the actual english message to translate, if english is your default),
 * you still need to add this default locale to the $locales array passed to the constructor Locale()
 *
 * AND
 *
 * you need to create either one, based on your config, a gettext translation file (domain.mo),
 * OR a JSON translation file, both with at least the headers in it:
 *
 *   $locales_dir/<default_lang>/LC_MESSAGES/$domain.mo  OR
 *   $locales_dir/<default_lang>/$domain.json
 *
 * If you don't do this, you'll get exceptions when it checks for the locale's translation files, or exceptions when it tries to validate plural-forms!
 * No, in reality because you're using phrases as keys, you won't need real translation files.
 * However, doing it this way, you can always decide to use keywords as keys (i.e. profile.title) later on,
 * and then create complete translation files ALSO for your default language holding these keyword keys.
 * </pre>
 *
 * Notes about sprintf functionnality:
 * You can provide any of the *gettext() functions 1 or many 'v' optional argument(s).
 * These values will be used to replace the sprintf's placeholders! i.e.:
 * Locale::_n("Welcome, %s! You have %d mail.", "Welcome, %s! You have %d emails.", "John", 5);
 *
 * Exception codes thrown:
 * <pre>
 * code 1 = Invalid default language
 * code 2 = Gettext extension missing in php.ini
 * code 3 = Invalid LC category or Desired locale is not installed on server
 * code 4 = Gettext translation file not found
 * code 5 = JSON translation file not found
 * code 6 = Invalid JSON file
 * code 7 = Missing or invalid 'nplurals' in JSON file
 * code 8 = Missing 'plural' ternary test conditions in JSON file
 * code 9 = The 'nplurals' value doesn't match the actual number of 'plural' conditions in JSON file
 * code 10 = Invalid translation key plurals count (their arrays don't match nplurals!) in JSON file
 * code 11 = Invalid 'plural' ternary conditions format in JSON file
 * code 12 = Invalid characters found in 'plural' value of JSON file see Locale::PLURALVALIDCHARS
 * code 13 = Missing gettext 'plural-forms' ternary test conditions in JSON file
 * code 14 = Unsupported choosen language to switch to!
 *
 * When calling the constructor, exception code 1, 2, 11 oe 12 are ESSENTIAL to catch and deal with, otherwise will cause misfunctions, or security issues!
 * As for the other codes, they could eventually be ignored: the message keys will be returned, untranslated!
 * </pre>
 *
 * Last Modified:
 * <pre>
 *   2020/04/13 by PRoy - First release
 *   2020/04/15 by PRoy - Implemented the (stupid) $usePluralFormsFromGettext functionnality to regex parse the plural-forms given from gettext's documentation
 *   2020/04/18 by PRoy - Added getLoadedDomains_toWeb() and getMissingTranslations_toWeb()
 *   2020/04/19 by PRoy - Added getLang4HtmlTag()
 *   2020/04/20 by PRoy - Added getTranslationsAsJSON() and getDefaultDomain()
 *   2020/04/29 by PRoy - Class can no longer be used statically! You MUST create an instance of the class to use it!
 *                        Otherwise, I couldn't use more than one 'Locale' class, thus couldn't mix using Gettext's .MO files, AND also have another locale which would use JSON (say to send json data to a javascript variable)
 *   2020/04/29 by PRoy - Removed all 'fancy' *fgettext functions, and added the sprintf functionality directly within the regular *gettext() functions!
 *   2020/04/29 by PRoy - Stripped down gettext() and ngettext()'s code to simply call their corresponding d*gettext() and passing the default domain as the lookup domain!
 *   2020/05/05 by PRoy - Delete 'lang' cookie if we catch an exception in the constructor! These are not good so no keeping the set cookie as we want force user to re-choose lang!
 *   2020/05/12 by PRoy - Added stringToWeb() and setFormatMessages4Web(): if true, all returned messages will be htmlentities()'d and line breaks '\n' replaced with '<br/>'
 *                        Also added a few checks (i.e. isset(), array_key_exists(), etc.) to get rid of PHP NOTICE warnings!
 *   2020/05/17 by PRoy - Fix in _json_d*gettext(): linebreaks in JSON file (\n) wouldn't be found as keys!
 *   2020/05/19 by PRoy - Fixed setcookie() in constructor: cookie now expires in 10 years (instead of 'null' which would make it expire when browser is closed!) 
 *                        and added a $cookieroot to constructor to set root of cookie (normally the doc root '/').
 *   2020/05/23 by PRoy - Added setFormatMessages4WebInclPlaceholders(): If formatMessages4Web is on, do we want to also format the placeholder values? Default is true!
 *   2020/06/21 by PRoy - Bug fix in _json_dngettext() where we needed to make sure the $m1 and $m2 were SET in the array! Relevant update to 2020/05/12.
 *   2020/07/11 by PRoy - Bug fix when getting missing translations array: needed to check if the array was set for the domain before reading into it!
 *   2020/08/13 by PRoy - Bug fix in loadJSONDomain() where the count() check to validate key's plurals array size is the same as nplurals would fail when hitting a key who's value wasn't an array! This didn't happen in PHP 7.1, but fails in PHP 7.4
 *                        Also, $lc_category in constructor can now be an associative array in the form of [LC_MONETARY => 'fr_FR']. to specify other locales to be used for specific LCs, OTHER then the running locale !
 *                        i.e. user has 'en_US' to display all in english, but we want to force LC_MONETARY to be FRENCH EUROs when displaying currencies!
 *   2020/08/13 by PRoy - Added switchLocale() to be able to temporarily switch to another locale! It'll reload all loaded domains in the desired lang 
 * </pre>
 *
 * @todo: Change eval() calls by using intl GNU Libc?
 * 
 * @author      Patrick Roy (ravenlost2@gmail.com)
 * @version     1.6.0
 */
class Locale
{
  public const VERSION = '1.6.0';
  private const PLURALVALIDCHARS = '()%=&!?|:<>0123456789n';
  private const GETTEXT_PLURALFORMS_EXAMPLES_URL = 'http://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html';
  private const LC_DEFAULTCONFIG = LC_ALL; // if LC Category isn't specified when using gettext, set locale to what default LC ?
  private $_defaultPlural = '(n != 1)'; // the default ternary test condition for plural if $_usePluralForms = false; or if script failed to load JSON file; or if invalid JSON 'plural' value...
  private $_locales = null;
  private $_lang = null;
  private $_lc_category = null;
  private $_domain_default = null;
  private $_codeset = null;
  private $_locales_dir = null;
  private $_useGettext = null;
  private $_loadedDomains = array (); // contains list of loaded domains; if using JSON, will also have the translation messages
  private $_missingTranslations = array (); // contains list of missing translations
  private $_useCustomPluralForms = true;
  private $_usePluralFormsFromGettext = false;
  private $_project = null;
  private $_debug = null;
  private $_formatMessages4Web = false;
  private $_formatMessages4WebInclPlaceholders = true;

  /**
   * return default plural condition used if $_usePluralForms = false;
   * or if script failed to load JSON file; or if invalid JSON 'plural' value...
   * @return string
   */
  public function getdefaultPlural()
  {
    return $this->_defaultPlural;
  }

  /**
   * return the default domain name used by this class to fetch message
   * @return string
   */
  public function getDefaultDomain()
  {
    return $this->_domain_default;
  }

  /**
   * Set the default ternary test condition for plurals if $_usePluralForms = false <br/>
   * The ONLY allowed characters for this (excluding the quotes): '()%=&!?|:<>0123456789n'
   * <pre>
   * Example: note how $n is passed as a string, and not its value, nor its variable name '$n' !!
   * Locale::setdefaultPlural('(n > 1)');
   * </pre>
   * @param string $defaultPlural
   * @throws LocaleException
   */
  public function setdefaultPlural($defaultPlural)
  {
    // run a test case against the '$defaultPlural' conditions just to make sure it validates through eval() and doesnt return an exception
    try
    {
      // remove plural ending ';' if any
      $defaultPlural = rtrim($defaultPlural, ';');

      $this->_json_evalPlural($defaultPlural, 1);
      $this->_defaultPlural = $defaultPlural;
    }
    catch ( LocaleException $ex )
    {
      throw $ex;
    }
  }

  /**
   * return wether or not we're using plural-forms (used if using JSON files)
   * @return bool
   */
  public function getUseCustomPluralForms(): bool
  {
    return $this->_useCustomPluralForms;
  }

  /**
   * Use or not custom plural forms (used if using JSON files)
   * @param bool $usePluralForms
   */
  public function setUseCustomPluralForms(bool $useCustomPluralForms)
  {
    $this->_useCustomPluralForms = $useCustomPluralForms;
  }

  /**
   * Format all returned messages for web output ? Default is false!
   * i.e. '<' becomes '&lt;', 'é' becomes &eacute; and also '\n' becomes '<br/>'
   * @param bool $formatMessages4Web
   * @return bool old value which was set
   */
  public function setFormatMessages4Web(bool $formatMessages4Web)
  {
    $ov = $this->_formatMessages4Web;
    $this->_formatMessages4Web = $formatMessages4Web;
    return $ov;
  }

  /**
   * If formatMessages4Web is on, do we want to also format the placeholder values? Default is true!
   * @param bool $inclPlaceholders
   * @return bool old value which was set
   */
  public function setFormatMessages4WebInclPlaceholders(bool $inclPlaceholders)
  {
    $ov = $this->_formatMessages4WebInclPlaceholders;
    $this->_formatMessages4WebInclPlaceholders = $inclPlaceholders;
    return $ov;
  }

  /**
   * Get the loaded domains list (includes domain's actual messages if using JSON)
   * @return array
   */
  public function getLoadedDomains(): array
  {
    return $this->_loadedDomains;
  }

  /**
   * Get the loaded domains list (includes domain's actual messages if using JSON), formatted for a webpage
   */
  public function getLoadedDomains_toWeb()
  {
    echo '<div style="font-size: larger;font-weight: bold;">Loaded domains:</div>';
    echo '<pre>';
    var_dump($this->_loadedDomains);
    echo '</pre>';
  }

  /**
   * Get the missing translations list
   * @return array
   */
  public function getMissingTranslations(): array
  {
    return $this->_missingTranslations;
  }

  /**
   * Get the missing translations list, formatted for a webpage
   */
  public function getMissingTranslations_toWeb()
  {
    echo '<div style="font-size: larger;font-weight: bold;">Missing translations:</div>';
    echo '<pre>';
    var_dump($this->_missingTranslations);
    echo '</pre>';
  }

  /**
   * Get the currently set locale
   * @return string
   */
  public function getLang()
  {
    return $this->_lang;
  }

  /**
   * Get the currently set locale
   * @return string
   */
  public function getLocale()
  {
    return $this->_lang;
  }

  /**
   * Get the currently set locale, to be used in <html> and <meta> html tags
   * @return string
   */
  public function getLang4HtmlTag()
  {
    return str_replace('_', '-', strtolower($this->_lang));
  }

  /**
   * Get a JSON string containing all translation for $domain<br/>
   * This works if using JSON and not Gettext
   * @param string $domain (optionnal!) if null, returns translations for default domain
   * @return string json string with translations
   */
  public function getTranslationsAsJSON(string $domain = null)
  {
    if ( ! isset($domain) ) $domain = $this->_domain_default;
    return json_encode($this->_loadedDomains[$domain]);
  }

  /**
   * Constructor()
   * Initializes the locale setting
   * <pre>
   * 1) tries to get locale from query-string, else
   * 2) tries to get locale from cookie ('lang'), else
   * 3) tries to get locale from the client's browser 'HTTP_ACCEPT_LANGUAGE', else
   * 4) sets the locale to the default value of $lang_default
   * </pre>
   *
   * @TODO: Add GEOIP to init lang by country ?
   *
   * @param array $locales associative array of supported langs: [ 'en' => 'en_US', 'fr' => 'fr_FR' ]
   * @param string $locales_dir base folder containing the gettext or json language files: i.e. /DOC_ROOT/ressources/locales/
   * @param string $domain_default main domain to search for messages
   * @param string $codeset encoding the gettext files are encoded into
   * @param string $lang_default fallback/default language if user doesn't specify a supported locale
   * @param string $cookieroot cookie path where to save 'lang' cookie on clients
   * @param mixed $lc_category an integer specifying a single LC category to set to running Locale, or 
   *                           an array with LC Categories to set to running Locale i.e. array(LC_MESSAGES,LC_TIME), or
   *                           an array with LC Categories set to specified locales for each category i.e. [LC_MESSAGES => 'en_US', LC_MONETARY => 'fr_FR']
   * @param bool $useGettext use *nix gettext; otherwise will use JSON files
   * @param bool $useCustomPluralForms (only if using JSON files) use custom plural forms condition tests; if false, will just get the 1st plural value where (n != 1); note this can be overwritten with Locale::setdefaultPlural()
   * @param bool $usePluralFormsFromGettext (only if using JSON files) use gettext()'s syntax of plural-forms (C-style) ternary condition tests; will use regex to parse the conditions, so not as effective and slower!   
   * @param bool $debug output errors such as 'failed to load domain' into error_log?
   * @param string $project name of the project: used only to prepend errors in error_log
   *
   * @throws LocaleException
   */
  public function __construct(array $locales, string $locales_dir, string $domain_default, string $codeset, string $lang_default, string $cookieroot = '/', $lc_category = null, bool $useGettext = true, bool $useCustomPluralForms = true, bool $usePluralFormsFromGettext = false, bool $debug = false, string $project = "UNDEFINED_PROJECT")
  {
    $this->_locales = $locales;
    $this->_locales_dir = $locales_dir;
    $this->_domain_default = $domain_default;
    $this->_codeset = $codeset;
    $this->_lang = $lang_default;
    $this->_lc_category = $lc_category;
    $this->_useGettext = $useGettext;
    $this->_useCustomPluralForms = $useCustomPluralForms;
    $this->_usePluralFormsFromGettext = $usePluralFormsFromGettext;
    $this->_debug = $debug;
    $this->_project = $project;

    // append '/' to cookie root
    if ( ! preg_match("/\/$/", $cookieroot) ) $cookieroot = $cookieroot . '/';

    $org_cookie = ( $_COOKIE['lang'] ) ?? ''; // if not set, set it to empty string so it get's set to null if fatal exception occurs! 

    // first check our desired default lang is valid. Otherwise, it will fail with exceptions when it tries to rollback to $lang_default
    // after having received an invalid lang by query-string, etc (it won't be able to find its locale translation files),
    if ( ! $this->_valid($lang_default) ) throw new LocaleException("Invalid default language ('$lang_default') specified ! It needs to exist in your \$locales array!!", 1);

    // get lang from query-string
    if ( isset($_GET['lang']) )
    {
      $_GET['lang'] = $this->_escape($_GET['lang']); // sanitize query-string lang

      if ( $this->_valid($_GET['lang']) )
      {
        //echo 'Getting lang from query-string...<br>';
        $this->_lang = $_GET['lang'];
        setcookie('lang', $this->_lang, time() + 3600 * 24 * 365 * 10, $cookieroot); // it's stored in a cookie so it can be reused        
      }
    }
    // get lang from cookie
    elseif ( isset($_COOKIE['lang']) )
    {
      $_COOKIE['lang'] = $this->_escape($_COOKIE['lang']); // sanitize cookie lang

      if ( $this->_valid($_COOKIE['lang']) )
      {
        //echo 'Getting lang from cookie...<br>';
        $this->_lang = $_COOKIE['lang'];
      }
    }
    // get the lang from the languages the browser says the user accepts
    elseif ( isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) )
    {
      $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

      array_walk($langs, function (&$lang)
      {
        $lang = strtr(strtok($lang, ';'), [  '-' => '_' ]);

        // debug
        //echo 'Browser available Lang = $lang<br>';
      });

      foreach ( $langs as $browser_lang )
      {
        $browser_lang = $this->_escape($browser_lang); // sanitize browser lang

        if ( $this->_valid($browser_lang) )
        {
          //echo 'Getting lang from browser...<br>';
          $this->_lang = $browser_lang;
          break;
        }
      }
    }

    // set desired lang to what it maps to on server (i.e. en_US, fr_FR, nl_NL, etc.)
    $this->_lang = $this->_locales[$this->_lang];

    // set the working session lang
    $_SESSION['lang'] = $this->_lang;

    try
    {
      if ( $useGettext )
      {
        $this->_initLocaleGettext();
      }
      else
      {
        $this->_initLocaleJSON();
      }
    }
    catch ( LocaleException $ex )
    {
      // change 'lang' cookie to whatever it was before: to force user to use what they had set before as lang
      setcookie('lang', $org_cookie, time() + 3600 * 24 * 365 * 10, $cookieroot);
      //unset($_SESSION['lang']);

      throw $ex;
    }
  }

  /**
   * switchLang()
   * Switch running locale to another language (say to send an email to a client in their prefered language!)
   * It'll also reload all loaded domains in the desired lang.
   * 
   * @param string $lang desired locale to set (same as querystring)
   * @param boolean $rollbackOnErrors try reverting back to previous language if failed to switch language?
   * @throws LocaleException
   * @return string previous language set!
   */
  public function switchLang(string $lang, bool $rollbackOnErrors = true)
  {
    $oldLang = array_search($this->_lang, $this->_locales); // set the old language used!

    // validate lang
    if ( $this->_valid($lang) )
    {
      // set desired lang to what it maps to on server (i.e. en_US, fr_FR, nl_NL, etc.)
      $this->_lang = $this->_locales[$lang];

      try
      {
        // load default domain
        if ( $this->_useGettext )
        {
          $this->_initLocaleGettext(true);
        }
        else
        {
          $this->_initLocaleJSON(true);
        }

        // also reload any other previously loaded domains
        $loadedDomains = array_keys($this->_loadedDomains);
        foreach ( $loadedDomains as $ld )
        {
          if ( $ld != $this->_domain_default ) $this->loadDomain($ld, true); // the init* above loads the default domain, so no point in re-loading it!
        }

        return $oldLang;
      }
      catch ( LocaleException $ex )
      {
        // revert back to previous language before throwing ex ! 
        // forcing false to rollback on errors as to prevent infinit loops in case say the hole locales folder got missing!
        if ( $rollbackOnErrors ) $this->switchLang($oldLang, false);
        throw $ex;
      }
    }
    else
    {
      throw new LocaleException("Unsupported language: $lang", 14);
    }
  }

  /**
   * class destructor
   */
  public function __destruct()
  {
    unset($this->_defaultPlural);
    unset($this->_locales);
    unset($this->_lang);
    unset($this->_domain_default);
    unset($this->_codeset);
    unset($this->_locales_dir);
    unset($this->_useGettext);
    unset($this->_loadedDomains);
    unset($this->_missingTranslations);
    unset($this->_useCustomPluralForms);
    unset($this->_usePluralFormsFromGettext);
    unset($this->_project);
    unset($this->_debug);
  }

  /**
   * Verifies if the given $locale is supported in the project
   * @param string $locale
   * @return bool
   */
  private function _valid(string $locale): bool
  {
    return array_key_exists($locale, $this->_locales);
  }

  /**
   * Escape special characters to protect against SQL Injections
   * @param string $value string to escape
   * @return string with escaped special characters
   */
  private function _escape(string $value): string
  {
    $search = [  "\\", "\x00", "\n", "\r", "'", '"', "\x1a" ];
    $replace = [  "\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z" ];

    return str_replace($search, $replace, $value);
  }

  /**
   * initialize locale settings using *nix gettext() (prefered way!)
   * @param boolean $forceload even if default domain is already loaded, force a reload? This is only true when switching language!
   * @throws LocaleException
   */
  private function _initLocaleGettext(bool $forceload = false)
  {
    try
    {
      // sanity check: gettext is properly configured on server
      if ( ! function_exists('gettext') ) throw new LocaleException('Gettext extension is not loaded in the server\'s php.ini!', 2);

      // here we define the global system locale given the desired language
      putenv('LANG=' . $this->_lang);

      if ( ( $this->_lc_category === null ) or ( $this->_lc_category === '' ) ) $this->_lc_category = self::LC_DEFAULTCONFIG;

      if ( is_array($this->_lc_category) )
      {
        // setting LC_* to specific locales, other then running locale (lang)
        if ( $this->isAssocArray($this->_lc_category) )
        {
          foreach ( $this->_lc_category as $category => $value )
          {
            // set locale LC_* and check if it succeded: if it returns false, then the desired lang's locale is not install on system or it's an invalid category!
            $setlocaleResults = setlocale($category, $value);
            if ( ( $setlocaleResults === false ) or ( $setlocaleResults === null ) ) throw new LocaleException("Invalid LC category specified ('$category'), OR the locale '" . $value . "' is not properly installed on the server! Run command 'locale -a' to get a list of installed locales.", 3);
          }
        }
        // setting LC_* to current running locale (lang)
        else
        {
          foreach ( $this->_lc_category as $category )
          {
            // set locale LC_* and check if it succeded: if it returns false, then the desired lang's locale is not install on system or it's an invalid category!
            $setlocaleResults = setlocale($category, $this->_lang);
            if ( ( $setlocaleResults === false ) or ( $setlocaleResults === null ) ) throw new LocaleException("Invalid LC category specified ('$category'), OR the locale '" . $this->_lang . "' is not properly installed on the server! Run command 'locale -a' to get a list of installed locales.", 3);
          }
        }
      }
      else
      {
        // set locale LC_* and check if it succeded: if it returns false, then the desired lang's locale is not install on system or it's an invalid category!
        $setlocaleResults = setlocale($this->_lc_category, $this->_lang);
        if ( ( $setlocaleResults === false ) or ( $setlocaleResults === null ) ) throw new LocaleException("Invalid LC category specified ('" . $this->_lc_category . "'), OR the locale '" . $this->_lang . "' is not properly installed on the server! Run command 'locale -a' to get a list of installed locales.", 3);
      }

      // load the gettext domain
      $this->_loadGettextDomain(null, $forceload);

      // here we indicate the default domain the gettext() calls will respond to
      textdomain($this->_domain_default);
    }
    catch ( LocaleException $ex )
    {
      throw $ex;
    }
  }

  /**
   * initialize locale settings using JSON files
   * @param boolean $forceload even if default domain is already loaded, force a reload? This is only true when switching language!
   * @throws LocaleException
   */
  private function _initLocaleJSON(bool $forceload = false)
  {
    try
    {
      // here we define the global system locale given the desired language
      putenv('LANG=' . $this->_lang);

      if ( ( isset($this->_lc_category) ) and ( $this->_lc_category !== '' ) )
      {
        if ( is_array($this->_lc_category) )
        {
          // setting LC_* to specific locales, other then running locale (lang)
          if ( $this->isAssocArray($this->_lc_category) )
          {
            foreach ( $this->_lc_category as $category => $value )
            {
              // set locale LC_* and check if it succeded: if it returns false, then the desired lang's locale is not install on system or it's an invalid category!
              $setlocaleResults = setlocale($category, $value);
              if ( ( $setlocaleResults === false ) or ( $setlocaleResults === null ) ) throw new LocaleException("Invalid LC category specified ('$category'), OR the locale '" . $value . "' is not properly installed on the server! Run command 'locale -a' to get a list of installed locales.", 3);
            }
          }
          // setting LC_* to current running locale (lang)
          else
          {
            foreach ( $this->_lc_category as $category )
            {
              // set locale LC_* and check if it succeded: if it returns false, then the desired lang's locale is not install on system or it's an invalid category!
              $setlocaleResults = setlocale($category, $this->_lang);
              if ( ( $setlocaleResults === false ) or ( $setlocaleResults === null ) ) throw new LocaleException("Invalid LC category specified ('$category'), OR the locale '" . $this->_lang . "' is not properly installed on the server! Run command 'locale -a' to get a list of installed locales.", 3);
            }
          }
        }
        else
        {
          // set locale LC_* and check if it succeded: if it returns false, then the desired lang's locale is not install on system or it's an invalid category!
          $setlocaleResults = setlocale($this->_lc_category, $this->_lang);
          if ( ( $setlocaleResults === false ) or ( $setlocaleResults === null ) ) throw new LocaleException("Invalid LC category specified ('" . $this->_lc_category . "'), OR the locale '" . $this->_lang . "' is not properly installed on the server! Run command 'locale -a' to get a list of installed locales.", 3);
        }
      }
      else
      {
        // NOT throwing exception here if LC Category is null, because one might not care about setting the LC_* locales if using just JSON files for messages only!
        // If this is the case, then it's also possible the server doesn't even have the locale installed (needed mainly if we're using gettext),
        // and therefor throwing an exception would be a mistake! Using NULL as LC Category would be the right choice here!
      }

      $this->_loadJSONDomain(null, $forceload);
    }
    catch ( LocaleException $ex )
    {
      throw $ex;
    }
  }

  /**
   * Load a domain from Gettext or JSON file, based on configuration passed in initLocale
   * @param string $domain
   * @param boolean $forceload even if domain is already loaded, force a reload?
   * @see Locale::_loadGettextDomain()
   * @see Locale::_loadJSONDomain()
   * @throws LocaleException
   */
  public function loadDomain(string $domain = null, bool $forceload = false)
  {
    try
    {
      if ( $this->_useGettext )
      {
        $this->_loadGettextDomain($domain, $forceload);
      }
      else
      {
        $this->_loadJSONDomain($domain, $forceload);
      }
    }
    catch ( LocaleException $ex )
    {
      throw $ex;
    }
  }

  /**
   * Load a gettext domain
   * @param string $domain
   * @param boolean $forceload even if domain is already loaded, force a reload?
   * @throws LocaleException
   */
  private function _loadGettextDomain(string $domain = null, bool $forceload = false)
  {
    // load default domain gettext file if not specified
    if ( empty($domain) ) $domain = $this->_domain_default;

    if ( ( ! in_array($domain, $this->_loadedDomains) ) or ( $forceload == true ) )
    {
      if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": Loading Gettext domain '$domain'...", 0);

      $gexttext_mo_filepath = $this->_locales_dir . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR . $domain . '.mo';

      // sanity check
      if ( ! file_exists($gexttext_mo_filepath) ) throw new LocaleException("Gettext translation file not found: $gexttext_mo_filepath", 4);

      // this will make Gettext look for $_locales_dir/<lang>/LC_MESSAGES/$_domain_default.mo
      bindtextdomain($domain, $this->_locales_dir);

      // indicates in what encoding the file should be read
      bind_textdomain_codeset($domain, $this->_codeset);

      // just push an indicator what we've binded the domain
      if ( ! in_array($domain, $this->_loadedDomains) ) array_push($this->_loadedDomains, $domain);
    }
  }

  /**
   * initialize locale settings using JSON files (say if gettext isn't supported on server)
   * @param string $domain
   * @param boolean $forceload even if domain is already loaded, force a reload?
   * @throws LocaleException
   */
  private function _loadJSONDomain(string $domain = null, bool $forceload = false)
  {
    // load default domain json file if not specified
    if ( empty($domain) ) $domain = $this->_domain_default;

    if ( ( ! array_key_exists($domain, $this->_loadedDomains) ) or ( $forceload == true ) )
    {
      $json_filepath = $this->_locales_dir . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . $domain . '.json';

      // sanity check
      if ( ! file_exists($json_filepath) ) throw new LocaleException("JSON translation file not found: $json_filepath", 5);

      // load json file
      $domain_translations = file_get_contents($json_filepath);
      $domain_translations = json_decode($domain_translations, true);

      if ( $domain_translations === null ) throw new LocaleException('Invalid JSON: ' . $json_filepath, 6);

      // load/re-load the domain translations into array
      $this->_loadedDomains[$domain] = $domain_translations;

      // check validity of plural-forms here...
      $nplurals = null;
      $plural = null;

      // if using using PHP style of plural-forms (PHP ternary conditions)
      if ( ( $this->_useCustomPluralForms ) and ( ! $this->_usePluralFormsFromGettext ) )
      {
        $nplurals = trim($this->_loadedDomains[$domain]['']['nplurals']);
        $plural = trim($this->_loadedDomains[$domain]['']['plural']);

        // validate 'nplurals' value
        if ( ( ! isset($nplurals) ) or ( $nplurals === '' ) or ( preg_match('/^[^0-9]*$/', $nplurals) ) or ( $nplurals < 0 ) )
        {
          throw new LocaleException("Missing or invalid 'nplurals' number in: $json_filepath", 7);
        }
        // validate 'plural' value
        elseif ( $nplurals >= 1 )
        {
          $pluralConditionsCount = substr_count($plural, '?') + 1;

          // validate we have a plural value set!
          if ( ( ! isset($plural) ) or ( $plural === '' ) )
          {
            throw new LocaleException("Missing 'plural' ternary test conditions in: $json_filepath", 8);
          }
          // validate that $nplurals actually matches the number of ternary conditions found in $plural
          elseif ( $pluralConditionsCount != $nplurals )
          {
            throw new LocaleException("The 'nplurals' value doesn't match the actual number of 'plural' conditions (number of possible returned values) in: $json_filepath", 9);
          }
          // validate the key's plurals array count is equal to nplurals value: did the user provide right amount of plural translations per key!?
          else
          {
            foreach ( $this->_loadedDomains[$domain] as $key => $value )
            {
              if ( is_array($value) )
              {
                $pluralCount = count($this->_loadedDomains[$domain][$key]);
                if ( ( $key != '' ) and ( $pluralCount != $nplurals ) )
                {
                  throw new LocaleException("Possible plurals count ($pluralCount) for key '$key' in the '" . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . "$domain.json' JSON file doesn't match the 'nplurals' value ($nplurals) !", 10);
                }
              }
            }
          }
          // run a test case against the 'plural' conditions just to make sure it validates through eval() and doesnt return an exception
          try
          {
            $this->_json_evalPlural($plural, 1, $domain);
          }
          catch ( LocaleException $ex )
          {
            throw $ex;
          }
        }
      }

      // if using using gettext style of plural-forms (C-style ternary conditions) from a copy/paste of gettext's documentation,
      // script will just parse with regex the plural value and rebuild the conditions in an array: not the most efficient!!
      elseif ( ( $this->_useCustomPluralForms ) and ( $this->_usePluralFormsFromGettext ) )
      {
        $plural_forms = trim($this->_loadedDomains[$domain]['']['plural-forms']);
        if ( ( ! isset($plural_forms) ) or ( $plural_forms === '' ) ) throw new LocaleException("Missing gettext 'plural-forms' ternary test conditions in: $json_filepath", 13);

        // get and validate the nplurals value
        preg_match('/^nplurals\s*=\s*(\d+)/i', $plural_forms, $nplurals);
        $nplurals = $nplurals[1];

        if ( ! isset($nplurals) ) throw new LocaleException("Missing proper plural-forms's 'nplurals' value in: $json_filepath" . PHP_EOL . PHP_EOL . 'See valid list: ' . self::GETTEXT_PLURALFORMS_EXAMPLES_URL, 7);

        // with gettext plural-forms, $nplural=2 when there's just really 1 possible plural value, and $nplural=1 when there is no plural possible!
        // so we only care about the 'plural' value IF $nplural>=2
        if ( $nplurals >= 2 )
        {
          // get and validate the plural test conditions
          //preg_match('/plural\s*=\s*(.+?;)$/i', $plural_forms, $plural); // // must include ';' at the end
          preg_match('/plural\s*=\s*(.+?);?$/i', $plural_forms, $plural); // ignore the ending ';' if present
          $plural = trim($plural[1]);

          // validate plural is set
          if ( $plural === '' ) throw new LocaleException("Missing proper plural-forms's 'plural' value in: $json_filepath" . PHP_EOL . PHP_EOL . 'See valid list: ' . self::GETTEXT_PLURALFORMS_EXAMPLES_URL, 8);

          // validate plural has proper characters! (to later pass in eval()!)
          if ( ! $this->_json_evalProperPluralChars($plural) ) throw new LocaleException("Invalid characters found in 'plural' value of the '" . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . "$domain.json' JSON file!" . PHP_EOL . "Content of 'plural' may only use the 'n' variable (without the '$'), and make use of the following characters (excluding the quotes): '" . self::PLURALVALIDCHARS . "'" . PHP_EOL . PHP_EOL . 'See valid list: ' . self::GETTEXT_PLURALFORMS_EXAMPLES_URL, 12);

          // validate that $nplurals actually matches the number of ternary conditions found in $plural
          $pluralConditionsCount = substr_count($plural, '?') + 1;
          if ( ( $nplurals > 2 ) and ( $pluralConditionsCount != $nplurals ) )
          {
            throw new LocaleException("The 'nplurals' value doesn't match the actual number of 'plural' conditions (number of possible returned values) in: $json_filepath", 9);
          }
          // validate the key's plurals array count is equal to nplurals value
          else
          {
            foreach ( $this->_loadedDomains[$domain] as $key => $value )
            {
              if ( ( $key != '' ) and ( $nplurals > 2 ) and ( is_array($value) ) and ( count($this->_loadedDomains[$domain][$key]) != $nplurals ) )
              {
                throw new LocaleException("Possible plurals count for key '$key' in the '" . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . "$domain.json' JSON file doesn't match the 'nplurals' value ($nplurals) !", 10);
              }
            }
          }

          // build proper plural tests conditions in array using regex
          try
          {
            $this->_json_buildGettextPluralTestsArray($domain, $plural);

            // run a test case against the 'plural' conditions just to make sure it validates through eval()
            foreach ( array_keys($this->_loadedDomains[$domain]['']['plural-testcases-list']) as $testcase )
            {
              $testcase4Eval = 'return ' . str_replace('n', 1, $testcase) . ';';

              try
              {
                eval($testcase4Eval);
              }
              catch ( ParseError $ex )
              {
                throw new LocaleException("Invalid plural-forms/plural evaluation '$testcase' in the '" . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . "$domain.json' JSON file: " . $ex->getMessage(), 11);
              }
            }
          }
          catch ( LocaleException $ex )
          {
            throw $ex;
          }
        }

        // set the working 'nplurals' and 'plural' to parse later on
        $this->_loadedDomains[$domain]['']['nplurals'] = $nplurals;
        $this->_loadedDomains[$domain]['']['plural'] = $plural;
      }
    }
  }

  /**
   * Lookup a message in the default domain, singular form
   * @param string $message The message being translated
   * @param mixed $v (optional!) One or more replacement values for sprintf placeholders
   * @return string
   */
  public function gettext(string $message, $v = null): string
  {
    $args = func_get_args();
    array_unshift($args, $this->_domain_default);
    return call_user_func_array(array ( $this, 'dgettext' ), $args);
  }

  /**
   * Lookup a message in the default domain, plural form
   * @param string $msgid1 The singular message ID.
   * @param string $msgid2 The plural message ID.
   * @param int $n The number (e.g. item count) to determine the translation for the respective grammatical number
   * @param mixed $v (optional!) One or more replacement values for sprintf placeholders
   * @return string
   */
  public function ngettext(string $msgid1, string $msgid2, int $n, $v = null): string
  {
    $args = func_get_args();
    array_unshift($args, $this->_domain_default);
    return call_user_func_array(array ( $this, 'dngettext' ), $args);
  }

  /**
   * Lookup a message in a given domain, singular form
   * @param string $domain The lookup domain
   * @param string $message The message being translated
   * @param mixed $v (optional!) One or more replacement values for sprintf placeholders
   * @return string
   */
  public function dgettext(string $domain, string $message, $v = null): string
  {
    $translation = null;

    // USING GETTEXT
    if ( $this->_useGettext )
    {
      // load the domain
      try
      {
        $this->_loadGettextDomain($domain);
      }
      catch ( LocaleException $ex )
      {
        // do nothing, will just return the untranslated text!
        if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": Failed to load domain '$domain': " . $ex->getMessage(), 0);
      }

      $translation = dgettext($domain, $message);

      // check if we have a translation for that message, in that domain
      // if not, add to missing translations array
      if ( $translation == $message )
      {
        $missingTranslationsList = ( $this->_missingTranslations[$domain] ) ?? null;

        if ( ! isset($missingTranslationsList) ) $missingTranslationsList = array ();
        if ( ! in_array($translation, $missingTranslationsList) ) array_push($missingTranslationsList, $translation);

        $this->_missingTranslations[$domain] = $missingTranslationsList;
      }
    }

    // USING JSON
    else
    {
      $translation = $this->_json_dgettext($domain, $message);
    }

    // format messages for web output, excluding the placeholders values!
    if ( ( $this->_formatMessages4Web === true ) and ( $this->_formatMessages4WebInclPlaceholders === false ) ) $translation = $this->stringToWeb($translation);

    // sprintf message ?
    if ( isset($v) )
    {
      $argv = func_get_args();
      $argv[1] = $translation; // replace $message arg with proper translated message
      unset($argv[0]); // no need for the $domain in sprintf

      $translation = call_user_func_array('sprintf', $argv);
    }

    // format messages for web output, including the placeholders values!
    if ( ( $this->_formatMessages4Web === true ) and ( $this->_formatMessages4WebInclPlaceholders === true ) ) $translation = $this->stringToWeb($translation);

    return $translation;
  }

  /**
   * Lookup a message in a given domain, plural form
   * @param string $domain The lookup domain
   * @param string $msgid1 The singular message ID.
   * @param string $msgid2 The plural message ID.
   * @param int $n The number (e.g. item count) to determine the translation for the respective grammatical number
   * @param mixed $v (optional!) One or more replacement values for sprintf placeholders
   * @return string
   */
  public function dngettext(string $domain, string $msgid1, string $msgid2, int $n, $v = null): string
  {
    $translation = null;
    $missingTranslationsTemp = array ();

    // USING GETTEXT
    if ( $this->_useGettext )
    {
      // load the domain
      try
      {
        $this->_loadGettextDomain($domain);
      }
      // failed to load domain
      catch ( LocaleException $ex )
      {
        // array_push($missingTranslationsTemp, $msgid1);
        // array_push($missingTranslationsTemp, $msgid2);
        array_push($missingTranslationsTemp, ( $msgid1 == $msgid2 ) ? $msgid1 : $msgid1 . ' / ' . $msgid2);

        if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": Failed to load domain '$domain': " . $ex->getMessage(), 0);

        try
        {
          // fetch proper UNtranslated message to show (singular or plural), based on simple plural test ($n != 1)
          $defaultPluralTest = 'return ' . str_replace('n', $n, $this->_defaultPlural) . ';';
          $translation = ( eval($defaultPluralTest) ? $msgid2 : $msgid1 );
        }
        catch ( ParseError $ex )
        {
          // if for whatever reason eval() failed, set translation to singular, $msgid1
          $translation = $msgid1;
          if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": Default plural evaluation '" . $this->_defaultPlural . "' for '\$n = $n' caused an Exception: " . $ex->getMessage(), 0);
        }
      }

      // domain loaded, get translation
      if ( empty($missingTranslationsTemp) )
      {
        $translation = dngettext($domain, $msgid1, $msgid2, $n);

        // check if we have a translation for that message, either singular or plural from the given domain
        // if not, add to missing translations array
        if ( ( $translation == $msgid1 ) or ( $translation == $msgid2 ) )
        {
          array_push($missingTranslationsTemp, ( $msgid1 == $msgid2 ) ? $msgid1 : $msgid1 . ' / ' . $msgid2);
        }
      }

      // populate missing translations array if any
      if ( ! empty($missingTranslationsTemp) )
      {
        $missingTranslationsList = ( $this->_missingTranslations[$domain] ) ?? null;

        if ( ! isset($missingTranslationsList) ) $missingTranslationsList = array ();

        foreach ( $missingTranslationsTemp as $missing )
        {
          if ( ! in_array($missing, $missingTranslationsList) ) array_push($missingTranslationsList, $missing);
        }

        $this->_missingTranslations[$domain] = $missingTranslationsList;
      }
    }

    // USING JSON
    else
    {
      $translation = $this->_json_dngettext($domain, $msgid1, $msgid2, $n);
    }

    // format messages for web output, excluding the placeholders values!
    if ( ( $this->_formatMessages4Web === true ) and ( $this->_formatMessages4WebInclPlaceholders === false ) ) $translation = $this->stringToWeb($translation);

    // sprintf message ?
    if ( isset($v) )
    {
      $argv = func_get_args();
      $argv[1] = $translation; // replace $msgid1 arg with translated $message
      unset($argv[0], $argv[2], $argv[3]); // no need for the $domain, $msgid2 and $n in sprintf

      $translation = call_user_func_array('sprintf', $argv);
    }

    // format messages for web output, including the placeholders values!
    if ( ( $this->_formatMessages4Web === true ) and ( $this->_formatMessages4WebInclPlaceholders === true ) ) $translation = $this->stringToWeb($translation);

    return $translation;
  }

  /**
   * get a singular translation from a loaded JSON domain
   * @param string $domain
   * @param string $message
   * @return string
   */
  private function _json_dgettext(string $domain, string $message): string
  {
    $translation = null;

    // load the domain
    try
    {
      $this->_loadJSONDomain($domain);
    }
    catch ( LocaleException $ex )
    {
      // do nothing, will just return the untranslated text!
      if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": Failed to load domain '$domain': " . $ex->getMessage(), 0);
    }

    $m = str_replace('\n', PHP_EOL, $message); // replace '\n' with actual linebreak if any (key and value will have actual linebreaks in JSON data array!)

    if ( ( isset($this->_loadedDomains[$domain][$m]) ) and ( ! is_array($this->_loadedDomains[$domain][$m]) ) ) $translation = trim($this->_loadedDomains[$domain][$m]);

    if ( ( ! isset($translation) ) or ( $translation === '' ) )
    {
      $translation = $message;

      $missingTranslationsList = ( $this->_missingTranslations[$domain] ) ?? null;

      if ( ! isset($missingTranslationsList) ) $missingTranslationsList = array ();
      if ( ! in_array($translation, $missingTranslationsList) ) array_push($missingTranslationsList, $translation);

      $this->_missingTranslations[$domain] = $missingTranslationsList;
    }

    return str_replace(PHP_EOL, '\n', $translation);
  }

  /**
   * get a plural translation from a loaded JSON domain
   * @param string $domain
   * @param string $msgid1
   * @param string $msgid2
   * @param int $n
   * @return string
   */
  private function _json_dngettext(string $domain, string $msgid1, string $msgid2, int $n): string
  {
    $translation = null;
    $translation_singular = null;
    $translation_plural = null;
    $missingTranslationsTemp = array ();
    $defaultPluralTest = 'return ' . str_replace('n', $n, $this->_defaultPlural) . ';';

    // load the domain
    try
    {
      $this->_loadJSONDomain($domain);
    }
    catch ( LocaleException $ex )
    {

      array_push($missingTranslationsTemp, $msgid1);
      array_push($missingTranslationsTemp, $msgid2);

      if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": Failed to load domain '$domain': " . $ex->getMessage(), 0);

      try
      {
        // fetch proper UNtranslated message to show (singular or plural), based on simple plural test ($n != 1)
        $translation = eval($defaultPluralTest) ? $msgid2 : $msgid1;
      }
      catch ( ParseError $ex )
      {
        // if for whatever reason eval() failed, set translation to $msgid1
        // though this should never happen since the $this->_defaultPlural is controlled by the setdefaultPlural() function!
        $translation = $msgid1;
        if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": Default plural evaluation '" . $this->_defaultPlural . "' for '\$n = $n' caused an Exception: " . $ex->getMessage(), 0);
      }
    }

    if ( empty($missingTranslationsTemp) )
    {
      $m1 = str_replace('\n', PHP_EOL, $msgid1); // replace '\n' with actual linebreak if any (key and value will have actual linebreaks in JSON data array!)
      $m2 = str_replace('\n', PHP_EOL, $msgid2);

      // get the translations
      $translation_singular = trim($this->_loadedDomains[$domain][$m1] ?? null);
      $translation_plural = $this->_loadedDomains[$domain][$m2] ?? null;

      if ( is_string($translation_plural) ) $translation_plural = trim($translation_plural);

      // ************************
      // using custom plurals, thus got a nplurals and plural set...
      if ( $this->_useCustomPluralForms )
      {
        /**
         * USING GETTEXT C-STYLE PLURAL
         */
        if ( $this->_usePluralFormsFromGettext )
        {
          $nplurals = $this->_loadedDomains[$domain]['']['nplurals'];

          try
          {
            $plural_value_id = $this->_json_evalPluralFromGettext($domain, $n);

            // get singular translation value if _json_evalPluralFromGettext() returns null (all testcases failed, or nplurals=1 meaning NO plurals available!)
            if ( $plural_value_id === null )
            {
              $translation = $translation_singular;

              // if we dont have a singular translation, set to $msgid1
              if ( ( ! isset($translation) ) or ( $translation === '' ) )
              {
                $translation = $msgid1;
                array_push($missingTranslationsTemp, $msgid1);
              }
            }
            // we have a plural ID; get right plural value array element!
            elseif ( $nplurals >= 2 )
            {
              $translation = $translation_plural;

              // if single test condition i.e. ('n > 1'), return array first element[0] if $translation_plural is an array
              if ( ( $nplurals == 2 ) and ( is_array($translation) ) )
              {
                $translation = trim($translation[$plural_value_id]); // $plural_value_id should be equal to zero here, normally!
              }
              // many possible conditions at this point ($nplurals >=3)
              elseif ( $nplurals >= 3 )
              {
                if ( is_array($translation) )
                {
                  $translation = trim($translation[$plural_value_id]);
                }
                else
                {
                  // if our available translations is just a single string (a.k.a NOT an array!), it's a mistake since we should have many plurals available at this point ($nplurals >=3 )
                  // ditch the single string translation! Otherwise, kinda dumb to keep it as THE right translation!
                  unset($translation);
                }
              }

              // if we dont have a plural translation, set to $msgid2
              if ( ( ! isset($translation) ) or ( $translation === '' ) )
              {
                $translation = $msgid2; // gettext, here, would return the singular untranslated text... I prefer the plural, since we did get a return value from the plurals test conditions!

                if ( ( is_array($translation_plural) ) or ( $plural_value_id >= 1 ) )
                {
                  array_push($missingTranslationsTemp, $msgid2 . " [array id: $plural_value_id]");
                }
                else
                {
                  array_push($missingTranslationsTemp, $msgid2);
                }
              }
            }
          }
          // if we get any fatal errors while evaluating the gettext possible plural values, just return UNtranslated $msgid1
          catch ( LocaleException $ex )
          {
            $translation = $msgid1;
            if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": " . $ex->getMessage(), 0);
          }
        }

        /**
         * USING PHP STYLE PLURAL: PREFERED WAY!!
         */
        else
        {
          $nplurals = trim($this->_loadedDomains[$domain]['']['nplurals']);
          $plural = trim($this->_loadedDomains[$domain]['']['plural']);

          // if the language as many plural forms, say NOT like Japanese (nplurals=1; plural=0;)
          // get the plural ternary test conditions and retrieve the right plural array value
          if ( $nplurals >= 1 )
          {
            try
            {
              $plural_value_id = $this->_json_evalPlural($plural, $n, $domain);

              // get the translations
              $translation = $translation_plural;

              // if we have a single nplurals, thus a single plural testcase (i.e. (n > 1)),
              // and test returned 'true', get the $translation[0] (i.e. the first translation)
              if ( ( $nplurals == 1 ) and ( $plural_value_id == 1 ) )
              {
                if ( is_array($translation) ) $translation = trim($translation[0]);

                // if we dont have a plural translation, set to $msgid2
                if ( ( ! isset($translation) ) or ( $translation === '' ) )
                {
                  $translation = $msgid2;
                  array_push($missingTranslationsTemp, $msgid2);
                }
              }
              // single nplural, but test returned false: return singular!
              elseif ( ( $nplurals == 1 ) and ( $plural_value_id == 0 ) )
              {
                $translation = $translation_singular;

                // if we dont have a singular translation, set to $msgid1
                if ( ( ! isset($translation) ) or ( $translation === '' ) )
                {
                  $translation = $msgid1;
                  array_push($missingTranslationsTemp, $msgid1);
                }
              }
              // everything else: get the right array value based on ternary conditions returned from _json_evalPlural()
              else
              {

                if ( is_array($translation) )
                {
                  $translation = trim($translation[$plural_value_id]);
                }
                else
                {
                  // if our available translations is just a single string (a.k.a NOT an array!), it's a mistake since we should have many plurals available at this point ($nplurals >=2 )
                  // ditch the single string translation! Otherwise, kinda dumb to keep it as THE right translation!
                  unset($translation);
                }

                // if we dont have a plural translation, set to $msgid2
                if ( ( ! isset($translation) ) or ( $translation === '' ) )
                {
                  $translation = $msgid2; // gettext, here, would return the singular untranslated text... I prefer the plural, since we did get a return value from the plurals test conditions!

                  if ( ( is_array($translation_plural) ) or ( $plural_value_id >= 1 ) )
                  {
                    array_push($missingTranslationsTemp, $msgid2 . " [array id: $plural_value_id]");
                  }
                  else
                  {
                    array_push($missingTranslationsTemp, $msgid2);
                  }
                }
              }
            }
            catch ( LocaleException $ex )
            {
              $translation = $msgid1;
              if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": Plural evaluation '$plural' for '\$n = $n' caused an Exception: " . $ex->getMessage(), 0);
            }
          }

          // we have no plural values possible for the language (i.e. nplurals=0 ),
          // meaning the language has no plural! Return singular form...
          else
          {
            $translation = $translation_singular;

            // if we dont have a singular translation, set to $msgid1
            if ( ( ! isset($translation) ) or ( $translation === '' ) )
            {
              $translation = $msgid1;
              array_push($missingTranslationsTemp, $msgid1);
            }
          }
        }
      }

      // ************************
      // $_useCustomPluralForms is set to FALSE, then just do a check against $this->_defaultPlural to set 1st plural value, if applicable
      else
      {
        $n_org = $n;
        $n = abs($n); // make $n positive for the plural tests

        try
        {
          if ( eval($defaultPluralTest) )
          {
            // not using plurals, so just get the 1st possible plural value
            $translation = is_array($translation_plural) ? trim($translation_plural[0]) : $translation_plural;

            // if we dont have a plural translation, set to $msgid2
            if ( ( ! isset($translation) ) or ( $translation === '' ) )
            {
              $translation = $msgid2;
              array_push($missingTranslationsTemp, $msgid2);
            }
          }
          else
          {
            $translation = $translation_singular;

            // if we dont have a singular translation, set to $msgid1
            if ( ( ! isset($translation) ) or ( $translation === '' ) )
            {
              $translation = $msgid1;
              array_push($missingTranslationsTemp, $msgid1);
            }
          }
        }
        catch ( ParseError $ex )
        {
          // if for whatever reason eval() failed, set translation to $msgid1
          // though this should never happen since the $this->_defaultPlural is controlled by the setdefaultPlural() function!
          $translation = $msgid1;
          if ( $this->_debug ) error_log($this->_project . " " . __CLASS__ . ": Default plural evaluation '" . $this->_defaultPlural . "' for '\$n = $n_org' caused an Exception: " . $ex->getMessage(), 0);
        }
      }
    }

    // populate missing translations array if any
    if ( ! empty($missingTranslationsTemp) )
    {
      $missingTranslationsList = ( $this->_missingTranslations[$domain] ) ?? null;

      if ( ! isset($missingTranslationsList) ) $missingTranslationsList = array ();

      foreach ( $missingTranslationsTemp as $missing )
      {
        if ( ! in_array($missing, $missingTranslationsList) ) array_push($missingTranslationsList, $missing);
      }

      $this->_missingTranslations[$domain] = $missingTranslationsList;
    }

    return str_replace(PHP_EOL, '\n', $translation);
  }

  /**
   * Evaluate the plural ternary test conditions and return plural array id to use for the matched condition
   * @param string $plural
   * @param int $n
   * @param string $domain
   * @throws LocaleException
   * @return int
   */
  private function _json_evalPlural(string $plural, int $n, $domain = null): int
  {
    $retval = null;

    $n = abs($n);

    // remove plural ending ';' if any
    $plural = rtrim($plural, ';');

    // I don't want any '$' in the plural value from the JSON file!
    // Users need to just enter 'n' as possible variable and I replace that here by the real value!
    // (just an extra precaution for eval)
    $testcase = 'return ' . str_replace('n', $n, $plural) . ';';

    if ( $this->_json_evalProperPluralChars($plural) )
    {
      try
      {
        $retval = (int) eval($testcase);
      }
      catch ( ParseError $ex )
      {
        if ( isset($domain) )
        {
          throw new LocaleException("Invalid 'plural' ternary conditions format in the '" . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . "$domain.json' JSON file!" . PHP_EOL . PHP_EOL . $ex->getMessage(), 11);
        }
        else
        {
          throw new LocaleException("Invalid default 'plural' ternary conditions format: " . PHP_EOL . PHP_EOL . $ex->getMessage(), 11);
        }
      }
    }
    else
    {
      if ( isset($domain) )
      {
        throw new LocaleException("Invalid characters found in 'plural' value of the '" . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . "$domain.json' JSON file!" . PHP_EOL . "Content of 'plural' may only use the 'n' variable (without the '$'), and make use of the following characters (excluding the quotes): '" . self::PLURALVALIDCHARS . "'", 12);
      }
      else
      {
        throw new LocaleException("Invalid characters found in default 'plural' value!" . PHP_EOL . "You may only use the 'n' variable (without the '$'), and make use of the following characters (excluding the quotes): '" . self::PLURALVALIDCHARS . "'", 12);
      }
    }

    return $retval;
  }

  /**
   * A simple security check to make sure only certain characters are entered as the 'plural' value
   * @param string $plural
   * @return bool
   */
  private function _json_evalProperPluralChars(string $plural): bool
  {
    // look for any invalid characters in $plural: if found any, return false / throw exception!
    if ( preg_match('/[^\s' . preg_quote(self::PLURALVALIDCHARS) . ']/', $plural) )
    {
      return false;
    }

    return true;
  }

  /**
   * Loop through the plural test conditions and return the right plural array ID to use, or null if need to use singular value!
   * @param string $domain
   * @param int $n
   * @throws LocaleException
   * @return mixed INT indicating to fetch proper plural[X] value; otherwise, NULL if need to fetch singular value
   */
  private function _json_evalPluralFromGettext(string $domain, int $n)
  {
    $retval = null;

    $nplurals = $this->_loadedDomains[$domain]['']['nplurals'];
    $plural_testcases_list = $this->_loadedDomains[$domain]['']['plural-testcases-list'];

    if ( $nplurals >= 2 )
    {
      // If we get to this function at some point, and $plural_testcases_list is still NOT set,
      // it's because it had failed to set it in the _loadJSONDomain() and user *ignored* the exception!
      // just re-throw the same damn exception! This would occur if user ignored the error when calling the constructor AND user called an *ngettext() function to get translations!
      // at worst, if that's the case and user ignores this, he will just get UNtranslated messages!
      if ( ! isset($plural_testcases_list) ) throw new LocaleException("Invalid format of plural-forms's 'plural' value specified in the '" . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . "$domain.json' JSON file! Check the syntax!!" . PHP_EOL . PHP_EOL . 'See valid list: ' . self::GETTEXT_PLURALFORMS_EXAMPLES_URL, 8);

      $n_positive = abs($n);

      $testcaseSuccess = null;

      // loop each test cases and try to evaluate it to find the right plural array[X] value to get
      foreach ( $plural_testcases_list as $testcase => $arrayIDtoUse )
      {
        // all other cardinal numbers use this array value
        if ( $testcase == 'OTHER_NUMBERS' )
        {
          //echo "Return TRUE (OTHER_NUMBERS): get \$array[$arrayIDtoUse] value!<br>";

          $testcaseSuccess = true;
          $retval = $arrayIDtoUse;
          break;
        }

        // run testcase and if TRUE, return its $arrayIDtoUse
        else
        {
          $testcase4Eval = 'return ' . str_replace('n', $n_positive, $testcase) . ';';

          try
          {
            if ( eval($testcase4Eval) )
            {
              //echo "Return TRUE: get \$array[$arrayIDtoUse] value!<br>";

              $testcaseSuccess = true;
              $retval = $arrayIDtoUse;
              break;
            }
          }
          catch ( ParseError $ex )
          {
            // We should never get here, since all of the testcases were tested for validity in the _loadJSONDomain(), unless user ignored the exception!
            // Just in case, break away with exception if even just 1 plural evaluation fails to eval()!
            throw new LocaleException("Invalid plural evaluation '$testcase' for '\$n = $n' in the '" . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . "$domain.json' JSON file: " . $ex->getMessage(), 11);
          }
        }
      }

      // no testcases passed, return singular form value...
      if ( ! $testcaseSuccess )
      {
        $retval = null;
      }
    }

    // we have no plural values for the language (i.e. nplurals=1; plural=0;),
    // meaning the language has no plural! Return singular form value...
    else
    {
      $retval = null;
    }

    return $retval;
  }

  /**
   * Build array of gettext's 'plural' conditions using regex, based on its single string value 'plural=...'<br/>
   * This is only used if using $_usePluralFormsFromGettext TRUE
   * @param string $domain
   * @param string $plural
   * @throws LocaleException
   */
  private function _json_buildGettextPluralTestsArray(string $domain, string $plural)
  {
    $plural_testcases_list = $this->_loadedDomains[$domain]['']['plural-testcases-list'];

    if ( ( ! isset($plural_testcases_list) ) or ( ! is_array($plural_testcases_list) ) )
    {
      $plural_buffer = $plural;
      $plural_testcases_list = array ();

      // parse the plural condition tests
      if ( strpos($plural, '?') )
      {
        $match = null;

        // get all testcases and push to array
        while ( preg_match('/^(?<FULL_MATCH>(?<TESTCASE>.+?)\s?\?\s*(?<ID_TO_USE>\d+)\s*:?\s*)/', $plural_buffer, $match) )
        {
          $testcase = trim($match['TESTCASE']);

          // make sure $testcase is surrounded by parenthesis
          // get first & last char:
          $fchar = $testcase[0];
          $lchar = $testcase[strlen($testcase) - 1];
          $addedOpenParenthesis = null;

          // if $testcase doesn't start with an opening '(' add one !
          if ( $fchar != '(' )
          {
            $testcase = '(' . $testcase;
            $addedOpenParenthesis = true;
          }
          // if $testcase doesn't ends with ')', or if an opening one was added, add one !
          if ( ( $lchar != ')' ) or ( $addedOpenParenthesis ) )
          {
            $testcase = $testcase . ')';
          }

          $plural_testcases_list[$testcase] = $match['ID_TO_USE'];
          $plural_buffer = substr($plural_buffer, strlen($match['FULL_MATCH']));
        }

        // get last test condition if present: 'ALL other cardinal numbers, get value from array[X]'
        // i.e. 'plural=(n>5) ? 0 : (n>30) ? 1 : 2;' it would find the array id '2' to use for all other cardinal numbers
        if ( preg_match('/(\d+).*$/', $plural_buffer, $match) )
        {
          $plural_testcases_list['OTHER_NUMBERS'] = $match[1];
        }
        else
        {
          // it would be an error if we have no final 'for everything else, use : X' integer!
          throw new LocaleException("Invalid format of plural-forms's 'plural' value specified in the '" . DIRECTORY_SEPARATOR . $this->_lang . DIRECTORY_SEPARATOR . "$domain.json' JSON file! Check the syntax!!" . PHP_EOL . PHP_EOL . 'See valid list: ' . self::GETTEXT_PLURALFORMS_EXAMPLES_URL, 8);
        }
      }

      // we have a single condition test (i.e. nplurals=2; plural=(n > 1);)
      else
      {
        $plural_testcases_list[$plural] = '0'; // populate to testcases to use first value (array[0])
      }

      // push testcases list to $domain so we don't have to alway re-parse the 'plural' eveytime we want a translation!
      $this->_loadedDomains[$domain]['']['plural-testcases-list'] = $plural_testcases_list;

      //echo "<h3>$domain.json</h3><pre>";
      //var_dump($this->_loadedDomains[$domain]['']['plural-testcases-list']);
      //echo '</pre>';
      //die();
    }
  }

  /**
   * stringToWeb()
   * Format a string for web output
   * @param string $value
   * @return string
   */
  private function stringToWeb(string $value)
  {
    $value = htmlentities($value, ENT_QUOTES);
    $value = str_replace('\n', '<br/>', $value);
    return $value;
  }

  /**
   * isAssocArray()
   * <pre>
   * Tries to check if a given array is an associative array (i.e. perl hash) or not.
   * The only time this will fail is if it's given an associative array
   * who's keys are all sequential intergers, AND starting with integer '0', like:
   *
   * $array = [
   *    0 => [ "somevalue", 5 ],
   *    1 => [ 9, "test" ],
   *    2  => [ null, "blabla" ]
   * ];
   * </pre>
   * @param array $array
   * @return boolean
   */
  private static function isAssocArray($array)
  {
    if ( ! is_array($array) ) return false;

    $keys = array_keys($array);

    // loop array key names,
    // if one ISN'T a sequential integer, we have an associative array
    for ( $i = 0; $i < count($keys); $i ++ )
    {
      if ( $keys[$i] !== $i )
      {
        return true;
        break;
      }
    }
    return false;
  }

  /**
   * alias to Locale::gettext()
   * Lookup a message in the default domain, singular form
   * @param string $message The message being translated
   * @param mixed $v (optional!) One or more replacement values for sprintf placeholders
   * @return string
   */
  public function _(): string
  {
    $args = func_get_args();
    array_unshift($args, $this->_domain_default);
    return call_user_func_array(array ( $this, 'dgettext' ), $args);
  }

  /**
   * alias to Locale::ngettext()
   * Lookup a message in the default domain, plural form
   * @param string $msgid1 The singular message ID.
   * @param string $msgid2 The plural message ID.
   * @param int $n The number (e.g. item count) to determine the translation for the respective grammatical number
   * @param mixed $v (optional!) One or more replacement values for sprintf placeholders
   * @return string
   */
  public function _n(): string
  {
    $args = func_get_args();
    array_unshift($args, $this->_domain_default);
    return call_user_func_array(array ( $this, 'dngettext' ), $args);
  }

  /**
   * alias to Locale::dgettext()
   * Lookup a message in a given domain, singular form
   * @param string $domain The lookup domain
   * @param string $message The message being translated
   * @param mixed $v (optional!) One or more replacement values for sprintf placeholders
   * @return string
   */
  public function _d(): string
  {
    return call_user_func_array(array ( $this, 'dgettext' ), func_get_args());
  }

  /**
   * alias to Locale::dngettext()
   * Lookup a message in a given domain, plural form
   * @param string $domain The lookup domain
   * @param string $msgid1 The singular message ID.
   * @param string $msgid2 The plural message ID.
   * @param int $n The number (e.g. item count) to determine the translation for the respective grammatical number
   * @param mixed $v (optional!) One or more replacement values for sprintf placeholders
   * @return string
   */
  public function _dn(): string
  {
    return call_user_func_array(array ( $this, 'dngettext' ), func_get_args());
  }
}

/******************************************************************************
 * Locale Exception class
 ******************************************************************************/
class LocaleException extends Exception
{

  /**
   * Generate custom 'Locale' Exception
   * @param string $message error message to generate! This in turn can be caught using ex.getMessage();
   * @param integer $code error code ($ex.getCode())
   */
  public function __construct(string $message, $code = 0)
  {
    parent::__construct($message, $code);
  }
}

?>
