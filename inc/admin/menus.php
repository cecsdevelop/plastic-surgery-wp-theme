<?php
// Lógica de menús y walkers
// Registrar ubicaciones de menús
add_action('after_setup_theme', function() {
    register_nav_menus([
        'primary' => __('Menú principal', 'intelindev'),
        'footer-copy-right' => __('Footer Copy Right', 'intelindev'),
        'footer-about' => __('Footer Acerca de', 'intelindev'),
        'footer-programs' => __('Footer Programas', 'intelindev'),
        'footer-account' => __('Footer Cuenta', 'intelindev'),
    ]);
});

// Keep primary menu DOM stable across pages by removing dynamic "current/page" classes.
add_filter('nav_menu_css_class', function($classes, $item, $args, $depth) {
    if (empty($args->theme_location) || $args->theme_location !== 'primary') {
        return $classes;
    }

    if (!is_array($classes) || empty($classes)) {
        return $classes;
    }

    $filtered = [];
    foreach ($classes as $class_name) {
        $class_name = (string) $class_name;
        if ($class_name === '') {
            continue;
        }

        if (
            strpos($class_name, 'current-') === 0 ||
            strpos($class_name, 'current_') === 0 ||
            strpos($class_name, 'page_item') === 0 ||
            strpos($class_name, 'page-item-') === 0
        ) {
            continue;
        }

        $filtered[] = $class_name;
    }

    return array_values(array_unique($filtered));
}, 100, 4);

add_filter('nav_menu_css_class', function($classes, $item, $args, $depth) {
    if (empty($args->theme_location) || $args->theme_location !== 'primary') {
        return $classes;
    }

    if (empty($item->url) || !is_string($item->url)) {
        return $classes;
    }

    $current_lang = function_exists('idml_get_current_language') ? idml_get_current_language() : 'es';
    $current_url = function_exists('id_get_translated_current_url')
        ? id_get_translated_current_url($current_lang)
        : home_url((string) ($_SERVER['REQUEST_URI'] ?? '/'));

    $item_url = function_exists('idml_normalize_menu_url')
        ? idml_normalize_menu_url($item->url)
        : untrailingslashit((string) $item->url);

    $current_url = function_exists('idml_normalize_menu_url')
        ? idml_normalize_menu_url($current_url)
        : untrailingslashit((string) $current_url);

    if ($current_url === '' || $item_url === '') {
        return $classes;
    }

    if ($current_url === $item_url) {
        $classes[] = 'current_page_item';
        $classes[] = 'current-menu-item';
    }

    return array_values(array_unique($classes));
}, 110, 4);

// Also remove aria-current in primary nav to avoid browser/theme current-state deltas.
add_filter('nav_menu_link_attributes', function($atts, $item, $args, $depth) {
    if (empty($args->theme_location) || $args->theme_location !== 'primary') {
        return $atts;
    }

    if (is_array($atts) && isset($atts['aria-current'])) {
        unset($atts['aria-current']);
    }

    return $atts;
}, 100, 4);

// Filtro para traducir dinámicamente los textos y URLs de los ítems del menú principal
add_filter('wp_nav_menu_objects', function($items, $args) {
    if (empty($args->theme_location) || $args->theme_location !== 'primary') {
        return $items;
    }
    $current_lang = function_exists('idml_get_current_language') ? idml_get_current_language() : 'es';
    foreach ($items as $item) {
        // Ítems que apuntan a un Post/Page real ya traen título y URL correctos
        // desde los filtros 'the_title'/'post_link'/'page_link' (basados en la
        // traducción por post, no en el JSON de slugs de menú). Sobrescribirlos
        // acá los rompe: sin header-menu-slugs.json (se eliminó al pasar el
        // rewrite a idml_resolve_translated_slug()) toda key cae al fallback de
        // id_get_menu_url(), que devuelve el home del idioma en vez del post.
        if ($item->type === 'post_type' && in_array($item->object, ['post', 'page'], true)) {
            continue;
        }

        $slug = sanitize_title($item->title);
        $item->title = function_exists('idml_t') ? idml_t('menu.' . $slug, $current_lang) : $item->title;

        // Nuevo: intentar obtener la URL multilenguaje desde el JSON si existe clave
        $slug_key = 'menu-slug.' . $slug;
        if (function_exists('id_get_menu_url')) {
            $url = id_get_menu_url($slug_key, $current_lang);
            if ($url) {
                $item->url = $url;
                continue;
            }
        }

        // Fallback robusto: normaliza URLs internas aunque traigan subcarpeta legacy.
        $relative = idml_extract_relative_menu_path($item->url);
        if ($relative !== '') {
            $parts = explode('/', trim($relative, '/'));
            if (count($parts) > 0) {
                $base_slug = $parts[0];
                $translated_slug = function_exists('idml_t') ? idml_t('menu-slug.' . $base_slug, $current_lang) : $base_slug;
                if ($translated_slug && $translated_slug !== 'menu-slug.' . $base_slug) {
                    $parts[0] = $translated_slug;
                }
                $relative = implode('/', $parts);
            }
            $item->url = idml_get_language_home_url($current_lang) . ltrim($relative, '/');
        }
    }
    return $items;
}, 20, 2);

