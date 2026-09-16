<?php
/**
 * @package Intelindev
 * Enqueues CSS and JS files.
 */

namespace IntelindevInit\General;

class EnqueueController extends BaseController
{
    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_files']);
    }

    public function enqueue_files()
    {
        // Grid + utilidades (generado por scripts/build-grid.js). Va antes de
        // styles.css: los componentes del theme pueden pisar utilidades.
        $grid_path = $this->plugin_path . 'assets/css/grid.css';
        $deps = [];
        if (file_exists($grid_path)) {
            wp_enqueue_style(
                'intelindev-grid',
                $this->plugin_url . 'assets/css/grid.css',
                [],
                filemtime($grid_path)
            );
            $deps[] = 'intelindev-grid';
        }

        $css_path = $this->plugin_path . 'assets/css/styles.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'intelindev-styles',
                $this->plugin_url . 'assets/css/styles.css',
                $deps,
                filemtime($css_path)
            );
        }

        $js_path = $this->plugin_path . 'assets/js/scripts.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'intelindev-scripts',
                $this->plugin_url . 'assets/js/scripts.js',
                ['jquery'],
                filemtime($js_path),
                true
            );
        }
    }
}
