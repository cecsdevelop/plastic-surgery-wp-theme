<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('idml_normalize_lang')) {
  function idml_normalize_lang($lang) {
    $lang = sanitize_key((string) $lang);

    if ($lang === '') {
      return '';
    }

    if (strpos($lang, '_') !== false) {
      $lang = sanitize_key(substr($lang, 0, 2));
    }

    return $lang;
  }
}

if (!function_exists('idml_get_languages')) {
  function idml_get_languages() {
    $stored = get_option('idml_languages', ['es', 'en']);
    // Si es string separado por comas, convertir a array
    if (is_string($stored)) {
      $stored = array_map('trim', explode(',', $stored));
    }
    if (!is_array($stored)) {
      $stored = ['es', 'en'];
    }
    $langs = array_values(array_unique(array_filter(array_map('idml_normalize_lang', $stored))));
    if (empty($langs)) {
      $langs = ['es', 'en'];
    }

    // Keep baseline bilingual support for admin/runtime consumers.
    if (!in_array('es', $langs, true)) {
      $langs[] = 'es';
    }
    if (!in_array('en', $langs, true)) {
      $langs[] = 'en';
    }

    return array_values(array_unique($langs));
  }
}

if (!function_exists('idml_get_default_language')) {
  function idml_get_default_language() {
    // Sin valor guardado el defecto es el PRIMER idioma declarado, no 'es'.
    // Con 'es' como defecto de get_option, la comprobación de abajo lo aceptaba
    // (está entre los idiomas) y el return $languages[0] quedaba inalcanzable:
    // declarar "en, es" seguía dando 'es' como base.
    $default = idml_normalize_lang(get_option('idml_default_language', ''));
    $languages = idml_get_languages();

    if ($default === '' || !in_array($default, $languages, true)) {
      return $languages[0];
    }

    return $default;
  }
}

