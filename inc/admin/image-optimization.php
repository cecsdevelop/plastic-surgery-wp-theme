<?php
/**
 * Pestaña Performance de pswpt Settings → filtros del core al subir
 * imágenes. Solo afecta a subidas nuevas (las ya subidas no se reprocesan):
 *   - image_quality  (60–100, por defecto 82) → calidad de JPEG y WebP.
 *   - enable_webp    (por defecto activo)     → los tamaños derivados de un
 *                    JPEG se generan en WebP (el original se conserva). Solo si
 *                    el editor de imágenes del servidor soporta WebP.
 *   - max_image_width (1200–4000, por defecto 2560) → umbral a partir del cual
 *                    el core escala el original al subirlo.
 */
if (!defined('ABSPATH')) {
    exit;
}

function pswpt_image_quality(): int {
    return max(60, min(100, (int) pswpt_get_setting('image_quality', 82)));
}
add_filter('wp_editor_set_quality', fn($quality, $mime_type = '') => pswpt_image_quality(), 10, 2);
add_filter('jpeg_quality', fn($quality) => pswpt_image_quality());

add_filter('image_editor_output_format', function (array $formats): array {
    if (!pswpt_get_setting('enable_webp', 1) || !wp_image_editor_supports(['mime_type' => 'image/webp'])) {
        return $formats;
    }
    $formats['image/jpeg'] = 'image/webp';
    return $formats;
});

add_filter('big_image_size_threshold', fn($threshold) => max(1200, min(4000, (int) pswpt_get_setting('max_image_width', 2560))));
