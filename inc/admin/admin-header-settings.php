<?php
/**
 * Intelindev Header (Apariencia → Intelindev Header): sticky, logos, menú,
 * colores y CTA del header en tres pestañas (Header / Menú / CTA).
 *
 * Todo vive en la option intelindev_header_settings y se lee con
 * intelindev_get_header_setting(). Los colores se imprimen como custom
 * properties CSS (--intelindev-header-*) en un <style> en wp_head, solo las
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

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'appearance_page_intelindev-header-settings') {
        intelindev_admin_enqueue_field_assets();
    }
}, 20);

/* ------------------------------------------------------------------ */
/* Definición de colores                                                */
/* ------------------------------------------------------------------ */

/**
 * Matriz de colores del header: clave de option => label + custom property.
 * La variante sticky de cada clave es 'sticky_' . $key y su custom property
 * intelindev_header_sticky_css_var(). Única fuente para sanitize, salida CSS
 * y formulario: agregar un color acá alcanza para que aparezca en todos lados
 * (styles.css debe traer el default de la variable nueva).
 */
function intelindev_header_color_matrix(): array {
    return [
        'menu' => [
            'bg_color'                 => ['label' => __('Fondo del header', 'intelindev'),               'var' => '--intelindev-header-bg'],
            'nav_bg_color'             => ['label' => __('Fondo de la barra del menú', 'intelindev'),     'var' => '--intelindev-header-nav-bg'],
            'link_color'               => ['label' => __('Color de los links', 'intelindev'),             'var' => '--intelindev-header-link-color'],
            'active_bg_color'          => ['label' => __('Fondo del ítem activo', 'intelindev'),          'var' => '--intelindev-header-active-bg'],
            'active_link_color'        => ['label' => __('Texto del ítem activo', 'intelindev'),          'var' => '--intelindev-header-active-color'],
            'submenu_bg_color'         => ['label' => __('Fondo del submenú', 'intelindev'),              'var' => '--intelindev-header-submenu-bg'],
            'submenu_link_color'       => ['label' => __('Color de los links del submenú', 'intelindev'), 'var' => '--intelindev-header-submenu-link-color'],
            'submenu_hover_bg_color'   => ['label' => __('Fondo del submenú (hover)', 'intelindev'),      'var' => '--intelindev-header-submenu-hover-bg'],
            'submenu_hover_link_color' => ['label' => __('Link del submenú (hover)', 'intelindev'),       'var' => '--intelindev-header-submenu-hover-link-color'],
        ],
        'cta' => [
            'cta_bg_color'         => ['label' => __('Fondo', 'intelindev'),         'var' => '--intelindev-header-cta-bg'],
            'cta_text_color'       => ['label' => __('Texto', 'intelindev'),         'var' => '--intelindev-header-cta-text-color'],
            'cta_hover_bg_color'   => ['label' => __('Fondo (hover)', 'intelindev'), 'var' => '--intelindev-header-cta-hover-bg'],
            'cta_hover_text_color' => ['label' => __('Texto (hover)', 'intelindev'), 'var' => '--intelindev-header-cta-hover-text-color'],
        ],
    ];
}

function intelindev_header_sticky_css_var(string $var): string {
    return str_replace('--intelindev-header-', '--intelindev-header-sticky-', $var);
}

/* ------------------------------------------------------------------ */
/* Lectura (frontend)                                                   */
/* ------------------------------------------------------------------ */

if (!function_exists('intelindev_get_header_setting')) {
    function intelindev_get_header_setting($key, $default = null) {
        $opts = get_option('intelindev_header_settings', []);
        return (is_array($opts) && array_key_exists($key, $opts)) ? $opts[$key] : $default;
    }
}

function intelindev_header_is_sticky(): bool {
    return !empty(intelindev_get_header_setting('sticky_enabled', 0));
}

function intelindev_get_header_sticky_threshold(): int {
    $threshold = (int) intelindev_get_header_setting('sticky_threshold', 80);
    return max(0, min(1000, $threshold));
}

function intelindev_get_header_cta_text($lang = null): string {
    return intelindev_resolve_lang_text(intelindev_get_header_setting('cta_text', []), $lang);
}

/**
 * CTA resuelto para el idioma: type none|page|url|modal, href, text,
 * modal_title y modal_content (ya con do_shortcode). type 'none' = no imprimir.
 */
