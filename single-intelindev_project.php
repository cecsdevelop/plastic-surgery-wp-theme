<?php
/**
 * Detalle de proyecto (Proyecto Detalle del Figma): hero interior con la
 * imagen destacada, contenido (bloques por idioma) + galería en la columna
 * principal y la ficha "Información del proyecto" a la derecha.
 */
if (!defined('ABSPATH')) exit;
get_header();
$portfolio = new \IntelindevInit\Portfolio\PortfolioController();
?>
<main class="site-main">
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('entry project'); ?>>
      <?php if (!intelindev_content_starts_with_hero()) intelindev_render_interior_hero(get_the_title()); ?>
      <div class="project__inner wrap">
        <div class="project__main entry-content"><?php the_content(); echo $portfolio->gallery_html(get_post()); ?></div>
        <aside class="project__aside"><?php echo $portfolio->info_html(get_post()); ?></aside>
      </div>
    </article>
  <?php endwhile; ?>
</main>
<?php
get_footer();
