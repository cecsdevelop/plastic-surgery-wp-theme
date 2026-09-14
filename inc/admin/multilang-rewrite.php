<?php
// Multilanguage rewrite rules and template_redirect modularizado desde functions.php

if (!function_exists('idml_get_request_path_segments')) {
    function idml_get_request_path_segments(): array {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($request_uri === '') {
            return [];
        }

        $request_path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
        $home_path = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

        if ($home_path !== '') {
            if ($request_path === $home_path) {
                $request_path = '';
            } elseif (strpos($request_path, $home_path . '/') === 0) {
                $request_path = (string) substr($request_path, strlen($home_path) + 1);
            }
        }

        $request_path = trim((string) $request_path, '/');
        if ($request_path === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $request_path), static function($segment) {
            return trim((string) $segment) !== '';
        }));
    }
}

if (!function_exists('idml_pretty_title_from_slug')) {
    function idml_pretty_title_from_slug(string $slug): string {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return '';
        }

        $words = str_replace('-', ' ', $slug);
        return ucwords($words);
    }
}

if (!function_exists('idml_get_browser_title_overrides')) {
    function idml_get_browser_title_overrides(): array {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $path = get_template_directory() . '/languages/modules/page-browser-titles.json';
        if (!file_exists($path)) {
            $cache = [];
            return $cache;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $cache = is_array($decoded) ? $decoded : [];

        return $cache;
    }
}

if (!function_exists('idml_get_browser_title_for_slug_key')) {
    function idml_get_browser_title_for_slug_key(string $slug_key, string $lang): string {
        $slug_key = trim($slug_key);
        $lang = sanitize_key($lang);
        if ($slug_key === '' || $lang === '') {
            return '';
        }

        $titles = idml_get_browser_title_overrides();
        if (!isset($titles[$slug_key]) || !is_array($titles[$slug_key])) {
            return '';
        }

        $entry = $titles[$slug_key];
        $default_lang = function_exists('idml_get_default_language') ? idml_get_default_language() : 'es';

        if (isset($entry[$lang]) && is_string($entry[$lang]) && trim($entry[$lang]) !== '') {
            return trim($entry[$lang]);
        }

        if (isset($entry[$default_lang]) && is_string($entry[$default_lang]) && trim($entry[$default_lang]) !== '') {
            return trim($entry[$default_lang]);
        }

        return '';
    }
}

if (!function_exists('idml_resolve_request_browser_title')) {
    function idml_resolve_request_browser_title(): string {
        $segments = idml_get_request_path_segments();
        if (count($segments) < 2) {
            return '';
        }

        $supported_langs = function_exists('idml_get_supported_languages') ? idml_get_supported_languages() : idml_get_languages();
        $default_lang = function_exists('idml_get_default_language') ? idml_get_default_language() : 'es';
        $lang = sanitize_key((string) $segments[0]);

        if ($lang === '' || !in_array($lang, $supported_langs, true) || $lang === $default_lang) {
            return '';
        }

        $translated_slug = sanitize_title((string) $segments[1]);
        if ($translated_slug === '') {
            return '';
        }

        $json_path = get_template_directory() . '/languages/modules/header-menu-slugs.json';
        if (!file_exists($json_path)) {
            return '';
        }

        $slugs = json_decode((string) file_get_contents($json_path), true);
        if (!is_array($slugs)) {
            return '';
        }

        $matched_slug_key = '';
        foreach ($slugs as $slug_key => $translations) {
            if (strpos((string) $slug_key, 'menu-slug.') !== 0 || !is_array($translations)) {
                continue;
            }

            $candidate = isset($translations[$lang]) ? sanitize_title((string) $translations[$lang]) : '';
            if ($candidate !== $translated_slug) {
                continue;
            }

            $matched_slug_key = (string) $slug_key;
            break;
        }

        if ($matched_slug_key === '') {
            return '';
        }

        $localized_title = idml_get_browser_title_for_slug_key($matched_slug_key, $lang);
        if ($localized_title === '') {
            $localized_title = idml_pretty_title_from_slug($translated_slug);
        }

        return trim((string) $localized_title);
    }
}

add_filter('document_title_parts', function($parts) {
    if (is_admin()) {
        return $parts;
    }

    if (!is_array($parts)) {
        return $parts;
    }

    $localized_title = idml_resolve_request_browser_title();
    if ($localized_title === '') {
        return $parts;
    }

    $parts['title'] = $localized_title;
    return $parts;
}, 20);

add_filter('pre_get_document_title', function($title) {
    if (is_admin()) {
        return $title;
    }

    $localized_title = idml_resolve_request_browser_title();
    if ($localized_title === '') {
        return $title;
    }

    $site_name = get_bloginfo('name');
    $separator = apply_filters('document_title_separator', '-');

    if ($site_name === '') {
        return $localized_title;
    }

    return trim($localized_title . ' ' . $separator . ' ' . $site_name);
}, 9999);

/**
 * Rank Math hookea pre_get_document_title en prioridad 15 con su propio título
 * (no pasa por get_the_title()/'the_title'), asi que para posts regulares hay
 * que sobreescribirlo despues, con prioridad mas alta, para que la pestaña del
 * navegador tambien muestre el título traducido.
 */
add_filter('pre_get_document_title', function($title) {
    if (is_admin() || !is_singular(['post', 'page'])) {
        return $title;
    }

    $lang = function_exists('idml_get_current_language') ? idml_get_current_language() : 'es';
    $default_lang = function_exists('idml_get_default_language') ? idml_get_default_language() : 'es';

    if ($lang === '' || $lang === $default_lang) {
        return $title;
    }

    $post = get_queried_object();
    if (!($post instanceof WP_Post)) {
        return $title;
    }

    $translated_title = '';
    if (function_exists('intelindev_get_post_translated_title')) {
        $translated_title = intelindev_get_post_translated_title($post, $lang);
    }

    if ($translated_title === '') {
        return $title;
    }

    $site_name = get_bloginfo('name');
    $separator = apply_filters('document_title_separator', '-');

    if ($site_name === '') {
        return $translated_title;
    }

    return trim($translated_title . ' ' . $separator . ' ' . $site_name);
}, 10000);

add_action('init', function() {
    $langs = function_exists('idml_get_supported_languages') ? idml_get_supported_languages() : idml_get_languages();
    foreach ($langs as $lang) {
        if ($lang === idml_get_default_language()) continue; // No para el idioma por defecto
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/?$',
            'index.php?idml_lang_home=' . $lang,
            'top'
        );
    }
    // NOTE: flush_rewrite_rules() en cada request destruye las reglas de WP.
    // Solo hacer flush una vez, desde wp-admin > Ajustes > Permalinks.
});


