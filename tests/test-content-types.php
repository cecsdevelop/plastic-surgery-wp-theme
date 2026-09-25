<?php
/**
 * CPTs de contenido (Servicios, Portafolio, Testimonios, Clientes, Equipo) +
 * núcleo multilenguaje extendido a CPTs: registro, campos propios (sanitize
 * y lectura), traducción de título/contenido/excerpt/slug, URLs
 * /{lang}/{base}/{slug}/, archivos por idioma, switcher y metabox.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-content-types.php
 *
 * Crea posts temporales y los borra al final. Requiere el sitio en
 * http://localhost:8888/WPfemsculpt/.
 */
use pswptInit\Services\ServicesController;
use pswptInit\Portfolio\PortfolioController;
use pswptInit\Testimonials\TestimonialsController;
use pswptInit\Clients\ClientsController;
use pswptInit\Team\TeamController;

$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/WPfemsculpt/'; define('WP_USE_THEMES', false);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
wp_set_current_user(1);

$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/WPfemsculpt' . $path));
$sub  = fn(string $code) => trim((string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg('$_SERVER["HTTP_HOST"]="localhost:8888"; $_SERVER["REQUEST_URI"]="/WPfemsculpt/wp-admin/"; define("WP_USE_THEMES",false); define("WP_ADMIN",true); require "' . ABSPATH . 'wp-load.php"; require_once ABSPATH."wp-admin/includes/template.php"; wp_set_current_user(1); ' . $code) . ' 2>/dev/null'));
// Contexto de frontend (sin WP_ADMIN): los filtros de permalink se saltan en el admin a propósito.
$front = fn(string $code) => trim((string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg('$_SERVER["HTTP_HOST"]="localhost:8888"; $_SERVER["REQUEST_URI"]="/WPfemsculpt/"; define("WP_USE_THEMES",false); require "' . ABSPATH . 'wp-load.php"; ' . $code) . ' 2>/dev/null'));

$services = new ServicesController(); $portfolio = new PortfolioController(); $team = new TeamController();
$created = [];
$mk = function (string $type, string $title, string $slug, array $meta = []) use (&$created): int {
    $id = wp_insert_post(['post_type' => $type, 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'menu_order' => -100 + count($created)]);
    foreach ($meta as $k => $v) update_post_meta($id, $k, $v);
    $created[] = $id;
    return $id;
};

