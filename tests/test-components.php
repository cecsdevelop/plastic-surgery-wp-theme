<?php
/**
 * Test de integración del módulo Componentes (CLI + curl contra MAMP local).
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-components.php
 *
 * Crea componentes, imagen destacada y bloques traducidos temporales en la
 * página 18 (Contactanos) y los restaura al final. Requiere el sitio en
 * http://localhost:8888/Intelindev/.
 */
use IntelindevInit\Components\ComponentsController as C;

$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/'; define('WP_USE_THEMES', false);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));
$sub  = fn(string $code) => trim((string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg('$_SERVER["HTTP_HOST"]="localhost:8888"; $_SERVER["REQUEST_URI"]="/Intelindev/wp-admin/"; define("WP_USE_THEMES",false); define("WP_ADMIN",true); require "' . ABSPATH . 'wp-load.php"; require_once ABSPATH."wp-admin/includes/template.php"; wp_set_current_user(1); ' . $code) . ' 2>/dev/null'));
wp_set_current_user(1);

$PAGE = 18; $META = INTELINDEV_POST_TRANSLATED_CONTENT_META;
$orig_blocks = get_post_meta($PAGE, $META, true); $orig_thumb = get_post_thumbnail_id($PAGE);
$created = []; $att = 0;
$mk = function (string $title, string $slug, string $template, string $status = 'publish') use (&$created): int {
    $id = wp_insert_post(['post_type' => C::POST_TYPE, 'post_title' => $title, 'post_name' => $slug, 'post_status' => $status]);
    update_post_meta($id, C::META_TEMPLATE, $template);
    $created[] = $id;
    return $id;
};

