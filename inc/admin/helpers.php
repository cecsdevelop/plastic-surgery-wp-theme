<?php
// Helpers para intelindev
if (!function_exists('intelindev_get_setting')) {
    function intelindev_get_setting($key, $default = null) {
        $opts = get_option('intelindev_settings', []);
        if (is_array($opts) && array_key_exists($key, $opts)) {
            return $opts[$key];
        }
        return $default;
    }
}
