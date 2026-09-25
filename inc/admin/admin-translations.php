<?php
/**
 * Apariencia → Traducciones: editor de los strings fijos de UI del theme
 * (labels, "nada encontrado", copyright, nombres de idioma...). Es EL
 * mecanismo para cambiar esos textos sin tocar código. El contenido de
 * Pages/Posts se traduce aparte, por post (metabox "Contenido traducido").
 *
 * Guarda en la option idml_translations_ui (ver idml_get_dictionary() en
 * i18n.php), no en languages/ui.json: la carpeta del theme suele no ser
 * escribible en hosting, un deploy del zip pisaría lo editado, y una option
 * autoloaded no cuesta queries en frontend. languages/ui.json queda como
 * default del theme: acá se muestra como placeholder y un campo vacío vuelve
 * a él. Solo se guardan valores no vacíos.
 *
 * @package pswpt
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_theme_page(
        __('UI Translations', 'pswpt'),
        __('Translations', 'pswpt'),
        'manage_options',
        'idml-translations',
        'idml_translations_admin_page'
    );
});

// El POST se procesa antes de imprimir nada y redirige (PRG): sin reenvío al
// refrescar y sin "headers already sent".
add_action('admin_init', 'idml_translations_handle_save');

/**
 * Normaliza una clave de traducción: minúsculas, espacios a "_", solo
 * [a-z0-9._-]. Devuelve '' si no queda nada usable.
 */
function idml_sanitize_translation_key($key) {
    $key = strtolower(trim(wp_strip_all_tags((string) $key)));
    $key = preg_replace('/\s+/', '_', $key);
    $key = preg_replace('/[^a-z0-9._-]/', '', $key);
    return trim((string) $key, '._-');
}

/**
 * Convierte los arrays del formulario (idml_keys[i], idml_val[lang][i]) en el
 * mapa de overrides clave => [lang => texto] y lo guarda. Sin redirect: la
 * usa el handler del POST y los tests.
 */
function idml_translations_save(array $keys, array $vals): array {
    $langs = idml_get_languages();

    $overrides = [];
    foreach ($keys as $i => $raw_key) {
        $key = idml_sanitize_translation_key($raw_key);
        if ($key === '') continue;

        $row = [];
        foreach ($langs as $lang) {
            $value = isset($vals[$lang][$i]) ? sanitize_text_field((string) $vals[$lang][$i]) : '';
            if ($value !== '') {
                $row[$lang] = $value;
            }
        }
        if ($row) {
            $overrides[$key] = $row;
        }
    }

    update_option(idml_get_translations_option_name('ui'), $overrides, true);
    return $overrides;
}

function idml_translations_handle_save() {
    if (!isset($_POST['idml_translations_nonce'])) return;
    if (!current_user_can('manage_options')) return;
    check_admin_referer('idml_translations_save', 'idml_translations_nonce');

    idml_translations_save(
        isset($_POST['idml_keys']) && is_array($_POST['idml_keys']) ? wp_unslash($_POST['idml_keys']) : [],
        isset($_POST['idml_val']) && is_array($_POST['idml_val']) ? wp_unslash($_POST['idml_val']) : []
    );

    wp_safe_redirect(add_query_arg(['page' => 'idml-translations', 'updated' => '1'], admin_url('themes.php')));
    exit;
}

/**
 * Título legible del grupo de una clave (la parte antes del primer punto).
 * Prefijos desconocidos (ej. "hero" de un Componente {t:hero.x}) se muestran
 * capitalizados; claves sin punto van a "General". El orden del array es el
 * orden de las secciones.
 */
function idml_translation_group_labels(): array {
    return apply_filters('idml_translation_group_labels', [
        'menu'     => __('Menu', 'pswpt'),
        'nav'      => __('Navigation', 'pswpt'),
        'title'    => __('Browser titles', 'pswpt'),
        'archive'  => __('Listings', 'pswpt'),
        'footer'   => __('Footer', 'pswpt'),
        'cta'      => __('CTA', 'pswpt'),
        'language' => __('Languages', 'pswpt'),
        'general'  => __('General', 'pswpt'),
    ]);
}

function idml_translation_group_of(string $key): string {
    $dot = strpos($key, '.');
    return $dot === false ? 'general' : substr($key, 0, $dot);
}

