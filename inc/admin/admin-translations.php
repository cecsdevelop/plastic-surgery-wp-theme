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
 * @package intelindev
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_theme_page(
        __('Traducciones UI', 'intelindev'),
        __('Traducciones', 'intelindev'),
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

function idml_translations_handle_save() {
    if (!isset($_POST['idml_translations_nonce'])) return;
    if (!current_user_can('manage_options')) return;
    check_admin_referer('idml_translations_save', 'idml_translations_nonce');

    $langs = idml_get_languages();
    $keys  = isset($_POST['idml_keys']) && is_array($_POST['idml_keys']) ? wp_unslash($_POST['idml_keys']) : [];
    $vals  = isset($_POST['idml_val']) && is_array($_POST['idml_val']) ? wp_unslash($_POST['idml_val']) : [];

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

    wp_safe_redirect(add_query_arg(['page' => 'idml-translations', 'updated' => '1'], admin_url('themes.php')));
    exit;
}

function idml_translations_admin_page() {
    if (!current_user_can('manage_options')) return;

    $langs     = idml_get_languages();
    $defaults  = idml_get_dictionary_defaults('ui');
    $overrides = idml_get_dictionary_overrides('ui');

    // Unión de claves del theme + del admin, ordenadas para que sea fácil
    // ubicar una (ej. todas las "footer.*" juntas).
    $keys = array_unique(array_merge(array_keys($defaults), array_keys($overrides)));
    sort($keys, SORT_STRING);

    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Traducciones guardadas.', 'intelindev') . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Traducciones UI', 'intelindev'); ?></h1>
        <p class="description">
            <?php esc_html_e('Textos fijos del sitio (menús, botones, avisos, pie de página). El texto en gris es el default del theme: escribí para reemplazarlo, vaciá el campo para volver a él. Las claves que trae el theme no se pueden eliminar, solo sobrescribir; las que agregues vos se eliminan borrando la clave y guardando.', 'intelindev'); ?>
        </p>
        <form method="post">
            <?php wp_nonce_field('idml_translations_save', 'idml_translations_nonce'); ?>
            <table class="widefat fixed striped" id="idml-translations-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Clave', 'intelindev'); ?></th>
                        <?php foreach ($langs as $lang): ?><th><?php echo esc_html(strtoupper($lang)); ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 0; foreach ($keys as $key): ?>
                    <tr>
                        <td>
                            <input type="text" name="idml_keys[<?php echo (int) $i; ?>]" value="<?php echo esc_attr($key); ?>" class="regular-text"<?php echo isset($defaults[$key]) ? ' readonly' : ''; ?> />
                        </td>
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
                    <tr class="idml-new-row">
                        <td><input type="text" name="idml_keys[<?php echo (int) $i; ?>]" value="" class="regular-text" placeholder="<?php esc_attr_e('nueva.clave', 'intelindev'); ?>" /></td>
                        <?php foreach ($langs as $lang): ?>
                        <td><input type="text" name="idml_val[<?php echo esc_attr($lang); ?>][<?php echo (int) $i; ?>]" value="" class="regular-text" /></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
            <p>
                <button type="button" class="button" id="idml-add-row"><?php esc_html_e('+ Agregar fila', 'intelindev'); ?></button>
            </p>
            <p class="submit"><input type="submit" class="button-primary" value="<?php esc_attr_e('Guardar cambios', 'intelindev'); ?>" /></p>
        </form>
    </div>
    <script>
    (function () {
        var table = document.getElementById('idml-translations-table');
        var button = document.getElementById('idml-add-row');
        if (!table || !button) return;
        var next = <?php echo (int) $i + 1; ?>;
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
    })();
    </script>
    <?php
}
