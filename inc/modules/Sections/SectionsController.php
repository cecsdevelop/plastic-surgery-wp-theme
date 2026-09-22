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
 *   [team]                           personas del equipo (CPT Equipo)
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
use IntelindevInit\Team\TeamController;
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
        add_shortcode('team', [$this, 'team']);
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

    /**
     * Tarjeta 503×609 de los listados (Servicios y Portafolio del Figma): foto
     * a sangre con etiqueta grafito + botón rojo; al pasar el mouse la
     * etiqueta se vuelve blanca y despliega el excerpt.
     */
    private function tile(WP_Post $post, string $excerpt, string $more, bool $hidden = false): string
    {
        return '<article class="tile"' . ($hidden ? ' hidden' : '') . '><a class="tile__link" href="' . esc_url(get_permalink($post)) . '">'
            . (has_post_thumbnail($post) ? get_the_post_thumbnail($post, 'large', ['class' => 'tile__image', 'alt' => '', 'loading' => 'lazy']) : '<span class="tile__image"></span>')
            . '<div class="tile__label"><h3 class="tile__name">' . esc_html(get_the_title($post)) . '</h3>'
            . ($excerpt !== '' ? '<p class="tile__excerpt"><span>' . esc_html($excerpt) . '</span></p>' : '')
            . '<span class="tile__arrow" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html($more) . '</span></div></a></article>';
    }

    /**
     * Grilla de tarjetas en 3 columnas. Con $per_page > 0 solo se ven las
     * primeras y el botón "ver más" ([data-tiles-more], scripts.js) destapa el
     * siguiente lote; sin lotes pendientes no se imprime el botón.
     */
    private function tiles(array $posts, string $more, string $lang, int $per_page = 0, string $more_label = ''): string
    {
        $html = '';
        foreach (array_values($posts) as $i => $post) {
            $excerpt = function_exists('intelindev_get_post_translated_excerpt') ? intelindev_get_post_translated_excerpt($post, $lang) : '';
            $html   .= $this->tile($post, $excerpt, $more, $per_page > 0 && $i >= $per_page);
        }
        $grid = '<div class="tiles"' . ($per_page > 0 ? ' data-tiles-step="' . $per_page . '"' : '') . '>' . $html . '</div>';
        if ($per_page > 0 && count($posts) > $per_page && $more_label !== '') {
            $grid .= '<p class="tiles__more"><button type="button" class="btn btn--primary btn--plus" data-tiles-more>' . esc_html($more_label) . '</button></p>';
        }

        return $grid;
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
     * Tarjetas de servicio. layout="icons" (home, 362×421 gris con borde
     * degradado rosa): ícono del campo "icon" (o el del theme por posición),
     * título, excerpt y "Conocer más". layout="cards" (listado /servicios/):
     * tarjetas tile() en 3 columnas. layout="list" (columna derecha del
     * detalle): solo los nombres enlazados, el actual con aria-current.
     * Cabecera centrada.
     */
    public function services($atts = []): string
    {
        $atts  = shortcode_atts(['eyebrow' => '', 'title' => '', 'text' => '', 'limit' => 4, 'more' => '', 'layout' => 'icons'], is_array($atts) ? $atts : [], 'services');
        $ctrl  = new ServicesController();
        $items = $ctrl->get_items(['numberposts' => (int) $atts['limit']]);
        if (!$items) {
            return '';
        }
        $lang = $this->get_current_lang();
        $more = trim((string) $atts['more']) !== '' ? (string) $atts['more'] : idml_t('services.more', $lang);

        if ((string) $atts['layout'] === 'list') {
            $current = (int) get_queried_object_id();
            $links   = '';
            foreach ($items as $service) {
                $links .= '<li class="services-nav__item"><a class="services-nav__link" href="' . esc_url(get_permalink($service)) . '"' . ($service->ID === $current ? ' aria-current="page"' : '') . '>' . esc_html(get_the_title($service)) . '</a></li>';
            }
            return '<nav class="services-nav" aria-label="' . esc_attr($ctrl->label('name', $lang)) . '"><ul class="services-nav__list">' . $links . '</ul></nav>';
        }

        if ((string) $atts['layout'] === 'cards') {
            return '<section class="services section services--cards"><div class="services__inner wrap">' . $this->heading($atts, 'services')
                . $this->tiles($items, $more, $lang) . '</div></section>';
        }

        $fallback_icons = ['code', 'design', 'update', 'search'];
        $cards = '';
        foreach (array_values($items) as $i => $service) {
            $excerpt = function_exists('intelindev_get_post_translated_excerpt') ? intelindev_get_post_translated_excerpt($service, $lang) : '';
            $url     = esc_url(get_permalink($service));
            $icon_id = (int) $ctrl->get_field($service, 'icon');
            $icon    = $icon_id > 0
                ? wp_get_attachment_image($icon_id, 'thumbnail', false, ['class' => 'services__icon', 'alt' => '', 'loading' => 'lazy'])
                : '<img class="services__icon" src="' . esc_url(get_template_directory_uri() . '/assets/img/icons/' . $fallback_icons[$i % 4] . '.svg') . '" alt="" width="72" height="72" loading="lazy">';
            $cards .= '<article class="services__card">' . $icon
                . '<div class="services__body"><h3 class="services__name"><a href="' . $url . '">' . esc_html(get_the_title($service)) . '</a></h3>'
                . ($excerpt !== '' ? '<p class="services__excerpt">' . esc_html($excerpt) . '</p>' : '') . '</div>'
                . '<a class="services__more" href="' . $url . '">' . esc_html($more) . '<span class="services__more-icon" aria-hidden="true"></span></a></article>';
        }

        return '<section class="services section"><div class="services__inner wrap">' . $this->heading($atts, 'services')
            . '<div class="services__grid">' . $cards . '</div></div></section>';
    }

    /* ------------------------------------------------------------------ */
    /* [projects]                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Proyectos destacados (home): tarjetas 440×480 con la imagen destacada
     * de fondo y degradado, título + excerpt abajo; scroll horizontal con
     * flechas (scripts.js mueve el scroll, sin librerías). layout="cards"
     * para el listado.
     */
    public function projects($atts = []): string
    {
        $atts  = shortcode_atts(['eyebrow' => '', 'title' => '', 'text' => '', 'limit' => 4, 'layout' => 'scroller', 'per_page' => 6, 'more' => ''], is_array($atts) ? $atts : [], 'projects');
        $items = (new PortfolioController())->get_items(['numberposts' => (int) $atts['limit']]);
        if (!$items) {
            return '';
        }
        $lang = $this->get_current_lang();

        // layout="cards" (listado /portafolio/): tarjetas tile() en 3 columnas,
        // per_page visibles y botón "Ver más proyectos" para el resto.
        if ((string) $atts['layout'] === 'cards') {
            $more = trim((string) $atts['more']) !== '' ? (string) $atts['more'] : idml_t('projects.more', $lang);
            return '<section class="projects section projects--cards"><div class="projects__inner wrap">' . $this->heading($atts, 'projects')
                . $this->tiles($items, idml_t('projects.view', $lang), $lang, max(0, (int) $atts['per_page']), $more) . '</div></section>';
        }

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
     * Últimas entradas en acordeón horizontal (home): la primera abierta
     * (imagen + título serif + fecha + excerpt + botón), el resto plegadas con
     * el título en vertical; al pasar el mouse se abre la que toque (solo CSS).
     * layout="cards" para la página del blog (ver post_cards()).
     */
    public function latest_posts($atts = []): string
    {
        $atts = shortcode_atts(['eyebrow' => '', 'title' => '', 'text' => '', 'limit' => 4, 'more' => '', 'layout' => 'accordion'], is_array($atts) ? $atts : [], 'latest_posts');
        $lang = $this->get_current_lang();
        $more = trim((string) $atts['more']) !== '' ? (string) $atts['more'] : idml_t('posts.read_more', $lang);

        // layout="cards" (página del blog): en el índice de entradas lista la
        // query principal (paginada con /page/N/); fuera de él, las últimas `limit`.
        if ((string) $atts['layout'] === 'cards') {
            $in_index = is_home() && !is_admin();
            $posts    = $in_index ? (array) $GLOBALS['wp_query']->posts : get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => (int) $atts['limit'], 'suppress_filters' => false]);
            return '<section class="posts section posts--cards"><div class="posts__inner wrap">' . $this->heading($atts, 'posts')
                . ($posts ? $this->post_cards($posts, $more, $lang) : '<p class="posts__empty">' . esc_html(idml_t('archive.nothing_found', $lang)) . '</p>')
                . ($in_index ? $this->pagination($lang) : '') . '</div></section>';
        }

        $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => (int) $atts['limit'], 'suppress_filters' => false]);
        if (!$posts) {
            return '';
        }

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
                . '<time class="posts__date" datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html((new \IntelindevInit\Blog\BlogController())->date($post, 'date.medium', $lang)) . '</time>'
                . ($excerpt !== '' ? '<p class="posts__excerpt">' . esc_html($excerpt) . '</p>' : '')
                . '<a class="btn btn--primary btn--arrow posts__more" href="' . esc_url(get_permalink($post)) . '">' . esc_html($more) . '</a></div></div></li>';
        }

        return '<section class="posts section"><div class="posts__inner wrap">'
            . '<div class="posts__top">' . $this->heading($atts, 'posts', 'h2', true) . '</div>'
            . '<ul class="posts__list">' . $items . '</ul></div></section>';
    }

    /**
     * Tarjetas 769×380 del blog (2 columnas): foto 372 a la izquierda y caja
     * blanca con fecha corta (línea + "Diciembre 05, 2026"), título, excerpt
     * y el ícono add-circle que enlaza al post.
     */
    private function post_cards(array $posts, string $more, string $lang): string
    {
        $blog  = new \IntelindevInit\Blog\BlogController();
        $cards = '';
        foreach ($posts as $post) {
            $excerpt = function_exists('intelindev_get_post_translated_excerpt') ? intelindev_get_post_translated_excerpt($post, $lang) : '';
            if ($excerpt === '') {
                $excerpt = wp_trim_words(wp_strip_all_tags((string) $post->post_excerpt), 20);
            }
            $url    = esc_url(get_permalink($post));
            $cards .= '<article class="post-card">'
                . '<a class="post-card__media" href="' . $url . '" tabindex="-1" aria-hidden="true">' . (has_post_thumbnail($post) ? get_the_post_thumbnail($post, 'medium_large', ['class' => 'post-card__image', 'alt' => '', 'loading' => 'lazy']) : '<span class="post-card__image"></span>') . '</a>'
                . '<div class="post-card__body">' . $blog->time_html($post, 'date.short', 'post-card__date')
                . '<h3 class="post-card__title"><a href="' . $url . '">' . esc_html(get_the_title($post)) . '</a></h3>'
                . ($excerpt !== '' ? '<p class="post-card__excerpt">' . esc_html($excerpt) . '</p>' : '')
                . '<a class="post-card__more" href="' . $url . '"><span class="screen-reader-text">' . esc_html($more) . '</span></a></div></article>';
        }

        return '<div class="post-cards">' . $cards . '</div>';
    }

    /** Paginación de la query principal: números (el actual en círculo rojo) y salto a la última página. */
    private function pagination(string $lang): string
    {
        $query = $GLOBALS['wp_query'];
        $total = (int) $query->max_num_pages;
        if ($total < 2) {
            return '';
        }
        $current = max(1, (int) $query->get('paged'));
        $links   = paginate_links(['type' => 'array', 'prev_next' => false, 'current' => $current, 'total' => $total, 'mid_size' => 2]);
        $html    = implode('', (array) $links);
        if ($current < $total) {
            $html .= '<a class="page-numbers page-numbers--last" href="' . esc_url(get_pagenum_link($total)) . '" aria-label="' . esc_attr(idml_t('nav.last_page', $lang)) . '"></a>';
        }

        return '<nav class="pagination" aria-label="' . esc_attr(idml_t('nav.pagination_label', $lang)) . '">' . $html . '</nav>';
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

    /* ------------------------------------------------------------------ */
    /* [team]                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Equipo: cabecera centrada y tarjetas 380×508 (foto con radio 20 y
     * etiqueta blanca superpuesta con nombre y cargo), alternando la altura
     * par/impar como en el diseño, en scroller horizontal.
     */
    public function team($atts = []): string
    {
        $atts  = shortcode_atts(['eyebrow' => '', 'title' => '', 'text' => '', 'limit' => -1], is_array($atts) ? $atts : [], 'team');
        $ctrl  = new TeamController();
        $items = $ctrl->get_items(['numberposts' => (int) $atts['limit']]);
        if (!$items) {
            return '';
        }
        $lang  = $this->get_current_lang();
        $cards = '';
        foreach ($items as $member) {
            $photo    = has_post_thumbnail($member) ? get_the_post_thumbnail($member, 'medium_large', ['class' => 'team__photo', 'loading' => 'lazy']) : '<span class="team__photo team__photo--empty"></span>';
            $role     = $ctrl->get_field($member, 'role', $lang);
            $linkedin = $ctrl->get_field($member, 'linkedin');
            $name     = esc_html(get_the_title($member));
            $cards .= '<li class="team__item"><figure class="team__card">' . $photo
                . '<figcaption class="team__label"><strong class="team__name">' . ($linkedin !== '' ? '<a href="' . esc_url($linkedin) . '" target="_blank" rel="noopener">' . $name . '</a>' : $name) . '</strong>'
                . ($role !== '' ? '<span class="team__role">' . esc_html($role) . '</span>' : '') . '</figcaption></figure></li>';
        }
        if (trim((string) $atts['eyebrow']) === '') {
            $atts['eyebrow'] = idml_t('team.eyebrow', $lang);
        }

        return '<section class="team section" id="team"><div class="team__inner wrap">' . $this->heading($atts, 'team', 'h2', true)
            . '<ul class="team__list" data-scroller>' . $cards . '</ul></div></section>';
    }
}