function idml_translations_admin_page() {
    if (!current_user_can('manage_options')) return;

    $langs     = idml_get_languages();
    $defaults  = idml_get_dictionary_defaults('ui');
    $overrides = idml_get_dictionary_overrides('ui');
    $labels    = idml_translation_group_labels();

    // Unión de claves del theme + del admin, agrupadas por prefijo. Grupos
    // conocidos en el orden de idml_translation_group_labels(), el resto
    // alfabético al final.
    $keys = array_unique(array_merge(array_keys($defaults), array_keys($overrides)));
    sort($keys, SORT_STRING);
    $groups = [];
    foreach ($keys as $key) {
        $groups[idml_translation_group_of($key)][] = $key;
    }
    uksort($groups, function ($a, $b) use ($labels) {
        $ia = array_search($a, array_keys($labels), true);
        $ib = array_search($b, array_keys($labels), true);
        if ($ia === false && $ib === false) return strcmp($a, $b);
        if ($ia === false) return 1;
        if ($ib === false) return -1;
        return $ia <=> $ib;
    });

    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Translations saved.', 'pswpt') . '</p></div>';
    }

    $origin_badge = function (string $key) use ($defaults, $overrides): string {
        if (!isset($defaults[$key])) {
            return '<span class="idml-badge idml-badge--own">' . esc_html__('custom', 'pswpt') . '</span>';
        }
        if (!empty($overrides[$key])) {
            return '<span class="idml-badge idml-badge--edited">' . esc_html__('edited', 'pswpt') . '</span>';
        }
        return '<span class="idml-badge idml-badge--default">' . esc_html__('default', 'pswpt') . '</span>';
    };
    ?>
    <style>
        .idml-translations .idml-toolbar { display: flex; gap: 12px; align-items: center; margin: 12px 0; }
        .idml-translations .idml-toolbar input[type="search"] { min-width: 320px; }
        .idml-translations details.idml-group { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; margin: 0 0 12px; }
        .idml-translations details.idml-group > summary { cursor: pointer; padding: 10px 14px; font-weight: 600; list-style: none; display: flex; align-items: center; gap: 10px; }
        .idml-translations details.idml-group > summary::-webkit-details-marker { display: none; }
        .idml-translations details.idml-group > summary::before { content: "▸"; color: #8c8f94; }
        .idml-translations details.idml-group[open] > summary::before { content: "▾"; }
        .idml-translations details.idml-group > summary .idml-count { font-weight: 400; color: #646970; }
        .idml-translations details.idml-group table { border: 0; border-top: 1px solid #dcdcde; }
        .idml-translations .idml-badge { display: inline-block; font-size: 11px; line-height: 18px; padding: 0 7px; border-radius: 9px; white-space: nowrap; }
        .idml-translations .idml-badge--default { background: #f0f0f1; color: #646970; }
        .idml-translations .idml-badge--edited { background: #2271b1; color: #fff; }
        .idml-translations .idml-badge--own { background: #00a32a; color: #fff; }
        .idml-translations .idml-col-key { width: 34%; }
        .idml-translations .idml-col-origin { width: 80px; }
        .idml-translations tr.idml-hidden, .idml-translations details.idml-hidden { display: none; }
    </style>
    <div class="wrap idml-translations">
        <h1><?php esc_html_e('UI Translations', 'pswpt'); ?></h1>
        <p class="description">
            <?php esc_html_e('Fixed site texts, grouped by key prefix (menu.*, footer.*…). Gray text is the theme default: type to override it, clear the field to go back to it. Keys shipped with the theme cannot be deleted, only overridden; keys you add are deleted by clearing the key and saving. A new key lands in its group on save (e.g. menu.blog → Menu); a new prefix creates its own group.', 'pswpt'); ?>
        </p>
        <form method="post">
            <?php wp_nonce_field('idml_translations_save', 'idml_translations_nonce'); ?>

            <div class="idml-toolbar">
                <input type="search" id="idml-search" placeholder="<?php esc_attr_e('Search by key or text…', 'pswpt'); ?>" />
                <button type="button" class="button" id="idml-expand"><?php esc_html_e('Expand all', 'pswpt'); ?></button>
                <button type="button" class="button" id="idml-collapse"><?php esc_html_e('Collapse all', 'pswpt'); ?></button>
            </div>

            <?php $i = 0; foreach ($groups as $prefix => $group_keys) :
                $edited = count(array_filter($group_keys, fn($k) => !empty($overrides[$k])));
                $label  = $labels[$prefix] ?? ucfirst($prefix);
            ?>
            <details class="idml-group" data-group="<?php echo esc_attr($prefix); ?>" open>
                <summary>
                    <span><?php echo esc_html($label); ?></span>
                    <code><?php echo esc_html($prefix === 'general' ? '' : $prefix . '.*'); ?></code>
                    <span class="idml-count"><?php printf(esc_html__('%1$d keys · %2$d edited', 'pswpt'), count($group_keys), $edited); ?></span>
                </summary>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="idml-col-key"><?php esc_html_e('Key', 'pswpt'); ?></th>
                            <th class="idml-col-origin"><?php esc_html_e('Source', 'pswpt'); ?></th>
                            <?php foreach ($langs as $lang): ?><th><?php echo esc_html(strtoupper($lang)); ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group_keys as $key): ?>
                        <tr class="idml-row">
                            <td><input type="text" name="idml_keys[<?php echo (int) $i; ?>]" value="<?php echo esc_attr($key); ?>" class="regular-text"<?php echo isset($defaults[$key]) ? ' readonly' : ''; ?> /></td>
                            <td><?php echo $origin_badge($key); ?></td>
                            <?php foreach ($langs as $lang): ?>
                            <td>
                                <input type="text"
                                       name="idml_val[<?php echo esc_attr($lang); ?>][<?php echo (int) $i; ?>]"
                                       value="<?php echo esc_attr($overrides[$key][$lang] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($defaults[$key][$lang] ?? ''); ?>"
                                       class="regular-text" />
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php $i++; endforeach; ?>
                    </tbody>
                </table>
            </details>
            <?php endforeach; ?>

            <details class="idml-group idml-group--new" open>
                <summary><span><?php esc_html_e('New keys', 'pswpt'); ?></span><span class="idml-count"><?php esc_html_e('are placed in their group on save', 'pswpt'); ?></span></summary>
                <table class="widefat fixed striped" id="idml-translations-table">
                    <thead>
                        <tr>
                            <th class="idml-col-key"><?php esc_html_e('Key', 'pswpt'); ?></th>
                            <th class="idml-col-origin"></th>
                            <?php foreach ($langs as $lang): ?><th><?php echo esc_html(strtoupper($lang)); ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="idml-new-row">
                            <td><input type="text" name="idml_keys[<?php echo (int) $i; ?>]" value="" class="regular-text" placeholder="<?php esc_attr_e('group.key', 'pswpt'); ?>" /></td>
                            <td></td>
                            <?php foreach ($langs as $lang): ?>
                            <td><input type="text" name="idml_val[<?php echo esc_attr($lang); ?>][<?php echo (int) $i; ?>]" value="" class="regular-text" /></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
                <p style="padding: 0 14px 12px"><button type="button" class="button" id="idml-add-row"><?php esc_html_e('+ Add row', 'pswpt'); ?></button></p>
            </details>

            <p class="submit"><input type="submit" class="button-primary" value="<?php esc_attr_e('Save changes', 'pswpt'); ?>" /></p>
        </form>
    </div>
    <script>
    (function () {
        var wrap = document.querySelector('.idml-translations');
        if (!wrap) return;

        // Fila nueva
        var table = document.getElementById('idml-translations-table');
        var button = document.getElementById('idml-add-row');
        var next = <?php echo (int) $i + 1; ?>;
        if (table && button) {
            button.addEventListener('click', function () {
                var template = table.querySelector('tr.idml-new-row');
                var row = template.cloneNode(true);
                row.querySelectorAll('input').forEach(function (input) {
                    input.value = '';
                    input.name = input.name.replace(/\[\d+\]$/, '[' + next + ']');
                });
                template.parentNode.appendChild(row);
                row.querySelector('input').focus();
                next++;
            });
        }

        // Buscador: filtra filas por clave o texto (valor o default); oculta grupos vacíos.
        var search = document.getElementById('idml-search');
        var groups = wrap.querySelectorAll('details.idml-group:not(.idml-group--new)');
        if (search) {
            search.addEventListener('input', function () {
                var q = search.value.trim().toLowerCase();
                groups.forEach(function (group) {
                    var visible = 0;
                    group.querySelectorAll('tr.idml-row').forEach(function (row) {
                        var text = Array.prototype.map.call(row.querySelectorAll('input'), function (input) {
                            return (input.value + ' ' + (input.placeholder || '')).toLowerCase();
                        }).join(' ');
                        var match = q === '' || text.indexOf(q) !== -1;
                        row.classList.toggle('idml-hidden', !match);
                        if (match) visible++;
                    });
                    group.classList.toggle('idml-hidden', visible === 0);
                    if (q !== '' && visible > 0) group.open = true;
                });
            });
        }

        // Expandir / contraer, con memoria por grupo en localStorage.
        var KEY = 'idml-translations-collapsed';
        var collapsed = [];
        try { collapsed = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) {}
        groups.forEach(function (group) {
            if (collapsed.indexOf(group.dataset.group) !== -1) group.open = false;
            group.addEventListener('toggle', function () {
                var list = Array.prototype.filter.call(groups, function (g) { return !g.open; }).map(function (g) { return g.dataset.group; });
                try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {}
            });
        });
        var setAll = function (open) { groups.forEach(function (g) { g.open = open; }); };
        var expand = document.getElementById('idml-expand'), collapse = document.getElementById('idml-collapse');
        if (expand) expand.addEventListener('click', function () { setAll(true); });
        if (collapse) collapse.addEventListener('click', function () { setAll(false); });
    })();
    </script>
    <?php
}