add_action('init', function() {
    $supported_langs = function_exists('idml_get_supported_languages') ? idml_get_supported_languages() : idml_get_languages();
    $default_lang = function_exists('idml_get_default_language') ? idml_get_default_language() : 'es';

    // Regular posts (CPT 'post') may have a translated slug (per-post meta) or keep the
    // native one. The 'request' filter below resolves idml_post_slug against the
    // translated-slug meta first, falling back to the native post_name.
    // Must be 'top' (WP's own generated rules include a generic 2-segment attachment
    // rule that would otherwise swallow this pattern first) but registered BEFORE the
    // page-slugs block below, so those more specific literal-slug rules end up in front
    // of this generic one and keep taking precedence for real translated pages.
    foreach ((array) $supported_langs as $lang) {
        $lang = sanitize_key((string) $lang);
        if ($lang === '' || $lang === $default_lang) {
            continue;
        }

        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/([^/]+)/?$',
            'index.php?idml_post_slug=$matches[1]&idml_post_lang=' . $lang,
            'top'
        );
    }
});

add_filter('query_vars', function($vars) {
    $vars[] = 'idml_post_slug';
    $vars[] = 'idml_post_lang';
    return $vars;
});

/**
 * Resuelve un slug con prefijo de idioma contra 'post' o 'page': primero contra
 * el slug traducido (meta por idioma), si no contra el slug nativo. Compartida
 * entre el filtro 'request' de abajo (camino normal) y el fallback de
 * template_redirect mas abajo (red de seguridad para cuando las reglas de
 * rewrite dinámicas todavía no se flushearon — ver el NOTE de este archivo).
 *
 * Solo páginas de primer nivel: get_page_by_path() con un slug de un solo
 * segmento matchea por post_name sin importar jerarquía, asi que una página
 * hija es alcanzable en /{lang}/{slug-hijo}/ salteando a su padre. Es el mismo
 * comportamiento que ya tenía el fallback de 'post' (no hay jerarquía ahí), se
 * documenta acá porque con 'page' sí puede sorprender. Páginas anidadas no
 * están cubiertas por este mecanismo.
 *
 * @return array{id:int,post_type:string}|array{} vacío si no resuelve a nada publicado.
 */
if (!function_exists('idml_resolve_translated_slug')) {
    function idml_resolve_translated_slug(string $slug, string $lang): array {
        $slug = sanitize_title($slug);
        $lang = sanitize_key($lang);
        if ($slug === '' || $lang === '') {
            return [];
        }

        $resolved_id = 0;
        if (function_exists('intelindev_get_post_id_by_translated_slug')) {
            $resolved_id = intelindev_get_post_id_by_translated_slug($slug, $lang);
        }

        if (!$resolved_id) {
            $native = get_page_by_path($slug, OBJECT, ['post', 'page']);
            if ($native instanceof WP_Post && $native->post_status === 'publish') {
                $resolved_id = (int) $native->ID;
            }
        }

        if (!$resolved_id) {
            return [];
        }

        $post_type = get_post_type($resolved_id);
        if (!in_array($post_type, ['post', 'page'], true)) {
            return [];
        }

        return ['id' => $resolved_id, 'post_type' => $post_type];
    }
}

