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

/**
 * Título de la pestaña en idioma no-default: se cambia solo la parte 'title'
 * y WP arma el resto (separador, nombre del sitio, y en la portada usa el
 * nombre del sitio en vez del título de la página, igual que en el idioma
 * default). Si algún día se instala un plugin SEO que hookee
 * pre_get_document_title (Rank Math, Yoast), ese plugin pasa a mandar acá.
 */
add_filter('document_title_parts', function($parts) {
    if (is_admin() || !is_array($parts)) {
        return $parts;
    }

    $lang = idml_get_current_language();

    // Búsqueda y 404: WP core los titula en el locale del sitio (es_*) sin
    // importar el idioma de la URL; acá salen del diccionario, en ambos idiomas.
    if (is_search()) {
        $parts['title'] = idml_t_vars('title.search', ['query' => get_search_query(false)], $lang);
        return $parts;
    }
    if (is_404()) {
        $parts['title'] = idml_t('title.404', $lang);
        return $parts;
    }

    $translatable = function_exists('intelindev_translatable_post_types') ? intelindev_translatable_post_types() : ['post', 'page'];
    if (!is_singular($translatable) || is_front_page() || $lang === idml_get_default_language()) {
        return $parts;
    }

    $post = get_queried_object();
    if (!($post instanceof WP_Post) || !function_exists('intelindev_get_post_translated_title')) {
        return $parts;
    }

    $translated_title = intelindev_get_post_translated_title($post, $lang);
    if ($translated_title !== '') {
        $parts['title'] = $translated_title;
    }

    return $parts;
}, 20);

add_action('init', function() {
    $langs = idml_get_languages();
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
    $supported_langs = idml_get_languages();
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

        // CPTs públicos: /{lang}/{base-del-tipo}/{slug}/. La base se resuelve en
        // el filtro 'request' contra intelindev_post_type_by_lang_slug(), así un
        // cambio de base no exige flush; el archivo /{lang}/{base}/ cae en la
        // regla de 2 segmentos de arriba y se distingue ahí.
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/([^/]+)/([^/]+)/?$',
            'index.php?idml_cpt_base=$matches[1]&idml_post_slug=$matches[2]&idml_post_lang=' . $lang,
            'top'
        );
    }
});

/**
 * Las reglas de arriba se registran en cada request pero WP solo las persiste al
 * hacer flush. En vez de pedir "guardar Enlaces permanentes" tras cada cambio
 * del theme, se flushea una sola vez cuando cambia esta versión (subirla al
 * tocar reglas o registrar un CPT nuevo). Prioridad 99: después de los CPT
 * (init 5) y de las reglas.
 */
const IDML_REWRITE_VERSION = 3;
add_action('init', function() {
    if ((int) get_option('idml_rewrite_version', 0) !== IDML_REWRITE_VERSION) {
        flush_rewrite_rules(false);
        update_option('idml_rewrite_version', IDML_REWRITE_VERSION);
    }
}, 99);

add_filter('query_vars', function($vars) {
    $vars[] = 'idml_post_slug';
    $vars[] = 'idml_post_lang';
    $vars[] = 'idml_cpt_base';
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
    function idml_resolve_translated_slug(string $slug, string $lang, array $post_types = ['post', 'page']): array {
        $slug = sanitize_title($slug);
        $lang = sanitize_key($lang);
        if ($slug === '' || $lang === '') {
            return [];
        }

        $resolved_id = 0;
        if (function_exists('intelindev_get_post_id_by_translated_slug')) {
            $resolved_id = intelindev_get_post_id_by_translated_slug($slug, $lang, $post_types);
        }

        if (!$resolved_id) {
            $native = get_page_by_path($slug, OBJECT, $post_types);
            if ($native instanceof WP_Post && $native->post_status === 'publish') {
                $resolved_id = (int) $native->ID;
            }
        }

        if (!$resolved_id) {
            return [];
        }

        $post_type = get_post_type($resolved_id);
        if (!in_array($post_type, $post_types, true)) {
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

    // /{lang}/{base}/{slug}/ → single de un CPT público; base desconocida → 404.
    if (!empty($query_vars['idml_cpt_base'])) {
        $type = function_exists('intelindev_post_type_by_lang_slug') ? intelindev_post_type_by_lang_slug((string) $query_vars['idml_cpt_base'], $lang) : '';
        if ($type === '') {
            return $not_found_query;
        }
        $resolved = idml_resolve_translated_slug($slug, $lang, [$type]);

        return empty($resolved) ? ['name' => $slug, 'post_type' => $type] : ['p' => $resolved['id'], 'post_type' => $type];
    }

    // /{lang}/{base}/ → archivo del CPT cuya base en ese idioma es {base}.
    $archive_type = function_exists('intelindev_post_type_by_lang_slug') ? intelindev_post_type_by_lang_slug($slug, $lang) : '';
    if ($archive_type !== '') {
        return ['post_type' => $archive_type];
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
        // La página del blog (page_for_posts) es la home de posts: WP ignora page_id
        // en el WHERE y lista lo que diga post_type, así que no se fija 'page'.
        if ((int) get_option('page_for_posts') === (int) $resolved['id']) {
            return ['page_id' => $resolved['id']];
        }

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

    // /{lang}/?s=x matchea la misma regla: WP ya armó la query de búsqueda
    // (lee 's' de $_GET), así que se deja seguir al template normal.
    if (is_search()) {
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

    $supported_langs = idml_get_languages();
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

