<?php
/**
 * Secciones estáticas del diseño como Componentes (CPT intelindev_component):
 * modificadores :html/:url/:icon, defaults anidados y render de las 6
 * plantillas (hero, about, stats, cta, contact, newsletter) en ES/EN.
 *
 * Las plantillas viven en el dashboard (Componentes); tests/fixtures/components/
 * guarda una copia de referencia con la que este test crea componentes
 * temporales (slug test-*) y los borra al final.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-sections.php
 */
use IntelindevInit\Components\ComponentsController as C;

$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/'; define('WP_USE_THEMES', false);
require dirname(__DIR__, 4) . '/wp-load.php';
wp_set_current_user(1);

$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
// Los shortcodes se registran en init (subproceso limpio por render).
$render = fn(string $code, string $lang = 'es') => trim((string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg('$_SERVER["HTTP_HOST"]="localhost:8888"; $_SERVER["REQUEST_URI"]="/Intelindev/' . ($lang === 'es' ? '' : $lang . '/') . '"; define("WP_USE_THEMES",false); require "' . ABSPATH . 'wp-load.php"; echo do_shortcode(' . var_export($code, true) . ');') . ' 2>/dev/null'));

$c = new C();
$fixtures = glob(__DIR__ . '/fixtures/components/*.html');
$created = [];
try {
    echo "1) fixtures → componentes temporales\n";
    check('6 plantillas de referencia', count($fixtures) === 6);
    foreach ($fixtures as $file) {
        $slug = 'test-' . basename($file, '.html');
        $id = wp_insert_post(['post_type' => C::POST_TYPE, 'post_title' => $slug, 'post_name' => $slug, 'post_status' => 'publish']);
        update_post_meta($id, C::META_TEMPLATE, file_get_contents($file));
        $created[] = $id;
    }
    delete_transient(C::TRANSIENT);
    check('atributos detectados (hero): con modificador, "title" es atributo', $c->template_attributes(file_get_contents(__DIR__ . '/fixtures/components/hero.html')) === ['cta2_text', 'cta2_url', 'cta_text', 'cta_url', 'image', 'text', 'title', 'variant']);

    echo "2) motor: modificadores y defaults anidados\n";
    check(':html convierte *x* en <em> y filtra tags', $c->render_template('{x:html}', ['x' => 'A *b* <script>x</script><strong>c</strong>']) === 'A <em>b</em> x<strong>c</strong>');
    check(':url escapa y :icon resuelve nombre o URL', $c->render_template('{u:url}|{i:icon}|{j:icon}', ['u' => 'javascript:alert(1)', 'i' => 'code-file', 'j' => 'https://x.test/a.svg']) === '|' . get_template_directory_uri() . '/assets/img/icons/code-file.svg|https://x.test/a.svg');
    check('default anidado con diccionario y con modificador', $c->render_template('{eyebrow|{t:about.eyebrow}}/{icon1:icon|mobile-app}', []) === '¿Qué hacemos?/' . get_template_directory_uri() . '/assets/img/icons/mobile-app.svg');
    check('atributo vacío + default vacío ⇒ vacío (los .btn:empty se ocultan)', $c->render_template('<a class="btn" href="{cta2_url:url|}">{cta2_text}</a>', []) === '<a class="btn" href=""></a>');

    echo "3) render de las 6 secciones (subproceso, ES/EN)\n";
    $hero = $render('[test-hero title="Construimos el *futuro digital*" text="Sub" cta_text="Contáctanos" cta_url="#c" cta2_text="Ver" cta2_url="/x/"]');
    check('hero: variante, título con <em>, dos botones', strpos($hero, '<section class="hero hero--home"') !== false && strpos($hero, '<h1 class="hero__title">Construimos el <em>futuro digital</em></h1>') !== false && strpos($hero, 'class="btn btn--primary btn--arrow" href="#c">Contáctanos</a>') !== false && strpos($hero, 'class="btn btn--secondary" href="/x/">Ver</a>') !== false);
    $about = $render('[test-about title="T" text="X" item1_title="A" item1_text="a" cta_text="Más" cta_url="/n/"]');
    check('about: eyebrow del diccionario, íconos del theme, bullets', strpos($about, '<span class="eyebrow">¿Qué hacemos?</span>') !== false && strpos($about, '/assets/img/icons/mobile-app.svg') !== false && strpos($about, '<h3 class="about__item-title">A</h3><p>a</p>') !== false);
    check('about EN: eyebrow traducido', strpos($render('[test-about title="T"]', 'en'), '<span class="eyebrow">What we do</span>') !== false);
    $stats = $render('[test-stats value1="+20" label1="Stacks" value2="+50" label2="P" value3="+100" label3="H" value4="+5" label4="C"]');
    check('stats: 4 cifras', substr_count($stats, 'class="stats__item"') === 4 && strpos($stats, '<span class="stats__value">+100</span><span class="stats__label">H</span>') !== false);
    $cta = $render('[test-cta title="Construyamos" text="x"]');
    check('cta: botón con etiqueta por defecto del diccionario y contenedor de imagen', strpos($cta, 'href="#contacto">Contáctanos</a>') !== false && strpos($cta, '<div class="cta__media">') !== false && strpos($cta, 'section section--surface') !== false);
    check('cta EN: etiqueta traducida', strpos($render('[test-cta title="T"]', 'en'), '>Contact us</a>') !== false);
    $contact = $render('[test-contact title="Hola"]<p>inner</p>[/test-contact]');
    check('contact: eyebrow del diccionario, marca de agua y contenido envolvente', strpos($contact, '<span class="eyebrow">Contacto</span>') !== false && strpos($contact, 'data-watermark="Intelindev"') !== false && strpos($contact, '<div class="contact__form"><p>inner</p></div>') !== false);
    $news = $render('[test-newsletter]', 'en');
    check('newsletter EN: título, texto, etiqueta, placeholder y botón del diccionario', strpos($news, 'Join our newsletter') !== false && strpos($news, 'placeholder="Enter your email address"') !== false && strpos($news, '>Email</label>') !== false && strpos($news, '>Subscribe</button>') !== false && strpos($news, 'type="email"') !== false);

    echo "4) [clients] (módulo Sections)\n";
    $im = imagecreatetruecolor(200, 60); imagefill($im, 0, 0, imagecolorallocate($im, 55, 71, 79));
    $up = wp_upload_bits('test-logo.png', null, ''); ob_start(); imagepng($im); file_put_contents($up['file'], ob_get_clean()); imagedestroy($im);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $att = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'test logo', 'post_status' => 'inherit'], $up['file']);
    wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
    $cli = wp_insert_post(['post_type' => 'intelindev_client', 'post_title' => 'Cliente Test', 'post_name' => 'cliente-test-sections', 'post_status' => 'publish', 'menu_order' => -1]);
    set_post_thumbnail($cli, $att); update_post_meta($cli, '_intelindev_client_url', 'https://cliente.test/');
    $created[] = $cli; $created[] = $att;
    $clients = $render('[clients eyebrow="Confían" title="Nuestros *clientes*"]', 'en');
    check('clients: cabecera con acento, lista con logo enlazado', strpos($clients, '<section class="clients">') !== false && strpos($clients, '<h2 class="clients__title">Nuestros <em>clientes</em></h2>') !== false && strpos($clients, 'aria-label="They trust us"') !== false && preg_match('#<li class="clients__item"><a href="https://cliente\.test/" target="_blank" rel="noopener"><img[^>]*alt="Cliente Test"#', $clients) === 1);

    echo "5) [services] [projects] [latest_posts] [testimonials]\n";
    $svc = wp_insert_post(['post_type' => 'intelindev_service', 'post_title' => 'Servicio Test', 'post_name' => 'servicio-test-sections', 'post_status' => 'publish', 'menu_order' => -5]);
    update_post_meta($svc, INTELINDEV_POST_TRANSLATED_EXCERPT_META, ['es' => 'Resumen test', 'en' => 'Test summary']); update_post_meta($svc, INTELINDEV_POST_TRANSLATED_TITLE_META, ['en' => 'Test Service']);
    $prj = wp_insert_post(['post_type' => 'intelindev_project', 'post_title' => 'Proyecto Test', 'post_name' => 'proyecto-test-sections', 'post_status' => 'publish', 'menu_order' => -5]);
    update_post_meta($prj, INTELINDEV_POST_TRANSLATED_EXCERPT_META, ['es' => 'Excerpt proyecto']);
    $pst = wp_insert_post(['post_type' => 'post', 'post_title' => 'Entrada Test Secciones', 'post_name' => 'entrada-test-sections', 'post_status' => 'publish']);
    update_post_meta($pst, INTELINDEV_POST_TRANSLATED_EXCERPT_META, ['es' => 'Excerpt entrada']);
    $tst = wp_insert_post(['post_type' => 'intelindev_testimony', 'post_title' => 'Ana Test', 'post_name' => 'ana-test-sections', 'post_status' => 'publish', 'menu_order' => -5]);
    update_post_meta($tst, INTELINDEV_POST_TRANSLATED_CONTENT_META, ['es' => ['<p>Cita de prueba.</p>'], 'en' => ['<p>Test quote.</p>']]); update_post_meta($tst, '_intelindev_testimony_role', ['es' => 'CEO']); update_post_meta($tst, '_intelindev_testimony_company', 'Acme');
    array_push($created, $svc, $prj, $pst, $tst);
    $sv = $render('[services eyebrow="Nuestros servicios" title="Soluciones *para escalar*" limit="1"]');
    check('services: cabecera centrada, tarjeta con ícono del theme, título, excerpt y "Conocer más"', strpos($sv, '<h2 class="services__title">Soluciones <em>para escalar</em></h2>') !== false && strpos($sv, '/assets/img/icons/code.svg') !== false && strpos($sv, '>Servicio Test</a></h3><p class="services__excerpt">Resumen test</p>') !== false && strpos($sv, 'class="services__more" href="http://localhost:8888/Intelindev/servicios/servicio-test-sections/">Conocer más') !== false);
    check('services EN: título y "Learn more" traducidos', strpos($render('[services limit="1"]', 'en'), '>Test Service</a>') !== false && strpos($render('[services limit="1"]', 'en'), 'Learn more<') !== false);
    $pj = $render('[projects title="Proyectos" limit="1"]');
    check('projects: scroller con flechas y tarjeta con nombre + excerpt', strpos($pj, 'data-scroll-prev') !== false && strpos($pj, '<ul class="projects__list" data-scroller>') !== false && strpos($pj, '<span class="projects__name">Proyecto Test</span><span class="projects__excerpt">Excerpt proyecto</span>') !== false);
    $lp = $render('[latest_posts title="Visión digital" limit="1"]');
    check('latest_posts: título serif, ítem con toggle vertical, tarjeta con fecha, excerpt y "Leer más"', strpos($lp, '<h2 class="posts__title accent">Visión digital</h2>') !== false && strpos($lp, '<span class="posts__vertical">Entrada Test Secciones</span>') !== false && strpos($lp, '<time class="posts__date"') !== false && strpos($lp, '<p class="posts__excerpt">Excerpt entrada</p>') !== false && strpos($lp, 'posts__more" href="http://localhost:8888/Intelindev/entrada-test-sections/">Leer más</a>') !== false);
    $tm = $render('[testimonials eyebrow="Clientes" value="+25" label="clientes" limit="1"]');
    check('testimonials: cifra, etiqueta, flechas y tarjeta con cita y autor · cargo · empresa', strpos($tm, '<p class="testimonials__value">+25</p><p class="testimonials__label">clientes</p>') !== false && strpos($tm, 'scroller-arrow--on-dark') !== false && strpos($tm, '<blockquote class="testimonials__quote"><p>Cita de prueba.</p></blockquote><figcaption class="testimonials__author"><strong>Ana Test</strong><span>CEO · Acme</span></figcaption>') !== false);
    check('testimonials EN: cita traducida', strpos($render('[testimonials limit="1"]', 'en'), '<p>Test quote.</p>') !== false);

    echo "6) CSS e íconos\n";
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/styles.css');
    check('estilos de las secciones y botones :empty ocultos', strpos($css, '.hero {') !== false && strpos($css, '.clients__list {') !== false && strpos($css, '.about__inner {') !== false && strpos($css, '.stats__inner {') !== false && strpos($css, '.cta__inner {') !== false && strpos($css, '.contact {') !== false && strpos($css, '.newsletter__form {') !== false && strpos($css, '.services__grid {') !== false && strpos($css, '.projects__card {') !== false && strpos($css, '.posts__list {') !== false && strpos($css, '.testimonials__card {') !== false && strpos($css, '.btn:empty { display: none; }') !== false);
    check('23 íconos SVG del diseño en assets/img/icons + logo', count(glob(dirname(__DIR__) . '/assets/img/icons/*.svg')) === 23 && file_exists(dirname(__DIR__) . '/assets/img/logo.svg'));
} finally {
    foreach ($created as $id) { if (get_post_type($id) === 'attachment') wp_delete_attachment($id, true); else wp_delete_post($id, true); }
    delete_transient(C::TRANSIENT);
    echo "   (limpieza: " . count($created) . " fixtures temporales borrados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
