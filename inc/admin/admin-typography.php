<?php
/**
 * Pestaña "Tipografía" de Apariencia → Intelindev Settings: cierra el circuito
 * que WordPress deja abierto en un theme clásico. Apariencia → Fuentes (la
 * Biblioteca de fuentes de WP) instala y activa fuentes y el core imprime sus
 * @font-face y la variable --wp--preset--font-family--{slug}, pero "Estilos"
 * es solo previsualización en themes clásicos: nada aplica la fuente al sitio.
 *
 * Acá el admin elige, entre las familias del theme.json y las activadas en la
 * Biblioteca, la fuente del texto, la de los títulos y la de acento (<em> en
 * títulos), el tamaño base y el peso de los títulos grandes. Se imprimen como custom properties en wp_head (los
 * defaults viven en styles.css) y, si la fuente del texto tiene archivos, un
 * <link rel="preload"> de su woff2. Las fuentes de la Biblioteca se sirven
 * desde uploads/fonts: sin requests a Google.
 *
 * @package intelindev
 */

if (!defined('ABSPATH')) exit;

const INTELINDEV_TYPO_SIZE_MIN = 14;
const INTELINDEV_TYPO_SIZE_MAX = 22;
const INTELINDEV_TYPO_WEIGHTS  = [300, 400, 500, 600, 700, 800];
const INTELINDEV_TYPO_DEFAULT_BODY = 'dm-sans'; // slug en theme.json; es el default de --psw-font-body en styles.css

/**
 * Al activar una fuente, la Biblioteca guarda en los estilos globales del usuario
 * una COPIA de la lista de fuentes del theme (clave "theme") que desde entonces
 * pisa a theme.json: si el theme agrega o cambia fuentes, dejan de aparecer.
 * Nos quedamos solo con "custom" (las de la Biblioteca); las del theme salen
 * siempre de theme.json.
 */
add_filter('wp_theme_json_data_user', function ($theme_json) {
    $data     = $theme_json->get_data();
    $families = $data['settings']['typography']['fontFamilies'] ?? null;
    if (!is_array($families) || (!isset($families['theme']) && !isset($families['default']))) return $theme_json;
    unset($families['theme'], $families['default']);
    $data['settings']['typography']['fontFamilies'] = $families;
    return new WP_Theme_JSON_Data($data, 'custom');
});

/**
 * Familias disponibles: theme.json + activadas en Apariencia → Fuentes.
 * slug => [name, fontFamily, fontFace[]]. Excluye el origen 'default' de WP.
 */
function intelindev_typography_font_families(): array {
    $settings = wp_get_global_settings(['typography', 'fontFamilies']);
    $out = [];
    foreach (['theme', 'custom'] as $origin) {
        foreach ((array) ($settings[$origin] ?? []) as $family) {
            $slug = sanitize_key((string) ($family['slug'] ?? ''));
            if ($slug === '' || isset($out[$slug])) continue;
            $out[$slug] = [
                'name'       => (string) ($family['name'] ?? $slug),
                'fontFamily' => (string) ($family['fontFamily'] ?? ''),
                'fontFace'   => (array) ($family['fontFace'] ?? []),
                'origin'     => $origin,
            ];
        }
    }
    return $out;
}

/** URL del woff2 de la cara regular (400/normal) de una familia, o '' si no tiene archivos. */
function intelindev_typography_preload_src(string $slug): string {
    $families = intelindev_typography_font_families();
    $faces = $families[$slug]['fontFace'] ?? [];
    if (!$faces) return '';

    $pick = null;
    foreach ($faces as $face) {
        $weight = trim((string) ($face['fontWeight'] ?? '400'));
        $style  = (string) ($face['fontStyle'] ?? 'normal');
        if ($style !== 'normal') continue;
        // Peso exacto (400/normal) o rango de fuente variable que lo incluya ("100 1000").
        $range = preg_split('/\s+/', $weight === 'normal' ? '400' : $weight);
        $min   = (int) $range[0];
        $max   = (int) ($range[1] ?? $range[0]);
        if ($min <= 400 && 400 <= $max) {
            $pick = $face;
            break;
        }
    }
    $pick = $pick ?? $faces[0];
    $src  = $pick['src'] ?? '';
    $src  = is_array($src) ? (string) reset($src) : (string) $src;
    // theme.json referencia los archivos del theme como "file:./ruta"; WP los resuelve al imprimir el @font-face.
    if (str_starts_with($src, 'file:./')) {
        $src = get_theme_file_uri(substr($src, 7));
    }
    return preg_match('/\.woff2(\?.*)?$/i', $src) ? esc_url_raw($src) : '';
}

