<?php
/**
 * Equipo (CPT pswpt_member): resto del equipo — todo el que no es cirujano
 * (coordinadoras, staff clínico...) — con ficha propia (/team/{slug}/,
 * /es/equipo/{slug}/), igual que Cirujanos (Surgeons\SurgeonsController)
 * pero para el personal que no opera. Título = nombre; foto = imagen
 * destacada; cargo por idioma y LinkedIn acá; bio opcional en "Contenido
 * traducido". Orden manual con "Atributos → Orden". También alimenta la
 * sección "El talento detrás de nuestras soluciones" (Nosotros) vía [team].
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package pswpt
 */

namespace pswptInit\Team;

use pswptInit\General\ContentTypeController;

class TeamController extends ContentTypeController
{
    public const POST_TYPE = 'pswpt_member';

    protected function config(): array
    {
        return [
            'post_type'       => self::POST_TYPE,
            'labels'          => [
                'es' => ['name' => 'Equipo', 'singular' => 'Miembro del equipo'],
                'en' => ['name' => 'Team', 'singular' => 'Team member'],
            ],
            'description'     => __('Team members (non-surgeon staff): profile page and the About section.', 'pswpt'),
            'public'          => true,
            'slugs'           => ['es' => 'equipo', 'en' => 'team'],
            'menu_icon'       => 'dashicons-id',
            'menu_position'   => 30,
            'thumbnail_label' => __('Photo', 'pswpt'),
            'fields'          => [
                'role'     => ['label' => __('Job title', 'pswpt'), 'type' => 'lang_text', 'column' => true],
                'linkedin' => ['label' => __('LinkedIn', 'pswpt'), 'type' => 'url'],
            ],
        ];
    }
}
