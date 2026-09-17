<?php
/**
 * Header (Apariencia → Intelindev Header): CTA con estilo píldora/ícono,
 * etiqueta accesible por idioma, matriz de colores → variables, sticky y
 * toggle del menú móvil en el HTML.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-header.php
 *
 * Guarda ajustes temporales en intelindev_header_settings y los restaura.
 */
$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/wp-admin/'; define('WP_USE_THEMES', false); define('WP_ADMIN', true);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user(1);

$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));

$orig = get_option('intelindev_header_settings', null);

try {
    echo "1) sanitize\n";
    $out = intelindev_header_settings_sanitize(['cta_type' => 'url', 'cta_url' => 'https://example.com/x', 'cta_style' => 'icon', 'nav_bg_color' => '#a4271c', 'active_dot_color' => 'rgba(165, 217, 228, 1)', 'sticky_enabled' => '1', 'sticky_threshold' => '80']);
    check('cta_style icon/text, colores nuevos de la matriz', $out['cta_style'] === 'icon' && $out['nav_bg_color'] === '#a4271c' && $out['active_dot_color'] === 'rgba(165, 217, 228, 1)' && $out['cta_type'] === 'url');
    check('cta_style inválido → text', intelindev_header_settings_sanitize(['cta_style' => 'x'])['cta_style'] === 'text');

    echo "2) CTA resuelto\n";
    update_option('intelindev_header_settings', $out);
    $es = intelindev_get_header_cta('es'); $en = intelindev_get_header_cta('en');
    check('estilo ícono sin texto → etiqueta cta.icon_label por idioma', $es['style'] === 'icon' && $es['text'] === 'Contáctanos' && $en['text'] === 'Contact us' && $es['href'] === 'https://example.com/x');
    update_option('intelindev_header_settings', intelindev_header_settings_sanitize(['cta_type' => 'url', 'cta_url' => 'https://example.com/x', 'cta_style' => 'text']));
    check('estilo texto sin texto → sin CTA (como antes)', intelindev_get_header_cta('es')['type'] === 'none');
    update_option('intelindev_header_settings', $out);

    echo "3) HTML (curl)\n";
    $h = $curl('/contacto/');
    check('header sticky con umbral, toggle móvil con etiqueta del diccionario', strpos($h, 'class="site-header site-header--sticky" data-scroll-threshold="80"') !== false && strpos($h, 'class="site-nav-toggle" aria-controls="site-nav" aria-expanded="false" aria-label="Abrir menú"') !== false && strpos($h, '<nav id="site-nav" class="site-nav"') !== false);
    check('CTA ícono: enlace con clase --icon, texto solo para lectores de pantalla y SVG', preg_match('#<a href="https://example.com/x" class="header-cta header-cta--icon"><span class="screen-reader-text">Contáctanos</span><svg class="header-cta__icon"#', $h) === 1);
    check('overrides de la matriz en <head> (barra del menú y punto activo)', strpos($h, '--intelindev-header-nav-bg:#a4271c') !== false && strpos($h, '--intelindev-header-active-dot:rgba(165, 217, 228, 1)') !== false);
    check('EN: etiquetas traducidas', strpos($curl('/en/contact-us/'), 'aria-label="Open menu"') !== false && strpos($curl('/en/contact-us/'), '<span class="screen-reader-text">Contact us</span>') !== false);

    echo "4) CSS del diseño\n";
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/styles.css');
    check('defaults: banda rojo profundo, barra rojo oscuro, punto celeste, footer rojo profundo', strpos($css, '--intelindev-header-bg: var(--intelindev-color-red-deep);') !== false && strpos($css, '--intelindev-header-nav-bg: var(--intelindev-color-red-dark);') !== false && strpos($css, '--intelindev-header-active-dot: var(--intelindev-color-sky);') !== false && strpos($css, '--intelindev-footer-bg: var(--intelindev-color-red-deep);') !== false);
    check('menú móvil y sticky compacto', strpos($css, 'body.nav-open .site-nav {') !== false && strpos($css, '.site-header.is-scrolled .nav-list {') !== false);
} finally {
    if ($orig === null) delete_option('intelindev_header_settings'); else update_option('intelindev_header_settings', $orig);
    echo "   (limpieza: ajustes del header restaurados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
