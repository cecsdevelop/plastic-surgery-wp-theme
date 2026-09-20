<?php
/**
 * Enlaces relativos a la raíz en contenido del dashboard (href="/contacto/")
 * → el theme antepone el subdirectorio de la instalación al imprimir
 * (content-filters.php); placeholder de imagen vacío en un componente → sin
 * background-image:url(''); frontend sin jQuery.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-content-links.php
 */
use IntelindevInit\Components\ComponentsController as C;

$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/'; define('WP_USE_THEMES', false);
require dirname(__DIR__, 4) . '/wp-load.php';
wp_set_current_user(1);
$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));

$base = rtrim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
check("la instalación local vive en un subdirectorio ($base)", $base !== '');

$orig_header = get_option('intelindev_header_settings', null);
$orig_widgets = get_option('widget_block', null);
$orig_sidebars = get_option('sidebars_widgets', null);
$page_id = 0; $comp_id = 0;

try {
    echo "1) función pura\n";
    $f = 'intelindev_resolve_root_relative_urls';
    check('href/src/action relativos a la raíz reciben el subdirectorio', $f('<a href="/contacto/">x</a><img src="/wp-content/u.png"><form action="/x/">') === "<a href=\"$base/contacto/\">x</a><img src=\"$base/wp-content/u.png\"><form action=\"$base/x/\">");
    check('comillas simples, "/" solo y "/#ancla"', $f("<a href='/'>x</a><a href=\"/#contacto\">") === "<a href='$base/'>x</a><a href=\"$base/#contacto\">");
    check('absolutas, protocol-relative, mailto y anclas no cambian', $f('<a href="https://x.com/a/"><a href="//cdn.x/a"><a href="mailto:a@b.c"><a href="#top">') === '<a href="https://x.com/a/"><a href="//cdn.x/a"><a href="mailto:a@b.c"><a href="#top">');
    check('idempotente: ya prefijado no se duplica', $f($f('<a href="/nosotros/">')) === "<a href=\"$base/nosotros/\">");
    check('sin URLs relativas → misma cadena, no-string intacto', $f('<p>hola</p>') === '<p>hola</p>' && $f(null) === null);

    echo "2) componente con placeholder de imagen vacío\n";
    check('style con url(\'\') desaparece entero', C::strip_empty_background_image('<section class="hero" style="background-image:url(\'\')"><i style="color:red"></i>') === '<section class="hero"><i style="color:red"></i>');
    check('con imagen no toca nada', C::strip_empty_background_image('<section style="background-image:url(\'/a.jpg\')">') === '<section style="background-image:url(\'/a.jpg\')">');
    check('declaración vacía entre otras: solo se quita esa', C::strip_empty_background_image('<i style="color:red;background-image:url(\'\')">') === '<i style="color:red;">');

    echo "3) en la página real (the_content + componente + widget + modal)\n";
    $comp_id = wp_insert_post(['post_type' => C::POST_TYPE, 'post_title' => 'Test links hero', 'post_name' => 'test-links-hero', 'post_status' => 'publish']);
    update_post_meta($comp_id, C::META_TEMPLATE, '<section class="tl-hero" style="background-image:url(\'{image:url|{featured_image:full}}\')"><a class="tl-cta" href="{cta_url:url}">{cta_text}</a></section>');
    delete_transient(C::TRANSIENT);
    $page_id = wp_insert_post(['post_type' => 'page', 'post_title' => 'Test links', 'post_name' => 'test-links', 'post_status' => 'publish']);
    update_post_meta($page_id, INTELINDEV_POST_TRANSLATED_CONTENT_META, [
        'es' => ['[test-links-hero cta_text="Servicios" cta_url="/servicios/"]', '<p><a class="tl-raw" href="/contacto/">Contacto</a> <a class="tl-abs" href="https://intelindev.com/x/">abs</a></p>'],
        'en' => ['[test-links-hero cta_text="Services" cta_url="/en/services/"]', '<p><a class="tl-raw" href="/en/contact/">Contact</a></p>'],
    ]);
    $widgets = is_array($orig_widgets) ? $orig_widgets : [];
    $widgets[9901] = ['content' => '<!-- wp:paragraph --><p><a class="tl-widget" href="/nosotros/">Nosotros</a></p><!-- /wp:paragraph -->'];
    update_option('widget_block', $widgets);
    $sidebars = is_array($orig_sidebars) ? $orig_sidebars : [];
    $first = null; foreach ($sidebars as $k => $v) { if ($k !== 'wp_inactive_widgets' && $k !== 'array_version' && is_array($v)) { $first = $k; break; } }
    check('hay un área de widgets registrada para probar', $first !== null);
    if ($first !== null) { $sidebars[$first][] = 'block-9901'; update_option('sidebars_widgets', $sidebars); }
    update_option('intelindev_header_settings', ['cta_type' => 'modal', 'cta_text' => ['es' => 'Hablemos'], 'cta_modal_title' => ['es' => 'Escribinos'], 'cta_modal_content' => '<p><a class="tl-modal" href="/contacto/">c</a></p>']);

    $es = $curl('/test-links/'); $en = $curl('/en/test-links/');
    check('ES: CTA del componente y enlace crudo llevan el subdirectorio', strpos($es, "class=\"tl-cta\" href=\"$base/servicios/\"") !== false && strpos($es, "class=\"tl-raw\" href=\"$base/contacto/\"") !== false);
    check('EN: idem con prefijo de idioma dentro del contenido', strpos($en, "class=\"tl-cta\" href=\"$base/en/services/\"") !== false && strpos($en, "class=\"tl-raw\" href=\"$base/en/contact/\"") !== false);
    check('absoluta intacta', strpos($es, 'class="tl-abs" href="https://intelindev.com/x/"') !== false);
    check('ningún enlace relativo sin subdirectorio en toda la página', preg_match('#href="/(?!' . preg_quote(ltrim($base, '/'), '#') . '/)#', $es) === 0);
    check('componente sin imagen: sin url(\'\') ni style vacío', strpos($es, '<section class="tl-hero">') !== false && strpos($es, "url('')") === false);
    check('widget de bloque del footer resuelto', $first === null || strpos($es, "class=\"tl-widget\" href=\"$base/nosotros/\"") !== false);
    $modal = json_decode((string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev/wp-json/intelindev/v1/modal?lang=es')), true);
    check('contenido del modal del header resuelto', strpos((string) ($modal['html'] ?? ''), "class=\"tl-modal\" href=\"$base/contacto/\"") !== false);

    echo "4) frontend sin jQuery\n";
    check('la página no encola jquery ni jquery-migrate', strpos($es, 'jquery.min.js') === false && strpos($es, 'jquery-migrate') === false);
    check('scripts.js se encola sin dependencias', preg_match('#<script[^>]*src="[^"]*/assets/js/scripts\.js#', $es) === 1);
} finally {
    if ($orig_header === null) delete_option('intelindev_header_settings'); else update_option('intelindev_header_settings', $orig_header);
    if ($orig_widgets === null) delete_option('widget_block'); else update_option('widget_block', $orig_widgets);
    if ($orig_sidebars === null) delete_option('sidebars_widgets'); else update_option('sidebars_widgets', $orig_sidebars);
    if ($page_id) wp_delete_post($page_id, true);
    if ($comp_id) wp_delete_post($comp_id, true);
    delete_transient(C::TRANSIENT);
    echo "   (limpieza: página, componente, widget y option del header)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
