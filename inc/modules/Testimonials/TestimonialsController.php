<?php
/**
 * Testimonios (CPT intelindev_testimony — el nombre corto es por el límite
 * de 20 caracteres de WP): citas de clientes para el carrusel "Clientes
 * testimoniales". Sin URL pública: se consumen desde componentes. Título =
 * nombre de la persona; foto = imagen destacada; la cita va en el metabox
 * "Contenido traducido" (por idioma); cargo por idioma, empresa y país acá.
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Testimonials;

use IntelindevInit\General\ContentTypeController;

class TestimonialsController extends ContentTypeController
{
    public const POST_TYPE = 'intelindev_testimony';

    protected function config(): array
    {
        return [
            'post_type'       => self::POST_TYPE,
            'labels'          => [
                'es' => ['name' => 'Testimonios', 'singular' => 'Testimonio'],
                'en' => ['name' => 'Testimonials', 'singular' => 'Testimonial'],
            ],
            'description'     => __('Citas de clientes. El título es el nombre de la persona; la cita va en "Contenido traducido".', 'intelindev'),
            'public'          => false,
            'menu_icon'       => 'dashicons-format-quote',
            'menu_position'   => 28,
            'thumbnail_label' => __('Foto', 'intelindev'),
            'fields'          => [
                'role'    => ['label' => __('Cargo', 'intelindev'), 'type' => 'lang_text', 'column' => true],
                'company' => ['label' => __('Empresa', 'intelindev'), 'type' => 'text', 'column' => true],
                'country' => ['label' => __('País', 'intelindev'), 'type' => 'text'],
            ],
        ];
    }
}
