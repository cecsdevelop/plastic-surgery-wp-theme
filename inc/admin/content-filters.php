<?php
// Filtros de salida del contenido: enlaces relativos a la raíz y language switcher.
if (!defined('ABSPATH')) exit;

/**
 * Enlaces relativos a la raíz en contenido editado desde el dashboard
 * (href="/contacto/", src="/wp-content/…", action="/…"): se escriben siempre
 * respecto a la raíz del sitio y, si WordPress vive en un subdirectorio (local:
 * /Intelindev/), se les antepone esa ruta al imprimir. En producción (raíz) no
 * cambia nada. Así el mismo contenido funciona en local, staging y producción
 * sin search-replace ni rutas locales que luego dan 404 (el origen del bug de
 * los enlaces /Intelindev/… en staging). No toca URLs absolutas,
 * protocol-relative (//) ni las que ya llevan el subdirectorio (idempotente).
 */
if (!function_exists('intelindev_resolve_root_relative_urls')) {
    function intelindev_resolve_root_relative_urls($html) {
        if (!is_string($html) || $html === '' || (strpos($html, '="/') === false && strpos($html, "='/") === false)) {
            return $html;
        }
        $base = rtrim((string) parse_url(home_url('/'), PHP_URL_PATH), '/'); // '' en raíz, '/Intelindev' en subdirectorio
        if ($base === '') {
            return $html;
        }
        $already = preg_quote(ltrim($base, '/') . '/', '#');

        return (string) preg_replace_callback(
            '#\b(href|src|action)=(["\'])/(?!/)(?!' . $already . ')#i',
            fn($m) => $m[1] . '=' . $m[2] . $base . '/',
            $html
        );
    }
}
// Después de do_shortcode (11) y wp_filter_content_tags (12) para cubrir el HTML de los componentes.
add_filter('the_content', 'intelindev_resolve_root_relative_urls', 13);
add_filter('widget_block_content', 'intelindev_resolve_root_relative_urls', 13);
add_filter('widget_text_content', 'intelindev_resolve_root_relative_urls', 13);

// Language switcher como último ítem del menú primario (submenú con los demás idiomas).

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
