<?php



function pai_get_locale() {
  if (defined('PAI_LOCALE')) {
    return PAI_LOCALE;
  }

  $conf = pai_conf('plugins', 'locale');

  $client_header = explode(',', @$_SERVER['HTTP_ACCEPT_LANGUAGE']);

  if (count($client_header) < 1) {
    return false;
  }

  $locale = false;

  $available = $conf['available'];
  if (!is_array($available)) {
    $available = explode(',', $available);
  }

  foreach ($client_header AS $raw_entry) {
    $temp = explode(';', $raw_entry);
    $temp = trim($temp[0]);

    if (in_array($temp, $available)) {
      $locale = $temp;
      break;
    }
  }

  if (!$locale) {
    $locale = $conf['default'];
  }

  $locale = pai_apply_filters('locale', $locale);

  define('PAI_LOCALE', $locale);
  return $locale;
}

/**
 * Retrieves the translation of $text. If there is no translation, or
 * the domain isn't loaded the original text is returned.
 *
 * @see __() Don't use pai_translate() directly, use __()
 * @since 2.2.0
 *
 * @param string $text Text to translate.
 * @param string $domain Domain to retrieve the translated text.
 * @return string Translated text
 */
function pai_translate( $text, $domain = 'default' ) {
  $translations = &pai_get_translations_for_domain( $domain );
  return pai_apply_filters( 'gettext', $translations->translate( $text ), $text, $domain );
}

function pai_translate_with_gettext_context( $text, $context, $domain = 'default' ) {
  $translations = &pai_get_translations_for_domain( $domain );
  return pai_apply_filters( 'gettext_with_context', $translations->translate( $text, $context ), $text, $context, $domain );
}

/**
 * Retrieves the translation of $text. If there is no translation, or
 * the domain isn't loaded the original text is returned.
 *
 * @see translate() An alias of translate()
 * @since 2.1.0
 *
 * @param string $text Text to translate
 * @param string $domain Optional. Domain to retrieve the translated text
 * @return string Translated text
 */
if (!function_exists('__')) {
  function __( $text, $domain = 'default' ) {
    return pai_translate( $text, $domain );
  }
}



/**
 * Displays the returned translated text from translate().
 *
 * @see translate() Echoes returned translate() string
 * @since 2.0.0
 *
 * @param string $text Text to translate
 * @param string $domain Optional. Domain to retrieve the translated text
 */
if (!function_exists('_e')) {
  function _e( $text, $domain = 'default' ) {
    echo pai_translate( $text, $domain );
  }
}


/**
 * Retrieve translated string with gettext context
 *
 * Quite a few times, there will be collisions with similar translatable text
 * found in more than two places but with different translated context.
 *
 * By including the context in the pot file translators can translate the two
 * string differently.
 *
 * @since 2.8.0
 *
 * @param string $text Text to translate
 * @param string $context Context information for the translators
 * @param string $domain Optional. Domain to retrieve the translated text
 * @return string Translated context string without pipe
 */
if (!function_exists('_x')) {
  function _x( $text, $context, $domain = 'default' ) {
    return pai_translate_with_gettext_context( $text, $context, $domain );
  }
}

/**
 * Loads a MO file into the domain $domain.
 *
 * If the domain already exists, the translations will be merged. If both
 * sets have the same string, the translation from the original value will be taken.
 *
 * On success, the .mo file will be placed in the $l10n global by $domain
 * and will be a MO object.
 *
 * @since 2.0.0
 * @uses $l10n Gets list of domain translated string objects
 *
 * @param string $domain Unique identifier for retrieving translated strings
 * @param string $mofile Path to the .mo file
 * @return bool true on success, false on failure
 */
function pai_load_textdomain( $domain, $mofile ) {
  global $l10n;

  if ( !is_readable( $mofile ) ) return false;

  $mo = new MO();
  if ( !$mo->import_from_file( $mofile ) ) return false;

  if ( isset( $l10n[$domain] ) )
    $mo->merge_with( $l10n[$domain] );

  $l10n[$domain] = &$mo;

  return true;
}

/**
 * Unloads translations for a domain
 *
 * @since 3.0.0
 * @param string $domain Textdomain to be unloaded
 * @return bool Whether textdomain was unloaded
 */
function pai_unload_textdomain( $domain ) {
  global $l10n;

  $plugin_override = pai_apply_filters( 'override_unload_textdomain', false, $domain );

  if ( $plugin_override )
    return true;

  if ( isset( $l10n[$domain] ) ) {
    unset( $l10n[$domain] );
    return true;
  }

  return false;
}

/**
 * Loads default translated strings based on locale.
 *
 * @since 2.0.0
 */
function pai_load_default_textdomain() {
  $locale = pai_get_locale();

  $folder = @pai_conf('plugins', 'locale', 'folder');
  if (!$folder) { $folder = 'languages'; }

  pai_load_textdomain( 'default', PAI_FILEPATH_CONTENT . $folder . "/$locale.mo" );
}


/**
 * Returns the Translations instance for a domain. If there isn't one,
 * returns empty Translations instance.
 *
 * @param string $domain
 * @return object A Translation instance
 */
function &pai_get_translations_for_domain( $domain ) {
  global $l10n;
  if ( !isset( $l10n[$domain] ) ) {
    $l10n[$domain] = new NOOP_Translations;
  }
  return $l10n[$domain];
}

/**
 * Whether there are translations for the domain
 *
 * @since 3.0.0
 * @param string $domain
 * @return bool Whether there are translations
 */
function pai_is_textdomain_loaded( $domain ) {
  global $l10n;
  return isset( $l10n[$domain] );
}

function pai_translate_pageinfo($pageinfo) {
  $keys = @pai_conf('plugins', 'locale', 'pageinfo');
  if (!$keys) {
    $keys = array('title');
  }

  foreach($keys AS $key) {
    if (!$pageinfo->$key) { continue; }

    if (is_string($pageinfo->$key)) {
      $pageinfo->$key = pai_translate($pageinfo->$key);
    }
    else if (is_array($pageinfo[$key])) {
      $pageinfo->$key = array_map('pai_translate', $pageinfo->$key);
    }
    else if (is_object($pageinfo->$key)) {
      $pageinfo->$key = pai_translate_pageinfo($pageinfo->$key);
    }
  }

  return $pageinfo;
}

