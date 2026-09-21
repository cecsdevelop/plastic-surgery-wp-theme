<?php
/**
 * Listado de proyectos (/portafolio/, /en/portfolio/ — Portafolio del Figma).
 * El cuerpo es la "Página de Portafolio" de Ajustes → Lectura: su hero y sus
 * bloques por idioma ([projects layout="cards"], [inner-contact]…). Sin
 * página asignada: hero con el nombre del tipo y la grilla de tarjetas.
 */
if (!defined('ABSPATH')) exit;
get_header();
$archive_page = intelindev_get_archive_page(\IntelindevInit\Portfolio\PortfolioController::POST_TYPE);
?>
<main class="site-main">
  <?php if ($archive_page) : ?>
    <?php intelindev_render_archive_page($archive_page); ?>
  <?php else : ?>
    <?php intelindev_render_interior_hero(post_type_archive_title('', false), '', false); ?>
    <div class="entry-content"><?php echo do_shortcode('[projects layout="cards" limit="-1"]'); ?></div>
  <?php endif; ?>
</main>
<?php
get_footer();
