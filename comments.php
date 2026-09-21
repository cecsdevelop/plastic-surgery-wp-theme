<?php
/**
 * Comentarios nativos con el diseño del Figma: lista (avatar, autor, fecha,
 * texto, responder) y formulario "Deja un comentario" con campos subrayados
 * y botón rojo. Etiquetas del diccionario (BlogController::comment_form_args).
 */
if (!defined('ABSPATH')) exit;
if (post_password_required()) {
    return;
}
$blog = new \IntelindevInit\Blog\BlogController();
$lang = $blog->get_current_lang();
?>
<section id="comments" class="comments">
  <?php if (have_comments()) : ?>
    <h2 class="comments__title"><?php echo esc_html(idml_t('comments.list_title', $lang)); ?> <span class="comments__count">(<?php echo esc_html(number_format_i18n(get_comments_number())); ?>)</span></h2>
    <ol class="comments__list">
      <?php wp_list_comments(['style' => 'ol', 'short_ping' => true, 'callback' => [\IntelindevInit\Blog\BlogController::class, 'comment']]); ?>
    </ol>
    <?php the_comments_navigation(['prev_text' => esc_html(idml_t('nav.prev_page', $lang)), 'next_text' => esc_html(idml_t('nav.next_page', $lang)), 'screen_reader_text' => esc_html(idml_t('nav.pagination_label', $lang)), 'aria_label' => idml_t('nav.pagination_label', $lang)]); ?>
  <?php endif; ?>
  <?php comment_form($blog->comment_form_args($lang)); ?>
</section>
