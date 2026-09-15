<?php
/**
 * Helpers compartidos de los paneles Intelindev (Apariencia → Intelindev
 * Header / Footer / …): assets de admin, sanitize de color y los campos que se
 * repiten (tabs, imagen de la biblioteca, tabla de colores con picker, número
 * con unidad, texto por idioma). Cada panel guarda su propia option y define
 * sus claves; acá no hay estado.
 *
 * Convenciones que asumen los helpers:
 *  - El <form> lleva class="intelindev-settings-form intelindev-panel-form"
 *    (tabs + campos), action=options.php y settings_fields() del grupo.
 *  - Visibilidad condicional con data-show-if (ver intelindev-admin-fields.js).
 *  - Un color vacío = default de assets/css/styles.css (:root).
 *
 * @package intelindev
 */

if (!defined('ABSPATH')) exit;

/**
 * Encola media, color picker de WP, tabs y los campos compartidos. Llamar
 * desde el admin_enqueue_scripts de cada panel, solo en su hook.
 */
function intelindev_admin_enqueue_field_assets(): void {
    wp_enqueue_media();
    wp_enqueue_style('wp-color-picker');

    $dir = get_template_directory();
    $uri = get_template_directory_uri();

    $assets = [
        ['style',  'intelindev-settings-tabs', '/assets/css/intelindev-settings-tabs.css', []],
        ['script', 'intelindev-settings-tabs', '/assets/js/intelindev-settings-tabs.js', ['jquery']],
        ['style',  'intelindev-admin-fields',  '/assets/css/intelindev-admin-fields.css', ['wp-color-picker']],
        ['script', 'intelindev-admin-fields',  '/assets/js/intelindev-admin-fields.js', ['jquery', 'wp-color-picker']],
    ];
    foreach ($assets as [$type, $handle, $path, $deps]) {
        if (!file_exists($dir . $path)) continue;
        if ($type === 'style') {
            wp_enqueue_style($handle, $uri . $path, $deps, filemtime($dir . $path));
        } else {
            wp_enqueue_script($handle, $uri . $path, $deps, filemtime($dir . $path), true);
        }
    }

    wp_localize_script('intelindev-admin-fields', 'intelindevAdminFields', [
        'mediaTitle'  => __('Seleccionar imagen', 'intelindev'),
        'mediaButton' => __('Usar esta imagen', 'intelindev'),
    ]);
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
 * Texto por idioma guardado como array lang => texto: el del idioma pedido,
 * si no el del idioma por defecto, si no el primero cargado. Tolera un string.
 */
function intelindev_resolve_lang_text($texts, $lang = null): string {
    if (is_string($texts)) {
        return trim($texts);
    }
    if (!is_array($texts) || !$texts) {
        return '';
    }

    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    foreach ([$lang, idml_get_default_language()] as $candidate) {
        if (isset($texts[$candidate]) && is_string($texts[$candidate]) && trim($texts[$candidate]) !== '') {
            return trim($texts[$candidate]);
        }
    }

    return trim((string) reset($texts));
}

/**
 * Sanitize de un campo de texto por idioma (array lang => texto del POST).
 * Devuelve solo los idiomas activos con texto; [] si no queda ninguno.
 */
function intelindev_sanitize_lang_text($raw): array {
    if (is_string($raw)) {
        $raw = [idml_get_default_language() => $raw]; // valor legacy global
    }
    $texts = [];
    if (is_array($raw)) {
        foreach (idml_get_languages() as $lang) {
            $text = sanitize_text_field((string) ($raw[$lang] ?? ''));
            if ($text !== '') {
                $texts[$lang] = $text;
            }
        }
    }
    return $texts;
}

/* ------------------------------------------------------------------ */
/* Markup                                                               */
/* ------------------------------------------------------------------ */

/**
 * Nav de pestañas: $tabs = ['slug' => [icono, label], …]. Los panes los
 * imprime cada panel como <div class="tab-pane fade[ show active]" id="content-{slug}">.
 */
function intelindev_admin_tabs_nav(array $tabs): void {
    ?>
    <ul class="nav nav-tabs intelindev-tabs-nav" role="tablist">
        <?php $first = true; foreach ($tabs as $slug => [$icon, $label]) : ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link<?php echo $first ? ' active' : ''; ?>" id="tab-<?php echo esc_attr($slug); ?>" data-bs-toggle="tab" data-bs-target="#content-<?php echo esc_attr($slug); ?>" type="button" role="tab">
                    <span class="intelindev-tab-icon"><?php echo esc_html($icon); ?></span> <?php echo esc_html($label); ?>
                </button>
            </li>
        <?php $first = false; endforeach; ?>
    </ul>
    <?php
}

function intelindev_admin_show_if_attr(string $show_if): string {
    return $show_if !== '' ? ' data-show-if="' . esc_attr($show_if) . '"' : '';
}

/**
 * Fila de form-table para elegir una imagen de la biblioteca (ID de adjunto).
 */
function intelindev_admin_media_field(string $option, string $key, string $label, array $opts, string $description = '', string $show_if = ''): void {
    $id      = (int) ($opts[$key] ?? 0);
    $preview = $id > 0 ? (string) wp_get_attachment_image_url($id, 'medium') : '';
    $field   = $option . '_' . $key;
    ?>
    <tr<?php echo intelindev_admin_show_if_attr($show_if); ?>>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td>
            <div class="intelindev-media-field">
                <img class="intelindev-media-preview" src="<?php echo esc_url($preview); ?>" alt="" <?php echo $preview === '' ? 'hidden' : ''; ?> />
                <input type="hidden" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo (int) $id; ?>" />
                <button type="button" class="button intelindev-media-upload"><?php esc_html_e('Seleccionar imagen', 'intelindev'); ?></button>
                <button type="button" class="button-link-delete intelindev-media-remove" <?php echo $id > 0 ? '' : 'hidden'; ?>><?php esc_html_e('Quitar', 'intelindev'); ?></button>
            </div>
            <?php if ($description !== '') : ?><p class="description"><?php echo esc_html($description); ?></p><?php endif; ?>
        </td>
    </tr>
    <?php
}

/**
 * Tabla de colores: una fila por propiedad ($rows = [key => label]) y una
 * columna por variante ($columns = [['prefix' => '', 'label' => 'Normal'],
 * ['prefix' => 'sticky_', 'label' => 'Sticky', 'show_if' => '…']]). La clave de
 * option de cada celda es prefix . key. Inputs con el color picker de WP encima.
 */
function intelindev_admin_color_table(string $option, array $rows, array $opts, array $columns = [['prefix' => '', 'label' => '']]): void {
    ?>
    <table class="widefat intelindev-color-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Propiedad', 'intelindev'); ?></th>
                <?php foreach ($columns as $column) : ?>
                    <th<?php echo intelindev_admin_show_if_attr((string) ($column['show_if'] ?? '')); ?>><?php echo esc_html((string) ($column['label'] ?? '')); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $key => $label) : ?>
            <tr>
                <th scope="row"><?php echo esc_html($label); ?></th>
                <?php foreach ($columns as $column) : ?>
                    <td<?php echo intelindev_admin_show_if_attr((string) ($column['show_if'] ?? '')); ?>><?php intelindev_admin_color_input($option, (string) ($column['prefix'] ?? '') . $key, $opts); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function intelindev_admin_color_input(string $option, string $key, array $opts): void {
    $val = (string) ($opts[$key] ?? '');
    ?>
    <input type="text" id="<?php echo esc_attr($option . '_' . $key); ?>" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($val); ?>" class="intelindev-color-input" placeholder="<?php esc_attr_e('default', 'intelindev'); ?>" autocomplete="off" />
    <?php
}

/**
 * Fila de form-table con un número + unidad (px por defecto). Vacío = default.
 */
function intelindev_admin_number_field(string $option, string $key, string $label, array $opts, int $min, int $max, string $unit = 'px', string $description = '', string $show_if = ''): void {
    $val   = array_key_exists($key, $opts) ? (string) $opts[$key] : '';
    $field = $option . '_' . $key;
    ?>
    <tr<?php echo intelindev_admin_show_if_attr($show_if); ?>>
        <th scope="row"><label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <input type="number" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($val); ?>" min="<?php echo (int) $min; ?>" max="<?php echo (int) $max; ?>" step="1" class="small-text" placeholder="<?php esc_attr_e('default', 'intelindev'); ?>" /> <?php echo esc_html($unit); ?>
            <?php if ($description !== '') : ?><p class="description"><?php echo esc_html($description); ?></p><?php endif; ?>
        </td>
    </tr>
    <?php
}

/**
 * Filas de form-table con un input de texto por idioma activo. $label lleva
 * %s para el código de idioma. $placeholders = [lang => placeholder].
 */
function intelindev_admin_lang_text_fields(string $option, string $key, string $label, array $opts, string $description = '', string $show_if = '', array $placeholders = []): void {
    $texts = $opts[$key] ?? [];
    if (is_string($texts)) {
        $texts = [idml_get_default_language() => $texts];
    }
    foreach (idml_get_languages() as $lang) :
        $field = $option . '_' . $key . '_' . $lang;
        ?>
        <tr<?php echo intelindev_admin_show_if_attr($show_if); ?>>
            <th scope="row"><label for="<?php echo esc_attr($field); ?>"><?php echo esc_html(sprintf($label, strtoupper($lang))); ?></label></th>
            <td>
                <input type="text" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>][<?php echo esc_attr($lang); ?>]" value="<?php echo esc_attr((string) ($texts[$lang] ?? '')); ?>" class="regular-text" placeholder="<?php echo esc_attr((string) ($placeholders[$lang] ?? '')); ?>" />
                <?php if ($description !== '' && $lang === idml_get_default_language()) : ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    endforeach;
}
