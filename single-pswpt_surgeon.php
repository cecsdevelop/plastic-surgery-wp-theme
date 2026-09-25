<?php
/**
 * Ficha de cirujano: hero interior (o el que traiga el primer bloque, p. ej.
 * un componente "surgeon-hero" armado desde el dashboard) + contenido por
 * idioma. Credenciales y rol viven en el metabox propio; todo lo demás
 * (badges, cita, formación, testimonio, FAQ) se arma por bloques.
 */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main class="site-main">
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('entry surgeon'); ?>>
      <?php if (!pswpt_content_starts_with_hero()) pswpt_render_interior_hero(get_the_title()); ?>
      <div class="entry-content"><?php the_content(); ?></div>
    </article>
  <?php endwhile; ?>
</main>
<?php
get_footer();