/* ------------------------------------------------------------------ */
/* Admin                                                                */
/* ------------------------------------------------------------------ */

add_action('admin_init', function () {
    add_settings_section('intelindev_typography_section', __('Fuentes del sitio', 'intelindev'), function () {
        printf(
            '<p>%s</p>',
            sprintf(
                esc_html__('Las familias disponibles son las del theme más las activadas en %s (Instalar fuentes trae Google Fonts y las aloja en el servidor: sin llamadas externas). Vacío = default del theme.', 'intelindev'),
                '<a href="' . esc_url(admin_url('font-library.php')) . '">' . esc_html__('Apariencia → Fuentes', 'intelindev') . '</a>'
            )
        );
    }, 'intelindev-settings-typography');

    add_settings_field('intelindev_font_body',    __('Fuente del texto', 'intelindev'),     'intelindev_field_font_body_cb',    'intelindev-settings-typography', 'intelindev_typography_section');
    add_settings_field('intelindev_font_heading', __('Fuente de los títulos', 'intelindev'), 'intelindev_field_font_heading_cb', 'intelindev-settings-typography', 'intelindev_typography_section');
    add_settings_field('intelindev_font_accent',  __('Fuente de acento', 'intelindev'),      'intelindev_field_font_accent_cb',  'intelindev-settings-typography', 'intelindev_typography_section');
    add_settings_field('intelindev_font_size_base', __('Tamaño base del texto', 'intelindev'), 'intelindev_field_font_size_base_cb', 'intelindev-settings-typography', 'intelindev_typography_section');
    add_settings_field('intelindev_font_weight_heading', __('Peso de los títulos grandes (h1–h3)', 'intelindev'), 'intelindev_field_font_weight_heading_cb', 'intelindev-settings-typography', 'intelindev_typography_section');
    add_settings_field('intelindev_font_preload', __('Precarga', 'intelindev'), 'intelindev_field_font_preload_cb', 'intelindev-settings-typography', 'intelindev_typography_section');
});

function intelindev_typography_family_select(string $key, string $description): void {
    $current  = (string) intelindev_get_setting($key, '');
    $families = intelindev_typography_font_families();
    echo '<select id="intelindev_' . esc_attr($key) . '" name="intelindev_settings[' . esc_attr($key) . ']">';
    echo '<option value="">' . esc_html__('— Default del theme —', 'intelindev') . '</option>';
    foreach ($families as $slug => $family) {
        $label = $family['name'] . ($family['origin'] === 'custom' ? ' · ' . __('Biblioteca', 'intelindev') : '') . ($family['fontFace'] ? '' : ' · ' . __('sin archivos', 'intelindev'));
        printf('<option value="%s"%s>%s</option>', esc_attr($slug), selected($current, $slug, false), esc_html($label));
    }
    echo '</select>';
    if ($description !== '') echo '<p class="description">' . esc_html($description) . '</p>'; // <em> se muestra literal a propósito
}

function intelindev_field_font_body_cb(): void {
    intelindev_typography_family_select('font_body', __('Se aplica a todo el sitio (body).', 'intelindev'));
}

function intelindev_field_font_heading_cb(): void {
    intelindev_typography_family_select('font_heading', __('h1–h6. Vacío = la misma del texto.', 'intelindev'));
}

function intelindev_field_font_accent_cb(): void {
    intelindev_typography_family_select('font_accent', __('Para los <em> dentro de títulos y la clase .accent (en el diseño, la serif itálica). Vacío = la del theme.', 'intelindev'));
}

function intelindev_field_font_size_base_cb(): void {
    $val = intelindev_get_setting('font_size_base', '');
    printf(
        '<input type="number" id="intelindev_font_size_base" name="intelindev_settings[font_size_base]" value="%s" min="%d" max="%d" step="1" class="small-text" placeholder="16" /> px',
        esc_attr((string) $val), INTELINDEV_TYPO_SIZE_MIN, INTELINDEV_TYPO_SIZE_MAX
    );
}