function intelindev_get_header_cta($lang = null): array {
    $none = ['type' => 'none', 'href' => '', 'text' => '', 'modal_title' => '', 'modal_content' => '', 'style' => 'text'];

    $type = (string) intelindev_get_header_setting('cta_type', 'none');
    $text = intelindev_get_header_cta_text($lang);
    $href = '';
    $modal_title = '';
    $modal_content = '';

    switch ($type) {
        case 'page':
            $page_id = (int) intelindev_get_header_setting('cta_page_id', 0);
            if ($page_id <= 0 || get_post_type($page_id) !== 'page' || get_post_status($page_id) !== 'publish') {
                return $none;
            }
            $href = (string) get_permalink($page_id); // page_link → slug traducido
            if ($text === '') {
                $text = (string) get_the_title($page_id); // the_title → título traducido
            }
            break;

        case 'url':
            $href = trim((string) intelindev_get_header_setting('cta_url', ''));
            if (!preg_match('#^https?://#i', $href)) {
                return $none;
            }
            break;

        case 'modal':
            if (trim((string) intelindev_get_header_setting('cta_modal_content', '')) === '') {
                return $none;
            }
            // El contenido no se imprime en la página: lo pide scripts.js al primer
            // clic a GET intelindev/v1/modal?lang= (ver intelindev_render_header_modal_content).
            $modal_content = '';
            $modal_title = intelindev_resolve_lang_text(intelindev_get_header_setting('cta_modal_title', []), $lang);
            break;

        default:
            return $none;
    }

    // Estilo 'icon' (botón cuadrado con ícono, como en el diseño): el texto pasa a
    // ser la etiqueta accesible y puede venir vacío.
    $style = (string) intelindev_get_header_setting('cta_style', 'text') === 'icon' ? 'icon' : 'text';
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
function intelindev_render_header_modal_content(string $lang): string {
    $content = trim((string) intelindev_get_header_setting('cta_modal_content', ''));
    if ($content === '' || (string) intelindev_get_header_setting('cta_type', 'none') !== 'modal') {
        return '';
    }
    idml_set_current_language($lang);
    return do_shortcode($content);
}

add_action('rest_api_init', function () {
    register_rest_route('intelindev/v1', '/modal', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'args'                => ['lang' => ['sanitize_callback' => 'sanitize_key']],
        'callback'            => function (\WP_REST_Request $request) {
            $lang = idml_normalize_lang((string) $request->get_param('lang'));
            if (!in_array($lang, idml_get_languages(), true)) {
                $lang = idml_get_default_language();
            }
            $html = intelindev_render_header_modal_content($lang);
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
function intelindev_render_header_logo(string $home_url): void {
    $logo_id        = (int) intelindev_get_header_setting('logo_id', 0);
    $sticky_logo_id = intelindev_header_is_sticky() ? (int) intelindev_get_header_setting('sticky_logo_id', 0) : 0;
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
add_action('wp_head', 'intelindev_print_header_style_overrides', 20);

function intelindev_print_header_style_overrides() {
    $sticky = intelindev_header_is_sticky();
    $declarations = '';

    foreach (intelindev_header_color_matrix() as $group) {
        foreach ($group as $key => $def) {
            $value = intelindev_sanitize_css_color((string) intelindev_get_header_setting($key, ''));
            if ($value !== '') {
                $declarations .= $def['var'] . ':' . $value . ';';
            }
            if ($sticky) {
                $value = intelindev_sanitize_css_color((string) intelindev_get_header_setting('sticky_' . $key, ''));
                if ($value !== '') {
                    $declarations .= intelindev_header_sticky_css_var($def['var']) . ':' . $value . ';';
                }
            }
        }
    }

    if ($declarations === '') return;

    echo '<style id="intelindev-header-style-overrides">:root{' . $declarations . '}</style>' . "\n";
}

/* ------------------------------------------------------------------ */
/* Sanitize                                                             */
/* ------------------------------------------------------------------ */

/**
 * Parte de lo ya guardado y sobreescribe solo las claves que este formulario
 * gestiona; una clave sin valor válido se quita (vuelve al default).
 */
function intelindev_header_settings_sanitize($input) {
    $input    = is_array($input) ? $input : [];
    $existing = get_option('intelindev_header_settings', []);
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
    foreach (intelindev_header_color_matrix() as $group) {
        foreach (array_keys($group) as $key) {
            foreach ([$key, 'sticky_' . $key] as $option_key) {
                $clean = intelindev_sanitize_css_color((string) ($input[$option_key] ?? ''));
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
        $texts = intelindev_sanitize_lang_text($input[$key] ?? []);
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

function intelindev_header_settings_page_html() {
    if (!current_user_can('manage_options')) return;

    $opts   = get_option('intelindev_header_settings', []);
    $opts   = is_array($opts) ? $opts : [];
    $sticky = !empty($opts['sticky_enabled']);
    $matrix = intelindev_header_color_matrix();
    $option = 'intelindev_header_settings';
    $sticky_if = $option . '[sticky_enabled]';
    $cta_if    = $option . '[cta_type]';

    $tabs = [
        'header' => ['🧭', __('Header', 'intelindev')],
        'menu'   => ['☰', __('Menú', 'intelindev')],
        'cta'    => ['🔘', __('CTA', 'intelindev')],
    ];

    if (isset($_GET['settings-updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Configuración del header guardada correctamente.', 'intelindev') . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Intelindev Header', 'intelindev'); ?></h1>
        <p><?php esc_html_e('Comportamiento sticky, logos, menú, colores y botón CTA del header.', 'intelindev'); ?></p>

        <form action="options.php" method="post" class="intelindev-settings-form intelindev-panel-form">
            <?php settings_fields('intelindev_header_settings_group'); ?>

            <?php intelindev_admin_tabs_nav($tabs); ?>

            <div class="tab-content intelindev-tabs-container">

                <!-- ===================== HEADER ===================== -->
                <div class="tab-pane fade show active" id="content-header" role="tabpanel">
                    <h2><?php esc_html_e('Comportamiento', 'intelindev'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Header sticky', 'intelindev'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="intelindev_sticky_enabled" name="intelindev_header_settings[sticky_enabled]" value="1"<?php checked($sticky); ?> />
                                    <?php esc_html_e('El header queda fijo arriba al hacer scroll', 'intelindev'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Activarlo habilita el logo sticky y la columna "Sticky" de los colores en Menú y CTA. Apagado, el header es estático y no carga ningún script de scroll.', 'intelindev'); ?></p>
                            </td>
                        </tr>
                        <tr data-show-if="<?php echo esc_attr($sticky_if); ?>">
                            <th scope="row"><label for="intelindev_sticky_threshold"><?php esc_html_e('Umbral de scroll', 'intelindev'); ?></label></th>
                            <td>
                                <input type="number" id="intelindev_sticky_threshold" name="intelindev_header_settings[sticky_threshold]" value="<?php echo esc_attr((string) ($opts['sticky_threshold'] ?? 80)); ?>" min="0" max="1000" step="1" class="small-text" /> px
                                <p class="description"><?php esc_html_e('Píxeles de scroll a partir de los cuales el header pasa a su estado sticky (logo y colores sticky).', 'intelindev'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h2><?php esc_html_e('Logo', 'intelindev'); ?></h2>
                    <table class="form-table" role="presentation">
                        <?php
                        intelindev_admin_media_field($option, 'logo_id', __('Logo del header', 'intelindev'), $opts, __('Sin logo elegido se usa el del Personalizador (Identidad del sitio) y, si tampoco hay, el nombre del sitio.', 'intelindev'));
                        intelindev_admin_media_field($option, 'sticky_logo_id', __('Logo con header sticky', 'intelindev'), $opts, __('Se muestra en lugar del logo principal cuando el header está en estado sticky. Vacío = se mantiene el principal.', 'intelindev'), $sticky_if);
                        ?>
                    </table>
                </div>

                <!-- ===================== MENÚ ===================== -->
                <div class="tab-pane fade" id="content-menu" role="tabpanel">
                    <h2><?php esc_html_e('Menú principal', 'intelindev'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="intelindev_menu_id"><?php esc_html_e('Menú a mostrar', 'intelindev'); ?></label></th>
                            <td>
                                <?php
                                $locations  = get_nav_menu_locations();
                                $current_id = (int) ($locations['primary'] ?? 0);
                                $menus      = wp_get_nav_menus();
                                ?>
                                <select id="intelindev_menu_id" name="intelindev_header_settings[menu_id]">
                                    <option value="0"><?php esc_html_e('— Ninguno —', 'intelindev'); ?></option>
                                    <?php foreach ($menus as $menu) : ?>
                                        <option value="<?php echo (int) $menu->term_id; ?>"<?php selected($current_id, (int) $menu->term_id); ?>><?php echo esc_html($menu->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php
                                    printf(
                                        esc_html__('Es la ubicación "Menú principal": lo mismo que asignarla en %s. Los ítems del menú se editan ahí.', 'intelindev'),
                                        '<a href="' . esc_url(admin_url('nav-menus.php')) . '">' . esc_html__('Apariencia → Menús', 'intelindev') . '</a>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h2><?php esc_html_e('Colores del header y del menú', 'intelindev'); ?></h2>
                    <?php
                    $color_columns = [
                        ['prefix' => '', 'label' => __('Normal', 'intelindev')],
                        ['prefix' => 'sticky_', 'label' => __('Sticky', 'intelindev'), 'show_if' => $sticky_if],
                    ];
                    intelindev_admin_color_table($option, array_map(fn($def) => $def['label'], $matrix['menu']), $opts, $color_columns);
                    ?>
                </div>

                <!-- ===================== CTA ===================== -->
                <div class="tab-pane fade" id="content-cta" role="tabpanel">
                    <h2><?php esc_html_e('Botón CTA', 'intelindev'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Tipo', 'intelindev'); ?></th>
                            <td>
                                <?php
                                $cta_type = (string) ($opts['cta_type'] ?? 'none');
                                $types = [
                                    'none'  => __('Sin CTA', 'intelindev'),
                                    'page'  => __('Ir a una página del sitio', 'intelindev'),
                                    'url'   => __('Ir a una URL', 'intelindev'),
                                    'modal' => __('Abrir un modal', 'intelindev'),
                                ];
                                foreach ($types as $value => $label) :
                                ?>
                                    <label class="intelindev-radio-row">
                                        <input type="radio" name="intelindev_header_settings[cta_type]" value="<?php echo esc_attr($value); ?>"<?php checked($cta_type, $value); ?> />
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>

                        <tr data-show-if="<?php echo esc_attr($cta_if); ?>=page">
                            <th scope="row"><label for="intelindev_cta_page_id"><?php esc_html_e('Página', 'intelindev'); ?></label></th>
                            <td>
                                <?php
                                wp_dropdown_pages([
                                    'name'              => 'intelindev_header_settings[cta_page_id]',
                                    'id'                => 'intelindev_cta_page_id',
                                    'selected'          => (int) ($opts['cta_page_id'] ?? 0),
                                    'show_option_none'  => __('— Seleccionar —', 'intelindev'),
                                    'option_none_value' => 0,
                                    'post_status'       => 'publish',
                                ]);
                                ?>
                                <p class="description"><?php esc_html_e('La URL y el título se toman de la página y cambian solos según el idioma (slug y título traducidos de la página).', 'intelindev'); ?></p>
                            </td>
                        </tr>

                        <tr data-show-if="<?php echo esc_attr($cta_if); ?>=url">
                            <th scope="row"><label for="intelindev_cta_url"><?php esc_html_e('URL', 'intelindev'); ?></label></th>
                            <td>
                                <input type="url" id="intelindev_cta_url" name="intelindev_header_settings[cta_url]" value="<?php echo esc_attr((string) ($opts['cta_url'] ?? '')); ?>" class="regular-text" placeholder="https://..." />
                                <p class="description"><?php esc_html_e('Debe empezar con http:// o https://.', 'intelindev'); ?></p>
                            </td>
                        </tr>

                        <tr data-show-if="<?php echo esc_attr($cta_if); ?>!=none">
                            <th scope="row"><?php esc_html_e('Estilo del botón', 'intelindev'); ?></th>
                            <td>
                                <?php $cta_style = (string) ($opts['cta_style'] ?? 'text'); ?>
                                <label style="margin-right:16px;"><input type="radio" name="intelindev_header_settings[cta_style]" value="text"<?php checked($cta_style, 'text'); ?> /> <?php esc_html_e('Píldora con texto', 'intelindev'); ?></label>
                                <label><input type="radio" name="intelindev_header_settings[cta_style]" value="icon"<?php checked($cta_style, 'icon'); ?> /> <?php esc_html_e('Cuadrado con ícono (el texto queda como etiqueta accesible)', 'intelindev'); ?></label>
                            </td>
                        </tr>
                        <?php
                        intelindev_admin_lang_text_fields($option, 'cta_text', __('Texto del botón (%s)', 'intelindev'), $opts, __('Si un idioma queda vacío se usa el del idioma por defecto. Con tipo "página", vacío = título de la página; con estilo ícono, vacío = "Contáctanos" (clave cta.icon_label).', 'intelindev'), $cta_if . '!=none');
                        intelindev_admin_lang_text_fields($option, 'cta_modal_title', __('Título del modal (%s)', 'intelindev'), $opts, '', $cta_if . '=modal');
                        ?>
                        <tr data-show-if="<?php echo esc_attr($cta_if); ?>=modal">
                            <th scope="row"><label for="intelindev_cta_modal_content"><?php esc_html_e('Contenido del modal', 'intelindev'); ?></label></th>
                            <td>
                                <textarea id="intelindev_cta_modal_content" name="intelindev_header_settings[cta_modal_content]" rows="10" class="large-text code" spellcheck="false"><?php echo esc_textarea((string) ($opts['cta_modal_content'] ?? '')); ?></textarea>
                                <p class="description"><?php esc_html_e('Acepta el script de embed de un CRM (GoHighLevel, HubSpot…), el HTML de un formulario o un shortcode. Se carga recién cuando el visitante abre el modal, así no afecta la velocidad del sitio.', 'intelindev'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div data-show-if="<?php echo esc_attr($cta_if); ?>!=none">
                        <h2><?php esc_html_e('Colores del CTA', 'intelindev'); ?></h2>
                        <?php intelindev_admin_color_table($option, array_map(fn($def) => $def['label'], $matrix['cta']), $opts, $color_columns); ?>
                    </div>
                </div>

            </div>

            <?php submit_button(__('Guardar', 'intelindev')); ?>
        </form>
    </div>
    <?php
}
