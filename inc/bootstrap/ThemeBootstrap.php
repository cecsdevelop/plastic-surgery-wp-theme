<?php

namespace IntelindevInit\Bootstrap;

class ThemeBootstrap
{
    private static $autoloader_registered = false;

    public function boot()
    {
        $this->load_core_files();
        $this->register_autoloader();
        $this->register_hooks();
    }

    private function load_core_files()
    {
        $loader_path = get_template_directory() . '/inc/bootstrap/CoreFileLoader.php';
        if (file_exists($loader_path)) {
            require_once $loader_path;
        }

        if (!class_exists('IntelindevInit\\Bootstrap\\CoreFileLoader')) {
            return;
        }

        (new CoreFileLoader())->load_all();
    }

    private function register_autoloader()
    {
        if (self::$autoloader_registered) {
            return;
        }

        spl_autoload_register(function ($class_name) {
            $prefix = 'IntelindevInit\\';
            if (strpos($class_name, $prefix) !== 0) {
                return;
            }

            $relative_class = substr($class_name, strlen($prefix));
            $relative_path = str_replace('\\', '/', $relative_class);
            $file_path = get_template_directory() . '/inc/modules/' . $relative_path . '.php';

            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }, true, true);

        self::$autoloader_registered = true;
    }

    private function register_hooks()
    {
        add_action('after_setup_theme', [$this, 'register_modules']);

        add_filter('sanitize_title', function ($title, $raw_title = '', $context = 'save') {
            if (is_null($title)) {
                $title = '';
            }
            return $title;
        }, 0, 3);
    }

    public function register_modules()
    {
        $module_registrar_path = get_template_directory() . '/inc/bootstrap/ModuleRegistrar.php';
        if (file_exists($module_registrar_path)) {
            require_once $module_registrar_path;
        }

        if (!class_exists('IntelindevInit\\Bootstrap\\ModuleRegistrar')) {
            return;
        }

        (new ModuleRegistrar())->register_all();
    }
}