/**
 * Convierte una URL de menú interna a ruta relativa del sitio actual.
 * Soporta cambios de carpeta base del proyecto (ej: /intelindev -> /wordpress).
 */
if (!function_exists('idml_extract_relative_menu_path')) {
    function idml_extract_relative_menu_path($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, '#') === 0) {
            return $url;
        }

        $home = wp_parse_url(home_url('/'));
        $home_host = isset($home['host']) ? strtolower((string) $home['host']) : '';
        $home_path = trim((string) ($home['path'] ?? ''), '/');

        $parsed = wp_parse_url($url);
        if ($parsed === false) {
            return '';
        }

        $url_host = isset($parsed['host']) ? strtolower((string) $parsed['host']) : '';
        if ($url_host !== '' && $home_host !== '' && $url_host !== $home_host) {
            return '';
        }

        $path = trim((string) ($parsed['path'] ?? ''), '/');
        if ($path === '') {
            return '/';
        }

        if ($home_path !== '') {
            if ($path === $home_path) {
                $path = '';
            } elseif (strpos($path, $home_path . '/') === 0) {
                $path = (string) substr($path, strlen($home_path) + 1);
            } else {
                $path_segments = explode('/', $path);
                $home_segments = explode('/', $home_path);
                if (count($path_segments) >= 2 && count($home_segments) === 1 && $path_segments[0] !== $home_segments[0]) {
                    $path = implode('/', array_slice($path_segments, 1));
                }
            }
        }

        $relative = '/' . ltrim($path, '/');
        if (!empty($parsed['query'])) {
            $relative .= '?' . (string) $parsed['query'];
        }
        if (!empty($parsed['fragment'])) {
            $relative .= '#' . (string) $parsed['fragment'];
        }

        return $relative;
    }
}

if (!function_exists('idml_normalize_menu_url')) {
    function idml_normalize_menu_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $url = preg_replace('/[?#].*$/', '', $url);
        return untrailingslashit($url);
    }
}

// Filtro para traducir dinámicamente los textos de los ítems de los menús del footer
add_filter('wp_nav_menu_objects', function($items, $args) {
    $footer_locations = ['footer-about', 'footer-programs', 'footer-account', 'footer-copy-right'];
    if (empty($args->theme_location) || !in_array($args->theme_location, $footer_locations, true)) {
        return $items;
    }
    $col_map = [
        'footer-about' => 'about',
        'footer-programs' => 'programs',
        'footer-account' => 'account',
        'footer-copy-right' => 'copy-right',
    ];
    $col = $col_map[$args->theme_location] ?? '';
    $current_lang = function_exists('idml_get_current_language') ? idml_get_current_language() : 'es';
    foreach ($items as $item) {
        // Mismo motivo que en el filtro del menú 'primary' de arriba: no pisar
        // ítems de Post/Page real, que ya vienen traducidos correctamente.
        if ($item->type === 'post_type' && in_array($item->object, ['post', 'page'], true)) {
            continue;
        }

        $slug = sanitize_title($item->title);
        $key = "footer.$col.$slug";
        // Traducir título
        if (function_exists('idml_t')) {
            $translated = idml_t($key, $current_lang);
            if ($translated && $translated !== $key) {
                $item->title = $translated;
            }
        }

        // Traducir URL usando el mismo sistema de slugs que el menú principal
        $slug_key = 'menu-slug.' . $slug;
        if (function_exists('id_get_menu_slugs_json')) {
            $all_slugs = id_get_menu_slugs_json();
            if (isset($all_slugs[$slug_key][$current_lang]) && $all_slugs[$slug_key][$current_lang] !== '') {
                $url = function_exists('id_get_menu_url') ? id_get_menu_url($slug_key, $current_lang) : '';
                if ($url) {
                    $item->url = $url;
                    continue;
                }
            }
        }

        // Fallback robusto: normaliza URLs internas aunque traigan subcarpeta legacy.
        if (function_exists('idml_extract_relative_menu_path')) {
            $relative = idml_extract_relative_menu_path($item->url);
            if ($relative !== '') {
                $parts = explode('/', trim($relative, '/'));
                if (count($parts) > 0) {
                    $base_slug = $parts[0];
                    $translated_slug = function_exists('idml_t') ? idml_t('menu-slug.' . $base_slug, $current_lang) : $base_slug;
                    if ($translated_slug && $translated_slug !== 'menu-slug.' . $base_slug) {
                        $parts[0] = $translated_slug;
                    }
                    $relative = implode('/', $parts);
                }
                $item->url = idml_get_language_home_url($current_lang) . ltrim($relative, '/');
            }
        }
    }
    return $items;
}, 21, 2);

