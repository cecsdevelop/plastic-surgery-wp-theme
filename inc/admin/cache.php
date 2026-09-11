<?php
if (!defined('ABSPATH')) exit;

/**
 * Simple theme-level cache helpers using transients
 * - Namespaced keys with 'intelindev_'
 * - Maintains an index of keys for flush operations
 */

if (!function_exists('intelindev_cache_key')) {
  function intelindev_cache_key(string $key): string {
    $prefix = 'intelindev_';
    // Include site + locale to avoid cross-site collisions on multisite
    $site   = is_multisite() ? get_current_blog_id() : 's';
    $lang   = function_exists('get_locale') ? get_locale() : 'en_US';
    return $prefix . md5($site . '|' . $lang . '|' . $key);
  }
}

if (!function_exists('intelindev_cache_get')) {
  function intelindev_cache_get(string $key) {
    $tkey = intelindev_cache_key($key);
    // Use site transients for multisite; fall back to transients
    if (is_multisite()) return get_site_transient($tkey);
    return get_transient($tkey);
  }
}

if (!function_exists('intelindev_cache_set')) {
  function intelindev_cache_set(string $key, $value, int $ttl = 3600): bool {
    $tkey = intelindev_cache_key($key);
    // Track key in index for flush
    $index = get_option('intelindev_cache_index', []);
    if (!is_array($index)) $index = [];
    $index[$tkey] = time();
    update_option('intelindev_cache_index', $index, false);

    if (is_multisite()) return set_site_transient($tkey, $value, $ttl);
    return set_transient($tkey, $value, $ttl);
  }
}

if (!function_exists('intelindev_cache_delete')) {
  function intelindev_cache_delete(string $key): void {
    $tkey = intelindev_cache_key($key);
    if (is_multisite()) delete_site_transient($tkey); else delete_transient($tkey);
    $index = get_option('intelindev_cache_index', []);
    if (isset($index[$tkey])) {
      unset($index[$tkey]);
      update_option('intelindev_cache_index', $index, false);
    }
  }
}

if (!function_exists('intelindev_cache_flush')) {
  function intelindev_cache_flush(): int {
    $index = get_option('intelindev_cache_index', []);
    if (!is_array($index) || empty($index)) return 0;
    $count = 0;
    foreach (array_keys($index) as $tkey) {
      if (is_multisite()) delete_site_transient($tkey); else delete_transient($tkey);
      $count++;
    }
    delete_option('intelindev_cache_index');
    return $count;
  }
}

if (!function_exists('intelindev_cache_remember')) {
  /**
   * Compute and cache value if missing
   * @param string   $key
   * @param int      $ttl
   * @param callable $callback returns string|array
   */
  function intelindev_cache_remember(string $key, int $ttl, callable $callback) {
    $cached = intelindev_cache_get($key);
    if ($cached !== false && $cached !== null) return $cached;
    $value = call_user_func($callback);
    intelindev_cache_set($key, $value, $ttl);
    return $value;
  }
}
