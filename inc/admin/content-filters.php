<?php
// Language switcher como último ítem del menú primario (submenú con los demás idiomas).
if (!defined('ABSPATH')) exit;

add_filter('wp_nav_menu_items', function($items, $args) {
    if (is_admin() || empty($args->theme_location) || $args->theme_location !== 'primary') {
        return $items;
    }
    if (!function_exists('idml_get_languages') || !function_exists('idml_get_current_language') || !function_exists('idml_get_language_label')) {
        return $items;
    }

    $langs = idml_get_languages();
    $current_lang = idml_get_current_language();

    $submenu_items = '';
    foreach ($langs as $lang) {
        if ($lang === $current_lang) continue;
        $url = function_exists('id_get_translated_current_url')
            ? id_get_translated_current_url($lang)
            : idml_get_language_home_url($lang);
        $submenu_items .= sprintf(
            '<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="%s" hreflang="%s">%s</a></li>',
            esc_url($url),
            esc_attr($lang),
            esc_html(idml_get_language_label($lang))
        );
    }

    $switcher_html = '<li id="menu-item-lang-switcher" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children idml-lang-switcher">';
    $switcher_html .= '<a href="#" aria-haspopup="true" aria-expanded="false">' . esc_html(idml_get_language_label($current_lang)) . '</a>';
    if ($submenu_items !== '') {
        $switcher_html .= '<button class="idml-submenu-toggle" tabindex="0" aria-label="' . esc_attr(idml_t('language.switcher_label')) . '" type="button">'
            . '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>'
            . '</button>';
        $switcher_html .= '<ul class="sub-menu">' . $submenu_items . '</ul>';
    }
    $switcher_html .= '</li>';

    return $items . $switcher_html;
}, 10, 2);
