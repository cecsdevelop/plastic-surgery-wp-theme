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
        ];
    }
}
