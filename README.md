# CorbeauPerdu\i18n\Locale Class
Wrapper class to set locale environment and then either use *nix gettext(), or JSON files for translations!

<a href="https://github.com/ravenlost/PHP_Locale/blob/master/UsageExamples/LocaleUsageExamples.php">**See: LocaleUsageExamples.php**</a>

You have two choices for locale messages: Gettext MO files or JSON files.

If using JSON files, here are two examples of proper formats:

<pre>
Example JSON French file: <locales_dir>/fr_FR/prestadesk.json
{
  "": {
    "domain": "prestadesk",
    "language": "fr_FR",
    "nplurals": "1",
    "plural": "(n > 1)"
  },

  "Welcome, %s!": "Bienvenu, %s!",
  "This page will show the dashboard": "Cette page affichera le tableau de bord",

  "I wrote a line of code": "J'ai écris une ligne de code",
  "I wrote %d lines of code": "J'ai un écris %d lignes de code"
}

Example JSON Serbian file: <locales_dir>/sr_CS/prestadesk.json
{
  "": {
    "domain": "prestadesk",
    "language": "sr_CS",
    "nplurals": "3",
    "plural": "((n%10==1 && n%100!=11) ? 0 : ((n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20)) ? 1 : 2))"
  },

  "Welcome, %s!": "Dobrodosli, %s!",
  "This page will show the dashboard": "Ova stranica ce prikazati kontrolnu tablu",

  "I wrote a line of code": "Napisao sam liniju koda",
  "I wrote %d lines of code": [
    "Napisao sam %d liniju koda",
    "Napisao sam %d linije koda",
    "Napisao sam %d linija koda"
  ]
}
</pre>

Note that the "plural" value needs to be a properly formated ternary condition for PHP!<br/>
You'll find gettext valid plurals here: http://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html<br/>
However, since these are for gettext (meaning C-Style ternary conditions), you need to adjust them to be valid in PHP!

ELSE, if you are too lazy to adjust them :p, set the constructor's $usePluralFormsFromGettext to TRUE,<br/>
and replace the "nplurals" and "plural" with a copy/pasted "plural-forms" from the link above, like so:

<pre>
"plural-forms": "nplurals=2; plural=(n > 1);"
"plural-forms": "nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);"
</pre>

The script will attempt to regex parse the nplurals and plural value, and rebuild proper ternary conditions for PHP!

**Notes about $locales array:**

Your supported $locales should be in the form of [ 'desired lang passed from querystring, cookie, etc' => 'real installed lang mapping on server' ]<br/>
`$locale = [ 'en' => 'en_US', 'fr' => 'fr_FR' ];`

In this case, 'en_US' and 'fr_FR' folders are expected to exist in your specified locales directory!

Even if you use 'phrases' as your language keys (say the actual english message to translate, if english is your default),<br/>
you still need to add this default locale to the $locales array passed to Locale() constructor!

AND

you need to create either one, based on your config, a gettext translation file (domain.mo),<br/>
OR a JSON translation file, both with at least the headers in it:

<pre>
$locales_dir/&lt;default_lang>/LC_MESSAGES/$domain.mo  OR
$locales_dir/&lt;default_lang>/$domain.json
</pre>

If you don't do this, you'll get exceptions when it checks for the locale's translation files, or exceptions when it tries to validate plural-forms!

No, in reality because you're using phrases as keys, you won't need real translation files.<br/>
However, doing it this way, you can always decide to use keywords as keys (i.e. profile.title) later on,<br/>
and then create complete translation files ALSO for your default language holding these keyword keys


**Functions:**
<pre>
  Locale::gettext()   or Locale::_()    // Lookup a message in the current domain, singular form
  Locale::ngettext()  or Locale::_n()   // Lookup a message in the current domain, plurial form
  Locale::dgettext()  or Locale::_d()   // Lookup a message in a given domain, singular form
  Locale::dngettext() or Locale::_dn()  // Lookup a message in a given domain, plurial form

  Locale::loadDomain()                     // load additionnal domain (translation files), on top of the default one

  Locale::getDefaultDomain()               // get the default domain set
  Locale::getMissingTranslations()         // get a list of missing translations in the page (call this at the very end of your script!)
  Locale::getMissingTranslations_toWeb()   // ... printed for a webpage!
  Locale::getLoadedDomains()               // get list of loaded domains: if using JSON, will also include the actual domain messages data
  Locale::getLoadedDomains_toWeb()         // ... printed for a webpage!
  Locale::getLang() or Locale::getLocale() // returns the currently set lang/locale
  Locale::getLang4HtmlTag()                // ... replaces '_' with '-' for proper format to put in &lt;html> and &lt;meta> html tags
  Locale::getTranslationsAsJSON()          // get a JSON string holding all translations for a given $domain
                                           // useful to populate say a javascript variable and thus get translations even in JS!
                                           // can be used only if using JSON files
  Locale::setFormatMessages4Web()          // if set to true, all returned messages will be htmlentities()'d and line breaks '\n' replaced with '&lt;br/>'
  Locale::setFormatMessages4WebInclPlaceholders() // if formatMessages4Web is on, do we want to also format the placeholder values? Default is true!
  Locale::switchLang()                     // switch running locale to another language; all previously loaded domains will be re-loaded in desired language!
</pre>

__Notes about the *ngetttext() functions for plurals:__<br/>
If you are using actual keywords as keys for message translations (i.e. profile.title):

  - if using gettext MOs : both singular and pural values needs to be the same:<br>
       `Locale::ngettext('cat.amount', 'cat.amount', 3)`
  - if using JSON files  : both singular and pural values needs to be different:<br>
       `Locale::ngettext('cat.amount', 'cat.amount.plural', 3)`

**Notes about sprintf functionnality:**<br/>
You can provide any of the *gettext() functions 1 or many 'v' optional argument(s).<br/>
These values will be used to replace the sprintf's placeholders! i.e.:<br/>
`Locale::_n("Hello %s, you have %d mail.", "Welcome, %s! You have %d emails.", "John", 5);`<br>
`Locale::_n("%.1f hour/week", "%.1f hours/week", 37.5000, 37.5000);`


**Extra tip:**<br/>
If you plan on using gettext, and use tools such as POedit, or xgettext, etc. to get and manage your translations,<br/>
you can add Sources Keywords to enable it to find all needed translations from all source code.

For this script, use the following keywords:
<pre>
gettext:1
ngettext:1,2
dgettext:2
dngettext:2,3
_:1
_n:1,2
_d:2
_dn:2,3
</pre>

If Using POedit, just add this to the header of your PO:

<pre>
"X-Poedit-KeywordsList: gettext:1;ngettext:1,2;dgettext:2;dngettext:2,3;_:1;_n:1,2;_d:2;_dn:2,3\n"
</pre>

When using JSON files, personnally I'd also use this method to retrieve all my needed translations into a PO file, and then just create my JSON file based on my values in my PO!

<h2>Known Bugs</h2>
There is currently a small issue when using plural tests (any of the <i>*<b>n</b>gettext()</i> functions). Passing a float value as the testing number to know if we need the plural or singular form will not work properly: it currently can only receive <b>INT</b>eger values! Need to also investigate if the same problem exists with <i><b><a href="https://github.com/ravenlost/JS_Locale">JS_Locale</a></b></i>.<br><br>

Example: `ngettext('I have one cat', 'I have many cats', 1.6)`

Will output: <b><i>I have one cat</i></b>, even though, there is more than one (1.6). Okay, using cats as an example is ridiculous (no one can have 1.<b>6</b> cats! lol), but it still shows the problem. 
