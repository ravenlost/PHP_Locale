<?php
use CorbeauPerdu\i18n\Locale;
use CorbeauPerdu\i18n\LocaleException;

require_once ( 'Locale.php' );

/******************************************************************************
 * Demo on Locale usage to set proper environment locale!
 *
 * <pre>
 * You have two choices for locale messages: Gettext MO files or JSON files.
 *
 * If using JSON files, here are two examples of proper formats:
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
 * Note that the "plural" value needs to be a properly formated ternary condition for PHP!
 * You'll find gettext valid plurals here: http://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html
 * However, since these are for gettext (meaning C-Style ternary conditions), you need to adjust them to be valid in PHP!
 *
 * ELSE, if you are too lazy to adjust them :p, set the constructor/initLocale's $usePluralFormsFromGettext to TRUE,
 * and replace the "nplurals" and "plural" with a copy/pasted "plural-forms" from the link above, like so:
 *
 *   "plural-forms": "nplurals=2; plural=(n > 1);"
 *   "plural-forms": "nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);"
 *
 * The script will attempt to regex parse the nplurals and plural value, and rebuild proper ternary conditions for PHP!
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
 * Functions:
 * <pre>
 *   Locale::gettext()   or Locale::_()    // Lookup a message in the current domain, singular form
 *   Locale::ngettext()  or Locale::_n()   // Lookup a message in the current domain, plural form
 *   Locale::dgettext()  or Locale::_d()   // Lookup a message in a given domain, singular form
 *   Locale::dngettext() or Locale::_dn()  // Lookup a message in a given domain, plural form
 *
 *   Locale::loadDomain()                     // load additionnal domain (translation files), on top of the default one
 *
 *   Locale::getDefaultDomain()               // get the default domain set
 *   Locale::getMissingTranslations()         // get a list of missing translations in the page (call this at the very end of your script!)
 *   Locale::getMissingTranslations_toWeb()   // ... printed for a webpage!
 *   Locale::getLoadedDomains()               // get list of loaded domains: if using JSON, will also include the actual domain messages data
 *   Locale::getLoadedDomains_toWeb()         // ... printed for a webpage!
 *   Locale::getLang() or Locale::getLocale() // returns the currently set lang/locale
 *   Locale::getLang4HtmlTag()                // ... replaces '_' with '-' for proper format to put in <html> and <meta> html tags
 *   Locale::getTranslationsAsJSON()          // get a JSON string holding all translations for a given $domain
 *                                            // useful to populate say a javascript variable and thus get translations even in JS!)
 *                                            // can be used only if using JSON files
 *   Locale::setFormatMessages4Web()          // if set to true, all returned messages will be htmlentities()'d and line breaks '\n' replaced with '<br/>'
 *   Locale::setFormatMessages4WebInclPlaceholders() // if formatMessages4Web is on, do we want to also format the placeholder values? Default is true!
 *   Locale::switchLang()                     // switch running locale to another language; all previously loaded domains will be re-loaded in desired language!
 * </pre>
 *
 * Notes about the *ngetttext() functions for plurals:
 * <pre>
 * If you are using actual keywords as keys for message translations (i.e. profile.title):
 *
 *   - if using gettext MOs : both singular and pural values needs to be the same:
 *        Locale::ngettext('cat.amount', 'cat.amount', 3)
 *   - if using JSON files  : both singular and pural values needs to be different:
 *        Locale::ngettext('cat.amount', 'cat.amount.plural', 3)
 * </pre>
 *
 * Notes about sprintf functionnality:
 * <pre>
 * You can provide any of the *gettext() functions 1 or many 'v' optional argument(s).
 * These values will be used to replace the sprintf's placeholders! i.e.:
 * Locale::_n("Hello %s, you have %d mail.", "Welcome, %s! You have %d emails.", "John", 5);
 * </pre>
 *
 * Extra tip:
 * <pre>
 * If you plan on using gettext, and use tools such as POedit, or xgettext, etc. to get and manage your translations,
 * you can add Sources Keywords to enable it to find all needed translations from all source code.
 *
 * For this script, use the following keywords:
 * gettext:1
 * ngettext:1,2
 * dgettext:2
 * dngettext:2,3
 * _:1
 * _n:1,2
 * _d:2
 * _dn:2,3
 *
 * If Using POedit, just add this to the header of your PO:
 * "X-Poedit-KeywordsList: gettext:1;ngettext:1,2;dgettext:2;dngettext:2,3;_:1;_n:1,2;_d:2;_dn:2,3\n"
 *
 * When using JSON files, personnally I'd also use this method to retrieve all my needed translations into a PO file, and then just create my JSON file based on the values in my PO!
 * </pre>
 *
 * @author Patrick Roy (ravenlost2@gmail.com)
 *******************************************************************************/

