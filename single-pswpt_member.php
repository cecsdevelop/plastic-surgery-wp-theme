<?php
/**
 * Ficha de un miembro del equipo (staff no cirujano): hero interior (o el
 * que traiga el primer bloque) + contenido por idioma. Cargo y LinkedIn
 * viven en el metabox propio; la bio, opcional, por bloques.
 */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main class="site-main">
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('entry team-member'); ?>>
      <?php if (!pswpt_content_starts_with_hero()) pswpt_render_interior_hero(get_the_title()); ?>
      <div class="entry-content"><?php the_content(); ?></div>
    </article>
  <?php endwhile; ?>
</main>
<?php
get_footer();
