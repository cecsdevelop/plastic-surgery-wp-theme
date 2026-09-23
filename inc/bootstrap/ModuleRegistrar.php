<?php

namespace IntelindevInit\Bootstrap;

class ModuleRegistrar
{
    public function register_all(): void
    {
        foreach ($this->module_classes() as $class_name) {
            if (!class_exists($class_name)) {
                continue;
            }

            $instance = new $class_name();
            if (method_exists($instance, 'register')) {
                $instance->register();
            }
        }
    }

    private function module_classes(): array
    {
        return [
            'IntelindevInit\\General\\EnqueueController',
            // CPTs: un módulo por carpeta en inc/modules/{Modulo}/{Modulo}Controller
            // (ver skill wp-theme-cpt-module).
            'IntelindevInit\\Components\\ComponentsController',
            'IntelindevInit\\Forms\\FormsController',
            // CPTs de contenido (extienden General\ContentTypeController).
            'IntelindevInit\\Services\\ServicesController',
            'IntelindevInit\\Portfolio\\PortfolioController',
            'IntelindevInit\\Testimonials\\TestimonialsController',
            'IntelindevInit\\Clients\\ClientsController',
            'IntelindevInit\\Team\\TeamController',
            // Secciones dinámicas (shortcodes que leen los CPT).
            'IntelindevInit\\Sections\\SectionsController',
            // Cabecera y pie derivados del sitio de referencia (opt-in).
            'IntelindevInit\\Chrome\\ChromeController',
            // Elemento global de móvil renderizado en wp_footer.
            'IntelindevInit\\StickyCta\\StickyCtaController',
            // Blog nativo: áreas de widgets por idioma, fechas, comentarios.
            'IntelindevInit\\Blog\\BlogController',
            'IntelindevInit\\Forms\\SubmissionsController',
        ];
    }
}
