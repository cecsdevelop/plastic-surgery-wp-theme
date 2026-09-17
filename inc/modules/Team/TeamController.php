<?php
/**
 * Equipo (CPT intelindev_member): personas de la sección "El talento detrás
 * de nuestras soluciones" (Nosotros). Sin URL pública. Título = nombre; foto =
 * imagen destacada; cargo por idioma y LinkedIn acá; bio opcional en
 * "Contenido traducido". Orden manual con "Atributos → Orden".
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Team;

use IntelindevInit\General\ContentTypeController;

class TeamController extends ContentTypeController
{
    public const POST_TYPE = 'intelindev_member';

    protected function config(): array
    {
        return [
            'post_type'       => self::POST_TYPE,
            'labels'          => [
                'es' => ['name' => 'Equipo', 'singular' => 'Miembro del equipo'],
                'en' => ['name' => 'Team', 'singular' => 'Team member'],
            ],
            'description'     => __('Personas del equipo para la sección de Nosotros.', 'intelindev'),
            'public'          => false,
            'menu_icon'       => 'dashicons-id',
            'menu_position'   => 30,
            'thumbnail_label' => __('Foto', 'intelindev'),
            'fields'          => [
                'role'     => ['label' => __('Cargo', 'intelindev'), 'type' => 'lang_text', 'column' => true],
                'linkedin' => ['label' => __('LinkedIn', 'intelindev'), 'type' => 'url'],
            ],
        ];
    }
}