try {
    echo "1) registro y núcleo multilenguaje\n";
    $types = [ServicesController::POST_TYPE, PortfolioController::POST_TYPE, TestimonialsController::POST_TYPE, ClientsController::POST_TYPE, TeamController::POST_TYPE];
    check('5 CPT registrados, nombres ≤ 20 chars', count(array_filter($types, 'post_type_exists')) === 5 && max(array_map('strlen', $types)) <= 20);
    check('todos traducibles; solo Servicios y Portafolio con URL', array_diff($types, pswpt_translatable_post_types()) === [] && array_diff(pswpt_public_translatable_post_types(), ['post', 'page', ServicesController::POST_TYPE, PortfolioController::POST_TYPE]) === [] && !is_post_type_viewable(TeamController::POST_TYPE));
    check('base por idioma y tipo por base', pswpt_post_type_lang_slug(ServicesController::POST_TYPE, 'en') === 'services' && pswpt_post_type_lang_slug(ServicesController::POST_TYPE, 'es') === 'servicios' && pswpt_post_type_by_lang_slug('portfolio', 'en') === PortfolioController::POST_TYPE && pswpt_post_type_by_lang_slug('portafolio', 'en') === '');
    check('sin editor de bloques: el contenido va por "Contenido traducido"', !post_type_supports(ServicesController::POST_TYPE, 'editor') && post_type_supports(ServicesController::POST_TYPE, 'thumbnail'));
    check('pestañas de idioma = idiomas configurados, default primero', array_keys(\pswptInit\General\MultilanguageTabsRenderer::get_languages()) === array_merge([idml_get_default_language()], pswpt_translation_languages()));

    echo "2) campos propios: sanitize y lectura\n";
    check('lang_text/url/number/gallery/media/select', $portfolio->sanitize_field('lang_text', ['es' => ' Salud ', 'en' => 'Health', 'xx' => 'no']) === ['es' => 'Salud', 'en' => 'Health'] && $portfolio->sanitize_field('url', 'javascript:alert(1)') === '' && $portfolio->sanitize_field('url', ' https://nexito.app ') === 'https://nexito.app' && $portfolio->sanitize_field('number', '-3') === 0 && $portfolio->sanitize_field('gallery', '12, 0, x, 7') === [12, 7] && $portfolio->sanitize_field('media', '9') === 9 && $portfolio->sanitize_field('select', 'b', ['options' => ['a' => 'A']]) === '');
    $svc1 = $mk(ServicesController::POST_TYPE, 'Desarrollo de Software', 'test-desarrollo-software', [
        pswpt_POST_TRANSLATED_TITLE_META => ['en' => 'Software Development'],
        pswpt_post_translated_slug_meta_key('en') => 'test-software-development',
        pswpt_POST_TRANSLATED_CONTENT_META => ['es' => ['<p>Creamos sistemas a medida.</p>'], 'en' => ['<p>We build custom systems.</p>']],
        pswpt_POST_TRANSLATED_EXCERPT_META => ['es' => 'Sistemas a medida', 'en' => 'Custom systems'],
    ]);
    $svc2 = $mk(ServicesController::POST_TYPE, 'SEO & Marketing Técnico', 'test-seo-marketing', [pswpt_POST_TRANSLATED_TITLE_META => ['en' => 'SEO & Technical Marketing']]);
    $prj  = $mk(PortfolioController::POST_TYPE, 'Néxito', 'test-nexito', [
        $portfolio->meta_key('client') => 'Néxito Inc.', $portfolio->meta_key('sector') => ['es' => 'Educación', 'en' => 'Education'], $portfolio->meta_key('year') => 2025, $portfolio->meta_key('gallery') => [1, 2],
        pswpt_post_translated_slug_meta_key('en') => 'test-nexito-en',
    ]);
    $mem  = $mk(TeamController::POST_TYPE, 'Adrián Alcántara', 'adrian', [$team->meta_key('role') => ['es' => 'CEO', 'en' => 'CEO']]);
    check('get_field resuelve por idioma con fallback; number/gallery tipados', $portfolio->get_field($prj, 'sector', 'en') === 'Education' && $portfolio->get_field($prj, 'sector', 'es') === 'Educación' && $portfolio->get_field($prj, 'year') === 2025 && $portfolio->get_field($prj, 'gallery') === [1, 2] && $portfolio->get_field($prj, 'url') === '' && $team->get_field($mem, 'role', 'en') === 'CEO');
    $items = array_values(array_filter($services->get_items(), fn($p) => in_array($p->ID, [$svc1, $svc2], true))); // puede haber servicios reales
    check('get_items: publicados en orden de menu_order', count($items) === 2 && $items[0]->ID === $svc1 && $items[1]->ID === $svc2);

    echo "3) URLs y contenido por idioma (curl)\n";
    $es = $curl('/servicios/test-desarrollo-software/');
    $en = $curl('/en/services/test-software-development/');
    check('single ES: título, contenido y switcher a /en/services/…', strpos($es, '<title>Desarrollo de Software') !== false && strpos($es, 'Creamos sistemas a medida.') !== false && strpos($es, 'href="http://localhost:8888/WPfemsculpt/en/services/test-software-development/" hreflang="en"') !== false);
    check('single EN por slug traducido: título, contenido y switcher a /servicios/…', strpos($en, '<title>Software Development') !== false && strpos($en, 'We build custom systems.') !== false && strpos($en, 'href="http://localhost:8888/WPfemsculpt/servicios/test-desarrollo-software/" hreflang="es"') !== false);
    check('single EN por slug nativo (sin traducción de slug) y permalink en EN', (bool) preg_match('/<title>SEO (&#038;|&amp;) Technical Marketing/', $curl('/en/services/test-seo-marketing/')) && $front('idml_set_current_language("en"); echo get_permalink(' . $svc2 . ');') === 'http://localhost:8888/WPfemsculpt/en/services/test-seo-marketing/');
    check('slug de otro tipo no resuelve en esta base (404)', strpos($curl('/en/services/test-nexito-en/'), '<title>Page not found') !== false && strpos($curl('/en/portfolio/test-nexito-en/'), '<title>Néxito') !== false);
    $arch_es = $curl('/servicios/'); $arch_en = $curl('/en/services/');
    check('archivo ES y EN: títulos traducidos, ambos ítems, switcher entre archivos', strpos($arch_es, '<title>Servicios ') !== false && strpos($arch_en, '<title>Services ') !== false && strpos($arch_en, 'Software Development') !== false && strpos($arch_en, 'SEO &amp; Technical Marketing') !== false && strpos($arch_en, 'href="http://localhost:8888/WPfemsculpt/servicios/" hreflang="es"') !== false && strpos($arch_es, 'href="http://localhost:8888/WPfemsculpt/en/services/" hreflang="en"') !== false);
    $blog_page = (int) get_option('page_for_posts');
    if ($blog_page > 0) {
        $tmp_post = $mk('post', 'Entrada temporal de prueba', 'entrada-temporal-prueba', [pswpt_POST_TRANSLATED_TITLE_META => ['en' => 'Temporary test post']]);
        $blog_en  = $curl('/en/' . pswpt_get_post_slug_for_lang(get_post($blog_page), 'en') . '/');
        check('página del blog en EN: lista posts (no pages) con título traducido', strpos($blog_en, 'class="blog') !== false && strpos($blog_en, 'Temporary test post') !== false && preg_match('/entry-title[^>]*>[^<]*(<a[^>]*>)?About us</', $blog_en) === 0);
    }
    check('pages y posts siguen igual', strpos($curl('/en/contact-us/'), '<title>Contact us') !== false && strpos($curl('/contacto/'), 'href="http://localhost:8888/WPfemsculpt/en/contact-us/" hreflang="en"') !== false);

    echo "4) admin\n";
    ob_start(); $portfolio->render_meta_box(get_post($prj)); pswpt_render_post_translation_metabox(get_post($prj)); $html = ob_get_clean();
    check('metabox de datos con campos tipados y valores', strpos($html, 'name="pswpt_ct[client]" value="Néxito Inc."') !== false && strpos($html, 'name="pswpt_ct[sector][en]" value="Education"') !== false && strpos($html, 'class="pswpt-gallery-field"') !== false && strpos($html, 'name="pswpt_ct[gallery]" value="1,2"') !== false && strpos($html, 'type="url"') !== false);
    check('metabox "Contenido traducido" con slug por idioma (tipo público) y sin PT', strpos($html, 'pswpt_post_slug[en]') !== false && strpos($html, 'pswpt_post_slug[pt]') === false && strpos($html, 'pswpt_post_content_blocks[en][]') !== false);
    ob_start(); pswpt_render_post_translation_metabox(get_post($mem)); $html_team = ob_get_clean();
    check('tipo sin URL: título traducido sí, slug no', strpos($html_team, 'pswpt_post_title[en]') !== false && strpos($html_team, 'pswpt_post_slug[en]') === false);
    $cols = $portfolio->columns(['cb' => '', 'title' => 'Título', 'date' => 'Fecha']);
    check('columnas propias tras el título', array_keys($cols) === ['cb', 'title', 'pswpt_ct_client', 'pswpt_ct_sector', 'date']);
    $saved = $sub('$_POST["pswpt_content_type_nonce"] = wp_create_nonce("pswpt_content_type_save"); $_POST["pswpt_ct"] = ["client" => "Cliente <b>x</b>", "sector" => ["es" => "Salud", "en" => ""], "year" => "2024", "url" => "https://ok.test/", "gallery" => "5,6"]; (new pswptInit\Portfolio\PortfolioController())->save(' . $prj . '); echo json_encode([get_post_meta(' . $prj . ', "_pswpt_project_client", true), get_post_meta(' . $prj . ', "_pswpt_project_sector", true), get_post_meta(' . $prj . ', "_pswpt_project_year", true), get_post_meta(' . $prj . ', "_pswpt_project_gallery", true)]);');
    check('save(): sanitize por tipo, lang vacío fuera', $saved === '["Cliente x",{"es":"Salud"},"2024",[5,6]]');
} finally {
    foreach ($created as $id) wp_delete_post($id, true);
    echo "   (limpieza: " . count($created) . " posts borrados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