// --- FUNCIONES DE SLUGS Y URLS MULTILENGUAJE ---
/**
 * Devuelve la URL base según el ambiente actual.
 */
if (!function_exists('id_get_base_url')) {
    function id_get_base_url() {
        return trailingslashit(home_url('/'));
    }
}

/**
 * Carga los slugs multilenguaje desde el JSON.
 * @return array
 */
if (!function_exists('id_get_menu_slugs_json')) {
    function id_get_menu_slugs_json() {
        static $slugs = null;
        if ($slugs === null) {
            $base_dir  = get_template_directory() . '/languages/modules/';
            $json_files = [
                $base_dir . 'header-menu-slugs.json',
                $base_dir . 'footer-menu-slugs.json',
            ];
            $merged = [];
            foreach ($json_files as $json_path) {
                if (file_exists($json_path)) {
                    $decoded = json_decode((string) file_get_contents($json_path), true);
                    if (is_array($decoded)) {
                        $merged = array_merge($merged, $decoded);
                    }
                }
            }
            $slugs = $merged;
        }
        return $slugs;
    }
}

/**
 * Devuelve la URL completa del menú según slug e idioma.
 * @param string $slug_key Ej: 'menu-slug.programas'
 * @param string $lang Ej: 'es', 'en', 'pt'
 * @return string
 */
if (!function_exists('id_get_menu_url')) {
    function id_get_menu_url($slug_key, $lang = 'es') {
        $slugs = id_get_menu_slugs_json();
        $slug = isset($slugs[$slug_key][$lang]) ? $slugs[$slug_key][$lang] : '';
        $base = function_exists('idml_get_language_home_url') ? idml_get_language_home_url($lang) : id_get_base_url();
        if (!$slug) return $base;
        // Si el slug empieza con #, es anchor de la home
        if (strpos($slug, '#') === 0) {
            return $base . $slug;
        } else {
            return $base . ltrim($slug, '/');
        }
    }
}

/**
 * Devuelve la URL actual traducida al idioma objetivo usando el mapa de slugs.
 * Si no encuentra coincidencia, cae al home del idioma.
 */
