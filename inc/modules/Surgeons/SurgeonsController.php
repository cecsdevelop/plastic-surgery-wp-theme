<?php
/**
 * Cirujanos (CPT pswpt_surgeon): ficha pública de cada cirujano
 * (/surgeon/{slug}/, /es/cirujano/{slug}/ — SEO: URL propia por nombre,
 * indexable). Título = nombre; foto = imagen destacada; credenciales y rol
 * acá porque son cortos y se reutilizan en listados futuros ("Nuestro
 * equipo médico", byline "Performed by Dr. X" en un procedimiento); el resto
 * de la ficha (cita, badges, lista de formación, procedimientos que realiza,
 * testimonio, FAQ) se arma como cualquier página, por bloques en "Contenido
 * traducido" — no son campos fijos porque varían en cantidad y estructura
 * entre un cirujano y otro.
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package pswpt
 */

namespace pswptInit\Surgeons;

use pswptInit\General\ContentTypeController;

class SurgeonsController extends ContentTypeController
{
    public const POST_TYPE = 'pswpt_surgeon';

    public function register(): void
    {
        parent::register();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_hero_css']);
    }

    /**
     * Safety net (no existe todavía detección de shortcodes por página, ver
     * AGENTS.md "CSS and JS"): el CSS del componente [surgeon-hero] solo se
     * imprime en la ficha del cirujano, nunca en el resto del sitio.
     */
    public function enqueue_hero_css(): void
    {
        if (!is_singular(self::POST_TYPE)) {
            return;
        }
        $path = $this->plugin_path . 'assets/css/sections/surgeon-hero.css';
        if (!file_exists($path)) {
            return;
        }
        wp_enqueue_style('pswpt-section-surgeon-hero', $this->plugin_url . 'assets/css/sections/surgeon-hero.css', [], filemtime($path));
    }

    protected function config(): array
    {
        return [
            'post_type'       => self::POST_TYPE,
            'labels'          => [
                'es' => ['name' => 'Cirujanos', 'singular' => 'Cirujano'],
                'en' => ['name' => 'Surgeons', 'singular' => 'Surgeon'],
            ],
            'description'     => __('Surgeon profile pages: photo, credentials, and per-language bio.', 'pswpt'),
            'public'          => true,
            'slugs'           => ['es' => 'cirujano', 'en' => 'surgeon'],
            'menu_icon'       => 'dashicons-businessman',
            'menu_position'   => 29,
            'thumbnail_label' => __('Photo', 'pswpt'),
            'fields'          => [
                'credentials' => ['label' => __('Credentials', 'pswpt'), 'type' => 'text', 'description' => __('Short suffix shown next to the name, e.g. "MD, FACOG".', 'pswpt'), 'column' => true],
                'role'        => ['label' => __('Role', 'pswpt'), 'type' => 'lang_text', 'description' => __('Eyebrow shown above the name, e.g. "Medical Director".', 'pswpt'), 'column' => true],
            ],
        ];
    }
}
