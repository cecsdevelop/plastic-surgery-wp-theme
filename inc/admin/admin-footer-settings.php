<?php
/**
 * pswpt Footer (Apariencia → pswpt Footer): estructura, barra de
 * copyright y estilos del footer, en tres pestañas.
 *
 * El "builder" lo pone WordPress: cada celda de la grilla (fila × columna) es
 * un área de widgets, y las áreas se editan con bloques de Gutenberg en
 * Apariencia → Widgets (párrafos, imágenes, menús, botones, HTML…). Hay un
 * área por idioma activo: si la del idioma actual está vacía se muestra la del
 * idioma por defecto, así lo neutro se carga una sola vez.
 *
 * Todo vive en la option pswpt_footer_settings (rows, barra, colores y
 * medidas). Las áreas se registran en widgets_init a partir de esa option, así
 * que al cambiar filas/columnas aparecen o desaparecen en Widgets (WP guarda
 * los widgets de un área que dejó de existir en "Widgets inactivos").
 *
 * Colores y medidas se imprimen como custom properties CSS en wp_head solo si
 * el admin las configuró; el resto lo cubre el default de styles.css (:root).
 * Frontend sin JS: grilla CSS con grid-template-columns por fila.
 *
 * @package pswpt
 */

if (!defined('ABSPATH')) exit;

const pswpt_FOOTER_OPTION   = 'pswpt_footer_settings';
const pswpt_FOOTER_MAX_ROWS = 6;
const pswpt_FOOTER_MAX_COLS = 6;

add_action('admin_menu', function () {
    add_theme_page(
        __('pswpt Footer', 'pswpt'),
        __('pswpt Footer', 'pswpt'),
        'manage_options',
        'pswpt-footer-settings',
        'pswpt_footer_settings_page_html'
    );
});

add_action('admin_init', function () {
    register_setting('pswpt_footer_settings_group', pswpt_FOOTER_OPTION, 'pswpt_footer_settings_sanitize');
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'appearance_page_pswpt-footer-settings') {
        return;
    }
    pswpt_admin_enqueue_field_assets();

    $path = '/assets/js/pswpt-footer-settings.js';
    if (file_exists(get_template_directory() . $path)) {
        wp_enqueue_script('pswpt-footer-settings', get_template_directory_uri() . $path, ['jquery', 'pswpt-admin-fields'], filemtime(get_template_directory() . $path), true);
    }
}, 20);

/* ------------------------------------------------------------------ */
/* Definición                                                           */
/* ------------------------------------------------------------------ */

function pswpt_footer_defaults(): array {
    return [
        'rows'        => [['columns' => 4, 'layout' => 'equal']],
        'bar_enabled' => 1,
        'bar_menu'    => 1,
        'bar_align'   => 'space-between',
    ];
}

function pswpt_footer_layouts(): array {
    return [
        'equal'      => __('Equal columns', 'pswpt'),
        'first-wide' => __('Wider first column', 'pswpt'),
        'last-wide'  => __('Wider last column', 'pswpt'),
    ];
}

function pswpt_footer_bar_aligns(): array {
    return [
        'space-between' => __('Text left, menu right', 'pswpt'),
        'left'          => __('All left-aligned', 'pswpt'),
        'center'        => __('All centered', 'pswpt'),
    ];
}

/** Colores: clave de option => [label, custom property]. */
function pswpt_footer_color_map(): array {
    return [
        'bg_color'         => [__('Footer background', 'pswpt'),        '--psw-footer-bg'],
        'text_color'       => [__('Text', 'pswpt'),                   '--psw-footer-text-color'],
        'heading_color'    => [__('Widget titles', 'pswpt'),  '--psw-footer-heading-color'],
        'link_color'       => [__('Links', 'pswpt'),                   '--psw-footer-link-color'],
        'link_hover_color' => [__('Links (hover)', 'pswpt'),           '--psw-footer-link-hover-color'],
        'bar_bg_color'     => [__('Bar background', 'pswpt'),       '--psw-footer-bar-bg'],
        'bar_text_color'   => [__('Bar text', 'pswpt'),       '--psw-footer-bar-text-color'],
        'bar_link_color'   => [__('Bar links', 'pswpt'),       '--psw-footer-bar-link-color'],
    ];
}

