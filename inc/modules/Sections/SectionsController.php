<?php
/**
 * Secciones dinámicas del diseño (shortcodes PHP que leen los CPT de
 * contenido): a diferencia de los Componentes del dashboard (HTML con
 * placeholders), estas necesitan consultas y bucles, así que viven en el
 * theme. Los textos editables (eyebrow, título, botón) entran por atributos
 * por idioma —escritos en el bloque de cada idioma— o por el diccionario.
 *
 *   [clients]                        franja de logos (CPT Clientes)
 *   [projects limit="4"]             proyectos destacados (CPT Portafolio)
 *   [latest_posts limit="3"]         últimas entradas del blog
 *   [testimonials]                   citas de clientes (CPT Testimonios)
 *   [team]                           personas del equipo (CPT Equipo)
 *   [home_reviews]                   franja de reseñas de portada (CPT Testimonios)
 *
 * Todos aceptan eyebrow="" title="" text="" cta_text="" cta_url="" y
 * marcan el HTML con clases BEM propias (CSS en styles.css, sección
 * "Secciones"). Sin JS: los carruseles son scroll horizontal con snap.
 *
 * @package pswpt
 */

namespace pswptInit\Sections;

use pswptInit\General\BaseController;
use pswptInit\Clients\ClientsController;
use pswptInit\Portfolio\PortfolioController;
use pswptInit\Testimonials\TestimonialsController;
use pswptInit\Team\TeamController;
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
        add_shortcode('projects', [$this, 'projects']);
        add_shortcode('latest_posts', [$this, 'latest_posts']);
        add_shortcode('testimonials', [$this, 'testimonials']);
        add_shortcode('team', [$this, 'team']);
        add_shortcode('home_reviews', [$this, 'home_reviews']);
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
            $html .= '<' . $level . ' class="' . esc_attr($class) . '__title' . ($serif ? ' accent' : '') . '">' . \pswptInit\Components\ComponentsController::format_attribute($title, 'html') . '</' . $level . '>';
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
            $excerpt = function_exists('pswpt_get_post_translated_excerpt') ? pswpt_get_post_translated_excerpt($post, $lang) : '';
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
            $excerpt = function_exists('pswpt_get_post_translated_excerpt') ? pswpt_get_post_translated_excerpt($project, $lang) : '';
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
            $excerpt = function_exists('pswpt_get_post_translated_excerpt') ? pswpt_get_post_translated_excerpt($post, $lang) : '';
            if ($excerpt === '') {
                $excerpt = wp_trim_words(wp_strip_all_tags((string) $post->post_excerpt), 20);
            }
            $items .= '<li class="posts__item">'
                . '<a class="posts__toggle" href="' . esc_url(get_permalink($post)) . '"><span class="posts__arrow" aria-hidden="true"></span><span class="posts__vertical">' . esc_html(get_the_title($post)) . '</span></a>'
                . '<div class="posts__card">' . (has_post_thumbnail($post) ? get_the_post_thumbnail($post, 'medium_large', ['class' => 'posts__image', 'loading' => 'lazy']) : '<span class="posts__image posts__image--empty"></span>')
                . '<div class="posts__body"><h3 class="posts__name">' . esc_html(get_the_title($post)) . '</h3>'
                . '<time class="posts__date" datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html((new \pswptInit\Blog\BlogController())->date($post, 'date.medium', $lang)) . '</time>'
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
        $blog  = new \pswptInit\Blog\BlogController();
        $cards = '';
        foreach ($posts as $post) {
            $excerpt = function_exists('pswpt_get_post_translated_excerpt') ? pswpt_get_post_translated_excerpt($post, $lang) : '';
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
            $quote = function_exists('pswpt_get_post_translated_content_blocks') ? implode(' ', pswpt_get_post_translated_content_blocks($t, $lang)) : '';
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
    /**
     * Franja de reseñas de la portada.
     *
     * Derivada de medidas reales (`section.homerev`): titular, valoración con
     * el número en cursiva, una reseña destacada con foto y una rejilla con el
     * resto, más la letra pequeña legal.
     *
     * Las reseñas salen del CPT de Testimonios, no de atributos: son contenido
     * que el cliente edita, y varias de ellas citan a pacientes, así que tienen
     * que vivir donde se puedan revisar y retirar. Lo que entra por atributos
     * es el marco: titular, cifra de valoración y aviso legal.
     *
     * La destacada es la primera del CPT; el resto van a la rejilla.
     */
    public function home_reviews($atts = []): string
    {
        $atts = shortcode_atts([
            'title'        => '',
            'rating'       => '',
            'rating_label' => '',
            'fine'         => '',
            'stars'        => '5',
            'limit'        => 5,
            'lang'         => '',
        ], is_array($atts) ? $atts : [], 'home_reviews');

        $lang  = $this->resolve_lang((string) $atts['lang']);
        $ctrl  = new TestimonialsController();
        $items = $ctrl->get_items(['numberposts' => max(1, (int) $atts['limit'])]);
        if (!$items) {
            return '';
        }

        $featured = array_shift($items);

        $out = '<section class="psw-reviews">';

        $title = trim((string) $atts['title']);
        if ($title !== '') {
            $out .= '<h2 class="psw-reviews__title">' . esc_html($title) . '</h2>';
        }

        $rating = trim((string) $atts['rating']);
        if ($rating !== '') {
            $stars = max(0, min(5, (int) $atts['stars']));
            $out .= '<p class="psw-reviews__rating">'
                . str_repeat($this->star_icon(), $stars)
                . '<span class="psw-reviews__num">' . esc_html($rating) . '</span>'
                . ($atts['rating_label'] !== '' ? ' ' . esc_html((string) $atts['rating_label']) : '')
                . '</p>';
        }

        $out .= '<div class="psw-reviews__feature">'
            . $this->review_figure($featured)
            . '<div class="psw-reviews__quote">' . $this->review_quote($featured, $ctrl, $lang) . '</div>'
            . '</div>';

        if ($items) {
            $cards = '';
            foreach ($items as $item) {
                $cards .= '<div class="psw-reviews__card">' . $this->review_quote($item, $ctrl, $lang) . '</div>';
            }
            $out .= '<div class="psw-reviews__grid">' . $cards . '</div>';
        }

        $fine = trim((string) $atts['fine']);
        if ($fine === '') {
            $fine = $this->translate_or('reviews.fine', $lang, '');
        }
        if ($fine !== '') {
            $out .= '<p class="psw-reviews__fine">' . esc_html($fine) . '</p>';
        }

        return $out . '</section>';
    }

    /** Cita y autoría de una reseña. La cita vive en el contenido del CPT. */
    private function review_quote(WP_Post $item, TestimonialsController $ctrl, string $lang): string
    {
        // El contenido se guarda en bloques por idioma. Un testimonio escrito sin
        // marcar idioma devuelve vacío para el idioma pedido, y una cita vacía
        // deja la tarjeta con el nombre y el cargo pero sin reseña, que es peor
        // que no pintarla: se cae al contenido crudo.
        $quote = '';
        if (function_exists('pswpt_get_post_translated_content_blocks')) {
            $quote = trim(implode(' ', pswpt_get_post_translated_content_blocks($item, $lang)));
        }
        if ($quote === '') {
            $quote = trim((string) $item->post_content);
        }

        $meta = array_filter([$ctrl->get_field($item, 'role', $lang), $ctrl->get_field($item, 'company')]);
        $cite = array_filter([get_the_title($item), $meta ? implode(' · ', $meta) : '']);

        if ($quote === '') {
            return '';
        }

        return '<blockquote>' . wp_kses_post($quote) . '</blockquote>'
            . ($cite ? '<cite>' . esc_html(implode(' · ', $cite)) . '</cite>' : '');
    }

    /** Foto de la reseña destacada. Sin imagen destacada no se pinta la figura. */
    private function review_figure(WP_Post $item): string
    {
        if (!has_post_thumbnail($item)) {
            return '';
        }
        return '<figure class="psw-reviews__figure">'
            . get_the_post_thumbnail($item, 'large', [
                'class'   => 'psw-reviews__img',
                'alt'     => get_the_title($item),
                'loading' => 'lazy',
            ])
            . '</figure>';
    }

    /**
     * Estrella inline.
     *
     * SVG en el marcado y no un carácter tipográfico: el original usa un icono
     * propio y una estrella de fuente cambia de forma en cada sistema.
     */
    private function star_icon(): string
    {
        return '<svg class="psw-reviews__star" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">'
            . '<path d="M12 2l2.9 6.3 6.9.8-5 4.7 1.3 6.8L12 17.4 5.9 20.6 7.2 13.8l-5-4.7 6.9-.8L12 2z"/></svg>';
    }

    /** Diccionario si existe; si no, el texto por defecto. */
    private function translate_or(string $key, string $lang, string $fallback): string
    {
        if (!function_exists('idml_t')) {
            return $fallback;
        }
        $value = (string) idml_t($key, $lang);
        return ($value === '' || $value === $key) ? $fallback : $value;
    }

}
