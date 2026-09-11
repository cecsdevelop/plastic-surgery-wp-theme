<?php
if (!defined('ABSPATH')) exit;

// Admin page for managing translations
add_action('admin_menu', function() {
    add_theme_page(
        __('Traducciones UI', 'intelindev'),
        __('Traducciones', 'intelindev'),
        'manage_options',
        'idml-translations',
        'idml_translations_admin_page'
    );
});

function idml_translations_admin_page() {
    if (!current_user_can('manage_options')) return;
    $json_path = get_template_directory() . '/languages/ui.json';
    $langs = get_option('idml_languages', ['es', 'en']);
    if (!is_array($langs)) $langs = ['es', 'en'];
    $dict = [];
    if (file_exists($json_path)) {
        $dict = json_decode(file_get_contents($json_path), true);
        if (!is_array($dict)) $dict = [];
    }
    if (isset($_POST['idml_translations_nonce']) && wp_verify_nonce($_POST['idml_translations_nonce'], 'idml_translations_save')) {
        $new_dict = [];
        if (isset($_POST['idml_keys']) && is_array($_POST['idml_keys'])) {
            foreach ($_POST['idml_keys'] as $i => $key) {
                $key = trim($key);
                if ($key === '') continue;
                $row = [];
                foreach ($langs as $lang) {
                    $row[$lang] = sanitize_text_field($_POST['idml_val'][$lang][$i] ?? '');
                }
                $new_dict[$key] = $row;
            }
        }
        file_put_contents($json_path, json_encode($new_dict, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $dict = $new_dict;
        echo '<div class="updated"><p>Traducciones guardadas.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Traducciones UI', 'intelindev'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('idml_translations_save', 'idml_translations_nonce'); ?>
            <table class="widefat fixed">
                <thead>
                    <tr>
                        <th>Clave</th>
                        <?php foreach ($langs as $lang): ?><th><?php echo esc_html(strtoupper($lang)); ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 0; foreach ($dict as $key => $row): ?>
                    <tr>
                        <td><input type="text" name="idml_keys[]" value="<?php echo esc_attr($key); ?>" class="regular-text" /></td>
                        <?php foreach ($langs as $lang): ?>
                        <td><input type="text" name="idml_val[<?php echo esc_attr($lang); ?>][]" value="<?php echo esc_attr($row[$lang] ?? ''); ?>" class="regular-text" /></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php $i++; endforeach; ?>
                    <!-- Empty row for new entry -->
                    <tr>
                        <td><input type="text" name="idml_keys[]" value="" class="regular-text" /></td>
                        <?php foreach ($langs as $lang): ?>
                        <td><input type="text" name="idml_val[<?php echo esc_attr($lang); ?>][]" value="" class="regular-text" /></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
            <p class="submit"><input type="submit" class="button-primary" value="Guardar cambios" /></p>
        </form>
        <p class="description">Para agregar una nueva clave, usa la última fila vacía. Para eliminar una clave, bórrala y guarda.</p>
    </div>
    <?php
}
