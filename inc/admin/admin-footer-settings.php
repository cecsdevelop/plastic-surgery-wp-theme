<?php
/**
 * Intelindev Footer (Apariencia → Intelindev Footer): estructura, barra de
 * copyright y estilos del footer, en tres pestañas.
 *
 * El "builder" lo pone WordPress: cada celda de la grilla (fila × columna) es
 * un área de widgets, y las áreas se editan con bloques de Gutenberg en
 * Apariencia → Widgets (párrafos, imágenes, menús, botones, HTML…). Hay un
 * área por idioma activo: si la del idioma actual está vacía se muestra la del
 * idioma por defecto, así lo neutro se carga una sola vez.
 *
 * Todo vive en la option intelindev_footer_settings (rows, barra, colores y
 * medidas). Las áreas se registran en widgets_init a partir de esa option, así
 * que al cambiar filas/columnas aparecen o desaparecen en Widgets (WP guarda
 * los widgets de un área que dejó de existir en "Widgets inactivos").
 *
 * Colores y medidas se imprimen como custom properties CSS en wp_head solo si
 * el admin las configuró; el resto lo cubre el default de styles.css (:root).
 * Frontend sin JS: grilla CSS con grid-template-columns por fila.
 *
 * @package intelindev
 */

if (!defined('ABSPATH')) exit;

const INTELINDEV_FOOTER_OPTION   = 'intelindev_footer_settings';
const INTELINDEV_FOOTER_MAX_ROWS = 6;
const INTELINDEV_FOOTER_MAX_COLS = 6;

add_action('admin_menu', function () {
    add_theme_page(
        __('Intelindev Footer', 'intelindev'),
        __('Intelindev Footer', 'intelindev'),
        'manage_options',
        'intelindev-footer-settings',
        'intelindev_footer_settings_page_html'
    );
});

add_action('admin_init', function () {
    register_setting('intelindev_footer_settings_group', INTELINDEV_FOOTER_OPTION, 'intelindev_footer_settings_sanitize');
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'appearance_page_intelindev-footer-settings') {
        return;
    }
    intelindev_admin_enqueue_field_assets();

    $path = '/assets/js/intelindev-footer-settings.js';
    if (file_exists(get_template_directory() . $path)) {
        wp_enqueue_script('intelindev-footer-settings', get_template_directory_uri() . $path, ['jquery', 'intelindev-admin-fields'], filemtime(get_template_directory() . $path), true);
    }
}, 20);

/* ------------------------------------------------------------------ */
/* Definición                                                           */
/* ------------------------------------------------------------------ */

function intelindev_footer_defaults(): array {
    return [
        'rows'        => [['columns' => 4, 'layout' => 'equal']],
        'bar_enabled' => 1,
        'bar_menu'    => 1,
        'bar_align'   => 'space-between',
    ];
}

function intelindev_footer_layouts(): array {
    return [
        'equal'      => __('Columnas iguales', 'intelindev'),
        'first-wide' => __('Primera columna más ancha', 'intelindev'),
        'last-wide'  => __('Última columna más ancha', 'intelindev'),
    ];
}

function intelindev_footer_bar_aligns(): array {
    return [
        'space-between' => __('Texto a la izquierda, menú a la derecha', 'intelindev'),
        'left'          => __('Todo a la izquierda', 'intelindev'),
        'center'        => __('Todo centrado', 'intelindev'),
    ];
}

/** Colores: clave de option => [label, custom property]. */
function intelindev_footer_color_map(): array {
    return [
        'bg_color'         => [__('Fondo del footer', 'intelindev'),        '--intelindev-footer-bg'],
        'text_color'       => [__('Texto', 'intelindev'),                   '--intelindev-footer-text-color'],
        'heading_color'    => [__('Títulos de los widgets', 'intelindev'),  '--intelindev-footer-heading-color'],
        'link_color'       => [__('Links', 'intelindev'),                   '--intelindev-footer-link-color'],
        'link_hover_color' => [__('Links (hover)', 'intelindev'),           '--intelindev-footer-link-hover-color'],
        'bar_bg_color'     => [__('Fondo de la barra', 'intelindev'),       '--intelindev-footer-bar-bg'],
        'bar_text_color'   => [__('Texto de la barra', 'intelindev'),       '--intelindev-footer-bar-text-color'],
        'bar_link_color'   => [__('Links de la barra', 'intelindev'),       '--intelindev-footer-bar-link-color'],
    ];
}

