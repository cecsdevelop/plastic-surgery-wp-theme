<?php
/**
 * Secciones dinámicas del diseño (shortcodes PHP que leen los CPT de
 * contenido): a diferencia de los Componentes del dashboard (HTML con
 * placeholders), estas necesitan consultas y bucles, así que viven en el
 * theme. Los textos editables (eyebrow, título, botón) entran por atributos
 * por idioma —escritos en el bloque de cada idioma— o por el diccionario.
 *
 *   [clients]                        franja de logos (CPT Clientes)
 *   [services limit="4"]             tarjetas de servicios (CPT Servicios)
 *   [projects limit="4"]             proyectos destacados (CPT Portafolio)
 *   [latest_posts limit="3"]         últimas entradas del blog
 *   [testimonials]                   citas de clientes (CPT Testimonios)
 *
 * Todos aceptan eyebrow="" title="" text="" cta_text="" cta_url="" y
 * marcan el HTML con clases BEM propias (CSS en styles.css, sección
 * "Secciones"). Sin JS: los carruseles son scroll horizontal con snap.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Sections;

use IntelindevInit\General\BaseController;
use IntelindevInit\Clients\ClientsController;
use IntelindevInit\Services\ServicesController;
use IntelindevInit\Portfolio\PortfolioController;
use IntelindevInit\Testimonials\TestimonialsController;
use WP_Post;

class SectionsController extends BaseController
{
    public function register(): void
    {
        add_action('init', [$this, 'register_shortcodes'], 20);
    }

    public function register_shortcodes(): void
    {
        add_shortcode('clients', [$this, 'clients']);
        add_shortcode('services', [$this, 'services']);
        add_shortcode('projects', [$this, 'projects']);
        add_shortcode('latest_posts', [$this, 'latest_posts']);
        add_shortcode('testimonials', [$this, 'testimonials']);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    /** Cabecera común de sección: eyebrow + título (con *acento* → <em>) + texto. */
    private function heading(array $atts, string $class, string $level = 'h2', bool $serif = false): string
    {
        $eyebrow = trim((string) ($atts['eyebrow'] ?? ''));
        $title   = trim((string) ($atts['title'] ?? ''));
        $text    = trim((string) ($atts['text'] ?? ''));
        if ($eyebrow === '' && $title === '' && $text === '') {
            return '';
        }
        $html = '<div class="' . esc_attr($class) . '__heading">';
        if ($eyebrow !== '') {
            $html .= '<span class="eyebrow">' . esc_html($eyebrow) . '</span>';
        }
        if ($title !== '') {
            $html .= '<' . $level . ' class="' . esc_attr($class) . '__title' . ($serif ? ' accent' : '') . '">' . \IntelindevInit\Components\ComponentsController::format_attribute($title, 'html') . '</' . $level . '>';
        }
        if ($text !== '') {
            $html .= '<p class="' . esc_attr($class) . '__text">' . esc_html($text) . '</p>';
        }
        return $html . '</div>';
    }

    /* ------------------------------------------------------------------ */
    /* [clients]                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Franja de logos: tarjetas 263×175 en scroll horizontal con desvanecido
     * en los bordes. Logo = imagen destacada del Cliente; con URL, enlaza.
     */
    public function clients($atts = []): string
    {
        $atts    = shortcode_atts(['eyebrow' => '', 'title' => '', 'text' => '', 'limit' => -1], is_array($atts) ? $atts : [], 'clients');
        $clients = (new ClientsController())->get_items(['numberposts' => (int) $atts['limit']]);
        $clients = array_filter($clients, fn(WP_Post $c) => has_post_thumbnail($c));
        if (!$clients) {
            return '';
        }

        $items = '';
        foreach ($clients as $client) {
            $logo = get_the_post_thumbnail($client, 'medium', ['class' => 'clients__logo', 'alt' => get_the_title($client), 'loading' => 'lazy']);
            $url  = (string) get_post_meta($client->ID, '_' . ClientsController::POST_TYPE . '_url', true);
            $items .= '<li class="clients__item">' . ($url !== '' ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $logo . '</a>' : $logo) . '</li>';
        }

        return '<section class="clients"><div class="clients__inner wrap">' . $this->heading($atts, 'clients')
            . '<ul class="clients__list" aria-label="' . esc_attr(idml_t('clients.eyebrow', $this->get_current_lang())) . '">' . $items . '</ul></div></section>';
    }

    /* ------------------------------------------------------------------ */
    /* [services]                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Tarjetas de servicio (362×421, gris claro con borde degradado rosa):
     * ícono del campo "icon" (o el del theme por posición), título, excerpt y
     * "Conocer más" al detalle. Cabecera centrada.
     */
    public function services($atts = []): string
    {
        $atts  = shortcode_atts(['eyebrow' => '', 'title' => '', 'text' => '', 'limit' => 4, 'more' => ''], is_array($atts) ? $atts : [], 'services');
        $ctrl  = new ServicesController();
        $items = $ctrl->get_items(['numberposts' => (int) $atts['limit']]);
        if (!$items) {
            return '';
        }
        $lang  = $this->get_current_lang();
        $more  = trim((string) $atts['more']) !== '' ? (string) $atts['more'] : idml_t('services.more', $lang);
        $fallback_icons = ['code', 'design', 'update', 'search'];

        $cards = '';
        foreach (array_values($items) as $i => $service) {
            $icon_id = (int) $ctrl->get_field($service, 'icon');
            $icon    = $icon_id > 0
                ? wp_get_attachment_image($icon_id, 'thumbnail', false, ['class' => 'services__icon', 'alt' => '', 'loading' => 'lazy'])
                : '<img class="services__icon" src="' . esc_url(get_template_directory_uri() . '/assets/img/icons/' . $fallback_icons[$i % 4] . '.svg') . '" alt="" width="72" height="72" loading="lazy">';
            $excerpt = function_exists('intelindev_get_post_translated_excerpt') ? intelindev_get_post_translated_excerpt($service, $lang) : '';
            $cards .= '<article class="services__card">' . $icon
                . '<div class="services__body"><h3 class="services__name"><a href="' . esc_url(get_permalink($service)) . '">' . esc_html(get_the_title($service)) . '</a></h3>'
                . ($excerpt !== '' ? '<p class="services__excerpt">' . esc_html($excerpt) . '</p>' : '') . '</div>'
                . '<a class="services__more" href="' . esc_url(get_permalink($service)) . '">' . esc_html($more) . '<span class="services__more-icon" aria-hidden="true"></span></a></article>';
        }

        return '<section class="services section"><div class="services__inner wrap">' . $this->heading($atts, 'services')
            . '<div class="services__grid">' . $cards . '</div></div></section>';
    }

    /* ------------------------------------------------------------------ */
    /* [projects]                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Proyectos destacados: tarjetas 440×480 con la imagen destacada de
     * fondo y degradado, título + excerpt abajo; scroll horizontal con
     * flechas (scripts.js mueve el scroll, sin librerías).
     */
    public function projects($atts = []): string
    {
        $atts  = shortcode_atts(['eyebrow' => '', 'title' => '', 'text' => '', 'limit' => 4], is_array($atts) ? $atts : [], 'projects');
        $items = (new PortfolioController())->get_items(['numberposts' => (int) $atts['limit']]);
        if (!$items) {
            return '';
        }
        $lang  = $this->get_current_lang();
        $cards = '';
        foreach ($items as $project) {
            $image   = get_the_post_thumbnail_url($project, 'large');
            $excerpt = function_exists('intelindev_get_post_translated_excerpt') ? intelindev_get_post_translated_excerpt($project, $lang) : '';
            $cards .= '<li class="projects__item"><a class="projects__card" href="' . esc_url(get_permalink($project)) . '"' . ($image ? ' style="background-image:url(\'' . esc_url($image) . '\')"' : '') . '>'
                . '<span class="projects__body"><span class="projects__name">' . esc_html(get_the_title($project)) . '</span>'
                . ($excerpt !== '' ? '<span class="projects__excerpt">' . esc_html($excerpt) . '</span>' : '') . '</span></a></li>';
        }

        return '<section class="projects section"><div class="projects__inner wrap">'
            . '<div class="projects__top">' . $this->heading($atts, 'projects') . $this->arrows('projects') . '</div>'
            . '<ul class="projects__list" data-scroller>' . $cards . '</ul></div></section>';
    }

    /* ------------------------------------------------------------------ */
    /* [latest_posts]                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Últimas entradas en acordeón horizontal: la primera abierta (imagen +
     * título serif + fecha + excerpt + botón), el resto plegadas con el
     * título en vertical; al pasar el mouse se abre la que toque (solo CSS).
     */
    public function latest_posts($atts = []): string
    {
        $atts  = shortcode_atts(['eyebrow' => '', 'title' => '', 'text' => '', 'limit' => 4, 'more' => ''], is_array($atts) ? $atts : [], 'latest_posts');
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => (int) $atts['limit'], 'suppress_filters' => false]);
        if (!$posts) {
            return '';
        }
        $lang = $this->get_current_lang();
        $more = trim((string) $atts['more']) !== '' ? (string) $atts['more'] : idml_t('posts.read_more', $lang);

        $items = '';
        foreach ($posts as $post) {
            $excerpt = function_exists('intelindev_get_post_translated_excerpt') ? intelindev_get_post_translated_excerpt($post, $lang) : '';
            if ($excerpt === '') {
                $excerpt = wp_trim_words(wp_strip_all_tags((string) $post->post_excerpt), 20);
            }
            $items .= '<li class="posts__item">'
                . '<a class="posts__toggle" href="' . esc_url(get_permalink($post)) . '"><span class="posts__arrow" aria-hidden="true"></span><span class="posts__vertical">' . esc_html(get_the_title($post)) . '</span></a>'
                . '<div class="posts__card">' . (has_post_thumbnail($post) ? get_the_post_thumbnail($post, 'medium_large', ['class' => 'posts__image', 'loading' => 'lazy']) : '<span class="posts__image posts__image--empty"></span>')
                . '<div class="posts__body"><h3 class="posts__name">' . esc_html(get_the_title($post)) . '</h3>'
                . '<time class="posts__date" datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html(date_i18n(get_option('date_format'), get_post_timestamp($post))) . '</time>'
                . ($excerpt !== '' ? '<p class="posts__excerpt">' . esc_html($excerpt) . '</p>' : '')
                . '<a class="btn btn--primary btn--arrow posts__more" href="' . esc_url(get_permalink($post)) . '">' . esc_html($more) . '</a></div></div></li>';
        }

        return '<section class="posts section"><div class="posts__inner wrap">'
            . '<div class="posts__top">' . $this->heading($atts, 'posts', 'h2', true) . '</div>'
            . '<ul class="posts__list">' . $items . '</ul></div></section>';
    }

    /* ------------------------------------------------------------------ */
    /* [testimonials]                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Banda oscura: a la izquierda cifra + etiqueta + texto + flechas; a la
     * derecha tarjetas 648×487 (cita, línea, autor · cargo · empresa · país)
     * con la insignia roja de comillas, en scroll horizontal.
     */
    public function testimonials($atts = []): string
    {
        $atts  = shortcode_atts(['eyebrow' => '', 'value' => '', 'label' => '', 'title' => '', 'text' => '', 'limit' => -1], is_array($atts) ? $atts : [], 'testimonials');
        $ctrl  = new TestimonialsController();
        $items = $ctrl->get_items(['numberposts' => (int) $atts['limit']]);
        if (!$items) {
            return '';
        }
        $lang  = $this->get_current_lang();
        $cards = '';
        foreach ($items as $t) {
            $quote = function_exists('intelindev_get_post_translated_content_blocks') ? implode(' ', intelindev_get_post_translated_content_blocks($t, $lang)) : '';
            $meta  = array_filter([$ctrl->get_field($t, 'role', $lang), $ctrl->get_field($t, 'company'), $ctrl->get_field($t, 'country')]);
            $cards .= '<li class="testimonials__item"><figure class="testimonials__card"><span class="testimonials__badge" aria-hidden="true">&ldquo;</span>'
                . '<blockquote class="testimonials__quote">' . wp_kses_post($quote) . '</blockquote>'
                . '<figcaption class="testimonials__author"><strong>' . esc_html(get_the_title($t)) . '</strong>' . ($meta ? '<span>' . esc_html(implode(' · ', $meta)) . '</span>' : '') . '</figcaption></figure></li>';
        }
        $aside = '<div class="testimonials__aside">';
        if (trim((string) $atts['eyebrow']) !== '') {
            $aside .= '<span class="eyebrow eyebrow--on-dark">' . esc_html($atts['eyebrow']) . '</span>';
        }
        if (trim((string) $atts['value']) !== '') {
            $aside .= '<p class="testimonials__value">' . esc_html($atts['value']) . '</p>';
        }
        if (trim((string) $atts['label']) !== '') {
            $aside .= '<p class="testimonials__label">' . esc_html($atts['label']) . '</p>';
        }
        if (trim((string) $atts['title']) !== '' || trim((string) $atts['text']) !== '') {
            $aside .= '<p class="testimonials__lead">' . (trim((string) $atts['title']) !== '' ? '<strong>' . esc_html($atts['title']) . '</strong> ' : '') . esc_html($atts['text']) . '</p>';
        }
        $aside .= $this->arrows('testimonials', true) . '</div>';

        return '<section class="testimonials section section--dark"><div class="testimonials__inner wrap">' . $aside
            . '<ul class="testimonials__list" data-scroller>' . $cards . '</ul></div></section>';
    }

    /** Flechas de un scroller horizontal (scripts.js: [data-scroll-prev|next] dentro de la sección). */
    private function arrows(string $class, bool $on_dark = false): string
    {
        $lang = $this->get_current_lang();
        $mod  = $on_dark ? ' scroller-arrow--on-dark' : '';
        return '<div class="' . esc_attr($class) . '__arrows scroller-arrows">'
            . '<button type="button" class="scroller-arrow scroller-arrow--prev' . $mod . '" data-scroll-prev aria-label="' . esc_attr(idml_t('nav.prev_page', $lang)) . '"></button>'
            . '<button type="button" class="scroller-arrow scroller-arrow--next' . $mod . '" data-scroll-next aria-label="' . esc_attr(idml_t('nav.next_page', $lang)) . '"></button></div>';
    }
}
