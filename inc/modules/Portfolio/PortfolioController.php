<?php
/**
 * Portafolio (CPT intelindev_project): proyectos realizados. Público: archivo
 * /portafolio/ (/en/portfolio/) y detalle /portafolio/{slug}/
 * (Portafolio_Detalle del Figma). Título, contenido, excerpt y slug por idioma
 * vienen del metabox "Contenido traducido"; acá van los datos de la ficha:
 * cliente, sector/periodo/servicio (por idioma), año, URL del proyecto y
 * galería. Listado: archive-intelindev_project.php con la "Página de
 * Portafolio" de Ajustes → Lectura (ver ContentTypeController); detalle:
 * single-intelindev_project.php (info_html() + gallery_html()).
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Portfolio;

use IntelindevInit\General\ContentTypeController;
use WP_Post;

class PortfolioController extends ContentTypeController
{
    public const POST_TYPE = 'intelindev_project';

    protected function config(): array
    {
        return [
            'post_type'     => self::POST_TYPE,
            'labels'        => [
                'es' => ['name' => 'Portafolio', 'singular' => 'Proyecto'],
                'en' => ['name' => 'Portfolio', 'singular' => 'Project'],
            ],
            'description'   => __('Proyectos del portafolio, con ficha y galería.', 'intelindev'),
            'public'        => true,
            'slugs'         => ['es' => 'portafolio', 'en' => 'portfolio'],
            'list_shortcode' => 'projects',
            'menu_icon'     => 'dashicons-portfolio',
            'menu_position' => 27,
            'orderby'       => ['menu_order' => 'ASC', 'date' => 'DESC'], // orden manual y, a igual orden, el más nuevo primero
            'fields'        => [
                'client'  => ['label' => __('Cliente', 'intelindev'), 'type' => 'text', 'column' => true],
                'sector'  => ['label' => __('Sector', 'intelindev'), 'type' => 'lang_text', 'column' => true],
                'year'    => ['label' => __('Año', 'intelindev'), 'type' => 'number'],
                'period'  => ['label' => __('Periodo', 'intelindev'), 'type' => 'lang_text', 'description' => __('Duración del proyecto ("1 año", "6 meses").', 'intelindev')],
                'service' => ['label' => __('Servicio', 'intelindev'), 'type' => 'lang_text', 'description' => __('Línea de servicio prestada ("Desarrollo de Frontend").', 'intelindev')],
                'url'     => ['label' => __('URL del proyecto', 'intelindev'), 'type' => 'url'],
                'gallery' => ['label' => __('Galería', 'intelindev'), 'type' => 'gallery', 'description' => __('Capturas del proyecto, en el orden en que se muestran.', 'intelindev')],
            ],
        ];
    }

    /**
     * Ficha "Información del proyecto" del detalle (columna derecha del
     * Proyecto Detalle del Figma): año, periodo, cliente, servicio, sector y
     * sitio, solo los que tengan valor; etiquetas del diccionario.
     */
    public function info_html(WP_Post $post, $lang = null): string
    {
        $lang  = $lang !== null ? idml_normalize_lang($lang) : $this->get_current_lang();
        $facts = [];
        foreach (['year', 'period', 'client', 'service', 'sector'] as $key) {
            $value = (string) $this->get_field($post, $key, $lang);
            if ($value !== '' && $value !== '0') {
                $facts[] = '<div class="project__fact"><dt>' . esc_html(idml_t('project.' . $key, $lang)) . '</dt><dd>' . esc_html($value) . '</dd></div>';
            }
        }
        $url = (string) $this->get_field($post, 'url');
        if ($url !== '') {
            $facts[] = '<div class="project__fact"><dt>' . esc_html(idml_t('project.url', $lang)) . '</dt><dd><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html((string) preg_replace('#^https?://(www\.)?#', '', untrailingslashit($url))) . '</a></dd></div>';
        }
        if (!$facts) {
            return '';
        }

        return '<h2 class="project__info-title accent">' . esc_html(idml_t('project.info_title', $lang)) . '</h2><dl class="project__facts">' . implode('', $facts) . '</dl>';
    }

    /** Galería del campo "gallery" (capturas 1004×620 r15), o '' si no hay. */
    public function gallery_html(WP_Post $post): string
    {
        $items = '';
        foreach ((array) $this->get_field($post, 'gallery') as $id) {
            $img = wp_get_attachment_image((int) $id, 'large', false, ['loading' => 'lazy']);
            if ($img !== '') {
                $items .= '<li class="project__gallery-item">' . $img . '</li>';
            }
        }

        return $items !== '' ? '<ul class="project__gallery">' . $items . '</ul>' : '';
    }
}
