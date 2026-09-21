<?php
/**
 * Servicios (CPT intelindev_service): las 4+ líneas de servicio del sitio.
 * Público: archivo /servicios/ (/en/services/) y detalle /servicios/{slug}/
 * (Servicios_Detalle del Figma). Título, contenido, excerpt (texto corto de la
 * tarjeta) y slug por idioma vienen del metabox "Contenido traducido"; acá
 * solo se agrega el ícono de la tarjeta. Orden manual con "Atributos → Orden".
 * Listado: archive-intelindev_service.php con la "Página de Servicios" de
 * Ajustes → Lectura (ver ContentTypeController); detalle: single-intelindev_service.php.
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Services;

use IntelindevInit\General\ContentTypeController;

class ServicesController extends ContentTypeController
{
    public const POST_TYPE = 'intelindev_service';

    protected function config(): array
    {
        return [
            'post_type'     => self::POST_TYPE,
            'labels'        => [
                'es' => ['name' => 'Servicios', 'singular' => 'Servicio'],
                'en' => ['name' => 'Services', 'singular' => 'Service'],
            ],
            'description'   => __('Líneas de servicio: tarjetas de la home y del listado, con página de detalle.', 'intelindev'),
            'public'        => true,
            'slugs'         => ['es' => 'servicios', 'en' => 'services'],
            'list_shortcode' => 'services layout="cards"',
            'menu_icon'     => 'dashicons-screenoptions',
            'menu_position' => 26,
            'fields'        => [
                'icon' => ['label' => __('Ícono', 'intelindev'), 'type' => 'media', 'column' => true, 'description' => __('Imagen del ícono de la tarjeta (PNG/SVG cuadrado).', 'intelindev')],
            ],
        ];
    }
}
