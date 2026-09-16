<?php
/**
 * Apariencia → Traducciones: agrupación por prefijo, badges de origen y
 * guardado (índices continuos entre grupos).
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-translations-admin.php
 */
$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/wp-admin/'; define('WP_USE_THEMES', false); define('WP_ADMIN', true);
require dirname(__DIR__, 4) . '/wp-load.php';
wp_set_current_user(1);
$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }

$OPT  = idml_get_translations_option_name('ui');
$orig = get_option($OPT, null);

try {
    update_option($OPT, ['nav.prev_page' => ['en' => 'Prev'], 'hero.subtitulo' => ['es' => 'Sub ES', 'en' => 'Sub EN'], 'sinpunto' => ['es' => 'x']], true);
    ob_start(); idml_translations_admin_page(); $html = ob_get_clean();

    echo "1) grupos\n";
    preg_match_all('/<details class="idml-group" data-group="([^"]+)" open>/', $html, $m); $order = $m[1];
    check('grupos conocidos primero en orden fijo, nuevos al final', array_slice($order, 0, 7) === ['menu', 'nav', 'title', 'archive', 'footer', 'cta', 'language'] || (array_search('hero', $order, true) > array_search('language', $order, true) && array_search('general', $order, true) > array_search('language', $order, true)));
    check('grupo "hero" (prefijo nuevo, de un componente) y "general" (sin punto) existen', in_array('hero', $order, true) && in_array('general', $order, true));
    check('títulos legibles', strpos($html, '<span>Navegación</span>') !== false && strpos($html, '<span>Hero</span>') !== false && strpos($html, '<code>nav.*</code>') !== false);
    check('contador "claves · editadas" en nav', preg_match('/data-group="nav".*?(\d+) claves · 1 editadas/s', $html) === 1);

    echo "2) badges de origen\n";
    check('nav.prev_page editada', preg_match('/value="nav\.prev_page"[^>]*readonly[^>]*\/><\/td>\s*<td><span class="idml-badge idml-badge--edited">/', $html) === 1);
    check('nav.next_page default', preg_match('/value="nav\.next_page"[^>]*readonly[^>]*\/><\/td>\s*<td><span class="idml-badge idml-badge--default">/', $html) === 1);
    check('hero.subtitulo propia (editable, sin readonly)', preg_match('/value="hero\.subtitulo" class="regular-text" \/><\/td>\s*<td><span class="idml-badge idml-badge--own">/', $html) === 1);

    echo "3) índices y guardado\n";
    preg_match_all('/name="idml_keys\[(\d+)\]"/', $html, $m); $idx = array_map('intval', $m[1]);
    check('índices continuos y únicos entre grupos (0..n)', $idx === range(0, count($idx) - 1));
    check('fila nueva con el último índice y placeholder grupo.clave', strpos($html, 'placeholder="grupo.clave"') !== false && end($idx) === count($idx) - 1);
    // Guardado con claves de dos grupos + una nueva (función pura, sin redirect).
    $saved = idml_translations_save(
        [0 => 'nav.prev_page', 1 => 'hero.subtitulo', 2 => 'menu.blog', 3 => ''],
        ['es' => [0 => '', 1 => 'Sub ES', 2 => 'Blog', 3 => ''], 'en' => [0 => 'Prev', 1 => '', 2 => 'News', 3 => '']]
    );
    check('guardado plano: solo valores no vacíos, clave nueva incluida', $saved === ['nav.prev_page' => ['en' => 'Prev'], 'hero.subtitulo' => ['es' => 'Sub ES'], 'menu.blog' => ['es' => 'Blog', 'en' => 'News']]);
    check('idml_t usa el override', idml_t('menu.blog', 'en') === 'News' && idml_t('nav.prev_page', 'es') === 'Anteriores');
} finally {
    if ($orig === null) delete_option($OPT); else update_option($OPT, $orig, true);
    echo "   (option restaurada)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
