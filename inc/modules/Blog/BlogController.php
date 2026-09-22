<?php
/**
 * Blog (posts nativos): lo que la página del blog (home.php) y el detalle
 * (single.php + comments.php) necesitan además de los shortcodes de Sections:
 * áreas de widgets por idioma ("Blog · Barra lateral" para Instagram u otros
 * bloques del detalle, y "Blog · Debajo de la entrada" para el contacto u
 * otra sección), fechas largas/cortas por idioma, bloques de categorías y
 * etiquetas, fila "Regresar al inicio / Compartir" y el callback que pinta
 * cada comentario nativo con el diseño del Figma.
 *
 * Módulo estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Blog;

use IntelindevInit\General\BaseController;
use WP_Comment;
use WP_Post;

class BlogController extends BaseController
{
    public const AREA_SIDEBAR = 'blog-sidebar';
    public const AREA_AFTER   = 'post-after';

    public function register(): void
    {
        add_action('widgets_init', [$this, 'register_sidebars']);
        add_filter('comment_form_fields', [$this, 'comment_field_last']);
    }

    /** Orden del wireframe: nombre y correo primero, el mensaje al final (WP lo pone primero). */
    public function comment_field_last(array $fields): array
    {
        if (isset($fields['comment'])) {
            $comment = $fields['comment'];
            unset($fields['comment']);
            $fields['comment'] = $comment;
        }

        return $fields;
    }

    /* ------------------------------------------------------------------ */
    /* Áreas de widgets por idioma (misma convención que el footer)         */
    /* ------------------------------------------------------------------ */

    public function register_sidebars(): void
    {
        $default = idml_get_default_language();
        $areas   = [
            self::AREA_SIDEBAR => [__('Blog · Barra lateral (%s)', 'intelindev'), __('Detalle de la entrada, debajo de categorías y etiquetas (p. ej. el bloque de Instagram).', 'intelindev')],
            self::AREA_AFTER   => [__('Blog · Debajo de la entrada (%s)', 'intelindev'), __('Sección a lo ancho después del detalle, p. ej. un bloque Shortcode con [inner-contact …][form slug="contacto"][/inner-contact].', 'intelindev')],
        ];
        foreach ($areas as $area => [$name, $description]) {
            foreach (idml_get_languages() as $lang) {
                register_sidebar([
                    'id'            => self::sidebar_id($area, $lang),
                    'name'          => sprintf($name, strtoupper($lang)),
                    'description'   => $description . ' ' . ($lang === $default
                        ? __('Idioma por defecto: se muestra también en los idiomas cuya área esté vacía.', 'intelindev')
                        : sprintf(__('Contenido en %s. Vacío = se muestra el del idioma por defecto.', 'intelindev'), strtoupper($lang))),
                    'before_widget' => '<div id="%1$s" class="widget blog-widget %2$s">',
                    'after_widget'  => '</div>',
                    'before_title'  => '<h2 class="widget-title blog-widget__title">',
                    'after_title'   => '</h2>',
                ]);
            }
        }
    }

    public static function sidebar_id(string $area, string $lang): string
    {
        return $area . '-' . sanitize_key($lang);
    }

    /** Área activa en el idioma actual, o la del idioma por defecto; null si ninguna. */
    public function active_sidebar(string $area, $lang = null): ?string
    {
        $lang = $lang !== null ? idml_normalize_lang($lang) : $this->get_current_lang();
        foreach (array_unique([$lang, idml_get_default_language()]) as $candidate) {
            if (is_active_sidebar(self::sidebar_id($area, $candidate))) {
                return self::sidebar_id($area, $candidate);
            }
        }

        return null;
    }

    public function render_area(string $area, string $class = ''): void
    {
        $id = $this->active_sidebar($area);
        if ($id === null) {
            return;
        }
        echo '<div class="' . esc_attr(trim('blog-area ' . $class)) . '">';
        dynamic_sidebar($id);
        echo '</div>';
    }

    /* ------------------------------------------------------------------ */
    /* Fechas por idioma                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Fecha con el formato del diccionario (date.long: "Miércoles 3 de
     * diciembre, 2025"; date.short: "Diciembre 05, 2026"; date.medium,
     * date.comment) en el idioma pedido, sin depender del idioma del sitio
     * (Ajustes → General) ni de packs instalados: se formatea en en_US y los
     * nombres de mes y día salen de date.months / date.days del diccionario.
     */
    public function format_date(int $timestamp, string $key = 'date.long', $lang = null): string
    {
        $lang   = $lang !== null ? idml_normalize_lang($lang) : $this->get_current_lang();
        $format = idml_t($key, $lang);
        if ($format === $key) {
            $format = (string) get_option('date_format');
        }
        $switched = get_locale() !== 'en_US' && switch_to_locale('en_US');
        $date     = wp_date($format, $timestamp);
        if ($switched) {
            restore_previous_locale();
        }

        $names = [];
        foreach (['date.months' => 12, 'date.days' => 7] as $dict => $count) {
            $en = explode('|', idml_t($dict, 'en'));
            $to = explode('|', idml_t($dict, $lang));
            if (count($en) === $count && count($to) === $count) {
                $names += array_combine($en, $to);
            }
        }
        $date = $names ? strtr($date, $names) : $date;

        return function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($date, 0, 1)) . mb_substr($date, 1) : ucfirst($date);
    }

    public function date(WP_Post $post, string $key = 'date.long', $lang = null): string
    {
        return $this->format_date(get_post_timestamp($post), $key, $lang);
    }

    public function time_html(WP_Post $post, string $key = 'date.long', string $class = ''): string
    {
        return '<time class="' . esc_attr($class) . '" datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html($this->date($post, $key)) . '</time>';
    }

    /* ------------------------------------------------------------------ */
    /* Bloques del detalle                                                  */
    /* ------------------------------------------------------------------ */

    /** "Categorías" (todas, enlazadas) y "Etiquetas" (todas, en línea); vacío si no hay. */
    public function taxonomies_html($lang = null): string
    {
        $lang = $lang !== null ? idml_normalize_lang($lang) : $this->get_current_lang();
        $html = '';

        $categories = get_categories(['hide_empty' => true]);
        if ($categories) {
            $items = '';
            foreach ($categories as $category) {
                $items .= '<li><a href="' . esc_url(get_category_link($category)) . '">' . esc_html($category->name) . '</a></li>';
            }
            $html .= '<section class="blog-widget blog-widget--categories"><h2 class="blog-widget__title">' . esc_html(idml_t('blog.categories', $lang)) . '</h2><ul class="blog-widget__list">' . $items . '</ul></section>';
        }

        $tags = get_tags(['hide_empty' => true, 'number' => 20, 'orderby' => 'count', 'order' => 'DESC']);
        if ($tags) {
            $items = '';
            foreach ($tags as $tag) {
                $items .= '<li><a href="' . esc_url(get_tag_link($tag)) . '">' . esc_html($tag->name) . '</a></li>';
            }
            $html .= '<section class="blog-widget blog-widget--tags"><h2 class="blog-widget__title">' . esc_html(idml_t('blog.tags', $lang)) . '</h2><ul class="blog-widget__tags">' . $items . '</ul></section>';
        }

        return $html;
    }

    /** Fila "Regresar al inicio" (página del blog en el idioma) y "Compartir" (Web Share / copiar enlace, scripts.js). */
    public function tools_html(WP_Post $post, $lang = null): string
    {
        $lang = $lang !== null ? idml_normalize_lang($lang) : $this->get_current_lang();
        $blog = (int) get_option('page_for_posts');
        $back = $blog > 0 ? get_permalink($blog) : home_url('/');

        return '<div class="post-tools">'
            . '<a class="post-tools__back" href="' . esc_url($back) . '">' . esc_html(idml_t('blog.back', $lang)) . '</a>'
            . '<button type="button" class="post-tools__share" data-share-url="' . esc_url(get_permalink($post)) . '" data-share-title="' . esc_attr(get_the_title($post)) . '" data-copied-label="' . esc_attr(idml_t('blog.link_copied', $lang)) . '">'
            . '<span>' . esc_html(idml_t('blog.share', $lang)) . '</span><span class="post-tools__share-icon" aria-hidden="true"></span></button></div>';
    }

    /* ------------------------------------------------------------------ */
    /* Comentarios nativos con el diseño del Figma                          */
    /* ------------------------------------------------------------------ */

    /** Callback de wp_list_comments(): autor, fecha, texto y "Responder"; el <li> lo cierra WP. */
    public static function comment(WP_Comment $comment, array $args, int $depth): void
    {
        $lang     = function_exists('idml_get_current_language') ? idml_get_current_language() : 'es';
        $approved = (string) $comment->comment_approved === '1';
        echo '<li ' . comment_class('comment-item', $comment, null, false) . ' id="comment-' . esc_attr((string) $comment->comment_ID) . '">';
        echo '<article class="comment-item__body" id="div-comment-' . esc_attr((string) $comment->comment_ID) . '">';
        echo '<header class="comment-item__meta">' . get_avatar($comment, 56, '', '', ['class' => 'comment-item__avatar'])
            . '<span class="comment-item__author">' . esc_html(get_comment_author($comment)) . '</span>'
            . '<time class="comment-item__date" datetime="' . esc_attr(get_comment_date('c', $comment)) . '">' . esc_html((new self())->format_date((int) get_comment_time('U', true, false, $comment), 'date.comment', $lang)) . '</time></header>';
        if (!$approved) {
            echo '<p class="comment-item__pending">' . esc_html(idml_t('comments.pending', $lang)) . '</p>';
        }
        echo '<div class="comment-item__text">' . apply_filters('comment_text', get_comment_text($comment), $comment, $args) . '</div>';
        $reply = get_comment_reply_link(array_merge($args, ['depth' => $depth, 'max_depth' => (int) ($args['max_depth'] ?? 0), 'reply_text' => idml_t('comments.reply', $lang), 'before' => '<span class="comment-item__reply">', 'after' => '</span>']), $comment);
        echo $reply ?: '';
        echo '</article>';
    }

    /** Argumentos de comment_form() con las etiquetas del diccionario y el botón del diseño. */
    public function comment_form_args($lang = null): array
    {
        $lang      = $lang !== null ? idml_normalize_lang($lang) : $this->get_current_lang();
        $commenter = wp_get_current_commenter();
        $required  = (bool) get_option('require_name_email');
        $req_attr  = $required ? ' required' : '';
        $fields    = [
            'author' => '<p class="comment-form-author"><label for="author">' . esc_html(idml_t('comments.name', $lang)) . ($required ? ' <span class="required">*</span>' : '') . '</label><input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" maxlength="245" autocomplete="name"' . $req_attr . '></p>',
            'email'  => '<p class="comment-form-email"><label for="email">' . esc_html(idml_t('comments.email', $lang)) . ($required ? ' <span class="required">*</span>' : '') . '</label><input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" maxlength="100" autocomplete="email"' . $req_attr . '></p>',
        ];
        if ((bool) get_option('show_comments_cookies_opt_in')) {
            $consent = empty($commenter['comment_author_email']) ? '' : ' checked';
            $fields['cookies'] = '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . '> <label for="wp-comment-cookies-consent">' . esc_html(idml_t('comments.cookies', $lang)) . '</label></p>';
        }

        return [
            'fields'               => $fields,
            'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . esc_html(idml_t('comments.message', $lang)) . ' <span class="required">*</span></label><textarea id="comment" name="comment" rows="4" maxlength="65525" required></textarea></p>',
            'title_reply'          => idml_t('comments.title', $lang),
            'title_reply_to'       => idml_t('comments.title_reply_to', $lang),
            'title_reply_before'   => '<h2 id="reply-title" class="comment-reply-title comments__form-title">',
            'title_reply_after'    => '</h2>',
            'cancel_reply_before'  => ' <small class="comments__cancel">',
            'cancel_reply_after'   => '</small>',
            'cancel_reply_link'    => idml_t('comments.cancel', $lang),
            'comment_notes_before' => '<p class="comment-notes comments__notes">' . esc_html(idml_t('comments.notes', $lang)) . '</p>',
            'logged_in_as'         => '',
            'label_submit'         => idml_t('comments.submit', $lang),
            'class_submit'         => 'btn btn--primary btn--arrow',
            'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
            'submit_field'         => '<p class="form-submit comments__submit">%1$s %2$s</p>',
            'class_container'      => 'comment-respond comments__respond',
            'class_form'           => 'comment-form comments__form',
            'must_log_in'          => '<p class="must-log-in">' . sprintf(wp_kses(idml_t('comments.must_log_in', $lang), ['a' => ['href' => []]]), esc_url(wp_login_url(get_permalink()))) . '</p>',
        ];
    }
}
