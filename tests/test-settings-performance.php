<?php
/**
 * Intelindev Settings → pestaña Performance (image-optimization.php) y
 * Preconnect Hosts (seo-analytics.php): sanitize con purga de claves
 * heredadas, filtros del core con los valores del panel, una subida real
 * (sub-tamaños en WebP + original escalado) y el panel sin los campos retirados.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-settings-performance.php
 */
$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/'; define('WP_USE_THEMES', false);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user(1);
$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));

$orig = get_option('intelindev_settings', null);
$attachment_id = 0; $tmp = '';

try {
    echo "1) sanitize: purga de claves heredadas y límites\n";
    update_option('intelindev_settings', array_merge(is_array($orig) ? $orig : [], ['bot_guard_enabled' => 1, 'contact_email' => 'x@y.z', 'archive_thumbs' => 1]));
    $out = intelindev_settings_sanitize(['image_quality' => '150', 'max_image_width' => '100', 'preconnect_hosts' => "https://fonts.gstatic.com\n, https://cdn.example.com/x/y", 'critical_images' => '/a.jpg', 'bot_guard_mode' => 'block']);
    check('claves retiradas fuera del resultado aunque existieran o se envíen', !array_intersect_key($out, array_flip(['bot_guard_enabled', 'bot_guard_mode', 'contact_email', 'archive_thumbs', 'critical_images', 'featured_default', 'enable_breadcrumbs', 'optimize_images_list'])));
    check('image_quality 150 → 100 · max_image_width 100 → 1200 · webp sin marcar → 0', $out['image_quality'] === 100 && $out['max_image_width'] === 1200 && $out['enable_webp'] === 0);
    check('preconnect_hosts: líneas y comas → array limpio', $out['preconnect_hosts'] === ['https://fonts.gstatic.com', 'https://cdn.example.com/x/y']);

    echo "2) filtros del core leen el panel\n";
    update_option('intelindev_settings', array_merge(is_array($orig) ? $orig : [], ['image_quality' => 70, 'enable_webp' => 1, 'max_image_width' => 1200, 'preconnect_hosts' => ['https://fonts.gstatic.com']]));
    check('jpeg_quality y wp_editor_set_quality → 70', apply_filters('jpeg_quality', 82, 'image_resize') === 70 && apply_filters('wp_editor_set_quality', 82, 'image/webp') === 70);
    check('big_image_size_threshold → 1200', apply_filters('big_image_size_threshold', 2560, [0, 0], '', 0) === 1200);
    $webp_ok = wp_image_editor_supports(['mime_type' => 'image/webp']);
    check('image_editor_output_format: jpeg → webp cuando está activo' . ($webp_ok ? '' : ' (sin soporte WebP en este servidor: se omite)'), !$webp_ok || (wp_get_image_editor_output_format('a.jpg', 'image/jpeg')['image/jpeg'] ?? '') === 'image/webp');
    update_option('intelindev_settings', array_merge(get_option('intelindev_settings'), ['enable_webp' => 0]));
    check('…y sin mapeo cuando está desactivado', !isset(wp_get_image_editor_output_format('a.jpg', 'image/jpeg')['image/jpeg']));
    update_option('intelindev_settings', array_merge(get_option('intelindev_settings'), ['enable_webp' => 1]));

    echo "3) subida real: 1600×1200 JPEG\n";
    $upload = wp_upload_dir();
    $tmp = trailingslashit($upload['path']) . 'intelindev-test-perf-' . wp_generate_password(6, false) . '.jpg';
    $im = imagecreatetruecolor(1600, 1200); imagefilledrectangle($im, 0, 0, 1599, 1199, imagecolorallocate($im, 30, 90, 200)); imagejpeg($im, $tmp, 95); imagedestroy($im);
    $attachment_id = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => 'test perf', 'post_status' => 'inherit'], $tmp);
    $meta = wp_generate_attachment_metadata($attachment_id, $tmp);
    wp_update_attachment_metadata($attachment_id, $meta);
    check('original escalado al umbral: -scaled de 1200 px, original conservado', (int) ($meta['width'] ?? 0) === 1200 && strpos((string) ($meta['file'] ?? ''), '-scaled.') !== false && !empty($meta['original_image']));
    $sizes = (array) ($meta['sizes'] ?? []);
    $mimes = array_values(array_unique(array_map(fn($s) => (string) ($s['mime-type'] ?? ''), $sizes)));
    check('sub-tamaños (y el -scaled) generados en WebP' . ($webp_ok ? '' : ' (sin soporte: se omite)'), !$webp_ok || ($sizes && $mimes === ['image/webp'] && substr((string) $meta['file'], -5) === '.webp'));
    $one = reset($sizes);
    check('archivo derivado existe y pesa menos que el original', $one && file_exists(dirname($tmp) . '/' . $one['file']) && filesize(dirname($tmp) . '/' . $one['file']) < filesize($tmp));

    echo "4) frontend y panel\n";
    $home = $curl('/');
    check("<link rel='preconnect'> impreso por el core con el host del panel", strpos($home, "rel='preconnect' href='https://fonts.gstatic.com'") !== false);
    ob_start(); @do_action('admin_init'); ob_end_clean();
    ob_start(); intelindev_settings_page_html(); $page = ob_get_clean();
    check('panel sin pestaña Content ni campos retirados', strpos($page, 'id="tab-content"') === false && strpos($page, 'Bot Guard') === false && strpos($page, 'Contact Email') === false && strpos($page, 'Critical Images') === false);
    check('panel con los 4 campos conservados', strpos($page, 'Compression Quality') !== false && strpos($page, 'Enable WebP') !== false && strpos($page, 'Max Image Width') !== false && strpos($page, 'Preconnect Hosts') !== false);
} finally {
    if ($attachment_id) wp_delete_attachment($attachment_id, true);
    if ($tmp && file_exists($tmp)) @unlink($tmp);
    if ($orig === null) delete_option('intelindev_settings'); else update_option('intelindev_settings', $orig);
    echo "   (limpieza: adjunto de prueba y option intelindev_settings)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
