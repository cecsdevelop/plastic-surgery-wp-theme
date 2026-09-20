<?php
/**
 * Contenido con rutas de un subdirectorio de instalación ("/Intelindev/…",
 * escritas cuando el sitio corría en local) → relativas a la raíz ("/…").
 * Desde content-filters.php el theme antepone el subdirectorio al imprimir,
 * así el mismo contenido sirve en local, staging y producción.
 *
 * Toca post_content, postmeta, options y termmeta (serializados incluidos:
 * se pasa por get_option / update_post_meta y afines). No toca URLs absolutas
 * (http://…/Intelindev/…), que ya reescribe la herramienta de migración.
 *
 *   php wp-content/themes/Intelindev/scripts/fix-root-relative-links.php --prefix=/Intelindev --dry-run
 *   php wp-content/themes/Intelindev/scripts/fix-root-relative-links.php --prefix=/Intelindev
 */
$opts   = getopt('', ['prefix:', 'dry-run']);
$prefix = '/' . trim((string) ($opts['prefix'] ?? ''), '/');
$dry    = isset($opts['dry-run']);
if ($prefix === '/') {
    fwrite(STDERR, "Falta --prefix=/Subdirectorio\n");
    exit(2);
}

$dir = __DIR__;
while (!file_exists($dir . '/wp-load.php')) {
    $parent = dirname($dir);
    if ($parent === $dir) { fwrite(STDERR, "No encuentro wp-load.php\n"); exit(2); }
    $dir = $parent;
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost'; $_SERVER['REQUEST_URI'] = '/'; define('WP_USE_THEMES', false);
require $dir . '/wp-load.php';

global $wpdb;
$pattern = '#(["\'=])' . preg_quote($prefix, '#') . '/#';       // solo relativas: precedidas de comilla o "="
$like    = '[\"\\\'=]' . $wpdb->esc_like($prefix) . '/';         // REGEXP equivalente para filtrar en SQL
$fix = function ($v) use (&$fix, $pattern) {
    if (is_string($v)) return preg_replace($pattern, '$1/', $v);
    if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = $fix($x); }
    return $v;
};
$changed = 0;
$note = fn(string $what) => print(($dry ? '[dry-run] ' : '') . $what . "\n");

foreach ($wpdb->get_results($wpdb->prepare("SELECT ID, post_type, post_content FROM {$wpdb->posts} WHERE post_content REGEXP %s AND post_type <> 'customize_changeset' AND post_status <> 'auto-draft'", $like)) as $r) {
    $new = $fix($r->post_content);
    if ($new !== $r->post_content) { $changed++; $note("post_content #{$r->ID} ({$r->post_type})"); if (!$dry) $wpdb->update($wpdb->posts, ['post_content' => $new], ['ID' => $r->ID]); }
}
foreach ($wpdb->get_results($wpdb->prepare("SELECT DISTINCT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_value REGEXP %s", $like)) as $r) {
    $old = get_post_meta($r->post_id, $r->meta_key, true); $new = $fix($old);
    if ($new !== $old) { $changed++; $note("postmeta #{$r->post_id} {$r->meta_key}"); if (!$dry) update_post_meta($r->post_id, $r->meta_key, $new); }
}
foreach ($wpdb->get_results($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_value REGEXP %s AND option_name NOT LIKE %s", $like, '_transient%')) as $r) {
    $old = get_option($r->option_name); $new = $fix($old);
    if ($new !== $old) { $changed++; $note("option {$r->option_name}"); if (!$dry) update_option($r->option_name, $new); }
}
foreach ($wpdb->get_results($wpdb->prepare("SELECT DISTINCT term_id, meta_key FROM {$wpdb->termmeta} WHERE meta_value REGEXP %s", $like)) as $r) {
    $old = get_term_meta($r->term_id, $r->meta_key, true); $new = $fix($old);
    if ($new !== $old) { $changed++; $note("termmeta #{$r->term_id} {$r->meta_key}"); if (!$dry) update_term_meta($r->term_id, $r->meta_key, $new); }
}
echo ($dry ? '[dry-run] ' : '') . "$changed registro(s)" . ($dry ? ' a corregir' : ' corregidos') . "\n";
