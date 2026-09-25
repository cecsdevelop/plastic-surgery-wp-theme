<?php
/**
 * pswpt Header (Apariencia → pswpt Header): sticky, logos, menú,
 * colores y CTA del header en tres pestañas (Header / Menú / CTA).
 *
 * Todo vive en la option pswpt_header_settings y se lee con
 * pswpt_get_header_setting(). Los colores se imprimen como custom
 * properties CSS (--psw-header-*) en un <style> en wp_head, solo las
 * que el admin configuró — el resto lo cubre el default de
 * assets/css/styles.css en :root, así que sin configurar nada no cambia nada.
 *
 * Sticky es la primera decisión: apagado, el header es estático (sin
 * position:sticky, sin listener de scroll, sin variables sticky); encendido,
 * habilita el logo sticky y la columna "Sticky" de cada color. Los valores
 * sticky se conservan en la option aunque se apague el toggle.
 *
 * El menú no se guarda acá: el dropdown asigna la ubicación 'primary'
 * (theme mod nav_menu_locations), lo mismo que Apariencia → Menús →
 * Ubicaciones, así las dos pantallas coinciden y los filtros de menus.php que
 * dependen de theme_location === 'primary' siguen aplicando.
 *
 * CTA: apunta a una Página (permalink y título pasan por los filtros de
 * traducción, así que cambian por idioma solos), a una URL libre, o abre un
 * modal cuyo contenido es libre (script de CRM, HTML de un form, shortcode).
 * Ese contenido se imprime en un <template> inerte y scripts.js lo inyecta en
 * el <dialog> recién al primer clic, así el JS del CRM no carga hasta que
 * alguien abre el modal.
 *
 * @package pswpt
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_theme_page(
        __('pswpt Header', 'pswpt'),
        __('pswpt Header', 'pswpt'),
        'manage_options',
        'pswpt-header-settings',
        'pswpt_header_settings_page_html'
    );
});

add_action('admin_init', function () {
    register_setting('pswpt_header_settings_group', 'pswpt_header_settings', 'pswpt_header_settings_sanitize');
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'appearance_page_pswpt-header-settings') {
        pswpt_admin_enqueue_field_assets();
    }
}, 20);

/* ------------------------------------------------------------------ */
/* Definición de colores                                                */
/* ------------------------------------------------------------------ */

/**
 * Matriz de colores del header: clave de option => label + custom property.
 * La variante sticky de cada clave es 'sticky_' . $key y su custom property
 * pswpt_header_sticky_css_var(). Única fuente para sanitize, salida CSS
 * y formulario: agregar un color acá alcanza para que aparezca en todos lados
 * (styles.css debe traer el default de la variable nueva).
 */
function pswpt_header_color_matrix(): array {
    return [
        'menu' => [
            'bg_color'                 => ['label' => __('Header background', 'pswpt'),               'var' => '--psw-header-bg'],
            'nav_bg_color'             => ['label' => __('Menu bar background', 'pswpt'),     'var' => '--psw-header-nav-bg'],
            'link_color'               => ['label' => __('Link color', 'pswpt'),             'var' => '--psw-header-link-color'],
            'active_bg_color'          => ['label' => __('Active item background', 'pswpt'),          'var' => '--psw-header-active-bg'],
            'active_link_color'        => ['label' => __('Active item text', 'pswpt'),          'var' => '--psw-header-active-color'],
            'submenu_bg_color'         => ['label' => __('Submenu background', 'pswpt'),              'var' => '--psw-header-submenu-bg'],
            'submenu_link_color'       => ['label' => __('Submenu link color', 'pswpt'), 'var' => '--psw-header-submenu-link-color'],
            'submenu_hover_bg_color'   => ['label' => __('Submenu background (hover)', 'pswpt'),      'var' => '--psw-header-submenu-hover-bg'],
            'submenu_hover_link_color' => ['label' => __('Submenu link (hover)', 'pswpt'),       'var' => '--psw-header-submenu-hover-link-color'],
        ],
        'cta' => [
            'cta_bg_color'         => ['label' => __('Background', 'pswpt'),         'var' => '--psw-header-cta-bg'],
            'cta_text_color'       => ['label' => __('Text', 'pswpt'),         'var' => '--psw-header-cta-text-color'],
            'cta_hover_bg_color'   => ['label' => __('Background (hover)', 'pswpt'), 'var' => '--psw-header-cta-hover-bg'],
            'cta_hover_text_color' => ['label' => __('Text (hover)', 'pswpt'), 'var' => '--psw-header-cta-hover-text-color'],
        ],
    ];
}

