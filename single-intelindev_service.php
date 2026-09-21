<?php
/**
 * Detalle de servicio (Servicios Detalle del Figma): hero interior con la
 * imagen destacada, contenido (bloques por idioma) en la columna principal y
 * la lista de servicios a la derecha con el actual resaltado.
 */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main class="site-main">
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('entry service'); ?>>
      <?php if (!intelindev_content_starts_with_hero()) intelindev_render_interior_hero(get_the_title()); ?>
      <div class="service__inner wrap">
        <div class="service__main entry-content"><?php the_content(); ?></div>
        <aside class="service__aside"><?php echo do_shortcode('[services layout="list" limit="-1"]'); ?></aside>
      </div>
    </article>
  <?php endwhile; ?>
</main>
<?php
get_footer();
