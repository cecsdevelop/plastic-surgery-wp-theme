<?php
/**
 * Pestaña "Tipografía" de Apariencia → pswpt Settings: cierra el circuito
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
 * @package pswpt
 */

if (!defined('ABSPATH')) exit;

const pswpt_TYPO_SIZE_MIN = 14;
const pswpt_TYPO_SIZE_MAX = 22;
const pswpt_TYPO_WEIGHTS  = [300, 400, 500, 600, 700, 800];
const pswpt_TYPO_DEFAULT_BODY = 'dm-sans'; // slug en theme.json; es el default de --psw-font-body en styles.css

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
function pswpt_typography_font_families(): array {
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
function pswpt_typography_preload_src(string $slug): string {
    $families = pswpt_typography_font_families();
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
    add_settings_section('pswpt_typography_section', __('Site fonts', 'pswpt'), function () {
        printf(
            '<p>%s</p>',
            sprintf(
                esc_html__('Available families are the theme\'s plus those enabled in %s (Install fonts pulls Google Fonts and hosts them on the server: no external requests). Empty = theme default.', 'pswpt'),
                '<a href="' . esc_url(admin_url('font-library.php')) . '">' . esc_html__('Appearance → Fonts', 'pswpt') . '</a>'
            )
        );
    }, 'pswpt-settings-typography');

    add_settings_field('pswpt_font_body',    __('Body font', 'pswpt'),     'pswpt_field_font_body_cb',    'pswpt-settings-typography', 'pswpt_typography_section');
    add_settings_field('pswpt_font_heading', __('Heading font', 'pswpt'), 'pswpt_field_font_heading_cb', 'pswpt-settings-typography', 'pswpt_typography_section');
    add_settings_field('pswpt_font_accent',  __('Accent font', 'pswpt'),      'pswpt_field_font_accent_cb',  'pswpt-settings-typography', 'pswpt_typography_section');
    add_settings_field('pswpt_font_size_base', __('Base text size', 'pswpt'), 'pswpt_field_font_size_base_cb', 'pswpt-settings-typography', 'pswpt_typography_section');
    add_settings_field('pswpt_font_weight_heading', __('Large heading weight (h1–h3)', 'pswpt'), 'pswpt_field_font_weight_heading_cb', 'pswpt-settings-typography', 'pswpt_typography_section');
    add_settings_field('pswpt_font_preload', __('Preload', 'pswpt'), 'pswpt_field_font_preload_cb', 'pswpt-settings-typography', 'pswpt_typography_section');
});

function pswpt_typography_family_select(string $key, string $description): void {
    $current  = (string) pswpt_get_setting($key, '');
    $families = pswpt_typography_font_families();
    echo '<select id="pswpt_' . esc_attr($key) . '" name="pswpt_settings[' . esc_attr($key) . ']">';
    echo '<option value="">' . esc_html__('— Theme default —', 'pswpt') . '</option>';
    foreach ($families as $slug => $family) {
        $label = $family['name'] . ($family['origin'] === 'custom' ? ' · ' . __('Library', 'pswpt') : '') . ($family['fontFace'] ? '' : ' · ' . __('no files', 'pswpt'));
        printf('<option value="%s"%s>%s</option>', esc_attr($slug), selected($current, $slug, false), esc_html($label));
    }
    echo '</select>';
    if ($description !== '') echo '<p class="description">' . esc_html($description) . '</p>'; // <em> se muestra literal a propósito
}

function pswpt_field_font_body_cb(): void {
    pswpt_typography_family_select('font_body', __('Applied site-wide (body).', 'pswpt'));
}

function pswpt_field_font_heading_cb(): void {
    pswpt_typography_family_select('font_heading', __('h1–h6. Empty = same as body text.', 'pswpt'));
}

function pswpt_field_font_accent_cb(): void {
    pswpt_typography_family_select('font_accent', __('For <em> inside headings and the .accent class (the italic serif in the design). Empty = the theme\'s.', 'pswpt'));
}

function pswpt_field_font_size_base_cb(): void {
    $val = pswpt_get_setting('font_size_base', '');
    printf(
        '<input type="number" id="pswpt_font_size_base" name="pswpt_settings[font_size_base]" value="%s" min="%d" max="%d" step="1" class="small-text" placeholder="16" /> px',
        esc_attr((string) $val), pswpt_TYPO_SIZE_MIN, pswpt_TYPO_SIZE_MAX
    );
}

function pswpt_field_font_weight_heading_cb(): void {
    $val = (string) pswpt_get_setting('font_weight_heading', '');
    echo '<select id="pswpt_font_weight_heading" name="pswpt_settings[font_weight_heading]"><option value="">' . esc_html__('— Theme default (300) —', 'pswpt') . '</option>';
    foreach (pswpt_TYPO_WEIGHTS as $w) printf('<option value="%d"%s>%d</option>', $w, selected($val, (string) $w, false), $w);
    echo '</select>';
}

function pswpt_field_font_preload_cb(): void {
    $on = pswpt_get_setting('font_preload', 1);
    echo '<label><input type="checkbox" name="pswpt_settings[font_preload]" value="1" ' . checked(1, (int) $on, false) . ' /> ' . esc_html__('Preload the body font\'s woff2 file (better LCP; only applies to fonts with files).', 'pswpt') . '</label>';
}

add_filter('pswpt_settings_sanitize', function (array $out, array $input): array {
    $families = pswpt_typography_font_families();
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
        $out['font_size_base'] = max(pswpt_TYPO_SIZE_MIN, min(pswpt_TYPO_SIZE_MAX, (int) $size));
    } else {
        unset($out['font_size_base']);
    }

    $weight = (int) ($input['font_weight_heading'] ?? 0);
    if (in_array($weight, pswpt_TYPO_WEIGHTS, true)) {
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
    $families = pswpt_typography_font_families();
    $decl = '';

    $body = (string) pswpt_get_setting('font_body', '');
    if ($body !== '' && isset($families[$body])) {
        $decl .= '--psw-font-body:var(--wp--preset--font-family--' . $body . ');';
    }
    $heading = (string) pswpt_get_setting('font_heading', '');
    if ($heading !== '' && isset($families[$heading])) {
        $decl .= '--psw-font-heading:var(--wp--preset--font-family--' . $heading . ');';
    }
    $accent = (string) pswpt_get_setting('font_accent', '');
    if ($accent !== '' && isset($families[$accent])) {
        $decl .= '--psw-font-accent:var(--wp--preset--font-family--' . $accent . ');';
    }
    $size = (int) pswpt_get_setting('font_size_base', 0);
    if ($size >= pswpt_TYPO_SIZE_MIN && $size <= pswpt_TYPO_SIZE_MAX) {
        $decl .= '--psw-font-size-base:' . $size . 'px;';
    }
    $weight = (int) pswpt_get_setting('font_weight_heading', 0);
    if (in_array($weight, pswpt_TYPO_WEIGHTS, true)) {
        $decl .= '--psw-font-weight-heading:' . $weight . ';';
    }

    if ($decl !== '') {
        echo '<style id="pswpt-typography-overrides">:root{' . $decl . '}</style>' . "\n";
    }
}, 20);

/** Preload del woff2 de la fuente del texto, antes de las hojas de estilo. */
add_action('wp_head', function () {
    if (!pswpt_get_setting('font_preload', 1)) return;
    $body = (string) pswpt_get_setting('font_body', '') ?: pswpt_TYPO_DEFAULT_BODY;
    $src = pswpt_typography_preload_src($body);
    if ($src !== '') {
        echo '<link rel="preload" href="' . esc_url($src) . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
}, 1);
