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
    check('default anidado con diccionario y con modificador', $c->render_template('{eyebrow|{t:about.eyebrow}}/{icon1:icon|users-group}', []) === '¿Qué hacemos?/' . get_template_directory_uri() . '/assets/img/icons/users-group.svg');
    check('atributo vacío + default vacío ⇒ vacío (los .btn:empty se ocultan)', $c->render_template('<a class="btn" href="{cta2_url:url|}">{cta2_text}</a>', []) === '<a class="btn" href=""></a>');

    echo "3) render de las 6 secciones (subproceso, ES/EN)\n";
    $hero = $render('[test-hero title="Construimos el *futuro digital*" text="Sub" cta_text="Contáctanos" cta_url="#c" cta2_text="Ver" cta2_url="/x/"]');
    check('hero: variante, título con <em>, dos botones', strpos($hero, '<section class="hero hero--home"') !== false && strpos($hero, '<h1 class="hero__title">Construimos el <em>futuro digital</em></h1>') !== false && strpos($hero, 'class="btn btn--primary btn--arrow" href="#c">Contáctanos</a>') !== false && strpos($hero, 'class="btn btn--outline" href="/x/">Ver</a>') !== false);
    $about = $render('[test-about title="T" text="X" item1_title="A" item1_text="a" cta_text="Más" cta_url="/n/"]');
    check('about: eyebrow del diccionario, íconos del theme, bullets', strpos($about, '<span class="eyebrow">¿Qué hacemos?</span>') !== false && strpos($about, '/assets/img/icons/code-file.svg') !== false && strpos($about, '<p><strong>A</strong> a</p>') !== false);
    check('about EN: eyebrow traducido', strpos($render('[test-about title="T"]', 'en'), '<span class="eyebrow">What we do</span>') !== false);
    $stats = $render('[test-stats value1="+20" label1="Stacks" value2="+50" label2="P" value3="+100" label3="H" value4="+5" label4="C"]');
    check('stats: 4 cifras', substr_count($stats, 'class="stats__item"') === 4 && strpos($stats, '<span class="stats__value">+100</span><span class="stats__label">H</span>') !== false);
    $cta = $render('[test-cta title="Construyamos" text="x"]');
    check('cta: botón con etiqueta por defecto del diccionario y contenedor de imagen', strpos($cta, 'href="#contacto">Contáctanos</a>') !== false && strpos($cta, '<div class="cta__media">') !== false);
    check('cta EN: etiqueta traducida', strpos($render('[test-cta title="T"]', 'en'), '>Contact us</a>') !== false);
    $contact = $render('[test-contact title="Hola"]<p>inner</p>[/test-contact]');
    check('contact: eyebrow del diccionario y contenido envolvente', strpos($contact, '<span class="eyebrow">Contacto</span>') !== false && strpos($contact, '<div class="contact__form"><p>inner</p></div>') !== false);
    $news = $render('[test-newsletter]', 'en');
    check('newsletter EN: título, texto, placeholder y botón del diccionario', strpos($news, 'Join our newsletter') !== false && strpos($news, 'placeholder="Enter your email address"') !== false && strpos($news, 'aria-label="Subscribe"') !== false && strpos($news, 'type="email"') !== false);

    echo "4) CSS e íconos\n";
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/styles.css');
    check('estilos de las 6 secciones y botones :empty ocultos', strpos($css, '.hero {') !== false && strpos($css, '.about__inner {') !== false && strpos($css, '.stats__inner {') !== false && strpos($css, '.cta {') !== false && strpos($css, '.contact {') !== false && strpos($css, '.newsletter__form {') !== false && strpos($css, '.btn:empty { display: none; }') !== false);
    check('12 íconos SVG del diseño en assets/img/icons + logo', count(glob(dirname(__DIR__) . '/assets/img/icons/*.svg')) === 12 && file_exists(dirname(__DIR__) . '/assets/img/logo.svg'));
} finally {
    foreach ($created as $id) wp_delete_post($id, true);
    delete_transient(C::TRANSIENT);
    echo "   (limpieza: " . count($created) . " componentes temporales borrados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