/** Medidas en px: clave => [label, custom property, min, max, descripción]. */
function pswpt_footer_size_map(): array {
    return [
        'padding_top'    => [__('Top padding', 'pswpt'),        '--psw-footer-padding-top',    0, 400,  ''],
        'padding_bottom' => [__('Bottom padding', 'pswpt'),        '--psw-footer-padding-bottom', 0, 400,  ''],
        'padding_x'      => [__('Side padding', 'pswpt'),         '--psw-footer-padding-x',      0, 200,  ''],
        'column_gap'     => [__('Column gap', 'pswpt'), '--psw-footer-gap',          0, 120,  ''],
        'max_width'      => [__('Max content width', 'pswpt'), '--psw-footer-max-width',   0, 2400, __('0 = full width.', 'pswpt')],
        'bar_padding'    => [__('Bar padding', 'pswpt'),     '--psw-footer-bar-padding',    0, 100,  ''],
    ];
}

/* ------------------------------------------------------------------ */
/* Lectura                                                              */
/* ------------------------------------------------------------------ */

/** Option completa con defaults para lo que nunca se guardó (get_option ya cachea). */
function pswpt_get_footer_settings(): array {
    $saved = get_option(pswpt_FOOTER_OPTION, null);
    return is_array($saved) ? array_merge(pswpt_footer_defaults(), $saved) : pswpt_footer_defaults();
}

function pswpt_get_footer_setting(string $key, $default = null) {
    $opts = pswpt_get_footer_settings();
    return array_key_exists($key, $opts) ? $opts[$key] : $default;
}

function pswpt_footer_sidebar_id(int $row, int $col, string $lang): string {
    return sprintf('footer-r%d-c%d-%s', $row, $col, $lang);
}

/**
 * grid-template-columns de una fila según cantidad y distribución. Se imprime
 * inline por fila (repeat() con calc() no es fiable en todos los navegadores).
 */
