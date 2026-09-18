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

/**
 * true si el contenido de la página (bloques traducidos del idioma actual o
 * post_content) arranca con un componente hero: entonces la plantilla no
 * imprime el título, porque el hero ya trae el <h1>.
 */
if (!function_exists('intelindev_content_starts_with_hero')) {
    function intelindev_content_starts_with_hero(?WP_Post $post = null): bool {
        $post = $post ?: get_post();
        if (!$post instanceof WP_Post) {
            return false;
        }
        $blocks = function_exists('intelindev_get_post_translated_content_blocks') ? intelindev_get_post_translated_content_blocks($post) : [];
        $first  = ltrim((string) ($blocks[0] ?? $post->post_content));

        return (bool) preg_match('/^\[[a-z0-9_-]*hero\b/i', $first);
    }
}

/**
 * Hero interior por defecto para páginas/entradas sin componente hero:
 * título (y subtítulo opcional) sobre la imagen destacada, con el mismo
 * markup que el componente hero para que herede su CSS.
 */
if (!function_exists('intelindev_render_interior_hero')) {
    function intelindev_render_interior_hero(string $title, string $subtitle = '', ?WP_Post $post = null): void {
        $post  = $post ?: get_post();
        $image = $post instanceof WP_Post ? (string) get_the_post_thumbnail_url($post, 'full') : '';
        echo '<section class="hero hero--interior"' . ($image !== '' ? ' style="background-image:url(\'' . esc_url($image) . '\')"' : '') . '><div class="hero__inner wrap"><div class="hero__content">';
        echo '<h1 class="hero__title">' . wp_kses($title, ['em' => [], 'strong' => [], 'br' => []]) . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="hero__text">' . wp_kses($subtitle, ['em' => [], 'strong' => [], 'a' => ['href' => []]]) . '</p>';
        }
        echo '</div></div></section>';
    }
}
