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

if (!function_exists('idml_get_supported_languages')) {
  function idml_get_supported_languages() {
    static $cache = null;

    if (is_array($cache)) {
      return $cache;
    }

    $langs = idml_get_languages();
    $json_files = glob(get_template_directory() . '/languages/modules/*.json');
    if (is_array($json_files)) {
      foreach ($json_files as $json_file) {
        if (!is_readable($json_file)) {
          continue;
        }

        $decoded = json_decode((string) file_get_contents($json_file), true);
        if (!is_array($decoded)) {
          continue;
        }

        $stack = [$decoded];
        while ($stack) {
          $node = array_pop($stack);
          if (!is_array($node)) {
            continue;
          }

          $keys = array_keys($node);
          $is_lang_map = !empty($keys) && count(array_filter($keys, function($key) {
            return is_string($key) && preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/i', $key);
          })) === count($keys);

          if ($is_lang_map) {
            foreach ($keys as $key) {
              $langs[] = idml_normalize_lang($key);
            }
            continue;
          }

          foreach ($node as $child) {
            if (is_array($child)) {
              $stack[] = $child;
            }
          }
        }
      }
    }

    $cache = array_values(array_unique(array_filter($langs)));
    return $cache;
  }
}

if (!function_exists('idml_get_default_language')) {
  function idml_get_default_language() {
    $default = idml_normalize_lang(get_option('idml_default_language', 'es'));
    $languages = idml_get_languages();

    if ($default === '' || !in_array($default, $languages, true)) {
      return $languages[0];
    }

    return $default;
  }
}

if (!function_exists('idml_get_current_language')) {
  function idml_get_current_language() {
    $languages = idml_get_supported_languages();

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


if (!function_exists('idml_get_dictionary')) {
  function idml_get_dictionary($namespace = 'ui') {
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

    if (isset($entry[$lang]) && is_string($entry[$lang])) {
      return $entry[$lang];
    }

    if (isset($entry[$default_lang]) && is_string($entry[$default_lang])) {
      return $entry[$default_lang];
    }

    return $key;
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