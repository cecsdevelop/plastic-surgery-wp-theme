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
    <?php the_posts_pagination(); ?>
  <?php else : ?>
    <p><?php esc_html_e('Nothing found.', 'intelindev'); ?></p>
  <?php endif; ?>
</main>
<?php
get_footer();
