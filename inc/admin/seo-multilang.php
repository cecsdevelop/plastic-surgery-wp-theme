<?php
/**
 * SEO por idioma cuando manda un plugin SEO.
 *
 * El theme traduce el <title> con 'document_title_parts' (multilang-rewrite.php),
 * pero Rank Math (o Yoast) imprime su propio <title>, og:title y twitter:title y
 * ese filtro deja de aplicarse: en /{lang}/… salían el título y la descripción
 * del idioma por defecto. Acá se resuelve qué texto quiere el theme para la
 * petición actual (título traducido del post, nombre del archivo del CPT o
 * categoría por idioma, y los textos de búsqueda/404 del diccionario) y se
 * aplica a los filtros del plugin, conservando la plantilla del plugin (el
 * " - pswpt" del final) cuando solo hay que cambiar el título dentro.
 *
 * Si no hay plugin SEO, estos filtros no existen y no pasa nada: manda
 * 'document_title_parts' como siempre.
 *
 * @package pswpt
 */

if (!defined('ABSPATH')) exit;

/**
 * Texto que el theme quiere para esta petición:
 *   ['title' => '', 'original' => '']  ⇒ dejar lo del plugin
 *   'original' es el texto del idioma por defecto que el plugin ya metió en su
 *   plantilla; si aparece, se reemplaza ahí y se conserva el resto.
 */
function pswpt_seo_title_override(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $none = ['title' => '', 'original' => ''];
    if (is_admin() || is_feed() || !function_exists('idml_get_current_language')) {
        return $cache = $none;
    }

    $lang    = idml_get_current_language();
    $default = idml_get_default_language();

    // Búsqueda y 404: el plugin (como el core) los titula en el idioma del
    // sitio; el diccionario los tiene en los dos.
    if (is_search()) {
        return $cache = ['title' => idml_t_vars('title.search', ['query' => get_search_query(false)], $lang), 'original' => ''];
    }
    if (is_404()) {
        return $cache = ['title' => idml_t('title.404', $lang), 'original' => ''];
    }

    if ($lang === $default) {
        return $cache = $none;
    }

    // Archivo de un CPT público: "Services" en vez de "Servicios".
    if (is_post_type_archive()) {
        $type   = (string) get_query_var('post_type'); // 'pswpt_post_type_lang_labels' es filtro, no función
        $labels = (array) apply_filters('pswpt_post_type_lang_labels', []);
        $set    = $labels[$type] ?? [];
        if (!empty($set[$lang]) && !empty($set[$default])) {
            return $cache = ['title' => (string) $set[$lang], 'original' => (string) $set[$default]];
        }
        return $cache = $none;
    }

    // Categoría o etiqueta con nombre traducido.
    if (is_category() || is_tag()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term && function_exists('pswpt_get_category_translated_name')) {
            $translated = pswpt_get_category_translated_name($term, $lang);
            if ($translated !== '' && $translated !== $term->name) {
                return $cache = ['title' => $translated, 'original' => $term->name];
            }
        }
        return $cache = $none;
    }

    // Singular (incluida la página del blog) con título traducido.
    $post = is_home() ? get_post((int) get_option('page_for_posts')) : get_queried_object();
    if (!($post instanceof WP_Post) || is_front_page() || !function_exists('pswpt_get_post_translated_title')) {
        return $cache = $none;
    }
    $translatable = function_exists('pswpt_translatable_post_types') ? pswpt_translatable_post_types() : ['post', 'page'];
    if (!in_array($post->post_type, $translatable, true)) {
        return $cache = $none;
    }
    $translated = pswpt_get_post_translated_title($post, $lang);

    return $cache = $translated !== '' && $translated !== $post->post_title
        ? ['title' => $translated, 'original' => $post->post_title]
        : $none;
}

/**
 * Aplica el texto del theme al título que armó el plugin: si el original está
 * dentro (el caso normal, "Servicio - pswpt"), se reemplaza ahí; si no,
 * se conserva la cola de la plantilla (" - pswpt") detrás del texto nuevo.
 */
function pswpt_seo_apply_title($title)
{
    $title    = (string) $title;
    $override = pswpt_seo_title_override();
    if ($override['title'] === '' || $title === '') {
        return $title;
    }

    if ($override['original'] !== '' && strpos($title, $override['original']) !== false) {
        return str_replace($override['original'], $override['title'], $title);
    }

    // Cola de la plantilla: separador + nombre del sitio al final.
    $site = (string) get_bloginfo('name');
    if ($site !== '' && preg_match('/(\s+\S{1,3}\s+' . preg_quote($site, '/') . ')\s*$/u', $title, $m)) {
        return $override['title'] . $m[1];
    }

    return $override['title'];
}

/**
 * Descripción: en un idioma distinto al de defecto se usa el resumen traducido
 * del post (el plugin genera la suya del contenido en el idioma por defecto).
 */
function pswpt_seo_apply_description($description)
{
    $description = (string) $description;
    // Si el plugin no imprime descripción (no está configurada), tampoco acá:
    // solo se traduce lo que el plugin ya decidió mostrar.
    if ($description === '' || is_admin() || is_feed() || !function_exists('idml_get_current_language') || !function_exists('pswpt_get_post_translated_excerpt')) {
        return $description;
    }
    $lang = idml_get_current_language();
    if ($lang === idml_get_default_language() || !is_singular()) {
        return $description;
    }
    $post = get_queried_object();
    if (!($post instanceof WP_Post)) {
        return $description;
    }
    $translated = pswpt_get_post_translated_excerpt($post, $lang);

    return $translated !== '' ? $translated : $description;
}

/** og:locale del idioma actual (es → es_ES, en → en_US), no el del sitio. */
function pswpt_seo_apply_locale($locale)
{
    if (is_admin() || !function_exists('idml_get_current_language')) {
        return $locale;
    }
    $lang = idml_get_current_language();
    $map  = apply_filters('pswpt_language_locales', ['es' => 'es_ES', 'en' => 'en_US', 'pt' => 'pt_BR'], $lang);

    return $map[$lang] ?? $locale;
}

// Rank Math (y cualquier plugin que use estos nombres de filtro).
add_filter('rank_math/frontend/title', 'pswpt_seo_apply_title', 20);
add_filter('rank_math/frontend/description', 'pswpt_seo_apply_description', 20);
add_filter('rank_math/opengraph/facebook/og_title', 'pswpt_seo_apply_title', 20);
add_filter('rank_math/opengraph/facebook/og_description', 'pswpt_seo_apply_description', 20);
add_filter('rank_math/opengraph/facebook/og_locale', 'pswpt_seo_apply_locale', 20);
add_filter('rank_math/opengraph/twitter/twitter_title', 'pswpt_seo_apply_title', 20);
add_filter('rank_math/opengraph/twitter/twitter_description', 'pswpt_seo_apply_description', 20);

// Yoast, por si algún día se cambia de plugin.
add_filter('wpseo_title', 'pswpt_seo_apply_title', 20);
add_filter('wpseo_metadesc', 'pswpt_seo_apply_description', 20);
add_filter('wpseo_opengraph_title', 'pswpt_seo_apply_title', 20);
add_filter('wpseo_opengraph_desc', 'pswpt_seo_apply_description', 20);
add_filter('wpseo_twitter_title', 'pswpt_seo_apply_title', 20);
add_filter('wpseo_twitter_description', 'pswpt_seo_apply_description', 20);
add_filter('wpseo_locale', 'pswpt_seo_apply_locale', 20);
