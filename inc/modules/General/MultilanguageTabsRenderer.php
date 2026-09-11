<?php
/**
 * @package General
 * 
 * Clase unificada para inyectar pestañas multilingües (ES, EN, PT)
 * en cualquier Metabox sin repetir código HTML/JS.
 */
namespace IntelindevInit\General;

if (!class_exists('IntelindevInit\\General\\MultilanguageTabsRenderer')):
class MultilanguageTabsRenderer
{
    private const LANGUAGES = [
        'es' => 'Español',
        'en' => 'English',
        'pt' => 'Português'
    ];

    /**
     * Renderiza el wrapper de idiomas y ejecuta un callback por cada idioma.
     *
     * @param string   $metabox_id ID único para el prefijo de los paneles.
     * @param callable $render_callback Función que recibe ($lang_code, $lang_label) y pinta dentro de la pestaña.
     */
    public static function render(string $metabox_id, callable $render_callback)
    {
        echo '<h2 class="nav-tab-wrapper">';
        $first = true;
        foreach (self::LANGUAGES as $code => $label) {
            $active = $first ? ' nav-tab-active' : '';
            echo '<a href="#" class="nav-tab ' . esc_attr($metabox_id) . '-lang-tab' . $active . '" data-lang="' . esc_attr($code) . '">' . esc_html($label) . '</a>';
            $first = false;
        }
        echo '</h2>';

        $first = true;
        foreach (self::LANGUAGES as $code => $label) :
            $display = $first ? 'block' : 'none';
        ?>
        <div id="<?php echo esc_attr($metabox_id); ?>-panel-<?php echo esc_attr($code); ?>" class="<?php echo esc_attr($metabox_id); ?>-lang-panel" style="display:<?php echo $display; ?>; padding:16px 0;">
            <?php call_user_func($render_callback, $code, $label); ?>
        </div>
        <?php 
            $first = false;
        endforeach;

        self::render_js($metabox_id);
    }

    /**
     * Devuelve el array base de idiomas por si la clase que llama necesita iterarlos (e.g. validación).
     */
    public static function get_languages(): array
    {
        return self::LANGUAGES;
    }

    private static function render_js(string $metabox_id)
    {
        ?>
        <style>
            .nav-tab-wrapper { margin-bottom: 1em; }
            .<?php echo esc_attr($metabox_id); ?>-lang-panel { background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-top: -1px; }
        </style>
        <script>
            (function ($) {
                $(document).ready(function () {
                    $(document).on('click', '.<?php echo esc_attr($metabox_id); ?>-lang-tab', function(e) {
                        e.preventDefault();
                        var lang = $(this).data('lang');
                        $('.<?php echo esc_attr($metabox_id); ?>-lang-tab').removeClass('nav-tab-active');
                        $(this).addClass('nav-tab-active');
                        $('.<?php echo esc_attr($metabox_id); ?>-lang-panel').hide();
                        $('#<?php echo esc_attr($metabox_id); ?>-panel-' + lang).show();
                    });
                });
            })(jQuery);
        </script>
        <?php
    }
}
endif;
