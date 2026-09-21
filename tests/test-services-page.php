<?php
/**
 * Página de Servicios (archive-intelindev_service.php + single): la "Página
 * de Servicios" de Ajustes → Lectura arma el listado con sus bloques por
 * idioma, su URL propia redirige al archivo, sin página cae al hero + grilla;
 * el detalle lleva la lista de servicios con el actual resaltado.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-services-page.php
 *
 * Crea una página y un servicio temporales, cambia page_for_intelindev_service
 * y lo restaura al final. Requiere el sitio en http://localhost:8888/Intelindev/.
 */
use IntelindevInit\Services\ServicesController;

$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/wp-admin/'; define('WP_USE_THEMES', false); define('WP_ADMIN', true);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user(1);

$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));
$head = fn(string $path) => (string) shell_exec('curl -s -o /dev/null -w "%{http_code} %{redirect_url}" ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));

$ctrl = new ServicesController();
$OPTION = $ctrl->archive_page_option();
$orig = get_option($OPTION, null);
$created = [];

try {
    echo "1) opción y página temporal\n";
    check('option page_for_intelindev_service, sin página ⇒ null', $OPTION === 'page_for_intelindev_service' && intelindev_get_archive_page('intelindev_service_x') === null);
    $page = wp_insert_post(['post_type' => 'page', 'post_title' => 'Servicios Test', 'post_name' => 'test-servicios-pagina', 'post_status' => 'publish']);
    update_post_meta($page, INTELINDEV_POST_TRANSLATED_TITLE_META, ['en' => 'Services Test']);
    update_post_meta($page, INTELINDEV_POST_TRANSLATED_CONTENT_META, [
        'es' => ['[services layout="cards" limit="1" eyebrow="Nuestros servicios" title="Soluciones *test*"]', '<p class="test-es">Bloque ES</p>'],
        'en' => ['[services layout="cards" limit="1" eyebrow="Our services" title="Solutions *test*"]', '<p class="test-en">Block EN</p>'],
    ]);
    $svc = wp_insert_post(['post_type' => ServicesController::POST_TYPE, 'post_title' => 'Servicio Test Página', 'post_name' => 'servicio-test-pagina', 'post_status' => 'publish', 'menu_order' => -50]);
    update_post_meta($svc, INTELINDEV_POST_TRANSLATED_TITLE_META, ['en' => 'Test Page Service']);
    update_post_meta($svc, INTELINDEV_POST_TRANSLATED_CONTENT_META, ['es' => ['<h2>Sección test</h2><p>Detalle ES</p>'], 'en' => ['<h2>Test section</h2><p>Detail EN</p>']]);
    array_push($created, $page, $svc);
    update_option($OPTION, $page);
    check('archive_page() devuelve la página publicada; estado "Página de Servicios" en el listado de páginas', $ctrl->archive_page()->ID === $page && intelindev_get_archive_page(ServicesController::POST_TYPE)->ID === $page && (apply_filters('display_post_states', [], get_post($page))[$OPTION] ?? '') === 'Página de Servicios');

    echo "2) archivo con página (curl)\n";
    $es = $curl('/servicios/');
    check('ES: hero interior con el título de la página, bloques (cards con el servicio temporal) y párrafo del idioma', strpos($es, '<h1 class="hero__title">Servicios Test</h1>') !== false && strpos($es, '<h2 class="services__title">Soluciones <em>test</em></h2>') !== false && strpos($es, '<h3 class="tile__name">Servicio Test Página</h3>') !== false && strpos($es, '<p class="test-es">Bloque ES</p>') !== false && strpos($es, 'test-en') === false);
    $en = $curl('/en/services/');
    check('EN: título, cards y párrafo traducidos', strpos($en, '<h1 class="hero__title">Services Test</h1>') !== false && strpos($en, '<h3 class="tile__name">Test Page Service</h3>') !== false && strpos($en, '<p class="test-en">Block EN</p>') !== false && strpos($en, 'test-es') === false);
    check('la URL propia de la página redirige 301 al archivo', $head('/test-servicios-pagina/') === '301 http://localhost:8888/Intelindev/servicios/');
    check('sin <h1 class="entry-title"> ni loop de servicios sueltos', strpos($es, 'entry-title') === false && substr_count($es, '<h1') === 1);

    echo "3) archivo sin página\n";
    delete_option($OPTION);
    $fb = $curl('/servicios/');
    check('fallback: hero con el nombre del tipo (sin imagen) y grilla de tarjetas completa', strpos($fb, '<section class="hero hero--interior"><div class="hero__inner wrap"><div class="hero__content"><h1 class="hero__title">Servicios</h1>') !== false && strpos($fb, 'class="tiles"') !== false && strpos($fb, 'Servicio Test Página') !== false);
    check('fallback EN', strpos($curl('/en/services/'), '<h1 class="hero__title">Services</h1>') !== false);

    echo "4) detalle\n";
    $d = $curl('/servicios/servicio-test-pagina/');
    check('hero interior + columnas: contenido con h2 y lista de servicios con el actual en aria-current', strpos($d, '<h1 class="hero__title">Servicio Test Página</h1>') !== false && strpos($d, '<div class="service__inner wrap">') !== false && strpos($d, '<h2>Sección test</h2>') !== false && strpos($d, '<nav class="services-nav" aria-label="Servicios">') !== false && strpos($d, 'href="http://localhost:8888/Intelindev/servicios/servicio-test-pagina/" aria-current="page">Servicio Test Página</a>') !== false && substr_count($d, 'aria-current="page"') === 1);
    $de = $curl('/en/services/servicio-test-pagina/');
    check('detalle EN: título, contenido y lista traducidos', strpos($de, '<h1 class="hero__title">Test Page Service</h1>') !== false && strpos($de, '<p>Detail EN</p>') !== false && strpos($de, 'aria-label="Services"') !== false && strpos($de, 'aria-current="page">Test Page Service</a>') !== false);
} finally {
    if ($orig === null) delete_option($OPTION); else update_option($OPTION, $orig);
    foreach ($created as $id) wp_delete_post($id, true);
    echo "   (limpieza: opción restaurada, " . count($created) . " posts temporales borrados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
