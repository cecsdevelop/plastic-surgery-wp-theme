<?php
/**
 * Blog: página de entradas (home.php con los bloques de la "Página de
 * entradas", [latest_posts layout="cards"] sobre la query principal y
 * paginación ES/EN), detalle (single.php: fecha por idioma, categorías y
 * etiquetas, "Regresar/Compartir", comentarios nativos, áreas de widgets por
 * idioma) y módulo Blog (fechas, comentario callback, áreas).
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-blog.php
 *
 * Crea entradas, un comentario y widgets temporales y baja posts_per_page a 2
 * mientras corre; restaura todo al final.
 */
use IntelindevInit\Blog\BlogController;

// Sin WP_ADMIN: los filtros de permalink por idioma se saltan en el admin a propósito.
$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/'; define('WP_USE_THEMES', false);
require dirname(__DIR__, 4) . '/wp-load.php';
wp_set_current_user(1);

$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));
$code = fn(string $path) => (string) shell_exec('curl -s -o /dev/null -w "%{http_code}" ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));
// Contexto EN (los permalinks siguen el idioma actual, no un parámetro).
$front_en = fn(string $php) => trim((string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg('$_SERVER["HTTP_HOST"]="localhost:8888"; $_SERVER["REQUEST_URI"]="/Intelindev/en/"; define("WP_USE_THEMES",false); require "' . ABSPATH . 'wp-load.php"; ' . $php) . ' 2>/dev/null'));

$blog = new BlogController();
$orig_ppp = get_option('posts_per_page'); $orig_sidebars = get_option('sidebars_widgets'); $orig_blocks = get_option('widget_block');
$created = []; $comment_id = 0;

try {
    echo "1) módulo Blog\n";
    check('áreas por idioma registradas', is_registered_sidebar('blog-sidebar-es') && is_registered_sidebar('blog-sidebar-en') && is_registered_sidebar('post-after-es') && is_registered_sidebar('post-after-en'));
    $p1 = wp_insert_post(['post_type' => 'post', 'post_title' => 'Entrada Test Blog Uno', 'post_name' => 'entrada-test-blog-uno', 'post_status' => 'publish', 'post_date' => '2025-12-03 10:00:00']);
    update_post_meta($p1, INTELINDEV_POST_TRANSLATED_TITLE_META, ['en' => 'Test Blog Post One']);
    update_post_meta($p1, intelindev_post_translated_slug_meta_key('en'), 'test-blog-post-one');
    update_post_meta($p1, INTELINDEV_POST_TRANSLATED_EXCERPT_META, ['es' => 'Resumen uno', 'en' => 'Summary one']);
    update_post_meta($p1, INTELINDEV_POST_TRANSLATED_CONTENT_META, ['es' => ['<h2>Problema</h2><p>Cuerpo ES</p>'], 'en' => ['<h2>Problem</h2><p>Body EN</p>']]);
    // Dos y Tres son las más recientes (primera página); Uno (2025) queda en la última.
    $p2 = wp_insert_post(['post_type' => 'post', 'post_title' => 'Entrada Test Blog Dos', 'post_name' => 'entrada-test-blog-dos', 'post_status' => 'publish', 'post_date' => gmdate('Y-m-d', time() - 3 * DAY_IN_SECONDS) . ' 10:00:00']);
    $p3 = wp_insert_post(['post_type' => 'post', 'post_title' => 'Entrada Test Blog Tres', 'post_name' => 'entrada-test-blog-tres', 'post_status' => 'publish', 'post_date' => gmdate('Y-m-d', time() - 2 * DAY_IN_SECONDS) . ' 10:00:00']);
    array_push($created, $p1, $p2, $p3);
    $p3_short = $blog->date(get_post($p3), 'date.short', 'es'); $p3_short_en = $blog->date(get_post($p3), 'date.short', 'en');
    $last = (int) ceil(((int) wp_count_posts('post')->publish) / 2);
    check('fecha larga/corta por idioma (ES con locale del sitio, EN con en_US)', $blog->date(get_post($p1), 'date.long', 'es') === 'Miércoles 3 de diciembre, 2025' && $blog->date(get_post($p1), 'date.long', 'en') === 'Wednesday, December 3, 2025' && $blog->date(get_post($p1), 'date.short', 'es') === 'Diciembre 03, 2025' && $blog->date(get_post($p1), 'date.short', 'en') === 'December 03, 2025');
    $tools = $front_en('echo (new IntelindevInit\Blog\BlogController())->tools_html(get_post(' . $p1 . '));');
    check('tools_html EN: vuelta a /en/blog/ y botón compartir con URL, título y etiqueta "copiado"', strpos($tools, '<a class="post-tools__back" href="http://localhost:8888/Intelindev/en/blog/">Back to blog</a>') !== false && strpos($tools, 'data-share-url="http://localhost:8888/Intelindev/en/test-blog-post-one/"') !== false && strpos($tools, 'data-copied-label="Link copied"') !== false && strpos($tools, '<span>Share</span>') !== false);
    $args = $blog->comment_form_args('es');
    check('comment_form_args: etiquetas del diccionario, botón <button> rojo con flecha, mensaje al final vía filtro', strpos($args['fields']['author'], '<label for="author">NOMBRE') !== false && $args['label_submit'] === 'Publicar comentario' && $args['class_submit'] === 'btn btn--primary btn--arrow' && strpos($args['submit_button'], '<button') === 0 && array_keys($blog->comment_field_last(['comment' => 'c', 'author' => 'a', 'email' => 'e'])) === ['author', 'email', 'comment']);

    echo "2) página de entradas (curl, posts_per_page = 2)\n";
    update_option('posts_per_page', 2);
    $es = $curl('/blog/');
    check('ES: hero de la página del blog, cabecera y 2 tarjetas de la query principal con fecha corta, título, excerpt y "+"', strpos($es, '<h1 class="hero__title">Blog</h1>') !== false && strpos($es, '<section class="posts section posts--cards">') !== false && substr_count($es, '<article class="post-card">') === 2 && strpos($es, '>' . $p3_short . '</time>') !== false && strpos($es, '<h3 class="post-card__title"><a href="http://localhost:8888/Intelindev/entrada-test-blog-tres/">Entrada Test Blog Tres</a></h3>') !== false && strpos($es, 'class="post-card__more" href="http://localhost:8888/Intelindev/entrada-test-blog-tres/"><span class="screen-reader-text">Leer más</span>') !== false);
    check('ES: paginación con el actual en círculo, página 2 y salto a la última', preg_match('#<nav class="pagination" aria-label="[^"]+"><span aria-current="page" class="page-numbers current">1</span><a class="page-numbers" href="http://localhost:8888/Intelindev/blog/page/2/">2</a>#', $es) === 1 && strpos($es, 'class="page-numbers page-numbers--last" href="http://localhost:8888/Intelindev/blog/page/') !== false);
    $es2 = $curl('/blog/page/' . $last . '/');
    check('ES última página: la entrada más vieja, sin salto a "última"', $code('/blog/page/' . $last . '/') === '200' && strpos($es2, 'Entrada Test Blog Uno') !== false && strpos($es2, 'Entrada Test Blog Tres') === false && strpos($es2, '<span aria-current="page" class="page-numbers current">' . $last . '</span>') !== false && strpos($es2, 'page-numbers--last') === false);
    $en = $curl('/en/blog/');
    check('EN: título, fecha y excerpt traducidos, enlaces /en/, paginación /en/blog/page/2/', strpos($en, '<h1 class="hero__title">Blog</h1>') !== false && strpos($en, '>' . $p3_short_en . '</time>') !== false && strpos($en, 'href="http://localhost:8888/Intelindev/en/blog/page/2/">2</a>') !== false);
    $en2 = $curl('/en/blog/page/' . $last . '/');
    check('EN última página resuelve (regla /{lang}/{slug}/page/N/) con la entrada uno traducida', $code('/en/blog/page/' . $last . '/') === '200' && strpos($en2, '>Test Blog Post One</a>') !== false && strpos($en2, 'href="http://localhost:8888/Intelindev/en/test-blog-post-one/"') !== false && strpos($en2, 'Summary one') !== false);
    check('página fuera de rango: 404 (ES) o vuelta a /en/blog/ (redirect_canonical con page_id)', $code('/blog/page/' . ($last + 5) . '/') === '404' && in_array($code('/en/blog/page/' . ($last + 5) . '/'), ['404', '301'], true));

    echo "3) detalle (curl)\n";
    $comment_id = wp_insert_comment(['comment_post_ID' => $p1, 'comment_author' => 'Tester Blog', 'comment_author_email' => 'tester@example.com', 'comment_content' => 'Comentario de prueba.', 'comment_approved' => 1]);
    $blocks = is_array($orig_blocks) ? $orig_blocks : []; $sidebars = is_array($orig_sidebars) ? $orig_sidebars : [];
    $next = 900; while (isset($blocks[$next])) $next++;
    $blocks[$next] = ['content' => '<!-- wp:paragraph --><p class="test-after-es">Debajo ES</p><!-- /wp:paragraph -->'];
    $blocks[$next + 1] = ['content' => '<!-- wp:paragraph --><p class="test-sidebar-es">Lateral ES</p><!-- /wp:paragraph -->'];
    $sidebars['post-after-es'] = ['block-' . $next]; $sidebars['post-after-en'] = []; $sidebars['blog-sidebar-es'] = ['block-' . ($next + 1)];
    update_option('widget_block', $blocks); update_option('sidebars_widgets', $sidebars);
    $d = $curl('/entrada-test-blog-uno/');
    check('ES: hero, fecha larga, contenido, categorías/etiquetas, widget lateral, tools y área debajo', strpos($d, '<h1 class="hero__title">Entrada Test Blog Uno</h1>') !== false && strpos($d, '>Miércoles 3 de diciembre, 2025</time>') !== false && strpos($d, '<h2>Problema</h2>') !== false && strpos($d, '<h2 class="blog-widget__title">Categorías</h2>') !== false && strpos($d, 'class="test-sidebar-es wp-block-paragraph">Lateral ES</p>') !== false && strpos($d, '<a class="post-tools__back" href="http://localhost:8888/Intelindev/blog/">Regresar al inicio</a>') !== false && strpos($d, '<div class="blog-area post-after"><div id="block-' . $next . '"') !== false && strpos($d, 'class="test-after-es wp-block-paragraph">Debajo ES</p>') !== false);
    check('ES: comentarios nativos con el callback (autor, texto, responder) y formulario del diseño', strpos($d, '<h2 class="comments__title">Comentarios <span class="comments__count">(1)</span></h2>') !== false && strpos($d, '<span class="comment-item__author">Tester Blog</span>') !== false && strpos($d, '<div class="comment-item__text"><p>Comentario de prueba.</p>') !== false && strpos($d, 'class="comment-reply-link"') !== false && strpos($d, '<h2 id="reply-title" class="comment-reply-title comments__form-title">Deja un comentario') !== false && strpos($d, '<button name="submit" type="submit" id="submit" class="btn btn--primary btn--arrow">Publicar comentario</button>') !== false && strpos($d, '<label for="comment">MENSAJE') > strpos($d, '<label for="email">CORREO ELECTRÓNICO'));
    $de = $curl('/en/test-blog-post-one/');
    check('EN: fecha, contenido, etiquetas de comentarios y área debajo con fallback al ES', strpos($de, '>Wednesday, December 3, 2025</time>') !== false && strpos($de, '<p>Body EN</p>') !== false && strpos($de, '>Leave a comment') !== false && strpos($de, '<label for="author">NAME') !== false && strpos($de, '<h2 class="blog-widget__title">Categories</h2>') !== false && strpos($de, 'class="test-after-es wp-block-paragraph">Debajo ES</p>') !== false);
    check('sin <h1 class="entry-title"> y un solo <h1>', strpos($d, 'entry-title') === false && substr_count($d, '<h1') === 1);
} finally {
    update_option('posts_per_page', $orig_ppp);
    update_option('sidebars_widgets', $orig_sidebars); update_option('widget_block', $orig_blocks);
    if ($comment_id) wp_delete_comment($comment_id, true);
    foreach ($created as $id) wp_delete_post($id, true);
    echo "   (limpieza: posts_per_page, widgets, comentario y entradas restaurados)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
