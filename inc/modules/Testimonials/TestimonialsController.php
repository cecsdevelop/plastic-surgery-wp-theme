<?php
/**
 * Testimonios (CPT pswpt_testimony — el nombre corto es por el límite
 * de 20 caracteres de WP): citas de clientes para el carrusel "Clientes
 * testimoniales". Sin URL pública: se consumen desde componentes. Título =
 * nombre de la persona; foto = imagen destacada; la cita va en el metabox
 * "Contenido traducido" (por idioma); cargo por idioma, empresa y país acá.
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package pswpt
 */

namespace pswptInit\Testimonials;

use pswptInit\General\ContentTypeController;

class TestimonialsController extends ContentTypeController
{
    public const POST_TYPE = 'pswpt_testimony';

    protected function config(): array
    {
        return [
            'post_type'       => self::POST_TYPE,
            'labels'          => [
                'es' => ['name' => 'Testimonios', 'singular' => 'Testimonio'],
                'en' => ['name' => 'Testimonials', 'singular' => 'Testimonial'],
            ],
            'description'     => __('Client quotes. The title is the person\'s name; the quote goes in "Translated content".', 'pswpt'),
            'public'          => false,
            'menu_icon'       => 'dashicons-format-quote',
            'menu_position'   => 28,
            'thumbnail_label' => __('Photo', 'pswpt'),
            'fields'          => [
                'role'    => ['label' => __('Job title', 'pswpt'), 'type' => 'lang_text', 'column' => true],
                'company' => ['label' => __('Company', 'pswpt'), 'type' => 'text', 'column' => true],
                'country' => ['label' => __('Country', 'pswpt'), 'type' => 'text'],
            ],
        ];
    }
}