function pswpt_header_sticky_css_var(string $var): string {
    return str_replace('--psw-header-', '--psw-header-sticky-', $var);
}

/* ------------------------------------------------------------------ */
/* Lectura (frontend)                                                   */
/* ------------------------------------------------------------------ */

if (!function_exists('pswpt_get_header_setting')) {
    function pswpt_get_header_setting($key, $default = null) {
        $opts = get_option('pswpt_header_settings', []);
        return (is_array($opts) && array_key_exists($key, $opts)) ? $opts[$key] : $default;
    }
}

function pswpt_header_is_sticky(): bool {
    return !empty(pswpt_get_header_setting('sticky_enabled', 0));
}

function pswpt_get_header_sticky_threshold(): int {
    $threshold = (int) pswpt_get_header_setting('sticky_threshold', 80);
    return max(0, min(1000, $threshold));
}

function pswpt_get_header_cta_text($lang = null): string {
    return pswpt_resolve_lang_text(pswpt_get_header_setting('cta_text', []), $lang);
}

/**
 * CTA resuelto para el idioma: type none|page|url|modal, href, text,
 * modal_title y modal_content (ya con do_shortcode). type 'none' = no imprimir.
 */
function pswpt_get_header_cta($lang = null): array {
    $none = ['type' => 'none', 'href' => '', 'text' => '', 'modal_title' => '', 'modal_content' => '', 'style' => 'text'];

    $type = (string) pswpt_get_header_setting('cta_type', 'none');
    $text = pswpt_get_header_cta_text($lang);
    $href = '';
    $modal_title = '';
    $modal_content = '';

    switch ($type) {
        case 'page':
            $page_id = (int) pswpt_get_header_setting('cta_page_id', 0);
            if ($page_id <= 0 || get_post_type($page_id) !== 'page' || get_post_status($page_id) !== 'publish') {
                return $none;
            }
            $href = (string) get_permalink($page_id); // page_link → slug traducido
            if ($text === '') {
                $text = (string) get_the_title($page_id); // the_title → título traducido
            }
            break;

        case 'url':
            $href = trim((string) pswpt_get_header_setting('cta_url', ''));
            if (!preg_match('#^https?://#i', $href)) {
                return $none;
            }
            break;

        case 'modal':
            if (trim((string) pswpt_get_header_setting('cta_modal_content', '')) === '') {
                return $none;
            }
            // El contenido no se imprime en la página: lo pide scripts.js al primer
            // clic a GET pswpt/v1/modal?lang= (ver pswpt_render_header_modal_content).
            $modal_content = '';
            $modal_title = pswpt_resolve_lang_text(pswpt_get_header_setting('cta_modal_title', []), $lang);
            break;

        default:
            return $none;
    }

    // Estilo 'icon' (botón cuadrado con ícono, como en el diseño): el texto pasa a
    // ser la etiqueta accesible y puede venir vacío.
    $style = (string) pswpt_get_header_setting('cta_style', 'text') === 'icon' ? 'icon' : 'text';
    if ($text === '' && $style === 'icon') {
        $text = idml_t('cta.icon_label', $lang);
    }
    if ($text === '') {
        return $none;
    }

    return compact('type', 'href', 'text', 'modal_title', 'modal_content', 'style');
}

/**
 * Contenido del modal renderizado (shortcodes incluidos) para un idioma. Lo
 * sirve el endpoint REST de abajo, así el HTML/JS del modal (formulario, script
 * de CRM, captcha) no viaja en cada página y el token antispam de un
 * formulario nace al abrir el modal, no al cachear la página.
 */
function pswpt_render_header_modal_content(string $lang): string {
    $content = trim((string) pswpt_get_header_setting('cta_modal_content', ''));
    if ($content === '' || (string) pswpt_get_header_setting('cta_type', 'none') !== 'modal') {
        return '';
    }
    idml_set_current_language($lang);
    return pswpt_resolve_root_relative_urls(do_shortcode($content));
}

add_action('rest_api_init', function () {
    register_rest_route('pswpt/v1', '/modal', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'args'                => ['lang' => ['sanitize_callback' => 'sanitize_key']],
        'callback'            => function (\WP_REST_Request $request) {
            $lang = idml_normalize_lang((string) $request->get_param('lang'));
            if (!in_array($lang, idml_get_languages(), true)) {
                $lang = idml_get_default_language();
            }
            $html = pswpt_render_header_modal_content($lang);
            $response = new \WP_REST_Response($html === '' ? ['html' => ''] : ['html' => $html], $html === '' ? 404 : 200);
            // Token antispam fresco en cada apertura: sin caché intermedia.
            $response->header('Cache-Control', 'no-store, max-age=0');
            return $response;
        },
    ]);
});