try {
    echo "1) fixtures\n";
    $im = imagecreatetruecolor(300, 120); imagefill($im, 0, 0, imagecolorallocate($im, 81, 102, 236));
    $up = wp_upload_bits('test-hero.png', null, ''); ob_start(); imagepng($im); file_put_contents($up['file'], ob_get_clean()); imagedestroy($im);
    $att = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'test hero', 'post_status' => 'inherit'], $up['file']);
    wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file'])); set_post_thumbnail($PAGE, $att);
    $hero = $mk('Hero', 'hero', '<section class="hero" data-lang="{lang}" style="background-image:url({featured_image:medium})"><h1>{title}</h1><p class="sub">{subtitle}</p><a class="btn" href="{cta_url|#contacto}">{cta|Contactanos}</a><span class="dict">{t:nav.prev_page}</span><span class="site">{site_name}</span><span class="link">{permalink}</span><div class="body">{content}</div>[inner]</section>');
    $mk('Inner', 'inner', '<em class="inner">inner:{title}</em>');
    $mk('Loop', 'loop', '<i class="loop">loop</i>[loop]');
    $mk('Galería', 'gallery', '<b>NO DEBE VERSE</b>');
    $mk('Borrador', 'borrador', '<b>NO DEBE VERSE</b>', 'draft');
    check('5 componentes creados (4 publicados + 1 borrador)', count($created) === 5);

    echo "2) registro de shortcodes (subproceso, transient invalidado)\n";
    $tags = explode(',', $sub('global $shortcode_tags; echo implode(",", array_keys(array_filter($shortcode_tags, fn($cb) => is_array($cb) && $cb[0] instanceof \IntelindevInit\Components\ComponentsController)));'));
    check('hero, inner, loop registrados por el módulo', array_diff(['hero', 'inner', 'loop'], $tags) === []);
    check('gallery (conflicto) y borrador NO', !in_array('gallery', $tags, true) && !in_array('borrador', $tags, true));
    check('gallery sigue siendo el de WP', $sub('global $shortcode_tags; echo is_string($shortcode_tags["gallery"]) ? $shortcode_tags["gallery"] : "closure";') === 'gallery_shortcode');

    echo "3) render en ES y EN (bloques por idioma de Contactanos)\n";
    $blocks = is_array($orig_blocks) ? $orig_blocks : [];
    $blocks['es'] = ['[hero subtitle="Hablemos de tu proyecto" cta="Escribinos"]<p>Cuerpo ES</p>[/hero]', '[loop]', '[borrador]'];
    $blocks['en'] = ['[hero subtitle="Let&#039;s talk" cta_url="https://x.com/?a=1&b=2"]<p>Body EN</p>[/hero]', '[hero subtitle="<b>x</b>"]'];
    update_post_meta($PAGE, $META, $blocks);
    $grab = function (string $h): string { preg_match('/<div class="entry-content">(.*?)<\/div>\s*<\/article>/s', $h, $m); return $m[1] ?? ''; };
    $ces = $grab($curl('/contactanos/')); $cen = $grab($curl('/en/contact-us/'));
    $img = wp_get_attachment_image_url($att, 'medium');
    check('ES: título traducido + atributos + default cta_url', strpos($ces, '<h1>Contactanos</h1>') !== false && strpos($ces, '<p class="sub">Hablemos de tu proyecto</p>') !== false && strpos($ces, '<a class="btn" href="#contacto">Escribinos</a>') !== false);
    check('ES: imagen destacada medium + lang + site + permalink', strpos($ces, 'url(' . $img . ')') !== false && strpos($ces, 'data-lang="es"') !== false && strpos($ces, '<span class="site">Intelindev</span>') !== false && strpos($ces, '<span class="link">http://localhost:8888/Intelindev/contactanos/</span>') !== false);
    check('ES: diccionario {t:} + contenido envolvente + componente anidado', strpos($ces, '<span class="dict">Anteriores</span>') !== false && strpos($ces, '<div class="body"><p>Cuerpo ES</p></div>') !== false && strpos($ces, '<em class="inner">inner:Contactanos</em>') !== false);
    check('ES: recursión cortada (un solo loop) y borrador sin registrar', substr_count($ces, '<i class="loop">loop</i>') === 1 && strpos($ces, '[borrador]') !== false && strpos($ces, 'NO DEBE VERSE') === false);
    check('EN: título/permalink traducidos + default cta + url escapada', strpos($cen, '<h1>Contact us</h1>') !== false && strpos($cen, '<span class="link">http://localhost:8888/Intelindev/en/contact-us/</span>') !== false && strpos($cen, '<a class="btn" href="https://x.com/?a=1&amp;b=2">Contactanos</a>') !== false);
    check('EN: atributo con entidad + diccionario en inglés + lang', strpos($cen, '<p class="sub">Let&#039;s talk</p>') !== false && strpos($cen, '<span class="dict">Previous</span>') !== false && strpos($cen, 'data-lang="en"') !== false);
    check('EN: atributo con HTML escapado + sin content → vacío', strpos($cen, '<p class="sub">&lt;b&gt;x&lt;/b&gt;</p>') !== false && substr_count($cen, '<div class="body"></div>') === 1);

    echo "4) admin: metabox y columna\n";
    $mb = $sub('ob_start(); (new \IntelindevInit\Components\ComponentsController())->render_meta_box(get_post(' . $hero . ')); echo ob_get_clean();');
    check('metabox: shortcode de ejemplo con atributos detectados (sin reservados)', strpos($mb, '[hero cta=&quot;&quot; cta_url=&quot;&quot; subtitle=&quot;&quot;]') !== false);
    check('metabox: plantilla en textarea + sin aviso de conflicto', strpos($mb, 'name="' . C::FIELD_TEMPLATE . '"') !== false && strpos($mb, '{cta|Contactanos}') !== false && strpos($mb, 'en conflicto') === false);
    $gal = (int) get_page_by_path('gallery', OBJECT, C::POST_TYPE)->ID;
    $mbg = $sub('ob_start(); (new \IntelindevInit\Components\ComponentsController())->render_meta_box(get_post(' . $gal . ')); echo ob_get_clean();');
    check('metabox gallery: aviso de conflicto', strpos($mbg, 'en conflicto') !== false);
    $col = $sub('ob_start(); do_action("manage_' . C::POST_TYPE . '_posts_custom_column", "intelindev_shortcode", ' . $gal . '); do_action("manage_' . C::POST_TYPE . '_posts_custom_column", "intelindev_shortcode", ' . $hero . '); echo ob_get_clean();');
    check('columna: gallery en conflicto, hero limpio', substr_count($col, 'en conflicto') === 1 && strpos($col, '[hero') !== false);

    echo "5) guardado vía save_post con nonce (sanitize)\n";
    $_POST[C::NONCE_FIELD] = wp_create_nonce(C::NONCE_ACTION);
    $_POST[C::FIELD_TEMPLATE] = addslashes('<div class="x">{title}</div><script>1</script>');
    do_action('save_post_' . C::POST_TYPE, $hero, get_post($hero), true);
    check('admin (unfiltered_html): plantilla tal cual, sin slashes', get_post_meta($hero, C::META_TEMPLATE, true) === '<div class="x">{title}</div><script>1</script>');
    $_POST[C::FIELD_TEMPLATE] = '';
    do_action('save_post_' . C::POST_TYPE, $hero, get_post($hero), true);
    check('vacío → meta eliminada', get_post_meta($hero, C::META_TEMPLATE, true) === '');
} finally {
    if ($orig_blocks === '') delete_post_meta($PAGE, $META); else update_post_meta($PAGE, $META, $orig_blocks);
    if ($orig_thumb) set_post_thumbnail($PAGE, $orig_thumb); else delete_post_thumbnail($PAGE);
    foreach ($created as $id) wp_delete_post($id, true);
    if ($att) wp_delete_attachment($att, true);
    delete_transient(C::TRANSIENT);
    echo "   (limpieza: bloques, imagen destacada, componentes, adjunto y transient restaurados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
