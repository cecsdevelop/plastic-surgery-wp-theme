<?php
/**
 * Pestaña Tipografía: familias (theme + Biblioteca de fuentes), sanitize,
 * overrides en <head>, preload y @font-face de WP.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-typography.php
 */
$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/wp-admin/'; define('WP_USE_THEMES', false); define('WP_ADMIN', true);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user(1);
$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));

$orig_settings = get_option('intelindev_settings', null);
$gs_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
$orig_gs = get_post_field('post_content', $gs_id);

try {
    echo "1) familias y sanitize (solo theme.json)\n";
    $fam = intelindev_typography_font_families();
    check('familia "system" del theme, sin archivos', isset($fam['system']) && $fam['system']['origin'] === 'theme' && $fam['system']['fontFace'] === []);
    $out = intelindev_settings_sanitize(['font_body' => 'system', 'font_heading' => 'inter', 'font_size_base' => '30', 'font_weight_heading' => '650', 'font_preload' => '']);
    check('font_body válido, font_heading inexistente fuera, tamaño clamp 22, peso inválido fuera, preload 0', ($out['font_body'] ?? '') === 'system' && !isset($out['font_heading']) && $out['font_size_base'] === 22 && !isset($out['font_weight_heading']) && $out['font_preload'] === 0);
    $out = intelindev_settings_sanitize(['font_size_base' => '', 'font_weight_heading' => '600', 'font_preload' => '1']);
    check('tamaño vacío fuera, peso 600 ok, preload 1', !isset($out['font_size_base']) && $out['font_weight_heading'] === 600 && $out['font_preload'] === 1);

    echo "2) frontend por defecto\n";
    delete_option('intelindev_settings');
    $h = $curl('/');
    check('styles.css aplica las variables al body y títulos', strpos(file_get_contents(dirname(__DIR__) . '/assets/css/styles.css'), "body {\n  margin: 0;\n  font-family: var(--intelindev-font-body);") !== false);
    check('sin ajustes: sin override ni preload; WP imprime el preset system', strpos($h, 'intelindev-typography-overrides') === false && strpos($h, 'rel="preload" href') === false && strpos($h, '--wp--preset--font-family--system') !== false);

    echo "3) fuente de la Biblioteca (simulada en los estilos globales del usuario)\n";
    $gs = json_decode($orig_gs ?: '{}', true) ?: [];
    $gs['version'] = 3; $gs['isGlobalStylesUserThemeJSON'] = true;
    $gs['settings']['typography']['fontFamilies']['custom'] = [[
        'slug' => 'inter', 'name' => 'Inter', 'fontFamily' => '"Inter", sans-serif',
        'fontFace' => [
            ['fontFamily' => 'Inter', 'fontStyle' => 'normal', 'fontWeight' => '700', 'src' => ['http://localhost:8888/Intelindev/wp-content/uploads/fonts/inter-700.woff2']],
            ['fontFamily' => 'Inter', 'fontStyle' => 'normal', 'fontWeight' => '400', 'src' => ['http://localhost:8888/Intelindev/wp-content/uploads/fonts/inter-400.woff2']],
        ],
    ]];
    wp_update_post(['ID' => $gs_id, 'post_content' => wp_slash(wp_json_encode($gs))]); // wp_update_post espera datos con slashes
    WP_Theme_JSON_Resolver::clean_cached_data(); wp_cache_flush(); // wp_get_global_settings cachea por request
    $fam = intelindev_typography_font_families();
    check('"inter" aparece como Biblioteca con 2 caras', isset($fam['inter']) && $fam['inter']['origin'] === 'custom' && count($fam['inter']['fontFace']) === 2);
    check('preload elige la cara 400 normal (woff2)', intelindev_typography_preload_src('inter') === 'http://localhost:8888/Intelindev/wp-content/uploads/fonts/inter-400.woff2' && intelindev_typography_preload_src('system') === '');
    update_option('intelindev_settings', ['font_body' => 'inter', 'font_heading' => 'system', 'font_size_base' => 18, 'font_weight_heading' => 600, 'font_preload' => 1]);
    $h = $curl('/');
    check('override en <head> con las 4 variables', strpos($h, '<style id="intelindev-typography-overrides">:root{--intelindev-font-body:var(--wp--preset--font-family--inter);--intelindev-font-heading:var(--wp--preset--font-family--system);--intelindev-font-size-base:18px;--intelindev-font-weight-heading:600;}</style>') !== false);
    check('preload del woff2 antes de las hojas', preg_match('#<link rel="preload" href="http://localhost:8888/Intelindev/wp-content/uploads/fonts/inter-400\.woff2" as="font" type="font/woff2" crossorigin>.*<link rel=\'stylesheet\' id=\'intelindev-grid-css\'#s', $h) === 1);
    check('WP imprime el @font-face de Inter y su preset', strpos($h, '@font-face{font-family:Inter;') !== false && strpos($h, '--wp--preset--font-family--inter:') !== false);
    update_option('intelindev_settings', ['font_body' => 'inter', 'font_preload' => 0]);
    check('preload desactivable', strpos($curl('/'), 'rel="preload" href') === false);

    echo "4) admin\n";
    ob_start(); @do_action('admin_init'); ob_end_clean(); // registra secciones y campos (en el admin real ya corrió)
    ob_start(); intelindev_settings_page_html(); $page = ob_get_clean();
    check('pestaña Tipografía con desplegables que listan theme + Biblioteca', strpos($page, 'id="content-typography"') !== false && preg_match('/<select id="intelindev_font_body"[^>]*>.*?<option value="system"[^>]*>System · sin archivos<\/option>.*?<option value="inter"[^>]*>Inter · Biblioteca<\/option>/s', $page) === 1 && strpos($page, 'Apariencia → Fuentes') !== false);
} finally {
    if ($orig_settings === null) delete_option('intelindev_settings'); else update_option('intelindev_settings', $orig_settings);
    wp_update_post(['ID' => $gs_id, 'post_content' => wp_slash($orig_gs)]);
    WP_Theme_JSON_Resolver::clean_cached_data(); wp_cache_flush();
    echo "   (limpieza: ajustes y estilos globales restaurados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
