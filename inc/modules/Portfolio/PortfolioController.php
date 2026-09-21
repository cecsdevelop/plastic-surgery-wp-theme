<?php
/**
 * Portafolio (CPT intelindev_project): proyectos realizados. Público: archivo
 * /portafolio/ (/en/portfolio/) y detalle /portafolio/{slug}/
 * (Portafolio_Detalle del Figma). Título, contenido, excerpt y slug por idioma
 * vienen del metabox "Contenido traducido"; acá van los datos de la ficha:
 * cliente, sector (por idioma), año, URL del proyecto y galería.
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Portfolio;

use IntelindevInit\General\ContentTypeController;

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
                'url'     => ['label' => __('URL del proyecto', 'intelindev'), 'type' => 'url'],
                'gallery' => ['label' => __('Galería', 'intelindev'), 'type' => 'gallery', 'description' => __('Capturas del proyecto, en el orden en que se muestran.', 'intelindev')],
            ],
        ];
    }
}