/**
 * Logo del header: el elegido acá (y el sticky, si hay), si no el del
 * Personalizador, si no el nombre del sitio. Los dos <img> se imprimen y CSS
 * muestra uno u otro según .is-scrolled — sin JS extra. Eager + fetchpriority
 * en el principal porque suele ser el LCP.
 */
function pswpt_render_header_logo(string $home_url): void {
    $logo_id        = (int) pswpt_get_header_setting('logo_id', 0);
    $sticky_logo_id = pswpt_header_is_sticky() ? (int) pswpt_get_header_setting('sticky_logo_id', 0) : 0;
    $site_name      = get_bloginfo('name');

    if ($logo_id > 0 && wp_attachment_is_image($logo_id)) {
        $classes = 'site-logo' . ($sticky_logo_id > 0 && $sticky_logo_id !== $logo_id ? ' site-logo--has-sticky' : '');
        echo '<a class="' . esc_attr($classes) . '" href="' . esc_url($home_url) . '" rel="home">';
        echo wp_get_attachment_image($logo_id, 'full', false, [
            'class'         => 'site-logo__img site-logo__img--default',
            'alt'           => $site_name,
            'loading'       => 'eager',
            'fetchpriority' => 'high',
        ]);
        if ($sticky_logo_id > 0 && $sticky_logo_id !== $logo_id && wp_attachment_is_image($sticky_logo_id)) {
            echo wp_get_attachment_image($sticky_logo_id, 'full', false, [
                'class'   => 'site-logo__img site-logo__img--sticky',
                'alt'     => $site_name,
                'loading' => 'eager',
            ]);
        }
        echo '</a>';
        return;
    }

    if (has_custom_logo()) {
        the_custom_logo();
        return;
    }

    // Sin logo configurado: el del diseño, que viene en el theme (assets/img/logo.svg, blanco).
    $theme_logo = get_theme_file_path('assets/img/logo.svg');
    if (file_exists($theme_logo)) {
        echo '<a class="site-logo" href="' . esc_url($home_url) . '" rel="home"><img class="site-logo__img site-logo__img--default" src="' . esc_url(get_theme_file_uri('assets/img/logo.svg')) . '" alt="' . esc_attr($site_name) . '" width="172" height="81" loading="eager" fetchpriority="high"></a>';
        return;
    }

    echo '<a class="site-title" href="' . esc_url($home_url) . '" rel="home">' . esc_html($site_name) . '</a>';
}

/**
 * Imprime los overrides de color como custom properties. Prioridad 20
 * (después de que wp_head imprime las hojas encoladas en prioridad 10): para
 * :root, con la misma especificidad gana la declaración que aparece después
 * en el documento, así que esto pisa los defaults de styles.css sin
 * !important. Las variables sticky solo se imprimen con sticky activo.
 */
add_action('wp_head', 'pswpt_print_header_style_overrides', 20);

function pswpt_print_header_style_overrides() {
    $sticky = pswpt_header_is_sticky();
    $declarations = '';

    foreach (pswpt_header_color_matrix() as $group) {
        foreach ($group as $key => $def) {
            $value = pswpt_sanitize_css_color((string) pswpt_get_header_setting($key, ''));
            if ($value !== '') {
                $declarations .= $def['var'] . ':' . $value . ';';
            }
            if ($sticky) {
                $value = pswpt_sanitize_css_color((string) pswpt_get_header_setting('sticky_' . $key, ''));
                if ($value !== '') {
                    $declarations .= pswpt_header_sticky_css_var($def['var']) . ':' . $value . ';';
                }
            }
        }
    }

    if ($declarations === '') return;

    echo '<style id="pswpt-header-style-overrides">:root{' . $declarations . '}</style>' . "\n";
}

/* ------------------------------------------------------------------ */
/* Sanitize                                                             */
/* ------------------------------------------------------------------ */

/**
 * Parte de lo ya guardado y sobreescribe solo las claves que este formulario
 * gestiona; una clave sin valor válido se quita (vuelve al default).
 */