if (!function_exists('id_get_translated_current_url')) {
    function id_get_translated_current_url($target_lang = 'es') {
        $target_lang = function_exists('idml_normalize_lang') ? idml_normalize_lang($target_lang) : sanitize_key((string) $target_lang);
        if ($target_lang === '') {
            $target_lang = 'es';
        }

        $default_lang = function_exists('idml_get_default_language') ? idml_get_default_language() : 'es';
        $fallback = function_exists('idml_get_language_home_url') ? idml_get_language_home_url($target_lang) : id_get_base_url();
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($request_uri === '') {
            return $fallback;
        }

        $request_path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
        $request_query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);
        $home_path = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

        if ($home_path !== '') {
            if ($request_path === $home_path) {
                $request_path = '';
            } elseif (strpos($request_path, $home_path . '/') === 0) {
                $request_path = (string) substr($request_path, strlen($home_path) + 1);
            }
        }

        $supported_langs = function_exists('idml_get_supported_languages') ? idml_get_supported_languages() : (function_exists('idml_get_languages') ? idml_get_languages() : ['es']);
        $segments = $request_path !== '' ? explode('/', trim($request_path, '/')) : [];
        if (empty($segments)) {
            return $fallback;
        }

        if (in_array($segments[0], $supported_langs, true)) {
            array_shift($segments);
        }

        if (empty($segments)) {
            return $fallback;
        }

        // Regular posts (CPT 'post') AND top-level Pages stay on the same content
        // when switching language, using its translated slug for the target
        // language if one was set (falls back to the native/es slug otherwise)
        // instead of going to home.
        if (is_singular(['post', 'page'])) {
            $current_post = get_queried_object();
            if ($current_post instanceof WP_Post) {
                $post_slug = function_exists('intelindev_get_post_slug_for_lang')
                    ? intelindev_get_post_slug_for_lang($current_post, $target_lang)
                    : sanitize_title($current_post->post_name);
                if ($post_slug !== '') {
                    $prefix = ($target_lang === $default_lang) ? '' : '/' . rawurlencode($target_lang);
                    $url = home_url($prefix . '/' . rawurlencode($post_slug) . '/');
                    if ($request_query !== '') {
                        $url .= '?' . $request_query;
                    }
                    return $url;
                }
            }
        }

        $current_slug = $segments[0];
        $suffix = array_slice($segments, 1);
        $slugs = id_get_menu_slugs_json();

        foreach ($slugs as $slug_key => $translations) {
            if (strpos((string) $slug_key, 'menu-slug.') !== 0 || !is_array($translations)) {
                continue;
            }

            $matched = false;
            foreach ($translations as $slug_lang => $translated_slug) {
                if (trim((string) $translated_slug) === $current_slug) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                continue;
            }

            $target_slug = isset($translations[$target_lang]) ? trim((string) $translations[$target_lang]) : '';
            if ($target_slug === '') {
                return $fallback;
            }

            if (strpos($target_slug, '#') === 0) {
                return $fallback . $target_slug;
            }

            $translated_path = trim($target_slug, '/');
            if (!empty($suffix)) {
                $translated_path .= '/' . implode('/', array_map('rawurlencode', $suffix));
            }

            $url = function_exists('idml_get_language_home_url') ? idml_get_language_home_url($target_lang) : id_get_base_url();
            $url .= ltrim($translated_path, '/');

            if ($request_query !== '') {
                $url .= '?' . $request_query;
            }

            return $url;
        }

        return $fallback;
    }
}
// --- FIN FUNCIONES SLUGS ---
// --- WALKER PERSONALIZADO PARA FLECHA FÍSICA EN SUBMENÚ ---
if (!class_exists('IDML_Walker_Nav_Menu')) {
    class IDML_Walker_Nav_Menu extends Walker_Nav_Menu {
        function start_el(&$output, $item, $depth = 0, $args = [], $id = 0) {
            $classes = empty($item->classes) ? [] : (array) $item->classes;
            $has_children = in_array('menu-item-has-children', $classes, true);
            $class_names = join(' ', apply_filters('nav_menu_css_class', array_filter($classes), $item, $args, $depth));
            $class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';
            $output .= '<li' . $class_names . '>';
            $atts = [];
            $atts['title'] = !empty($item->attr_title) ? $item->attr_title : '';
            $atts['target'] = !empty($item->target) ? $item->target : '';
            $atts['rel'] = !empty($item->xfn) ? $item->xfn : '';
            $atts['href'] = !empty($item->url) ? $item->url : '';
            $atts = apply_filters('nav_menu_link_attributes', $atts, $item, $args, $depth);
            $attributes = '';
            foreach ($atts as $attr => $value) {
                if (!empty($value)) {
                    $value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
                    $attributes .= ' ' . $attr . '="' . $value . '"';
                }
            }
            $title = apply_filters('the_title', $item->title, $item->ID);
            $title = apply_filters('nav_menu_item_title', $title, $item, $args, $depth);
            $output .= '<a' . $attributes . '>' . $title . '</a>';
            // Insertar botón SVG solo si tiene hijos
            if ($has_children) {
                $output .= '<button class="idml-submenu-toggle" tabindex="0" aria-label="Abrir submenú" type="button">'
                    . '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>'
                    . '</button>';
            }
        }
        function end_el(&$output, $item, $depth = 0, $args = [], $id = 0) {
            $output .= '</li>';
        }
    }
}