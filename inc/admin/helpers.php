<?php
// Helpers para pswpt
if (!function_exists('pswpt_get_setting')) {
    function pswpt_get_setting($key, $default = null) {
        $opts = get_option('pswpt_settings', []);
        if (is_array($opts) && array_key_exists($key, $opts)) {
            return $opts[$key];
        }
        return $default;
    }
}

/**
 * true si el contenido de la página (bloques traducidos del idioma actual o
 * post_content) arranca con un componente hero: entonces la plantilla no
 * imprime el título, porque el hero ya trae el <h1>.
 */
if (!function_exists('pswpt_content_starts_with_hero')) {
    function pswpt_content_starts_with_hero(?WP_Post $post = null): bool {
        $post = $post ?: get_post();
        if (!$post instanceof WP_Post) {
            return false;
        }
        $blocks = function_exists('pswpt_get_post_translated_content_blocks') ? pswpt_get_post_translated_content_blocks($post) : [];
        $first  = ltrim((string) ($blocks[0] ?? $post->post_content));

        return (bool) preg_match('/^\[[a-z0-9_-]*hero\b/i', $first);
    }
}

/**
 * Hero interior por defecto para páginas/entradas sin componente hero:
 * título (y subtítulo opcional) sobre la imagen destacada, con el mismo
 * markup que el componente hero para que herede su CSS. $post: null = el
 * actual; false = sin imagen (archivos sin página asignada).
 */
if (!function_exists('pswpt_render_interior_hero')) {
    function pswpt_render_interior_hero(string $title, string $subtitle = '', $post = null): void {
        $post  = $post === null ? get_post() : $post;
        $image = $post instanceof WP_Post ? (string) get_the_post_thumbnail_url($post, 'full') : '';
        echo '<section class="hero hero--interior"' . ($image !== '' ? ' style="background-image:url(\'' . esc_url($image) . '\')"' : '') . '><div class="hero__inner wrap"><div class="hero__content">';
        echo '<h1 class="hero__title">' . wp_kses($title, ['em' => [], 'strong' => [], 'br' => []]) . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="hero__text">' . wp_kses($subtitle, ['em' => [], 'strong' => [], 'a' => ['href' => []]]) . '</p>';
        }
        echo '</div></div></section>';
    }
}

/**
 * Página asignada como cuerpo del archivo de un CPT público (Ajustes →
 * Lectura, "Página de Servicios"; option page_for_{post_type}), o null.
 */
if (!function_exists('pswpt_get_archive_page')) {
    function pswpt_get_archive_page(string $post_type): ?WP_Post {
        $id   = (int) get_option('page_for_' . $post_type);
        $page = $id > 0 ? get_post($id) : null; // get_post(0) devolvería el post global

        return $page instanceof WP_Post && $page->post_type === 'page' && $page->post_status === 'publish' ? $page : null;
    }
}

/**
 * Imprime esa página dentro del archivo: hero interior con su título e imagen
 * destacada (salvo que sus bloques ya arranquen con un hero) y sus bloques
 * traducidos del idioma actual pasados por los filtros de the_content
 * (shortcodes de componentes y listados, enlaces relativos). Se hace fuera del
 * loop del archivo, así que el post global se apunta a la página mientras tanto.
 */
if (!function_exists('pswpt_render_archive_page')) {
    function pswpt_render_archive_page(WP_Post $page): void {
        global $post;
        $previous = $post;
        $post     = $page;
        setup_postdata($page);

        if (!pswpt_content_starts_with_hero($page)) {
            pswpt_render_interior_hero(get_the_title($page), '', $page);
        }
        $blocks  = function_exists('pswpt_get_post_translated_content_blocks') ? pswpt_get_post_translated_content_blocks($page) : [];
        $content = $blocks ? implode("\n\n", $blocks) : $page->post_content;
        echo '<div class="entry-content">' . apply_filters('the_content', $content) . '</div>';

        wp_reset_postdata();
        $post = $previous;
    }
}
