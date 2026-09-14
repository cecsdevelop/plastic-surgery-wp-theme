<?php
/**
 * Intelindev Header (Apariencia → Intelindev Header): CTA y estilos generales
 * del header.
 *
 * El menú en sí (qué items, a dónde apuntan) se administra en Apariencia →
 * Menús → Menú principal (location 'primary', hardcodeado en header.php): un
 * selector de menú aquí duplicaría esa asignación sin aportar nada.
 *
 * Los valores se leen con intelindev_get_header_setting() y se imprimen como
 * custom properties CSS (--intelindev-header-*) en un <style> en wp_head, solo
 * para las claves que el admin configuró — el resto lo cubre el default que ya
 * trae assets/css/styles.css en :root, así que sin configurar nada no cambia nada.
 *
 * @package intelindev
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_theme_page(
        __('Intelindev Header', 'intelindev'),
        __('Intelindev Header', 'intelindev'),
        'manage_options',
        'intelindev-header-settings',
        'intelindev_header_settings_page_html'
    );
});

add_action('admin_init', function () {
    register_setting('intelindev_header_settings_group', 'intelindev_header_settings', 'intelindev_header_settings_sanitize');
});

/**
 * Imprime los overrides de color/CTA del header como custom properties CSS.
 * Prioridad 20 (después de que wp_head imprime las hojas encoladas en
 * prioridad 10): para :root, con la misma especificidad gana la declaración que
 * aparece después en el documento, así que esto pisa los defaults de styles.css
 * sin !important y sin reescribir el archivo estático en cada guardado.
 */
add_action('wp_head', 'intelindev_print_header_style_overrides', 20);

function intelindev_print_header_style_overrides() {
    $map = [
        'link_color'                => '--intelindev-header-link-color',
        'scrolled_bg_color'         => '--intelindev-header-scrolled-bg',
        'scrolled_link_color'       => '--intelindev-header-scrolled-link-color',
        'submenu_bg_color'          => '--intelindev-header-submenu-bg',
        'submenu_link_color'        => '--intelindev-header-submenu-link-color',
        'submenu_hover_bg_color'    => '--intelindev-header-submenu-hover-bg',
        'submenu_hover_link_color'  => '--intelindev-header-submenu-hover-link-color',
        'cta_bg_color'              => '--intelindev-header-cta-bg',
        'cta_text_color'            => '--intelindev-header-cta-text-color',
        'cta_hover_bg_color'        => '--intelindev-header-cta-hover-bg',
        'cta_hover_text_color'      => '--intelindev-header-cta-hover-text-color',
        'scrolled_cta_bg_color'     => '--intelindev-header-scrolled-cta-bg',
        'scrolled_cta_text_color'   => '--intelindev-header-scrolled-cta-text-color',
    ];

    $declarations = '';
    foreach ($map as $key => $css_var) {
        $value = intelindev_sanitize_css_color((string) intelindev_get_header_setting($key, ''));
        if ($value === '') continue;
        $declarations .= $css_var . ':' . $value . ';';
    }

    if ($declarations === '') return;

    echo '<style id="intelindev-header-style-overrides">:root{' . $declarations . '}</style>' . "\n";
}

/**
 * Whitelist estricta para valores que se imprimen dentro de un bloque <style>: hex
 * (#fff, #ffffff, #ffffffff) o rgb()/rgba() con componentes numéricos. Cualquier
 * otra cosa (incluido un intento de cerrar la etiqueta </style>) se descarta entera.
 */
function intelindev_sanitize_css_color(string $value): string {
    $value = trim($value);
    if ($value === '') return '';

    if (preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
        return $value;
    }

    if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $value)) {
        return $value;
    }

    return '';
}

/**
 * Sanitize — parte de lo ya guardado y sobreescribe solo las claves que este
 * formulario gestiona. Un array nuevo con solo las claves del form borraría
 * cualquier otra clave que llegara a vivir en esta option más adelante.
 */
function intelindev_header_settings_sanitize($input) {
    $input    = is_array($input) ? $input : [];
    $existing = get_option('intelindev_header_settings', []);
    $out      = is_array($existing) ? $existing : [];

    // CTA: texto/URL vacíos = sin CTA (ver header.php). Un texto override es
    // global (mismo texto para todos los idiomas del sitio).
    if (!empty($input['cta_text'])) {
        $out['cta_text'] = sanitize_text_field($input['cta_text']);
    } else {
        unset($out['cta_text']);
    }

    if (!empty($input['cta_url'])) {
        $url = esc_url_raw($input['cta_url']);
        // Sin esquema http(s) no sirve como href real; se descarta en vez de
        // guardar algo que rompería el CTA.
        $out['cta_url'] = (is_string($url) && preg_match('#^https?://#i', $url)) ? $url : '';
        if ($out['cta_url'] === '') unset($out['cta_url']);
    } else {
        unset($out['cta_url']);
    }

    // Colores: vacío o inválido = se quita la clave (vuelve al default de styles.css).
    $color_keys = [
        'link_color', 'scrolled_bg_color', 'scrolled_link_color',
        'submenu_bg_color', 'submenu_link_color', 'submenu_hover_bg_color', 'submenu_hover_link_color',
        'cta_bg_color', 'cta_text_color', 'cta_hover_bg_color', 'cta_hover_text_color',
        'scrolled_cta_bg_color', 'scrolled_cta_text_color',
    ];
    foreach ($color_keys as $key) {
        $clean = intelindev_sanitize_css_color((string) ($input[$key] ?? ''));
        if ($clean !== '') {
            $out[$key] = $clean;
        } else {
            unset($out[$key]);
        }
    }

    return $out;
}

