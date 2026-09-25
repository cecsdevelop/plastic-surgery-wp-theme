<?php
/**
 * Menús: traducción de ítems custom, URL con prefijo de idioma, clase
 * current-menu-item estable y walker con toggle de submenú.
 *
 * Las locations se registran en setup.php ('primary', 'footer'). Los ítems
 * que apuntan a un Post/Page real ya llegan traducidos (título vía
 * 'the_title', URL vía 'post_link'/'page_link' — ver
 * admin-post-translation-settings.php); acá se tocan los ítems custom (links
 * externos, anchors, URLs internas cargadas a mano) y los de archivo de CPT
 * (post_type_archive: URL /{lang}/{base}/ y nombre plural del idioma). Los de
 * taxonomía (categorías) no se tocan todavía: el módulo de categorías aún no
 * traduce nombre/URL en frontend.
 *
 * @package pswpt
 */

if (!defined('ABSPATH')) exit;

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

    // En un single, el ítem del archivo de su tipo (Servicios, Portafolio) o la
    // página del blog (posts) quedan como "padre": el header les pinta el punto.
    if (is_singular()) {
        $queried = get_queried_object();
        $type    = $queried instanceof WP_Post ? $queried->post_type : '';
        $is_parent = ($item->type === 'post_type_archive' && $item->object === $type)
            || ($type === 'post' && $item->type === 'post_type' && $item->object === 'page' && (int) $item->object_id === (int) get_option('page_for_posts'));
        if ($is_parent) {
            $classes[] = 'current-menu-parent';
            $classes[] = 'current-menu-ancestor';
        }
    }

    // Fuera de singular/portada/archivo de CPT/blog no hay ítem "actual": la URL
    // traducida cae al home y marcaría el ítem de inicio en búsquedas y 404.
    if (!is_singular() && !is_front_page() && !is_post_type_archive() && !is_home()) {
        return array_values(array_unique($classes));
    }

    $current_url = idml_normalize_menu_url(id_get_translated_current_url(idml_get_current_language()));
    $item_url    = idml_normalize_menu_url($item->url);

    if ($current_url === '' || $item_url === '') {
        return array_values(array_unique($classes));
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

/**
 * Ítems custom de cualquier menú (primary, footer y los que vengan):
 *  - Título: si en Apariencia → Traducciones existe la clave
 *    "menu.{slug-del-título}" (ítem "Blog" → clave menu.blog) se usa; si no,
 *    queda el título tal cual (antes quedaba la clave literal en pantalla).
 *  - URL interna: se le antepone el prefijo del idioma actual (/en/…) para no
 *    sacar al visitante de su idioma; "#anchor" pasa a ser anchor de la home
 *    del idioma. Las URLs externas no se tocan.
 */
add_filter('wp_nav_menu_objects', function($items, $args) {
    if (is_admin() || empty($args->theme_location)) {
        return $items;
    }

    $current_lang = idml_get_current_language();

    foreach ($items as $item) {
        // Archivo de un CPT (ítem "Servicios"/"Portafolio" de tipo post_type_archive):
        // URL /{lang}/{base}/ y nombre plural del idioma, salvo título personalizado.
        if ($item->type === 'post_type_archive' && function_exists('pswpt_get_post_type_archive_url')) {
            $url = pswpt_get_post_type_archive_url((string) $item->object, $current_lang);
            if ($url !== '') {
                $item->url = $url;
            }
            $labels  = apply_filters('pswpt_post_type_lang_labels', []);
            $default = (string) ($labels[$item->object][idml_get_default_language()] ?? '');
            $label   = (string) ($labels[$item->object][$current_lang] ?? '');
            if ($label !== '' && ($item->post_title === '' || $item->post_title === $default)) {
                $item->title = $label;
            }
            continue;
        }

        if ($item->type !== 'custom') {
            continue;
        }

        $key = 'menu.' . sanitize_title($item->title);
        $translated = idml_t($key, $current_lang);
        if ($translated !== '' && $translated !== $key) {
            $item->title = $translated;
        }

        $relative = idml_extract_relative_menu_path($item->url);
        if ($relative !== '') {
            $item->url = idml_get_language_home_url($current_lang) . ltrim($relative, '/');
        }
    }

    return $items;
}, 20, 2);

/**
 * Convierte una URL de menú interna a ruta relativa del sitio actual (sin la
 * subcarpeta de home_url, si la hay). Devuelve '' para URLs externas.
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
            }
        }

        // Barra final según la estructura de permalinks: sin ella WP responde
        // con un 301 a la versión con barra en cada clic del menú.
        $relative = '/' . ltrim($path, '/');
        if ($path !== '' && pathinfo($path, PATHINFO_EXTENSION) === '') {
            $relative = user_trailingslashit($relative);
        }
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

/**
 * URL de la página actual en el idioma objetivo (para el switcher y para
 * detectar el ítem de menú activo). En un contenido traducible (post, page,
 * CPT) usa su permalink en ese idioma; en el archivo de un CPT, su base
 * traducida; en cualquier otra vista (búsqueda, 404) cae al home del idioma.
 */
if (!function_exists('id_get_translated_current_url')) {
    function id_get_translated_current_url($target_lang = 'es') {
        $target_lang = idml_normalize_lang($target_lang);
        if ($target_lang === '') {
            $target_lang = idml_get_default_language();
        }

        $fallback = idml_get_language_home_url($target_lang);

        // Archivo de un CPT público: /{lang}/{base-en-ese-idioma}/.
        if (is_post_type_archive() && function_exists('pswpt_get_post_type_archive_url')) {
            $type = get_query_var('post_type');
            $type = is_array($type) ? (string) reset($type) : (string) $type;
            $url  = $type !== '' ? pswpt_get_post_type_archive_url($type, $target_lang) : '';

            return $url !== '' ? $url : $fallback;
        }

        $translatable = function_exists('pswpt_translatable_post_types') ? pswpt_translatable_post_types() : ['post', 'page'];
        $posts_page   = (int) get_option('page_for_posts');

        if (is_home() && !is_front_page() && $posts_page > 0) {
            $current_post = get_post($posts_page); // página del blog: se traduce como cualquier page
        } elseif (is_singular($translatable)) {
            $current_post = get_queried_object();
        } else {
            return $fallback;
        }

        if (!($current_post instanceof WP_Post)) {
            return $fallback;
        }

        // La portada se sirve en /{lang}/ (regla idml_lang_home), no por slug.
        if ('page' === get_option('show_on_front') && (int) get_option('page_on_front') === (int) $current_post->ID) {
            return $fallback;
        }

        // Los filtros post_link / page_link / post_type_link ya arman la URL del
        // idioma activo (slug traducido, base del CPT): se conmuta el idioma un
        // instante en vez de duplicar esa lógica acá.
        $previous_override = $GLOBALS['idml_language_override'] ?? '';
        idml_set_current_language($target_lang);
        $url = (string) get_permalink($current_post);
        $GLOBALS['idml_language_override'] = $previous_override;

        if ($url === '') {
            return $fallback;
        }

        $request_query = isset($_SERVER['REQUEST_URI']) ? (string) wp_parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_QUERY) : '';
        if ($request_query !== '') {
            $url .= '?' . $request_query;
        }
        return $url;
    }
}

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
                $output .= '<button class="idml-submenu-toggle" tabindex="0" aria-label="' . esc_attr(idml_t('nav.submenu_toggle_label')) . '" type="button">'
                    . '<svg width="15" height="12" viewBox="0 0 24 24" fill="none" stroke="#302d26" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>'
                    . '</button>';
            }
        }
        function end_el(&$output, $item, $depth = 0, $args = [], $id = 0) {
            $output .= '</li>';
        }
    }
}
