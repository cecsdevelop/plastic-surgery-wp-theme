<?php
/**
 * @package pswpt
 */

namespace pswptInit\General;

class BaseController
{
    protected $plugin_path;
    protected $plugin_url;
    protected $version;

    public function __construct()
    {
        $this->plugin_path = trailingslashit(get_template_directory());
        $this->plugin_url = trailingslashit(get_template_directory_uri());
        $this->version = '1.0.0';
    }

    /**
     * Detectar idioma actual.
     * Delega a la capa multilang del theme si está disponible.
     * Fallback: URL-based detection (seguro en bootstrap temprano).
     */
    public function get_current_lang()
    {
        if (function_exists('idml_get_current_language')) {
            return idml_get_current_language();
        }
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return (strpos($request_uri, '/en/') !== false) ? 'en' : 'es';
    }

    /**
     * Resolver idioma para shortcodes y queries.
     * Prioridad: atributo explícito → capa multilang del theme.
     */
    protected function resolve_lang(string $attr_lang = ''): string
    {
        $attr_lang = sanitize_key($attr_lang);
        if (!empty($attr_lang)) {
            return $attr_lang;
        }
        return $this->get_current_lang();
    }
}