function intelindev_field_font_weight_heading_cb(): void {
    $val = (string) intelindev_get_setting('font_weight_heading', '');
    echo '<select id="intelindev_font_weight_heading" name="intelindev_settings[font_weight_heading]"><option value="">' . esc_html__('— Default del theme (300) —', 'intelindev') . '</option>';
    foreach (INTELINDEV_TYPO_WEIGHTS as $w) printf('<option value="%d"%s>%d</option>', $w, selected($val, (string) $w, false), $w);
    echo '</select>';
}

function intelindev_field_font_preload_cb(): void {
    $on = intelindev_get_setting('font_preload', 1);
    echo '<label><input type="checkbox" name="intelindev_settings[font_preload]" value="1" ' . checked(1, (int) $on, false) . ' /> ' . esc_html__('Precargar el archivo woff2 de la fuente del texto (mejor LCP; solo aplica a fuentes con archivos).', 'intelindev') . '</label>';
}

add_filter('intelindev_settings_sanitize', function (array $out, array $input): array {
    $families = intelindev_typography_font_families();
    foreach (['font_body', 'font_heading', 'font_accent'] as $key) {
        $slug = sanitize_key((string) ($input[$key] ?? ''));
        if ($slug !== '' && isset($families[$slug])) {
            $out[$key] = $slug;
        } else {
            unset($out[$key]);
        }
    }

    $size = $input['font_size_base'] ?? '';
    if ($size !== '' && is_numeric($size)) {
        $out['font_size_base'] = max(INTELINDEV_TYPO_SIZE_MIN, min(INTELINDEV_TYPO_SIZE_MAX, (int) $size));
    } else {
        unset($out['font_size_base']);
    }

    $weight = (int) ($input['font_weight_heading'] ?? 0);
    if (in_array($weight, INTELINDEV_TYPO_WEIGHTS, true)) {
        $out['font_weight_heading'] = $weight;
    } else {
        unset($out['font_weight_heading']);
    }

    $out['font_preload'] = !empty($input['font_preload']) ? 1 : 0;
    return $out;
}, 10, 2);

/* ------------------------------------------------------------------ */
/* Frontend                                                             */
/* ------------------------------------------------------------------ */

/** Overrides como custom properties (los defaults están en styles.css). */
add_action('wp_head', function () {
    $families = intelindev_typography_font_families();
    $decl = '';

    $body = (string) intelindev_get_setting('font_body', '');
    if ($body !== '' && isset($families[$body])) {
        $decl .= '--psw-font-body:var(--wp--preset--font-family--' . $body . ');';
    }
    $heading = (string) intelindev_get_setting('font_heading', '');
    if ($heading !== '' && isset($families[$heading])) {
        $decl .= '--psw-font-heading:var(--wp--preset--font-family--' . $heading . ');';
    }
    $accent = (string) intelindev_get_setting('font_accent', '');
    if ($accent !== '' && isset($families[$accent])) {
        $decl .= '--psw-font-accent:var(--wp--preset--font-family--' . $accent . ');';
    }
    $size = (int) intelindev_get_setting('font_size_base', 0);
    if ($size >= INTELINDEV_TYPO_SIZE_MIN && $size <= INTELINDEV_TYPO_SIZE_MAX) {
        $decl .= '--psw-font-size-base:' . $size . 'px;';
    }
    $weight = (int) intelindev_get_setting('font_weight_heading', 0);
    if (in_array($weight, INTELINDEV_TYPO_WEIGHTS, true)) {
        $decl .= '--psw-font-weight-heading:' . $weight . ';';
    }

    if ($decl !== '') {
        echo '<style id="intelindev-typography-overrides">:root{' . $decl . '}</style>' . "\n";
    }
}, 20);

/** Preload del woff2 de la fuente del texto, antes de las hojas de estilo. */
add_action('wp_head', function () {
    if (!intelindev_get_setting('font_preload', 1)) return;
    $body = (string) intelindev_get_setting('font_body', '') ?: INTELINDEV_TYPO_DEFAULT_BODY;
    $src = intelindev_typography_preload_src($body);
    if ($src !== '') {
        echo '<link rel="preload" href="' . esc_url($src) . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
}, 1);
