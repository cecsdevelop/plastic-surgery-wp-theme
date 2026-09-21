<?php
/**
 * Índice de entradas (/blog/, /en/blog/ — Blog del Figma). El cuerpo es la
 * página asignada en Ajustes → Lectura → "Página de entradas": su hero y sus
 * bloques por idioma ([latest_posts layout="cards"] lista la query principal
 * paginada, [inner-contact]…). Sin página asignada: hero + tarjetas.
 */
if (!defined('ABSPATH')) exit;
get_header();
$blog_page = get_post((int) get_option('page_for_posts'));
$blog_page = $blog_page instanceof WP_Post && $blog_page->post_status === 'publish' ? $blog_page : null;
?>
<main class="site-main">
  <?php if ($blog_page) : ?>
    <?php intelindev_render_archive_page($blog_page); ?>
  <?php else : ?>
    <?php intelindev_render_interior_hero(get_bloginfo('name'), '', false); ?>
    <div class="entry-content"><?php echo do_shortcode('[latest_posts layout="cards"]'); ?></div>
  <?php endif; ?>
</main>
<?php
get_footer();