/** Medidas en px: clave => [label, custom property, min, max, descripción]. */
function intelindev_footer_size_map(): array {
    return [
        'padding_top'    => [__('Padding superior', 'intelindev'),        '--intelindev-footer-padding-top',    0, 400,  ''],
        'padding_bottom' => [__('Padding inferior', 'intelindev'),        '--intelindev-footer-padding-bottom', 0, 400,  ''],
        'padding_x'      => [__('Padding lateral', 'intelindev'),         '--intelindev-footer-padding-x',      0, 200,  ''],
        'column_gap'     => [__('Separación entre columnas', 'intelindev'), '--intelindev-footer-gap',          0, 120,  ''],
        'max_width'      => [__('Ancho máximo del contenido', 'intelindev'), '--intelindev-footer-max-width',   0, 2400, __('0 = ancho completo.', 'intelindev')],
        'bar_padding'    => [__('Padding de la barra', 'intelindev'),     '--intelindev-footer-bar-padding',    0, 100,  ''],
    ];
}

/* ------------------------------------------------------------------ */
/* Lectura                                                              */
/* ------------------------------------------------------------------ */

/** Option completa con defaults para lo que nunca se guardó (get_option ya cachea). */
function intelindev_get_footer_settings(): array {
    $saved = get_option(INTELINDEV_FOOTER_OPTION, null);
    return is_array($saved) ? array_merge(intelindev_footer_defaults(), $saved) : intelindev_footer_defaults();
}

function intelindev_get_footer_setting(string $key, $default = null) {
    $opts = intelindev_get_footer_settings();
    return array_key_exists($key, $opts) ? $opts[$key] : $default;
}

function intelindev_footer_sidebar_id(int $row, int $col, string $lang): string {
    return sprintf('footer-r%d-c%d-%s', $row, $col, $lang);
}

/**
 * grid-template-columns de una fila según cantidad y distribución. Se imprime
 * inline por fila (repeat() con calc() no es fiable en todos los navegadores).
 */
function intelindev_footer_grid_template(int $columns, string $layout): string {
    $columns = max(1, $columns);
    if ($columns === 1 || $layout === 'equal') {
        return 'repeat(' . $columns . ', minmax(0, 1fr))';
    }
    $rest = array_fill(0, $columns - 1, 'minmax(0, 1fr)');
    return $layout === 'first-wide'
        ? implode(' ', array_merge(['minmax(0, 2fr)'], $rest))
        : implode(' ', array_merge($rest, ['minmax(0, 2fr)']));
}

/**
 * Filas a imprimir para un idioma: por celda, el área del idioma si tiene
 * widgets, si no la del idioma por defecto, si no null. Las filas sin ningún
 * contenido no se devuelven.
 */
function intelindev_get_footer_grid($lang = null): array {
    $lang    = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default = idml_get_default_language();
    $rows    = (array) intelindev_get_footer_setting('rows', []);
    $grid    = [];

    foreach (array_values($rows) as $index => $row) {
        $columns = (int) ($row['columns'] ?? 1);
        $layout  = (string) ($row['layout'] ?? 'equal');
        $cells   = [];
        $filled  = false;

        for ($col = 1; $col <= $columns; $col++) {
            $sidebar = null;
            foreach (array_unique([$lang, $default]) as $candidate_lang) {
                $candidate = intelindev_footer_sidebar_id($index + 1, $col, $candidate_lang);
                if (is_active_sidebar($candidate)) {
                    $sidebar = $candidate;
                    break;
                }
            }
            if ($sidebar !== null) {
                $filled = true;
            }
            $cells[] = $sidebar;
        }

        if ($filled) {
            $grid[] = [
                'cells'    => $cells,
                'template' => intelindev_footer_grid_template($columns, $layout),
            ];
        }
    }

    return $grid;
}