add_filter('request', function($query_vars) {
    if (empty($query_vars['idml_post_slug'])) {
        return $query_vars;
    }

    $slug = sanitize_title((string) $query_vars['idml_post_slug']);
    $lang = isset($query_vars['idml_post_lang']) ? sanitize_key((string) $query_vars['idml_post_lang']) : '';

    // An empty/unmatched main query resolves to the blog home in WordPress instead of
    // a 404. Fall back to a plain 'name' lookup (post_type=post) on failure so a
    // genuinely missing post correctly 404s instead of silently rendering the home page.
    $not_found_query = ['name' => $slug, 'post_type' => 'post'];

    if ($slug === '') {
        return $not_found_query;
    }

    $resolved = idml_resolve_translated_slug($slug, $lang);
    if (empty($resolved)) {
        return $not_found_query;
    }

    if ($resolved['post_type'] === 'page') {
        // 'page_id', NO 'p': WP_Query::parse_query() es un elseif — con 'p' arma
        // is_single()/is_singular('post') en vez de is_page(), y la plantilla de
        // página nunca se activa, sin ningún error visible. 'page_id' es la unica
        // query var que efectivamente resuelve a is_page() = true.
        return ['page_id' => $resolved['id'], 'post_type' => 'page'];
    }

    return ['p' => $resolved['id'], 'post_type' => 'post'];
});

add_filter('query_vars', function($vars) {
    $vars[] = 'idml_lang_home';
    return $vars;
});

/**
 * '^{lang}/?$' (arriba) solo aterriza en 'idml_lang_home=en' — no es pagename/p/page_id,
 * asi que la query principal de WP no resuelve a ningún post. Sin apuntar $wp_query/$post
 * a mano (mismo patrón que el fallback de abajo para '/{lang}/{slug}/'), have_posts()
 * da false y front-page.php renderiza con el <main> vacío: no es que la home muestre
 * el contenido en español, es que no muestra contenido de ningún idioma.
 */
add_action('template_redirect', function() {
    $lang = get_query_var('idml_lang_home');
    if (!$lang) {
        return;
    }

    if ('page' === get_option('show_on_front')) {
        $front_id = (int) get_option('page_on_front');
        $front = $front_id ? get_post($front_id) : null;

        if ($front instanceof WP_Post && $front->post_status === 'publish') {
            global $wp_query, $post;

            $post = $front;
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_home = false;
            $wp_query->posts = [$post];
            $wp_query->post = $post;
            $wp_query->post_count = 1;
            $wp_query->queried_object = $post;
            $wp_query->queried_object_id = (int) $post->ID;

            status_header(200);
            setup_postdata($post);
        }
    }

    $tpl = get_front_page_template();
    if ($tpl) {
        include $tpl;
        exit;
    }
});

/**
 * Red de seguridad: si las reglas de rewrite dinámicas (mas arriba en este
 * archivo) todavía no se flushearon, WP no reconoce /{lang}/{slug}/ y 404ea
 * antes de que el filtro 'request' llegue a intervenir — ver el NOTE de este
 * archivo sobre por qué no se flushea en cada request. Reparsea la URL a mano y
 * resuelve con la misma lógica (idml_resolve_translated_slug) para 'post' y
 * 'page' — antes esto eran dos bloques separados (uno vía diccionario JSON,
 * solo para 'page'; otro a mano, solo para 'post'). Ya no depende de JSON.
 */
add_action('template_redirect', function() {
    if (is_admin() || !is_404()) {
        return;
    }

    $segments = function_exists('idml_get_request_path_segments') ? idml_get_request_path_segments() : [];
    if (count($segments) !== 2) {
        return;
    }

    $supported_langs = function_exists('idml_get_supported_languages') ? idml_get_supported_languages() : idml_get_languages();
    $default_lang = function_exists('idml_get_default_language') ? idml_get_default_language() : 'es';
    $lang = sanitize_key((string) $segments[0]);

    if ($lang === '' || !in_array($lang, $supported_langs, true) || $lang === $default_lang) {
        return;
    }

    $slug = sanitize_title((string) $segments[1]);
    if ($slug === '') {
        return;
    }

    $resolved = idml_resolve_translated_slug($slug, $lang);
    if (empty($resolved)) {
        return;
    }

    $target = get_post($resolved['id']);
    if (!$target instanceof WP_Post || $target->post_status !== 'publish') {
        return;
    }

    global $wp_query, $post;

    $is_page = $resolved['post_type'] === 'page';

    $post = $target;
    $wp_query->is_404 = false;
    $wp_query->is_page = $is_page;
    $wp_query->is_single = !$is_page;
    $wp_query->is_singular = true;
    $wp_query->is_home = false;
    $wp_query->posts = [$post];
    $wp_query->post = $post;
    $wp_query->post_count = 1;
    $wp_query->queried_object = $post;
    $wp_query->queried_object_id = (int) $post->ID;

    status_header(200);
    setup_postdata($post);

    $template = $is_page ? get_page_template() : get_single_template();
    if (!$template) {
        $template = get_index_template();
    }

    include $template;
    exit;
}, 1);