/**
 * Lee una clave de intelindev_header_settings (CTA, colores).
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
if (!function_exists('intelindev_get_header_setting')) {
    function intelindev_get_header_setting($key, $default = null) {
        $opts = get_option('intelindev_header_settings', []);
        return (is_array($opts) && array_key_exists($key, $opts)) ? $opts[$key] : $default;
    }
}

/* ------------------------------------------------------------------ */
/* Page HTML                                                            */
/* ------------------------------------------------------------------ */
function intelindev_header_settings_page_html() {
    if (!current_user_can('manage_options')) return;

    if (isset($_GET['settings-updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__('Configuración del header guardada correctamente.', 'intelindev')
            . '</p></div>';
    }
    $opts = get_option('intelindev_header_settings', []);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Intelindev Header', 'intelindev'); ?></h1>
        <p><?php esc_html_e('CTA y colores del header. El logo se administra de forma nativa en Apariencia → Personalizar → Identidad del sitio; los items del menú principal, en Apariencia → Menús.', 'intelindev'); ?></p>

        <form action="options.php" method="post">
            <?php settings_fields('intelindev_header_settings_group'); ?>

            <h2><?php esc_html_e('CTA — botón del header', 'intelindev'); ?></h2>
            <p class="description"><?php esc_html_e('Ambos campos vacíos = sin CTA en el header.', 'intelindev'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="intelindev_cta_text"><?php esc_html_e('Texto', 'intelindev'); ?></label></th>
                    <td>
                        <input type="text" id="intelindev_cta_text" name="intelindev_header_settings[cta_text]" value="<?php echo esc_attr($opts['cta_text'] ?? ''); ?>" class="regular-text" placeholder="<?php esc_attr_e('Contactanos', 'intelindev'); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="intelindev_cta_url"><?php esc_html_e('URL', 'intelindev'); ?></label></th>
                    <td>
                        <input type="url" id="intelindev_cta_url" name="intelindev_header_settings[cta_url]" value="<?php echo esc_attr($opts['cta_url'] ?? ''); ?>" class="regular-text" placeholder="https://..." />
                        <p class="description"><?php esc_html_e('Debe empezar con http:// o https://.', 'intelindev'); ?></p>
                    </td>
                </tr>
                <?php
                intelindev_header_color_field('cta_bg_color', __('Fondo', 'intelindev'), $opts);
                intelindev_header_color_field('cta_text_color', __('Texto', 'intelindev'), $opts);
                intelindev_header_color_field('cta_hover_bg_color', __('Fondo (hover)', 'intelindev'), $opts);
                intelindev_header_color_field('cta_hover_text_color', __('Texto (hover)', 'intelindev'), $opts);
                intelindev_header_color_field('scrolled_cta_bg_color', __('Fondo con header sticky', 'intelindev'), $opts);
                intelindev_header_color_field('scrolled_cta_text_color', __('Texto con header sticky', 'intelindev'), $opts);
                ?>
            </table>

            <h2><?php esc_html_e('Estilos generales del header', 'intelindev'); ?></h2>
            <table class="form-table" role="presentation">
                <?php
                intelindev_header_color_field('link_color', __('Color de los links del menú', 'intelindev'), $opts);
                intelindev_header_color_field('scrolled_bg_color', __('Fondo del header sticky (al scrollear)', 'intelindev'), $opts);
                intelindev_header_color_field('scrolled_link_color', __('Color de los links con header sticky', 'intelindev'), $opts);
                intelindev_header_color_field('submenu_bg_color', __('Fondo de los submenús', 'intelindev'), $opts);
                intelindev_header_color_field('submenu_link_color', __('Color de los links del submenú', 'intelindev'), $opts);
                intelindev_header_color_field('submenu_hover_bg_color', __('Fondo del submenú (hover)', 'intelindev'), $opts);
                intelindev_header_color_field('submenu_hover_link_color', __('Color del link del submenú (hover)', 'intelindev'), $opts);
                ?>
            </table>

            <?php submit_button(__('Guardar', 'intelindev')); ?>
        </form>
    </div>
    <script>
    (function($){
        $('.intelindev-color-input').on('input', function(){
            var input = $(this);
            $('.intelindev-color-preview[data-input="#' + input.attr('id') + '"]').css('background', input.val() || 'transparent');
        });
    })(jQuery);
    </script>
    <?php
}

/**
 * Fila de form-table para un campo de color con vista previa. El input queda como
 * texto libre (no <input type="color">, que fuerza #rrggbb y no admite vacío ni
 * rgba) para que "vacío" siga significando "usar el default del sitio".
 */
function intelindev_header_color_field(string $key, string $label, array $opts): void {
    $val = esc_attr((string) ($opts[$key] ?? ''));
    ?>
    <tr>
        <th scope="row"><label for="intelindev_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <span class="intelindev-color-preview" data-input="#intelindev_<?php echo esc_attr($key); ?>" style="display:inline-block;width:24px;height:24px;border:1px solid #dcdcde;border-radius:4px;vertical-align:middle;margin-right:8px;background:<?php echo $val !== '' ? $val : 'transparent'; ?>;"></span>
            <input type="text" id="intelindev_<?php echo esc_attr($key); ?>" name="intelindev_header_settings[<?php echo esc_attr($key); ?>]" value="<?php echo $val; ?>" class="intelindev-color-input" placeholder="#5166ec" style="width:140px;" />
        </td>
    </tr>
    <?php
}