/**
 * Barra de copyright para un idioma, o null si está desactivada. El texto
 * cae al default del theme (clave footer.copyright, editable en Apariencia →
 * Traducciones) y admite {year} y {site}.
 */
function intelindev_get_footer_bar($lang = null): ?array {
    if (empty(intelindev_get_footer_setting('bar_enabled', 1))) {
        return null;
    }

    $text = intelindev_resolve_lang_text(intelindev_get_footer_setting('bar_text', []), $lang);
    if ($text === '') {
        $text = idml_t('footer.copyright', $lang);
    }
    $text = strtr($text, ['{year}' => date_i18n('Y'), '{site}' => get_bloginfo('name')]);

    $align = (string) intelindev_get_footer_setting('bar_align', 'space-between');
    if (!array_key_exists($align, intelindev_footer_bar_aligns())) {
        $align = 'space-between';
    }

    return [
        'text'  => $text,
        'menu'  => !empty(intelindev_get_footer_setting('bar_menu', 1)) && has_nav_menu('footer'),
        'align' => $align,
    ];
}

/**
 * Personalizador abierto directo en un área de widgets (con vista previa del
 * sitio) y botón "volver" al panel del footer.
 */
function intelindev_footer_customize_url(string $sidebar_id): string {
    return add_query_arg([
        'autofocus[section]' => 'sidebar-widgets-' . $sidebar_id,
        'return'             => rawurlencode(admin_url('themes.php?page=intelindev-footer-settings#content-structure')),
    ], admin_url('customize.php'));
}

/* ------------------------------------------------------------------ */
/* Áreas de widgets                                                     */
/* ------------------------------------------------------------------ */

add_action('widgets_init', 'intelindev_footer_register_sidebars');

function intelindev_footer_register_sidebars(): void {
    $rows    = (array) intelindev_get_footer_setting('rows', []);
    $langs   = idml_get_languages();
    $default = idml_get_default_language();

    foreach (array_values($rows) as $index => $row) {
        $columns = min(INTELINDEV_FOOTER_MAX_COLS, max(1, (int) ($row['columns'] ?? 1)));
        for ($col = 1; $col <= $columns; $col++) {
            foreach ($langs as $lang) {
                register_sidebar([
                    'id'            => intelindev_footer_sidebar_id($index + 1, $col, $lang),
                    'name'          => sprintf(__('Footer · Fila %1$d · Columna %2$d (%3$s)', 'intelindev'), $index + 1, $col, strtoupper($lang)),
                    'description'   => $lang === $default
                        ? __('Idioma por defecto: se muestra también en los idiomas cuya área esté vacía.', 'intelindev')
                        : sprintf(__('Contenido en %s. Vacío = se muestra el del idioma por defecto.', 'intelindev'), strtoupper($lang)),
                    'before_widget' => '<div id="%1$s" class="widget footer-widget %2$s">',
                    'after_widget'  => '</div>',
                    'before_title'  => '<h3 class="widget-title footer-widget__title">',
                    'after_title'   => '</h3>',
                ]);
            }
        }
    }
}

/* ------------------------------------------------------------------ */
/* CSS overrides                                                        */
/* ------------------------------------------------------------------ */

add_action('wp_head', 'intelindev_print_footer_style_overrides', 20);

function intelindev_print_footer_style_overrides(): void {
    $declarations = '';

    foreach (intelindev_footer_color_map() as $key => [$label, $var]) {
        $value = intelindev_sanitize_css_color((string) intelindev_get_footer_setting($key, ''));
        if ($value !== '') {
            $declarations .= $var . ':' . $value . ';';
        }
    }

    foreach (intelindev_footer_size_map() as $key => [$label, $var, $min, $max]) {
        $value = intelindev_get_footer_setting($key, null);
        if ($value === null || $value === '') {
            continue;
        }
        $value = max($min, min($max, (int) $value));
        $declarations .= $var . ':' . ($key === 'max_width' && $value === 0 ? 'none' : $value . 'px') . ';';
    }

    if ($declarations === '') return;

    echo '<style id="intelindev-footer-style-overrides">:root{' . $declarations . '}</style>' . "\n";
}

/* ------------------------------------------------------------------ */
/* Sanitize                                                             */
/* ------------------------------------------------------------------ */

