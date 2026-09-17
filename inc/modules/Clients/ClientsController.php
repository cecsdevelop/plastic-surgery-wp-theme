<?php
/**
 * Clientes (CPT intelindev_client): logos para la franja "confían en
 * nosotros". Sin URL pública. Título = nombre; logo = imagen destacada;
 * opcionalmente el sitio del cliente.
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Clients;

use IntelindevInit\General\ContentTypeController;

class ClientsController extends ContentTypeController
{
    public const POST_TYPE = 'intelindev_client';

    protected function config(): array
    {
        return [
            'post_type'       => self::POST_TYPE,
            'labels'          => [
                'es' => ['name' => 'Clientes', 'singular' => 'Cliente'],
                'en' => ['name' => 'Clients', 'singular' => 'Client'],
            ],
            'description'     => __('Logos de clientes para la franja de la home.', 'intelindev'),
            'public'          => false,
            'menu_icon'       => 'dashicons-groups',
            'menu_position'   => 29,
            'thumbnail_label' => __('Logo', 'intelindev'),
            'fields'          => [
                'url' => ['label' => __('Sitio web', 'intelindev'), 'type' => 'url', 'column' => true],
            ],
        ];
    }
}
