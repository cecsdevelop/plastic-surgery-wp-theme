<?php
if (!defined('ABSPATH')) exit;
get_header();
?>
<main class="site-main">
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('entry'); ?>>
      <h1 class="entry-title"><?php the_title(); ?></h1>
      <time class="entry-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
      <div class="entry-content"><?php the_content(); ?></div>
    </article>
    <?php the_post_navigation(); ?>
  <?php endwhile; ?>
</main>
<?php
get_footer();