function intelindev_footer_settings_sanitize($input) {
    $input    = is_array($input) ? $input : [];
    $existing = get_option(INTELINDEV_FOOTER_OPTION, []);
    $out      = is_array($existing) ? $existing : [];

    // --- Estructura ---
    $rows = [];
    if (isset($input['rows']) && is_array($input['rows'])) {
        foreach ($input['rows'] as $row) {
            if (!is_array($row)) continue;
            $layout = sanitize_key((string) ($row['layout'] ?? 'equal'));
            $rows[] = [
                'columns' => min(INTELINDEV_FOOTER_MAX_COLS, max(1, (int) ($row['columns'] ?? 1))),
                'layout'  => array_key_exists($layout, intelindev_footer_layouts()) ? $layout : 'equal',
            ];
            if (count($rows) >= INTELINDEV_FOOTER_MAX_ROWS) break;
        }
    }
    $out['rows'] = $rows;

    // --- Barra de copyright ---
    $out['bar_enabled'] = !empty($input['bar_enabled']) ? 1 : 0;
    $out['bar_menu']    = !empty($input['bar_menu']) ? 1 : 0;
    $align = sanitize_key((string) ($input['bar_align'] ?? 'space-between'));
    $out['bar_align'] = array_key_exists($align, intelindev_footer_bar_aligns()) ? $align : 'space-between';

    $texts = intelindev_sanitize_lang_text($input['bar_text'] ?? []);
    if ($texts) {
        $out['bar_text'] = $texts;
    } else {
        unset($out['bar_text']);
    }

    // --- Estilos ---
    foreach (array_keys(intelindev_footer_color_map()) as $key) {
        $clean = intelindev_sanitize_css_color((string) ($input[$key] ?? ''));
        if ($clean !== '') {
            $out[$key] = $clean;
        } else {
            unset($out[$key]);
        }
    }

    foreach (intelindev_footer_size_map() as $key => [$label, $var, $min, $max]) {
        $raw = $input[$key] ?? '';
        if ($raw === '' || $raw === null || !is_numeric($raw)) {
            unset($out[$key]);
        } else {
            $out[$key] = max($min, min($max, (int) $raw));
        }
    }

    return $out;
}

/* ------------------------------------------------------------------ */
/* Page HTML                                                            */
/* ------------------------------------------------------------------ */

