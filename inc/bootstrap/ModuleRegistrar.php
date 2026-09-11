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
        ];
    }
}