function pswpt_header_settings_sanitize($input) {
    $input    = is_array($input) ? $input : [];
    $existing = get_option('pswpt_header_settings', []);
    $out      = is_array($existing) ? $existing : [];

    // Claves de la versión anterior del panel (scrolled_*): ya no se leen.
    foreach (array_keys($out) as $key) {
        if (strpos((string) $key, 'scrolled_') === 0) {
            unset($out[$key]);
        }
    }

    // --- Header: sticky + logos ---
    $out['sticky_enabled']   = !empty($input['sticky_enabled']) ? 1 : 0;
    $out['sticky_threshold'] = max(0, min(1000, (int) ($input['sticky_threshold'] ?? 80)));

    foreach (['logo_id', 'sticky_logo_id'] as $key) {
        $id = (int) ($input[$key] ?? 0);
        if ($id > 0 && wp_attachment_is_image($id)) {
            $out[$key] = $id;
        } else {
            unset($out[$key]);
        }
    }

    // --- Menú: asigna la ubicación primary (no se guarda en la option) ---
    if (array_key_exists('menu_id', $input)) {
        $menu_id   = (int) $input['menu_id'];
        $locations = get_theme_mod('nav_menu_locations', []);
        $locations = is_array($locations) ? $locations : [];
        if ($menu_id > 0 && wp_get_nav_menu_object($menu_id)) {
            $locations['primary'] = $menu_id;
        } else {
            unset($locations['primary']);
        }
        set_theme_mod('nav_menu_locations', $locations);
    }

    // --- Colores (normal + sticky) ---
    foreach (pswpt_header_color_matrix() as $group) {
        foreach (array_keys($group) as $key) {
            foreach ([$key, 'sticky_' . $key] as $option_key) {
                $clean = pswpt_sanitize_css_color((string) ($input[$option_key] ?? ''));
                if ($clean !== '') {
                    $out[$option_key] = $clean;
                } else {
                    unset($out[$option_key]);
                }
            }
        }
    }

    // --- CTA ---
    $type = sanitize_key((string) ($input['cta_type'] ?? 'none'));
    $out['cta_type'] = in_array($type, ['page', 'url', 'modal'], true) ? $type : 'none';
    $out['cta_style'] = (string) ($input['cta_style'] ?? 'text') === 'icon' ? 'icon' : 'text';

    $page_id = (int) ($input['cta_page_id'] ?? 0);
    if ($page_id > 0 && get_post_type($page_id) === 'page') {
        $out['cta_page_id'] = $page_id;
    } else {
        unset($out['cta_page_id']);
    }

    $url = esc_url_raw((string) ($input['cta_url'] ?? ''));
    if (is_string($url) && preg_match('#^https?://#i', $url)) {
        $out['cta_url'] = $url;
    } else {
        unset($out['cta_url']);
    }

    foreach (['cta_text', 'cta_modal_title'] as $key) {
        $texts = pswpt_sanitize_lang_text($input[$key] ?? []);
        if ($texts) {
            $out[$key] = $texts;
        } else {
            unset($out[$key]);
        }
    }

    // Contenido del modal: HTML/script/shortcode. Mismo criterio que el widget
    // "HTML personalizado" de WP: tal cual solo para quien tiene unfiltered_html.
    $content = trim((string) ($input['cta_modal_content'] ?? ''));
    if ($content !== '') {
        $out['cta_modal_content'] = current_user_can('unfiltered_html') ? $content : wp_kses_post($content);
    } else {
        unset($out['cta_modal_content']);
    }

    return $out;
}

/* ------------------------------------------------------------------ */
/* Page HTML                                                            */
/* ------------------------------------------------------------------ */

