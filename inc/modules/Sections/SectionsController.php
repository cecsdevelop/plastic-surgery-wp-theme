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
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    /** Cabecera común de sección: eyebrow + título (con *acento* → <em>) + texto. */
    private function heading(array $atts, string $class, string $level = 'h2'): string
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
            $html .= '<' . $level . ' class="' . esc_attr($class) . '__title">' . \IntelindevInit\Components\ComponentsController::format_attribute($title, 'html') . '</' . $level . '>';
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
}
