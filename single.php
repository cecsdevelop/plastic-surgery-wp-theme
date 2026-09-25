<?php
/**
 * Detalle de entrada (Blog Post del Figma): hero interior con la imagen
 * destacada; a la izquierda fecha larga, contenido, fila "Regresar al inicio
 * / Compartir" y comentarios nativos (comments.php); a la derecha categorías,
 * etiquetas y el área de widgets "Blog · Barra lateral"; debajo, el área
 * "Blog · Debajo de la entrada" (p. ej. [inner-contact]).
 */
if (!defined('ABSPATH')) exit;
get_header();
$blog = new \pswptInit\Blog\BlogController();
?>
<main class="site-main">
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('entry post-single'); ?>>
      <?php if (!pswpt_content_starts_with_hero()) pswpt_render_interior_hero(get_the_title()); ?>
      <div class="post-single__inner wrap">
        <div class="post-single__main">
          <?php echo $blog->time_html(get_post(), 'date.long', 'post-single__date'); ?>
          <div class="entry-content"><?php the_content(); ?></div>
          <?php echo $blog->tools_html(get_post()); ?>
          <?php if (comments_open() || get_comments_number()) comments_template(); ?>
        </div>
        <aside class="post-single__aside">
          <?php echo $blog->taxonomies_html(); $blog->render_area(\pswptInit\Blog\BlogController::AREA_SIDEBAR, 'post-single__widgets'); ?>
        </aside>
      </div>
    </article>
    <?php $blog->render_area(\pswptInit\Blog\BlogController::AREA_AFTER, 'post-after'); ?>
  <?php endwhile; ?>
</main>
<?php
get_footer();