function pswpt_header_settings_page_html() {
    if (!current_user_can('manage_options')) return;

    $opts   = get_option('pswpt_header_settings', []);
    $opts   = is_array($opts) ? $opts : [];
    $sticky = !empty($opts['sticky_enabled']);
    $matrix = pswpt_header_color_matrix();
    $option = 'pswpt_header_settings';
    $sticky_if = $option . '[sticky_enabled]';
    $cta_if    = $option . '[cta_type]';

    $tabs = [
        'header' => ['🧭', __('Header', 'pswpt')],
        'menu'   => ['☰', __('Menu', 'pswpt')],
        'cta'    => ['🔘', __('CTA', 'pswpt')],
    ];

    if (isset($_GET['settings-updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Header settings saved successfully.', 'pswpt') . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('pswpt Header', 'pswpt'); ?></h1>
        <p><?php esc_html_e('Header sticky behavior, logos, menu, colors and CTA button.', 'pswpt'); ?></p>

        <form action="options.php" method="post" class="pswpt-settings-form pswpt-panel-form">
            <?php settings_fields('pswpt_header_settings_group'); ?>

            <?php pswpt_admin_tabs_nav($tabs); ?>

            <div class="tab-content pswpt-tabs-container">

                <!-- ===================== HEADER ===================== -->
                <div class="tab-pane fade show active" id="content-header" role="tabpanel">
                    <h2><?php esc_html_e('Behavior', 'pswpt'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Sticky header', 'pswpt'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="pswpt_sticky_enabled" name="pswpt_header_settings[sticky_enabled]" value="1"<?php checked($sticky); ?> />
                                    <?php esc_html_e('The header stays fixed at the top while scrolling', 'pswpt'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Enabling it turns on the sticky logo and the "Sticky" color column in Menu and CTA. When off, the header is static and no scroll script is loaded.', 'pswpt'); ?></p>
                            </td>
                        </tr>
                        <tr data-show-if="<?php echo esc_attr($sticky_if); ?>">
                            <th scope="row"><label for="pswpt_sticky_threshold"><?php esc_html_e('Scroll threshold', 'pswpt'); ?></label></th>
                            <td>
                                <input type="number" id="pswpt_sticky_threshold" name="pswpt_header_settings[sticky_threshold]" value="<?php echo esc_attr((string) ($opts['sticky_threshold'] ?? 80)); ?>" min="0" max="1000" step="1" class="small-text" /> px
                                <p class="description"><?php esc_html_e('Scroll distance in pixels after which the header switches to its sticky state (sticky logo and colors).', 'pswpt'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h2><?php esc_html_e('Logo', 'pswpt'); ?></h2>
                    <table class="form-table" role="presentation">
                        <?php
                        pswpt_admin_media_field($option, 'logo_id', __('Header logo', 'pswpt'), $opts, __('With no logo selected, the Customizer one (Site Identity) is used and, failing that, the site name.', 'pswpt'));
                        pswpt_admin_media_field($option, 'sticky_logo_id', __('Sticky header logo', 'pswpt'), $opts, __('Shown instead of the main logo when the header is sticky. Empty = the main logo is kept.', 'pswpt'), $sticky_if);
                        ?>
                    </table>
                </div>

                <!-- ===================== MENÚ ===================== -->
                <div class="tab-pane fade" id="content-menu" role="tabpanel">
                    <h2><?php esc_html_e('Main menu', 'pswpt'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="pswpt_menu_id"><?php esc_html_e('Menu to display', 'pswpt'); ?></label></th>
                            <td>
                                <?php
                                $locations  = get_nav_menu_locations();
                                $current_id = (int) ($locations['primary'] ?? 0);
                                $menus      = wp_get_nav_menus();
                                ?>
                                <select id="pswpt_menu_id" name="pswpt_header_settings[menu_id]">
                                    <option value="0"><?php esc_html_e('— None —', 'pswpt'); ?></option>
                                    <?php foreach ($menus as $menu) : ?>
                                        <option value="<?php echo (int) $menu->term_id; ?>"<?php selected($current_id, (int) $menu->term_id); ?>><?php echo esc_html($menu->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php
                                    printf(
                                        esc_html__('This is the "Main menu" location: same as assigning it in %s. Menu items are edited there.', 'pswpt'),
                                        '<a href="' . esc_url(admin_url('nav-menus.php')) . '">' . esc_html__('Appearance → Menus', 'pswpt') . '</a>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h2><?php esc_html_e('Header and menu colors', 'pswpt'); ?></h2>
                    <?php
                    $color_columns = [
                        ['prefix' => '', 'label' => __('Normal', 'pswpt')],
                        ['prefix' => 'sticky_', 'label' => __('Sticky', 'pswpt'), 'show_if' => $sticky_if],
                    ];
                    pswpt_admin_color_table($option, array_map(fn($def) => $def['label'], $matrix['menu']), $opts, $color_columns);
                    ?>
                </div>

                <!-- ===================== CTA ===================== -->
                <div class="tab-pane fade" id="content-cta" role="tabpanel">
                    <h2><?php esc_html_e('CTA Button', 'pswpt'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Type', 'pswpt'); ?></th>
                            <td>
                                <?php
                                $cta_type = (string) ($opts['cta_type'] ?? 'none');
                                $types = [
                                    'none'  => __('No CTA', 'pswpt'),
                                    'page'  => __('Go to a site page', 'pswpt'),
                                    'url'   => __('Go to a URL', 'pswpt'),
                                    'modal' => __('Open a modal', 'pswpt'),
                                ];
                                foreach ($types as $value => $label) :
                                ?>
                                    <label class="pswpt-radio-row">
                                        <input type="radio" name="pswpt_header_settings[cta_type]" value="<?php echo esc_attr($value); ?>"<?php checked($cta_type, $value); ?> />
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>

                        <tr data-show-if="<?php echo esc_attr($cta_if); ?>=page">
                            <th scope="row"><label for="pswpt_cta_page_id"><?php esc_html_e('Page', 'pswpt'); ?></label></th>
                            <td>
                                <?php
                                wp_dropdown_pages([
                                    'name'              => 'pswpt_header_settings[cta_page_id]',
                                    'id'                => 'pswpt_cta_page_id',
                                    'selected'          => (int) ($opts['cta_page_id'] ?? 0),
                                    'show_option_none'  => __('— Select —', 'pswpt'),
                                    'option_none_value' => 0,
                                    'post_status'       => 'publish',
                                ]);
                                ?>
                                <p class="description"><?php esc_html_e('The URL and title are taken from the page and change automatically per language (the page\'s translated slug and title).', 'pswpt'); ?></p>
                            </td>
                        </tr>

                        <tr data-show-if="<?php echo esc_attr($cta_if); ?>=url">
                            <th scope="row"><label for="pswpt_cta_url"><?php esc_html_e('URL', 'pswpt'); ?></label></th>
                            <td>
                                <input type="url" id="pswpt_cta_url" name="pswpt_header_settings[cta_url]" value="<?php echo esc_attr((string) ($opts['cta_url'] ?? '')); ?>" class="regular-text" placeholder="https://..." />
                                <p class="description"><?php esc_html_e('Must start with http:// or https://.', 'pswpt'); ?></p>
                            </td>
                        </tr>

                        <tr data-show-if="<?php echo esc_attr($cta_if); ?>!=none">
                            <th scope="row"><?php esc_html_e('Button style', 'pswpt'); ?></th>
                            <td>
                                <?php $cta_style = (string) ($opts['cta_style'] ?? 'text'); ?>
                                <label style="margin-right:16px;"><input type="radio" name="pswpt_header_settings[cta_style]" value="text"<?php checked($cta_style, 'text'); ?> /> <?php esc_html_e('Pill with text', 'pswpt'); ?></label>
                                <label><input type="radio" name="pswpt_header_settings[cta_style]" value="icon"<?php checked($cta_style, 'icon'); ?> /> <?php esc_html_e('Square with icon (the text is kept as an accessible label)', 'pswpt'); ?></label>
                            </td>
                        </tr>
                        <?php
                        pswpt_admin_lang_text_fields($option, 'cta_text', __('Button text (%s)', 'pswpt'), $opts, __('If a language is empty, the default language\'s is used. With type "page", empty = page title; with icon style, empty = "Contact us" (key cta.icon_label).', 'pswpt'), $cta_if . '!=none');
                        pswpt_admin_lang_text_fields($option, 'cta_modal_title', __('Modal title (%s)', 'pswpt'), $opts, '', $cta_if . '=modal');
                        ?>
                        <tr data-show-if="<?php echo esc_attr($cta_if); ?>=modal">
                            <th scope="row"><label for="pswpt_cta_modal_content"><?php esc_html_e('Modal content', 'pswpt'); ?></label></th>
                            <td>
                                <textarea id="pswpt_cta_modal_content" name="pswpt_header_settings[cta_modal_content]" rows="10" class="large-text code" spellcheck="false"><?php echo esc_textarea((string) ($opts['cta_modal_content'] ?? '')); ?></textarea>
                                <p class="description"><?php esc_html_e('Accepts a CRM embed script (GoHighLevel, HubSpot…), form HTML or a shortcode. It only loads when the visitor opens the modal, so it does not affect site speed.', 'pswpt'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div data-show-if="<?php echo esc_attr($cta_if); ?>!=none">
                        <h2><?php esc_html_e('CTA colors', 'pswpt'); ?></h2>
                        <?php pswpt_admin_color_table($option, array_map(fn($def) => $def['label'], $matrix['cta']), $opts, $color_columns); ?>
                    </div>
                </div>

            </div>

            <?php submit_button(__('Save', 'pswpt')); ?>
        </form>
    </div>
    <?php
}