$locales = [  'en' => 'en_US', 'fr' => 'fr_FR', 'nl' => 'nl_NL', 'sr' => 'sr_RS' ];
$locales_dir = './locales-testdata';
$domain_default = 'prestadesk';
$codeset = 'UTF-8';
$lang_default = 'en';
$cookieroot = '/';

$lc_category = null; // don't set locale LC categories... will use server's default, or
$lc_category = array(LC_MESSAGES, LC_MONETARY); // set specified LC categories to running Locale / Lang
$lc_category = array(LC_MESSAGES => 'en_US', LC_MONETARY => 'fr_FR'); // set specified LC categories to custom locales

$useGettext = false;
$useCustomPluralForms = true; // used if using JSON files; if false, then script will just use the Locale::_defaultPlural test condition to determine if it needs to return singular or plural
$usePluralFormsFromGettext = false; // used if using JSON files; if true, script will regex parse the plural-forms value taken possibly from gettext documentation (it's written for 'C' and doesn't work as is in PHP!)
$debug = true;
$project = 'MYPROJECT';

// initialize locale
try
{
  $locale = new Locale($locales, $locales_dir, $domain_default, $codeset, $lang_default, $cookieroot, $lc_category, $useGettext, $useCustomPluralForms, $usePluralFormsFromGettext, $debug, $project);

  // if you have additional 'domains' (translation files) to load, it's better here in order to catch exceptions, if any,
  // otherwise if you call the 'dgettext*' functions with the desired domain, it will try to load it for you,
  // but will not re-throw exceptions, if any. It will instead simply show the UNtranslated message key you were looking for!
  $locale->loadDomain('navbar');
}
catch ( LocaleException $ex )
{
  $code = $ex->getCode();
  die($ex->getMessage() . " Code: $code");

  // most importantly, die if:
  // $code=1 invalid default lang! => will cause problems:
  //   - if trying to load an invalid lang, it won't be able to revert to the default one, thus it won't find the default lang's translation files..
  //   - if passed a valid lang with query-string, etc, it will still not have set the proper locale and won't translate
  //
  // $code=2  Gettext extension is missing in php.ini: will cause a PHP Fatal error
  // $code=11 syntax error in plural value
  // $code=12 invalid chars found in plural value !IMPORTANT! This is dangerous, thus really needs to be dealt with!

  // the rest of the error codes can potentially be ignored: the message keys will be returned, untranslated!
  // but really, you should probably deal with all the possible error codes here!!
}

// print ou current LANG
echo '<h1>Locale: ' . $locale->getLang() . '</h1>';

/*********************************************
 * DEMO WITH DEFAULT DOMAIN
 *********************************************/
echo '<h2>Demo with default domain</h2>';

// normal gettext
echo '<h3>' . $locale->gettext('test.page.title') . '</h3>';
echo $locale->gettext('This page will show the dashboard') . '<br>';

// just showing missing translations...
echo $locale->gettext('This translation should be missing...') . '<br>';
echo $locale->gettext('And yet another missing translation...') . '<br><br>';

// fancy gettext, formated with sprintf
echo $locale->gettext('Welcome, %s!', 'John') . '<br>';

// plural gettext
echo $locale->ngettext('I have one car', 'I have A LOT of cars', 3) . '<br>';

// fancy plural gettext, formated with sprintf
echo $locale->ngettext('I wrote a line of code', 'I wrote %d lines of code', 44, 44) . '<br>';

/*********************************************
 * DEMO WITH ADDITIONNAL DOMAIN
 *********************************************/
echo '<h2>Demo with additionnal "navbar" domain </h2>';

// normal gettext with another domain
echo $locale->dgettext('navbar', 'This is my navbar') . "<br>";

// just showing a missing translation from another domain
echo $locale->dgettext('navbar', 'This translation should be missing in navbar domain') . "<br>";

// fancy gettext with another domain, formated with sprintf
echo $locale->dgettext('navbar', 'I like the color %s', 'red') . "<br>";

// plural gettext with another domain
echo $locale->dngettext('navbar', 'I have one coin', 'I have A LOT of coins', 5) . "<br>";

// fancy plural gettext with another domain, formated with sprintf
//echo $locale->_dn('navbar', 'cat.amount', 'cat.amount', 3, 3) . "<br>"; // using gettext MO's

// else if using JSON: singular and plural keys must differ, like so:
echo $locale->dngettext('navbar', 'cat.amount', 'cat.amount.plural', 3, 3) . "<br><br>"; // using JSON files

/*********************************************
 * GET SOME DATA FOR VALIDATION/DISGNOSTICS
 *********************************************/
echo '<h2>Data</h2>';

// print missing translations
$locale->getMissingTranslations_toWeb();

// print loaded domains
$locale->getLoadedDomains_toWeb();

?>