function pswpt_footer_grid_template(int $columns, string $layout): string {
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
function pswpt_get_footer_grid($lang = null): array {
    $lang    = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default = idml_get_default_language();
    $rows    = (array) pswpt_get_footer_setting('rows', []);
    $grid    = [];

    foreach (array_values($rows) as $index => $row) {
        $columns = (int) ($row['columns'] ?? 1);
        $layout  = (string) ($row['layout'] ?? 'equal');
        $cells   = [];
        $filled  = false;

        for ($col = 1; $col <= $columns; $col++) {
            $sidebar = null;
            foreach (array_unique([$lang, $default]) as $candidate_lang) {
                $candidate = pswpt_footer_sidebar_id($index + 1, $col, $candidate_lang);
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
                'template' => pswpt_footer_grid_template($columns, $layout),
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
function pswpt_get_footer_bar($lang = null): ?array {
    if (empty(pswpt_get_footer_setting('bar_enabled', 1))) {
        return null;
    }

    $text = pswpt_resolve_lang_text(pswpt_get_footer_setting('bar_text', []), $lang);
    if ($text === '') {
        $text = idml_t('footer.copyright', $lang);
    }
    $text = strtr($text, ['{year}' => date_i18n('Y'), '{site}' => get_bloginfo('name')]);

    $align = (string) pswpt_get_footer_setting('bar_align', 'space-between');
    if (!array_key_exists($align, pswpt_footer_bar_aligns())) {
        $align = 'space-between';
    }

    return [
        'text'  => $text,
        'menu'  => !empty(pswpt_get_footer_setting('bar_menu', 1)) && has_nav_menu('footer'),
        'align' => $align,
    ];
}

/**
 * Personalizador abierto directo en un área de widgets (con vista previa del
 * sitio) y botón "volver" al panel del footer.
 */
function pswpt_footer_customize_url(string $sidebar_id): string {
    return add_query_arg([
        'autofocus[section]' => 'sidebar-widgets-' . $sidebar_id,
        'return'             => rawurlencode(admin_url('themes.php?page=pswpt-footer-settings#content-structure')),
    ], admin_url('customize.php'));
}

/* ------------------------------------------------------------------ */
/* Áreas de widgets                                                     */
/* ------------------------------------------------------------------ */

add_action('widgets_init', 'pswpt_footer_register_sidebars');

function pswpt_footer_register_sidebars(): void {
    $rows    = (array) pswpt_get_footer_setting('rows', []);
    $langs   = idml_get_languages();
    $default = idml_get_default_language();

    foreach (array_values($rows) as $index => $row) {
        $columns = min(pswpt_FOOTER_MAX_COLS, max(1, (int) ($row['columns'] ?? 1)));
        for ($col = 1; $col <= $columns; $col++) {
            foreach ($langs as $lang) {
                register_sidebar([
                    'id'            => pswpt_footer_sidebar_id($index + 1, $col, $lang),
                    'name'          => sprintf(__('Footer · Row %1$d · Column %2$d (%3$s)', 'pswpt'), $index + 1, $col, strtoupper($lang)),
                    'description'   => $lang === $default
                        ? __('Default language: also shown for languages whose area is empty.', 'pswpt')
                        : sprintf(__('Content in %s. Empty = the default language\'s content is shown.', 'pswpt'), strtoupper($lang)),
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

add_action('wp_head', 'pswpt_print_footer_style_overrides', 20);

function pswpt_print_footer_style_overrides(): void {
    $declarations = '';

    foreach (pswpt_footer_color_map() as $key => [$label, $var]) {
        $value = pswpt_sanitize_css_color((string) pswpt_get_footer_setting($key, ''));
        if ($value !== '') {
            $declarations .= $var . ':' . $value . ';';
        }
    }

    foreach (pswpt_footer_size_map() as $key => [$label, $var, $min, $max]) {
        $value = pswpt_get_footer_setting($key, null);
        if ($value === null || $value === '') {
            continue;
        }
        $value = max($min, min($max, (int) $value));
        $declarations .= $var . ':' . ($key === 'max_width' && $value === 0 ? 'none' : $value . 'px') . ';';
    }

    if ($declarations === '') return;

    echo '<style id="pswpt-footer-style-overrides">:root{' . $declarations . '}</style>' . "\n";
}

/* ------------------------------------------------------------------ */
/* Sanitize                                                             */
/* ------------------------------------------------------------------ */

function pswpt_footer_settings_sanitize($input) {
    $input    = is_array($input) ? $input : [];
    $existing = get_option(pswpt_FOOTER_OPTION, []);
    $out      = is_array($existing) ? $existing : [];

    // --- Estructura ---
    $rows = [];
    if (isset($input['rows']) && is_array($input['rows'])) {
        foreach ($input['rows'] as $row) {
            if (!is_array($row)) continue;
            $layout = sanitize_key((string) ($row['layout'] ?? 'equal'));
            $rows[] = [
                'columns' => min(pswpt_FOOTER_MAX_COLS, max(1, (int) ($row['columns'] ?? 1))),
                'layout'  => array_key_exists($layout, pswpt_footer_layouts()) ? $layout : 'equal',
            ];
            if (count($rows) >= pswpt_FOOTER_MAX_ROWS) break;
        }
    }
    $out['rows'] = $rows;

    // --- Barra de copyright ---
    $out['bar_enabled'] = !empty($input['bar_enabled']) ? 1 : 0;
    $out['bar_menu']    = !empty($input['bar_menu']) ? 1 : 0;
    $align = sanitize_key((string) ($input['bar_align'] ?? 'space-between'));
    $out['bar_align'] = array_key_exists($align, pswpt_footer_bar_aligns()) ? $align : 'space-between';

    $texts = pswpt_sanitize_lang_text($input['bar_text'] ?? []);
    if ($texts) {
        $out['bar_text'] = $texts;
    } else {
        unset($out['bar_text']);
    }

    // --- Estilos ---
    foreach (array_keys(pswpt_footer_color_map()) as $key) {
        $clean = pswpt_sanitize_css_color((string) ($input[$key] ?? ''));
        if ($clean !== '') {
            $out[$key] = $clean;
        } else {
            unset($out[$key]);
        }
    }

    foreach (pswpt_footer_size_map() as $key => [$label, $var, $min, $max]) {
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

function pswpt_footer_settings_page_html(): void {
    if (!current_user_can('manage_options')) return;

    $option  = pswpt_FOOTER_OPTION;
    $opts    = pswpt_get_footer_settings();
    $rows    = array_values((array) ($opts['rows'] ?? []));
    $bar_if  = $option . '[bar_enabled]';
    $layouts = pswpt_footer_layouts();

    $tabs = [
        'structure' => ['🧱', __('Structure', 'pswpt')],
        'copyright' => ['©', __('Copyright', 'pswpt')],
        'styles'    => ['🎨', __('Styles', 'pswpt')],
    ];

    if (isset($_GET['settings-updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Footer settings saved. Each column\'s areas are now available in Appearance → Widgets.', 'pswpt') . '</p></div>';
    }

    $row_markup = function (int $index, array $row) use ($option, $layouts): void {
        $columns = (int) ($row['columns'] ?? 4);
        $layout  = (string) ($row['layout'] ?? 'equal');
        ?>
        <tr class="pswpt-footer-row">
            <td class="pswpt-footer-row__index"><?php echo $index === -1 ? '' : (int) ($index + 1); ?></td>
            <td>
                <select name="<?php echo esc_attr($option); ?>[rows][<?php echo $index === -1 ? '__i__' : (int) $index; ?>][columns]">
                    <?php for ($n = 1; $n <= pswpt_FOOTER_MAX_COLS; $n++) : ?>
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
            <td><button type="button" class="button-link-delete pswpt-footer-row-remove"><?php esc_html_e('Remove', 'pswpt'); ?></button></td>
        </tr>
        <?php
    };
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('pswpt Footer', 'pswpt'); ?></h1>
        <p><?php esc_html_e('Footer row and column structure, copyright bar and styles.', 'pswpt'); ?></p>

        <form action="options.php" method="post" class="pswpt-settings-form pswpt-panel-form">
            <?php settings_fields('pswpt_footer_settings_group'); ?>
            <?php pswpt_admin_tabs_nav($tabs); ?>

            <div class="tab-content pswpt-tabs-container">

                <!-- ===================== ESTRUCTURA ===================== -->
                <div class="tab-pane fade show active" id="content-structure" role="tabpanel">
                    <h2><?php esc_html_e('Rows and columns', 'pswpt'); ?></h2>
                    <p class="description">
                        <?php
                        printf(
                            esc_html__('Each column is a widget area: content (text, images, menus, buttons, HTML…) is added with blocks in %s. There is one area per language; if a language\'s area is empty, the default language\'s area is shown. Save structure changes so the new areas show up there.', 'pswpt'),
                            '<a href="' . esc_url(admin_url('widgets.php')) . '">' . esc_html__('Appearance → Widgets', 'pswpt') . '</a>'
                        );
                        ?>
                    </p>
                    <table class="widefat pswpt-footer-rows" id="pswpt-footer-rows" data-max-rows="<?php echo (int) pswpt_FOOTER_MAX_ROWS; ?>">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Row', 'pswpt'); ?></th>
                                <th><?php esc_html_e('Columns', 'pswpt'); ?></th>
                                <th><?php esc_html_e('Layout', 'pswpt'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $row) { $row_markup($index, $row); } ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button" id="pswpt-footer-row-add"><?php esc_html_e('+ Add row', 'pswpt'); ?></button>
                        <span class="description"><?php printf(esc_html__('Up to %d rows. With no rows, the footer only shows the copyright bar.', 'pswpt'), (int) pswpt_FOOTER_MAX_ROWS); ?></span>
                    </p>
                    <template id="pswpt-footer-row-template"><?php $row_markup(-1, ['columns' => 4, 'layout' => 'equal']); ?></template>

                    <?php if ($rows) : ?>
                    <h2><?php esc_html_e('Footer grid', 'pswpt'); ?></h2>
                    <p class="description"><?php esc_html_e('This is the saved structure. Pick a cell\'s language to load its blocks in the Customizer, with the live footer alongside. If you changed rows or columns above, save first to refresh the grid.', 'pswpt'); ?></p>
                    <div class="pswpt-footer-grid">
                        <?php
                        $langs   = idml_get_languages();
                        $default = idml_get_default_language();
                        $widgets = wp_get_sidebars_widgets();
                        foreach ($rows as $index => $row) :
                            $columns = min(pswpt_FOOTER_MAX_COLS, max(1, (int) ($row['columns'] ?? 1)));
                        ?>
                        <div class="pswpt-footer-grid__row" style="grid-template-columns: <?php echo esc_attr(pswpt_footer_grid_template($columns, (string) ($row['layout'] ?? 'equal'))); ?>">
                            <?php for ($col = 1; $col <= $columns; $col++) : ?>
                            <div class="pswpt-footer-grid__cell">
                                <span class="pswpt-footer-grid__name"><?php printf(esc_html__('Row %1$d · Col %2$d', 'pswpt'), $index + 1, $col); ?></span>
                                <span class="pswpt-footer-grid__langs">
                                    <?php foreach ($langs as $lang) :
                                        $sidebar_id = pswpt_footer_sidebar_id($index + 1, $col, $lang);
                                        $count      = isset($widgets[$sidebar_id]) && is_array($widgets[$sidebar_id]) ? count($widgets[$sidebar_id]) : 0;
                                        $title      = $count > 0
                                            ? sprintf(_n('%d block', '%d blocks', $count, 'pswpt'), $count)
                                            : ($lang === $default ? __('Empty', 'pswpt') : __('Empty: the default language\'s is shown', 'pswpt'));
                                    ?>
                                    <a class="button button-small<?php echo $count > 0 ? ' pswpt-footer-grid__lang--filled' : ''; ?>" href="<?php echo esc_url(pswpt_footer_customize_url($sidebar_id)); ?>" title="<?php echo esc_attr($title); ?>"><?php echo esc_html(strtoupper($lang)); ?><?php if ($count > 0) : ?> <span class="pswpt-footer-grid__count"><?php echo (int) $count; ?></span><?php endif; ?></a>
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
                    <h2><?php esc_html_e('Copyright bar', 'pswpt'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Show bar', 'pswpt'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($option); ?>[bar_enabled]" value="1"<?php checked(!empty($opts['bar_enabled'])); ?> />
                                    <?php esc_html_e('Bottom bar with the copyright and, optionally, the footer menu', 'pswpt'); ?>
                                </label>
                            </td>
                        </tr>
                        <?php
                        $placeholders = [];
                        foreach (idml_get_languages() as $lang) {
                            $placeholders[$lang] = idml_t('footer.copyright', $lang);
                        }
                        pswpt_admin_lang_text_fields($option, 'bar_text', __('Text (%s)', 'pswpt'), $opts, __('Supports {year} (current year) and {site} (site name). Empty = the theme default, editable in Appearance → Translations (key footer.copyright).', 'pswpt'), $bar_if, $placeholders);
                        ?>
                        <tr data-show-if="<?php echo esc_attr($bar_if); ?>">
                            <th scope="row"><?php esc_html_e('Footer menu', 'pswpt'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($option); ?>[bar_menu]" value="1"<?php checked(!empty($opts['bar_menu'])); ?> />
                                    <?php esc_html_e('Show the menu assigned to the "Footer menu" location', 'pswpt'); ?>
                                </label>
                                <p class="description">
                                    <?php
                                    printf(
                                        esc_html__('Ideal for Privacy / Terms. Assigned in %s; its items are translated like the main menu\'s.', 'pswpt'),
                                        '<a href="' . esc_url(admin_url('nav-menus.php?action=locations')) . '">' . esc_html__('Appearance → Menus → Locations', 'pswpt') . '</a>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <tr data-show-if="<?php echo esc_attr($bar_if); ?>">
                            <th scope="row"><label for="<?php echo esc_attr($option); ?>_bar_align"><?php esc_html_e('Alignment', 'pswpt'); ?></label></th>
                            <td>
                                <select id="<?php echo esc_attr($option); ?>_bar_align" name="<?php echo esc_attr($option); ?>[bar_align]">
                                    <?php foreach (pswpt_footer_bar_aligns() as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>"<?php selected((string) ($opts['bar_align'] ?? ''), $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ===================== ESTILOS ===================== -->
                <div class="tab-pane fade" id="content-styles" role="tabpanel">
                    <h2><?php esc_html_e('Colors', 'pswpt'); ?></h2>
                    <?php pswpt_admin_color_table($option, array_map(fn($def) => $def[0], pswpt_footer_color_map()), $opts, [['prefix' => '', 'label' => __('Color', 'pswpt')]]); ?>

                    <h2><?php esc_html_e('Sizing', 'pswpt'); ?></h2>
                    <table class="form-table" role="presentation">
                        <?php foreach (pswpt_footer_size_map() as $key => [$label, $var, $min, $max, $description]) : ?>
                            <?php pswpt_admin_number_field($option, $key, $label, $opts, $min, $max, 'px', $description); ?>
                        <?php endforeach; ?>
                    </table>
                </div>

            </div>

            <?php submit_button(__('Save', 'pswpt')); ?>
        </form>
    </div>
    <?php
}
