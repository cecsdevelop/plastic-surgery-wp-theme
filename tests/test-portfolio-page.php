<?php
/**
 * Página de Portafolio (archive-intelindev_project.php + single): la "Página
 * de Portafolio" de Ajustes → Lectura arma el listado ([projects layout="cards"]
 * con "Ver más"), su URL redirige al archivo; el detalle lleva la ficha
 * "Información del proyecto" y la galería del campo gallery.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-portfolio-page.php
 *
 * Crea una página, un proyecto y una imagen temporales, cambia
 * page_for_intelindev_project y lo restaura al final.
 */
use IntelindevInit\Portfolio\PortfolioController;

$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/wp-admin/'; define('WP_USE_THEMES', false); define('WP_ADMIN', true);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user(1);

$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));
$head = fn(string $path) => (string) shell_exec('curl -s -o /dev/null -w "%{http_code} %{redirect_url}" ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));

$ctrl = new PortfolioController();
$OPTION = $ctrl->archive_page_option();
$orig = get_option($OPTION, null);
$created = []; $att = 0;

try {
    echo "1) página, proyecto e imagen temporales\n";
    $page = wp_insert_post(['post_type' => 'page', 'post_title' => 'Portafolio Test', 'post_name' => 'test-portafolio-pagina', 'post_status' => 'publish']);
    update_post_meta($page, INTELINDEV_POST_TRANSLATED_TITLE_META, ['en' => 'Portfolio Test']);
    update_post_meta($page, INTELINDEV_POST_TRANSLATED_CONTENT_META, [
        'es' => ['[projects layout="cards" limit="-1" per_page="1" eyebrow="Nuestros Proyectos" title="Soluciones *test*"]'],
        'en' => ['[projects layout="cards" limit="-1" per_page="1" eyebrow="Our Projects" title="Solutions *test*"]'],
    ]);
    $im = imagecreatetruecolor(320, 200); imagefill($im, 0, 0, imagecolorallocate($im, 200, 60, 40));
    $up = wp_upload_bits('test-project.png', null, ''); ob_start(); imagepng($im); file_put_contents($up['file'], ob_get_clean()); imagedestroy($im);
    $att = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'test project', 'post_status' => 'inherit'], $up['file']);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
    $prj = wp_insert_post(['post_type' => PortfolioController::POST_TYPE, 'post_title' => 'Proyecto Test Página', 'post_name' => 'proyecto-test-pagina', 'post_status' => 'publish', 'menu_order' => -50]);
    update_post_meta($prj, INTELINDEV_POST_TRANSLATED_TITLE_META, ['en' => 'Test Page Project']);
    update_post_meta($prj, INTELINDEV_POST_TRANSLATED_EXCERPT_META, ['es' => 'Resumen proyecto', 'en' => 'Project summary']);
    update_post_meta($prj, INTELINDEV_POST_TRANSLATED_CONTENT_META, ['es' => ['<h2>Problema</h2><p>Detalle ES</p>'], 'en' => ['<h2>Problem</h2><p>Detail EN</p>']]);
    update_post_meta($prj, $ctrl->meta_key('client'), 'Cliente Test'); update_post_meta($prj, $ctrl->meta_key('year'), 2025);
    update_post_meta($prj, $ctrl->meta_key('period'), ['es' => '1 año', 'en' => '1 year']); update_post_meta($prj, $ctrl->meta_key('gallery'), [$att]);
    set_post_thumbnail($prj, $att);
    array_push($created, $page, $prj);
    update_option($OPTION, $page);
    check('archive_page() y estado "Página de Portafolio"', $ctrl->archive_page()->ID === $page && (apply_filters('display_post_states', [], get_post($page))[$OPTION] ?? '') === 'Página de Portafolio');

    echo "2) archivo con página (curl)\n";
    $es = $curl('/portafolio/');
    check('ES: hero con el título de la página, cabecera, tarjeta con foto y excerpt, resto oculto y botón "Ver más proyectos"', strpos($es, '<h1 class="hero__title">Portafolio Test</h1>') !== false && strpos($es, '<h2 class="projects__title">Soluciones <em>test</em></h2>') !== false && preg_match('#<article class="tile"><a class="tile__link" href="http://localhost:8888/Intelindev/portafolio/proyecto-test-pagina/"><img [^>]*class="tile__image wp-post-image"#', $es) === 1 && strpos($es, '<p class="tile__excerpt"><span>Resumen proyecto</span></p>') !== false && strpos($es, '<article class="tile" hidden>') !== false && strpos($es, 'data-tiles-more>Ver más proyectos</button>') !== false);
    $en = $curl('/en/portfolio/');
    check('EN: título, tarjeta y botón traducidos', strpos($en, '<h1 class="hero__title">Portfolio Test</h1>') !== false && strpos($en, '<h3 class="tile__name">Test Page Project</h3>') !== false && strpos($en, 'data-tiles-more>See more projects</button>') !== false);
    check('la URL propia de la página redirige 301 al archivo', $head('/test-portafolio-pagina/') === '301 http://localhost:8888/Intelindev/portafolio/');

    echo "3) archivo sin página\n";
    delete_option($OPTION);
    check('fallback: hero con el nombre del tipo y grilla', strpos($curl('/portafolio/'), '<h1 class="hero__title">Portafolio</h1>') !== false && strpos($curl('/portafolio/'), '<div class="tiles" data-tiles-step="6">') !== false);

    echo "4) detalle\n";
    $d = $curl('/portafolio/proyecto-test-pagina/');
    check('hero con la destacada, contenido, galería del campo y ficha con año/periodo/cliente', strpos($d, '<h1 class="hero__title">Proyecto Test Página</h1>') !== false && strpos($d, 'test-project') !== false && strpos($d, '<div class="project__inner wrap">') !== false && strpos($d, '<h2>Problema</h2>') !== false && preg_match('#<ul class="project__gallery"><li class="project__gallery-item"><img [^>]*test-project#', $d) === 1 && strpos($d, '<h2 class="project__info-title accent">Información del proyecto</h2>') !== false && strpos($d, '<dt>Año</dt><dd>2025</dd>') !== false && strpos($d, '<dt>Periodo</dt><dd>1 año</dd>') !== false && strpos($d, '<dt>Cliente</dt><dd>Cliente Test</dd>') !== false && strpos($d, '<dt>Servicio</dt>') === false);
    $de = $curl('/en/portfolio/proyecto-test-pagina/');
    check('detalle EN: título, contenido y ficha traducidos', strpos($de, '<h1 class="hero__title">Test Page Project</h1>') !== false && strpos($de, '<p>Detail EN</p>') !== false && strpos($de, '>Project information</h2>') !== false && strpos($de, '<dt>Period</dt><dd>1 year</dd>') !== false);
} finally {
    if ($orig === null) delete_option($OPTION); else update_option($OPTION, $orig);
    foreach ($created as $id) wp_delete_post($id, true);
    if ($att) wp_delete_attachment($att, true);
    echo "   (limpieza: opción restaurada, posts e imagen temporales borrados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