function intelindev_footer_settings_page_html(): void {
    if (!current_user_can('manage_options')) return;

    $option  = INTELINDEV_FOOTER_OPTION;
    $opts    = intelindev_get_footer_settings();
    $rows    = array_values((array) ($opts['rows'] ?? []));
    $bar_if  = $option . '[bar_enabled]';
    $layouts = intelindev_footer_layouts();

    $tabs = [
        'structure' => ['🧱', __('Estructura', 'intelindev')],
        'copyright' => ['©', __('Copyright', 'intelindev')],
        'styles'    => ['🎨', __('Estilos', 'intelindev')],
    ];

    if (isset($_GET['settings-updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Configuración del footer guardada. Las áreas de cada columna ya están disponibles en Apariencia → Widgets.', 'intelindev') . '</p></div>';
    }

    $row_markup = function (int $index, array $row) use ($option, $layouts): void {
        $columns = (int) ($row['columns'] ?? 4);
        $layout  = (string) ($row['layout'] ?? 'equal');
        ?>
        <tr class="intelindev-footer-row">
            <td class="intelindev-footer-row__index"><?php echo $index === -1 ? '' : (int) ($index + 1); ?></td>
            <td>
                <select name="<?php echo esc_attr($option); ?>[rows][<?php echo $index === -1 ? '__i__' : (int) $index; ?>][columns]">
                    <?php for ($n = 1; $n <= INTELINDEV_FOOTER_MAX_COLS; $n++) : ?>
                        <option value="<?php echo $n; ?>"<?php selected($columns, $n); ?>><?php echo $n; ?></option>
                    <?php endfor; ?>
                </select>
            </td>
            <td>
                <select name="<?php echo esc_attr($option); ?>[rows][<?php echo $index === -1 ? '__i__' : (int) $index; ?>][layout]">
                    <?php foreach ($layouts as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>"<?php selected($layout, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><button type="button" class="button-link-delete intelindev-footer-row-remove"><?php esc_html_e('Quitar', 'intelindev'); ?></button></td>
        </tr>
        <?php
    };
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Intelindev Footer', 'intelindev'); ?></h1>
        <p><?php esc_html_e('Estructura de filas y columnas, barra de copyright y estilos del footer.', 'intelindev'); ?></p>

        <form action="options.php" method="post" class="intelindev-settings-form intelindev-panel-form">
            <?php settings_fields('intelindev_footer_settings_group'); ?>
            <?php intelindev_admin_tabs_nav($tabs); ?>

            <div class="tab-content intelindev-tabs-container">

                <!-- ===================== ESTRUCTURA ===================== -->
                <div class="tab-pane fade show active" id="content-structure" role="tabpanel">
                    <h2><?php esc_html_e('Filas y columnas', 'intelindev'); ?></h2>
                    <p class="description">
                        <?php
                        printf(
                            esc_html__('Cada columna es un área de widgets: el contenido (textos, imágenes, menús, botones, HTML…) se carga con bloques en %s. Hay un área por idioma; si la de un idioma está vacía se muestra la del idioma por defecto. Guardá los cambios de estructura para que las áreas nuevas aparezcan ahí.', 'intelindev'),
                            '<a href="' . esc_url(admin_url('widgets.php')) . '">' . esc_html__('Apariencia → Widgets', 'intelindev') . '</a>'
                        );
                        ?>
                    </p>
                    <table class="widefat intelindev-footer-rows" id="intelindev-footer-rows" data-max-rows="<?php echo (int) INTELINDEV_FOOTER_MAX_ROWS; ?>">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Fila', 'intelindev'); ?></th>
                                <th><?php esc_html_e('Columnas', 'intelindev'); ?></th>
                                <th><?php esc_html_e('Distribución', 'intelindev'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $row) { $row_markup($index, $row); } ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button" id="intelindev-footer-row-add"><?php esc_html_e('+ Agregar fila', 'intelindev'); ?></button>
                        <span class="description"><?php printf(esc_html__('Hasta %d filas. Sin filas, el footer muestra solo la barra de copyright.', 'intelindev'), (int) INTELINDEV_FOOTER_MAX_ROWS); ?></span>
                    </p>
                    <template id="intelindev-footer-row-template"><?php $row_markup(-1, ['columns' => 4, 'layout' => 'equal']); ?></template>

                    <?php if ($rows) : ?>
                    <h2><?php esc_html_e('Grilla del footer', 'intelindev'); ?></h2>
                    <p class="description"><?php esc_html_e('Así queda la estructura guardada. Elegí el idioma de una celda para cargar sus bloques en el Personalizador, viendo el footer real al lado. Si cambiaste filas o columnas arriba, guardá primero para actualizar la grilla.', 'intelindev'); ?></p>
                    <div class="intelindev-footer-grid">
                        <?php
                        $langs   = idml_get_languages();
                        $default = idml_get_default_language();
                        $widgets = wp_get_sidebars_widgets();
                        foreach ($rows as $index => $row) :
                            $columns = min(INTELINDEV_FOOTER_MAX_COLS, max(1, (int) ($row['columns'] ?? 1)));
                        ?>
                        <div class="intelindev-footer-grid__row" style="grid-template-columns: <?php echo esc_attr(intelindev_footer_grid_template($columns, (string) ($row['layout'] ?? 'equal'))); ?>">
                            <?php for ($col = 1; $col <= $columns; $col++) : ?>
                            <div class="intelindev-footer-grid__cell">
                                <span class="intelindev-footer-grid__name"><?php printf(esc_html__('Fila %1$d · Col %2$d', 'intelindev'), $index + 1, $col); ?></span>
                                <span class="intelindev-footer-grid__langs">
                                    <?php foreach ($langs as $lang) :
                                        $sidebar_id = intelindev_footer_sidebar_id($index + 1, $col, $lang);
                                        $count      = isset($widgets[$sidebar_id]) && is_array($widgets[$sidebar_id]) ? count($widgets[$sidebar_id]) : 0;
                                        $title      = $count > 0
                                            ? sprintf(_n('%d bloque', '%d bloques', $count, 'intelindev'), $count)
                                            : ($lang === $default ? __('Vacía', 'intelindev') : __('Vacía: se muestra la del idioma por defecto', 'intelindev'));
                                    ?>
                                    <a class="button button-small<?php echo $count > 0 ? ' intelindev-footer-grid__lang--filled' : ''; ?>" href="<?php echo esc_url(intelindev_footer_customize_url($sidebar_id)); ?>" title="<?php echo esc_attr($title); ?>"><?php echo esc_html(strtoupper($lang)); ?><?php if ($count > 0) : ?> <span class="intelindev-footer-grid__count"><?php echo (int) $count; ?></span><?php endif; ?></a>
                                    <?php endforeach; ?>
                                </span>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ===================== COPYRIGHT ===================== -->
                <div class="tab-pane fade" id="content-copyright" role="tabpanel">
                    <h2><?php esc_html_e('Barra de copyright', 'intelindev'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Mostrar barra', 'intelindev'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($option); ?>[bar_enabled]" value="1"<?php checked(!empty($opts['bar_enabled'])); ?> />
                                    <?php esc_html_e('Barra al pie con el copyright y, opcionalmente, el menú del footer', 'intelindev'); ?>
                                </label>
                            </td>
                        </tr>
                        <?php
                        $placeholders = [];
                        foreach (idml_get_languages() as $lang) {
                            $placeholders[$lang] = idml_t('footer.copyright', $lang);
                        }
                        intelindev_admin_lang_text_fields($option, 'bar_text', __('Texto (%s)', 'intelindev'), $opts, __('Admite {year} (año actual) y {site} (nombre del sitio). Vacío = el default del theme, editable en Apariencia → Traducciones (clave footer.copyright).', 'intelindev'), $bar_if, $placeholders);
                        ?>
                        <tr data-show-if="<?php echo esc_attr($bar_if); ?>">
                            <th scope="row"><?php esc_html_e('Menú del footer', 'intelindev'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($option); ?>[bar_menu]" value="1"<?php checked(!empty($opts['bar_menu'])); ?> />
                                    <?php esc_html_e('Mostrar el menú asignado a la ubicación "Menú del footer"', 'intelindev'); ?>
                                </label>
                                <p class="description">
                                    <?php
                                    printf(
                                        esc_html__('Ideal para Privacidad / Términos. Se asigna en %s; sus ítems se traducen como los del menú principal.', 'intelindev'),
                                        '<a href="' . esc_url(admin_url('nav-menus.php?action=locations')) . '">' . esc_html__('Apariencia → Menús → Ubicaciones', 'intelindev') . '</a>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <tr data-show-if="<?php echo esc_attr($bar_if); ?>">
                            <th scope="row"><label for="<?php echo esc_attr($option); ?>_bar_align"><?php esc_html_e('Alineación', 'intelindev'); ?></label></th>
                            <td>
                                <select id="<?php echo esc_attr($option); ?>_bar_align" name="<?php echo esc_attr($option); ?>[bar_align]">
                                    <?php foreach (intelindev_footer_bar_aligns() as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>"<?php selected((string) ($opts['bar_align'] ?? ''), $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ===================== ESTILOS ===================== -->
                <div class="tab-pane fade" id="content-styles" role="tabpanel">
                    <h2><?php esc_html_e('Colores', 'intelindev'); ?></h2>
                    <?php intelindev_admin_color_table($option, array_map(fn($def) => $def[0], intelindev_footer_color_map()), $opts, [['prefix' => '', 'label' => __('Color', 'intelindev')]]); ?>

                    <h2><?php esc_html_e('Medidas', 'intelindev'); ?></h2>
                    <table class="form-table" role="presentation">
                        <?php foreach (intelindev_footer_size_map() as $key => [$label, $var, $min, $max, $description]) : ?>
                            <?php intelindev_admin_number_field($option, $key, $label, $opts, $min, $max, 'px', $description); ?>
                        <?php endforeach; ?>
                    </table>
                </div>

            </div>

            <?php submit_button(__('Guardar', 'intelindev')); ?>
        </form>
    </div>
    <?php
}