if (!function_exists('idml_get_current_language')) {
  function idml_get_current_language() {
    $languages = idml_get_languages();

    // Override explícito (ej. endpoints REST que reciben ?lang=): la URL de
    // /wp-json/ no lleva prefijo de idioma.
    if (!empty($GLOBALS['idml_language_override']) && in_array($GLOBALS['idml_language_override'], $languages, true)) {
      return $GLOBALS['idml_language_override'];
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ($request_uri !== '') {
      $path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
      $home_path = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

      if ($home_path !== '') {
        if ($path === $home_path) {
          $path = '';
        } elseif (strpos($path, $home_path . '/') === 0) {
          $path = (string) substr($path, strlen($home_path) + 1);
        }
      }

      if ($path !== '') {
        $candidate = idml_normalize_lang(strtok($path, '/'));
        if ($candidate !== '' && in_array($candidate, $languages, true)) {
          return $candidate;
        }
      }
    }

    // Forzar el idioma por defecto si no es subdirectorio, ignorando la cookie que causa que se salte al último idioma
    /* 
    if (isset($_COOKIE['idml_lang'])) {
      $cookie_lang = idml_normalize_lang(wp_unslash($_COOKIE['idml_lang']));
      if ($cookie_lang !== '' && in_array($cookie_lang, $languages, true)) {
        return $cookie_lang;
      }
    }
    */

    return idml_get_default_language();
  }
}

if (!function_exists('idml_set_current_language')) {
  /** Fija el idioma del request actual (REST/AJAX). '' lo quita. */
  function idml_set_current_language($lang) {
    $lang = idml_normalize_lang($lang);
    $GLOBALS['idml_language_override'] = in_array($lang, idml_get_languages(), true) ? $lang : '';
  }
}

if (!function_exists('idml_get_language_home_url')) {
  function idml_get_language_home_url($lang) {
    $lang = idml_normalize_lang($lang);

    $default = idml_get_default_language();

    if ($lang === '' || $lang === $default) {
      return home_url('/');
    }

    return home_url('/' . rawurlencode($lang) . '/');
  }
}


if (!function_exists('idml_get_translations_option_name')) {
  /**
   * Option donde Apariencia → Traducciones guarda los strings de UI de un
   * namespace. Autoloaded: en frontend llega con alloptions, cero queries extra.
   */
  function idml_get_translations_option_name($namespace = 'ui') {
    $namespace = sanitize_key((string) $namespace);
    return 'idml_translations_' . ($namespace !== '' ? $namespace : 'ui');
  }
}

if (!function_exists('idml_get_dictionary_defaults')) {
  /**
   * Defaults que trae el theme: languages/{namespace}.json. Es el piso del
   * diccionario y solo lo toca el desarrollador al agregar claves nuevas a los
   * templates; el admin no lo edita ni lo necesita (ver admin-translations.php).
   */
  function idml_get_dictionary_defaults($namespace = 'ui') {
    static $cache = [];

    $namespace = sanitize_key((string) $namespace);
    if ($namespace === '') {
      $namespace = 'ui';
    }

    if (isset($cache[$namespace])) {
      return $cache[$namespace];
    }

    $path = get_template_directory() . '/languages/' . $namespace . '.json';
    if (!file_exists($path)) {
      $cache[$namespace] = [];
      return $cache[$namespace];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    $cache[$namespace] = is_array($decoded) ? $decoded : [];

    return $cache[$namespace];
  }
}

if (!function_exists('idml_get_dictionary_overrides')) {
  /**
   * Lo que el admin guardó desde Apariencia → Traducciones. Solo guarda valores
   * no vacíos, así que un campo vaciado en el dashboard vuelve al default.
   */
  function idml_get_dictionary_overrides($namespace = 'ui') {
    $stored = get_option(idml_get_translations_option_name($namespace), []);
    return is_array($stored) ? $stored : [];
  }
}

if (!function_exists('idml_get_dictionary')) {
  /**
   * Diccionario efectivo: defaults del JSON pisados, clave por clave e idioma
   * por idioma, por los overrides del dashboard.
   */
  function idml_get_dictionary($namespace = 'ui') {
    static $cache = [];

    $namespace = sanitize_key((string) $namespace);
    if ($namespace === '') {
      $namespace = 'ui';
    }

    if (isset($cache[$namespace])) {
      return $cache[$namespace];
    }

    $dictionary = idml_get_dictionary_defaults($namespace);

    foreach (idml_get_dictionary_overrides($namespace) as $key => $row) {
      if (!is_array($row)) {
        continue;
      }
      foreach ($row as $lang => $value) {
        if (is_string($value) && $value !== '') {
          $dictionary[$key][$lang] = $value;
        }
      }
    }

    $cache[$namespace] = $dictionary;

    return $cache[$namespace];
  }
}

if (!function_exists('idml_t')) {
  function idml_t($key, $lang = null, $namespace = 'ui') {
    $key = trim((string) $key);
    if ($key === '') {
      return '';
    }

    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    if ($lang === '') {
      $lang = idml_get_default_language();
    }

    $default_lang = idml_get_default_language();
    $dictionary = idml_get_dictionary($namespace);
    $entry = isset($dictionary[$key]) && is_array($dictionary[$key]) ? $dictionary[$key] : [];

    if (isset($entry[$lang]) && is_string($entry[$lang]) && $entry[$lang] !== '') {
      return $entry[$lang];
    }

    if (isset($entry[$default_lang]) && is_string($entry[$default_lang]) && $entry[$default_lang] !== '') {
      return $entry[$default_lang];
    }

    return $key;
  }
}

if (!function_exists('idml_t_vars')) {
  /**
   * idml_t() + reemplazo de placeholders {nombre} (ej. "© {year} {site}").
   * Los valores se insertan tal cual: escapar el resultado al imprimir.
   */
  function idml_t_vars($key, array $vars = [], $lang = null, $namespace = 'ui') {
    $text = idml_t($key, $lang, $namespace);
    if (!$vars) {
      return $text;
    }

    $map = [];
    foreach ($vars as $name => $value) {
      $map['{' . $name . '}'] = (string) $value;
    }

    return strtr($text, $map);
  }
}

if (!function_exists('idml_get_language_label')) {
  function idml_get_language_label($lang) {
    $lang = idml_normalize_lang($lang);
    if ($lang === '') {
      return '';
    }

    $label = idml_t('language.name.' . $lang);

    return $label !== 'language.name.' . $lang ? $label : strtoupper($lang);
  }
}