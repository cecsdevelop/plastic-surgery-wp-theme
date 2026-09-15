<?php
if (!defined('ABSPATH')) exit;

// Admin page for managing active languages
add_action('admin_menu', function() {
    add_theme_page(
        __('Idiomas del sitio', 'intelindev'),
        __('Idiomas', 'intelindev'),
        'manage_options',
        'idml-languages',
        'idml_languages_admin_page'
    );
});

function idml_languages_admin_page() {
    if (!current_user_can('manage_options')) return;
    $option_name = 'idml_languages';
    $default_langs = ['es', 'en', 'pt'];
    $langs = get_option($option_name, $default_langs);
    if (!is_array($langs)) $langs = $default_langs;

    if (isset($_POST['idml_languages_nonce']) && wp_verify_nonce($_POST['idml_languages_nonce'], 'idml_languages_save')) {
        $new_langs = array_map('sanitize_key', explode(',', $_POST['idml_languages']));
        $new_langs = array_values(array_filter(array_unique($new_langs)));
        if ($new_langs) {
            update_option($option_name, $new_langs);
            $langs = $new_langs;
            echo '<div class="updated"><p>Idiomas actualizados.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Idiomas activos del sitio', 'intelindev'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('idml_languages_save', 'idml_languages_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="idml_languages">Códigos de idioma (ej: es,en,pt)</label></th>
                    <td>
                        <input type="text" id="idml_languages" name="idml_languages" value="<?php echo esc_attr(implode(',', $langs)); ?>" class="regular-text" />
                        <p class="description">Separados por coma. Cada idioma agrega una columna en Apariencia → Traducciones.</p>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" class="button-primary" value="Guardar cambios" /></p>
        </form>
    </div>
    <?php
}
