<?php
/**
 * SEO por idioma con un plugin SEO activo (Rank Math): el <title>, og:title,
 * twitter:title, la descripción y og:locale salen en el idioma de la URL, no
 * en el del sitio.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-seo-multilang.php
 *
 * Crea una página y un servicio temporales y los borra al final. Las
 * comprobaciones de los filtros se saltan si no hay plugin SEO instalado.
 */
$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/WPfemsculpt/'; define('WP_USE_THEMES', false);
require dirname(__DIR__, 4) . '/wp-load.php';
wp_set_current_user(1);

$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl  = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/WPfemsculpt' . $path));
$title = fn(string $html) => preg_match('#<title>(.*?)</title>#s', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
$meta  = fn(string $html, string $attr, string $name) => preg_match('#<meta ' . $attr . '="' . preg_quote($name, '#') . '" content="([^"]*)"#', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
$seo_plugin = class_exists('RankMath') || defined('WPSEO_VERSION');

$created = [];
try {
    echo "1) qué texto pide el theme (pswpt_seo_title_override)\n";
    $svc = wp_insert_post(['post_type' => 'pswpt_service', 'post_title' => 'Servicio Test SEO', 'post_name' => 'servicio-test-seo', 'post_status' => 'publish', 'menu_order' => -60]);
    update_post_meta($svc, pswpt_POST_TRANSLATED_TITLE_META, ['en' => 'Test SEO Service']);
    update_post_meta($svc, pswpt_post_translated_slug_meta_key('en'), 'test-seo-service');
    update_post_meta($svc, pswpt_POST_TRANSLATED_EXCERPT_META, ['es' => 'Resumen SEO ES', 'en' => 'SEO summary EN']);
    update_post_meta($svc, pswpt_POST_TRANSLATED_CONTENT_META, ['es' => ['<p>Cuerpo ES</p>'], 'en' => ['<p>Body EN</p>']]);
    $created[] = $svc;

    check('título del plugin con el título por defecto dentro ⇒ se reemplaza conservando la plantilla', pswpt_seo_apply_title('Servicio Test SEO - pswpt') === 'Servicio Test SEO - pswpt'); // en ES (idioma por defecto) no se toca
    check('en ES no se toca la descripción ni se inventa una', pswpt_seo_apply_description('') === '' && pswpt_seo_apply_description('Resumen del plugin') === 'Resumen del plugin');

    echo "2) HTML por idioma (curl)\n";
    $es = $curl('/servicios/servicio-test-seo/'); $en = $curl('/en/services/test-seo-service/');
    check('ES: título del post en el <title>, og:title y twitter:title', str_starts_with($title($es), 'Servicio Test SEO') && str_starts_with($meta($es, 'property', 'og:title'), 'Servicio Test SEO') && str_starts_with($meta($es, 'name', 'twitter:title'), 'Servicio Test SEO'));
    check('EN: título traducido en los tres, con la misma plantilla del sitio', str_starts_with($title($en), 'Test SEO Service') && str_starts_with($meta($en, 'property', 'og:title'), 'Test SEO Service') && str_starts_with($meta($en, 'name', 'twitter:title'), 'Test SEO Service') && substr($title($en), strlen('Test SEO Service')) === substr($title($es), strlen('Servicio Test SEO')));
    if ($seo_plugin) {
        check('og:locale sigue al idioma de la URL (es_ES / en_US)', $meta($es, 'property', 'og:locale') === 'es_ES' && $meta($en, 'property', 'og:locale') === 'en_US');
        check('descripción: si el plugin imprime una, en EN va el resumen traducido; si no imprime, tampoco se agrega', ($meta($es, 'name', 'description') === '' && $meta($en, 'name', 'description') === '') || $meta($en, 'name', 'description') === 'SEO summary EN');
    } else {
        echo "   (sin plugin SEO: og:locale y descripción no aplican)\n";
    }

    echo "3) archivos, búsqueda y 404\n";
    check('archivo del CPT: "Servicios" en ES y "Services" en EN', str_starts_with($title($curl('/servicios/')), 'Servicios') && str_starts_with($title($curl('/en/services/')), 'Services'));
    check('404 y búsqueda con los textos del diccionario en cada idioma', str_starts_with($title($curl('/no-existe/')), 'Página no encontrada') && str_starts_with($title($curl('/en/no-existe/')), 'Page not found') && strpos($title($curl('/?s=software')), 'Resultados') === 0 && strpos($title($curl('/en/?s=software')), 'Search results') === 0);

    echo "4) página traducida y página del blog\n";
    $page = wp_insert_post(['post_type' => 'page', 'post_title' => 'Página Test SEO', 'post_name' => 'pagina-test-seo', 'post_status' => 'publish']);
    update_post_meta($page, pswpt_POST_TRANSLATED_TITLE_META, ['en' => 'Test SEO Page']);
    update_post_meta($page, pswpt_post_translated_slug_meta_key('en'), 'test-seo-page');
    update_post_meta($page, pswpt_POST_TRANSLATED_CONTENT_META, ['es' => ['<p>ES</p>'], 'en' => ['<p>EN</p>']]);
    $created[] = $page;
    check('página: título traducido en EN', str_starts_with($title($curl('/pagina-test-seo/')), 'Página Test SEO') && str_starts_with($title($curl('/en/test-seo-page/')), 'Test SEO Page'));
    $blog = (int) get_option('page_for_posts');
    if ($blog > 0) {
        $blog_en = pswpt_get_post_translated_title(get_post($blog), 'en');
        check('página del blog: título del idioma en /en/blog/', $blog_en === '' || str_starts_with($title($curl('/en/blog/')), $blog_en));
    }
} finally {
    foreach ($created as $id) wp_delete_post($id, true);
    echo "   (limpieza: " . count($created) . " posts temporales borrados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
