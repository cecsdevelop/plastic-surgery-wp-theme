<?php
if (!defined('ABSPATH')) exit;
get_header();
?>
<main class="site-main">
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('entry'); ?>>
      <?php
      // Las páginas armadas con componentes traen su propio hero (con el <h1>);
      // las demás reciben el hero interior con el título sobre la imagen destacada.
      if (!intelindev_content_starts_with_hero()) {
        intelindev_render_interior_hero(get_the_title());
      }
      ?>
      <div class="entry-content"><?php the_content(); ?></div>
    </article>
  <?php endwhile; ?>
</main>
<?php
get_footer();
