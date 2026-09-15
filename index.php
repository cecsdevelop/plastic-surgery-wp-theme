<?php
if (!defined('ABSPATH')) exit;
get_header();
?>
<main class="site-main">
  <?php if (have_posts()) : ?>
    <?php while (have_posts()) : the_post(); ?>
      <article <?php post_class('entry'); ?>>
        <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <div class="entry-summary"><?php the_excerpt(); ?></div>
      </article>
    <?php endwhile; ?>
    <?php
    the_posts_pagination([
      'prev_text'          => esc_html(idml_t('nav.prev_page')),
      'next_text'          => esc_html(idml_t('nav.next_page')),
      'screen_reader_text' => esc_html(idml_t('nav.pagination_label')),
      'aria_label'         => idml_t('nav.pagination_label'),
    ]);
    ?>
  <?php else : ?>
    <p><?php echo esc_html(idml_t('archive.nothing_found')); ?></p>
  <?php endif; ?>
</main>
<?php
get_footer();
