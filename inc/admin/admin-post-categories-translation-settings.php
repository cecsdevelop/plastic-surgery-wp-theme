<?php
/**
 * Post Categories Translation Fields
 *
 * @package intelindev
 */

if (!defined('ABSPATH')) {
    exit;
}

const INTELINDEV_CATEGORY_TRANSLATED_CONTENT_META = '_intelindev_category_translated_content';
const INTELINDEV_CATEGORY_TRANSLATED_EXCERPT_META = '_intelindev_category_translated_excerpt';
const INTELINDEV_CATEGORY_TRANSLATED_NAME_META = '_intelindev_category_translated_name';
const INTELINDEV_CATEGORY_FEATURED_IMAGE_ID_META = '_intelindev_category_featured_image_id';
const INTELINDEV_CATEGORY_ORDER_META = '_intelindev_category_order';

/**
 * El campo "Name" nativo de la categoría es el nombre en el idioma por defecto (es).
 * Esta meta solo guarda los overrides para los demás idiomas.
 */
function intelindev_get_category_translated_name(WP_Term $term, $lang = null): string
{
    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default_lang = idml_get_default_language();

    if ($lang === '' || $lang === $default_lang) {
        return $term->name;
    }

    $translations = get_term_meta($term->term_id, INTELINDEV_CATEGORY_TRANSLATED_NAME_META, true);
    $translations = is_array($translations) ? $translations : [];
    $value = (string) ($translations[$lang] ?? '');

    return $value !== '' ? $value : $term->name;
}

/**
 * El excerpt traducido de categoría solo tiene ES/EN hoy (falta agregar PT
 * en la metabox). Mientras tanto, PT cae al fallback del idioma por defecto.
 */
function intelindev_get_category_translated_excerpt(WP_Term $term, $lang = null): string
{
    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default_lang = idml_get_default_language();

    $translations = get_term_meta($term->term_id, INTELINDEV_CATEGORY_TRANSLATED_EXCERPT_META, true);
    $translations = is_array($translations) ? $translations : [];

    if ($lang !== '' && trim((string) ($translations[$lang] ?? '')) !== '') {
        return (string) $translations[$lang];
    }

    return (string) ($translations[$default_lang] ?? '');
}

/**
 * El content traducido de categoría (descripción larga) es 100% custom meta,
 * sin fallback al campo nativo "description" (oculto en el admin).
 */
function intelindev_get_category_translated_content(WP_Term $term, $lang = null): string
{
    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default_lang = idml_get_default_language();

    $translations = get_term_meta($term->term_id, INTELINDEV_CATEGORY_TRANSLATED_CONTENT_META, true);
    $translations = is_array($translations) ? $translations : [];

    if ($lang !== '' && trim((string) ($translations[$lang] ?? '')) !== '') {
        return (string) $translations[$lang];
    }

    return (string) ($translations[$default_lang] ?? '');
}

function intelindev_get_category_featured_image_id(WP_Term $term): int
{
    return (int) get_term_meta($term->term_id, INTELINDEV_CATEGORY_FEATURED_IMAGE_ID_META, true);
}

/**
 * El slug nativo de la categoría es el slug en español (idioma por defecto).
 * Esta meta solo guarda los overrides de slug para los demás idiomas.
 */
function intelindev_category_translated_slug_meta_key(string $lang): string
{
    return '_intelindev_category_translated_slug_' . sanitize_key($lang);
}

add_action('category_add_form_fields', 'intelindev_render_category_translation_fields_add');
add_action('category_edit_form_fields', 'intelindev_render_category_translation_fields_edit');
add_action('created_category', 'intelindev_save_category_translation_fields');
add_action('edited_category', 'intelindev_save_category_translation_fields');
add_action('admin_head-edit-tags.php', 'intelindev_hide_default_category_description_field');
add_action('admin_head-term.php', 'intelindev_hide_default_category_description_field');
add_action('admin_enqueue_scripts', 'intelindev_enqueue_category_translation_media');
add_filter('manage_edit-category_columns', 'intelindev_add_category_order_column');
add_filter('manage_category_custom_column', 'intelindev_render_category_order_column', 10, 3);
add_filter('manage_edit-category_sortable_columns', 'intelindev_make_category_order_column_sortable');
add_action('pre_get_terms', 'intelindev_handle_category_order_column_sorting');
add_action('wp_ajax_intelindev_update_category_order', 'intelindev_ajax_update_category_order');

function intelindev_enqueue_category_translation_media($hook): void
{
    if (!in_array($hook, ['edit-tags.php', 'term.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->taxonomy !== 'category') {
        return;
    }

    wp_enqueue_media();

    wp_add_inline_script('jquery', "
        jQuery(function($){
            var frame = null;

            function bindCategoryImagePicker(context) {
                var root = context || $(document);
                root.find('.intelindev-category-image-upload').off('click').on('click', function(e){
                    e.preventDefault();

                    var button = $(this);
                    var wrapper = button.closest('.intelindev-category-image-wrap');
                    var idInput = wrapper.find('.intelindev-category-image-id');
                    var urlInput = wrapper.find('.intelindev-category-image-url');
                    var preview = wrapper.find('.intelindev-category-image-preview');

                    frame = wp.media({
                        title: 'Selecciona imagen destacada de categoría',
                        button: { text: 'Usar imagen' },
                        multiple: false,
                        library: { type: 'image' }
                    });

                    frame.on('select', function(){
                        var attachment = frame.state().get('selection').first().toJSON();
                        var previewUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

                        idInput.val(attachment.id);
                        urlInput.val(previewUrl || '');
                        if (previewUrl) {
                            preview.attr('src', previewUrl).show();
                        }
                    });

                    frame.open();
                });

                root.find('.intelindev-category-image-remove').off('click').on('click', function(e){
                    e.preventDefault();
                    var button = $(this);
                    var wrapper = button.closest('.intelindev-category-image-wrap');
                    wrapper.find('.intelindev-category-image-id').val('');
                    wrapper.find('.intelindev-category-image-url').val('');
                    wrapper.find('.intelindev-category-image-preview').attr('src', '').hide();
                });
            }

            bindCategoryImagePicker($(document));

            function relocateTranslationColumns(nativeWrapSelector, sourceRowSelector, colClass) {
                var nativeWrap = document.querySelector(nativeWrapSelector);
                var sourceRow = document.querySelector(sourceRowSelector);
                if (!nativeWrap || !sourceRow) {
                    return;
                }

                var isTableRow = nativeWrap.tagName === 'TR';
                var contentHost = isTableRow ? nativeWrap.querySelector('td') : nativeWrap;
                if (!contentHost) {
                    return;
                }

                var nativeCol = document.createElement('div');
                nativeCol.className = 'intelindev-translations-col intelindev-translations-col-native';

                if (isTableRow) {
                    while (contentHost.firstChild) {
                        nativeCol.appendChild(contentHost.firstChild);
                    }
                } else {
                    var label = contentHost.querySelector('label');
                    Array.prototype.slice.call(contentHost.childNodes).forEach(function (node) {
                        if (node !== label) {
                            nativeCol.appendChild(node);
                        }
                    });
                }

                var gridRow = document.createElement('div');
                gridRow.className = 'intelindev-translations-grid';
                gridRow.appendChild(nativeCol);

                sourceRow.querySelectorAll('.' + colClass).forEach(function (col) {
                    gridRow.appendChild(col);
                });

                contentHost.appendChild(gridRow);
                sourceRow.parentNode.removeChild(sourceRow);
            }

            relocateTranslationColumns(
                'tr.term-name-wrap, div.term-name-wrap',
                '#intelindev-name-translations-row, #intelindev-name-translations-add',
                'intelindev-name-col'
            );
            relocateTranslationColumns(
                'tr.term-slug-wrap, div.term-slug-wrap',
                '#intelindev-slug-translations-row, #intelindev-slug-translations-add',
                'intelindev-slug-col'
            );

            function toggleCategoryOrderField() {
                var parentVal = $('#parent').val();
                var isParent = !parentVal || parentVal === '0';
                $('.intelindev-category-order-wrap').toggle(isParent);
            }

            toggleCategoryOrderField();
            $(document).on('change', '#parent', toggleCategoryOrderField);
        });
    ");

    if ($hook !== 'edit-tags.php') {
        return;
    }

    $order_nonce = wp_create_nonce('intelindev_update_category_order');
    wp_add_inline_script('jquery', "
        jQuery(function($){
            var orderNonce = " . wp_json_encode($order_nonce) . ";

            function setOrderStatus(wrapper, message, isError) {
                var status = wrapper.find('.intelindev-category-order-status');
                status.text(message || '');
                status.css('color', isError ? '#b32d2e' : '#2271b1');
            }

            function saveCategoryOrder(wrapper) {
                var input = wrapper.find('.intelindev-category-order-inline-input');
                var button = wrapper.find('.intelindev-category-order-save');
                var spinner = wrapper.find('.spinner');
                var termId = parseInt(wrapper.data('termId'), 10);
                var orderVal = parseInt(input.val(), 10);

                if (Number.isNaN(orderVal) || orderVal < 0) {
                    orderVal = 0;
                    input.val(orderVal);
                }

                button.prop('disabled', true);
                spinner.addClass('is-active');
                setOrderStatus(wrapper, '');

                $.post(ajaxurl, {
                    action: 'intelindev_update_category_order',
                    nonce: orderNonce,
                    term_id: termId,
                    order: orderVal
                }).done(function(response){
                    if (response && response.success) {
                        setOrderStatus(wrapper, 'Guardado', false);
                        return;
                    }

                    var message = response && response.data && response.data.message ? response.data.message : 'No se pudo guardar.';
                    setOrderStatus(wrapper, message, true);
                }).fail(function(){
                    setOrderStatus(wrapper, 'Error de red al guardar.', true);
                }).always(function(){
                    spinner.removeClass('is-active');
                    button.prop('disabled', false);
                });
            }

            $(document).on('click', '.intelindev-category-order-save', function(e){
                e.preventDefault();
                saveCategoryOrder($(this).closest('.intelindev-category-order-inline'));
            });

            $(document).on('keydown', '.intelindev-category-order-inline-input', function(e){
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveCategoryOrder($(this).closest('.intelindev-category-order-inline'));
                }
            });
        });
    ");
}

function intelindev_add_category_order_column($columns)
{
    $columns['intelindev_category_order'] = __('Order', 'intelindev');
    return $columns;
}

function intelindev_render_category_order_column($output, $column_name, $term_id)
{
    if ($column_name !== 'intelindev_category_order') {
        return $output;
    }

    $term = get_term((int) $term_id, 'category');
    if (!$term instanceof WP_Term || (int) $term->parent !== 0) {
        return '&#8212;';
    }

    $order = get_term_meta((int) $term_id, INTELINDEV_CATEGORY_ORDER_META, true);
    $order_value = ($order === '' || $order === null) ? 0 : intval($order);

    return sprintf(
        '<div class="intelindev-category-order-inline" data-term-id="%1$d" style="display:flex; align-items:center; gap:6px;">'
        . '<input type="number" min="0" step="1" value="%2$d" class="small-text intelindev-category-order-inline-input" style="width:76px;" />'
        . '<button type="button" class="button button-small intelindev-category-order-save">%3$s</button>'
        . '<span class="spinner" style="float:none; margin:0;"></span>'
        . '<span class="intelindev-category-order-status" aria-live="polite"></span>'
        . '</div>',
        (int) $term_id,
        (int) $order_value,
        esc_html__('Guardar', 'intelindev')
    );
}

function intelindev_ajax_update_category_order(): void
{
    if (!current_user_can('manage_categories')) {
        wp_send_json_error(['message' => __('No autorizado.', 'intelindev')], 403);
    }

    check_ajax_referer('intelindev_update_category_order', 'nonce');

    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    if ($term_id <= 0) {
        wp_send_json_error(['message' => __('Categoría inválida.', 'intelindev')], 400);
    }

    $term = get_term($term_id, 'category');
    if (!$term instanceof WP_Term) {
        wp_send_json_error(['message' => __('Categoría no encontrada.', 'intelindev')], 404);
    }

    if ((int) $term->parent !== 0) {
        delete_term_meta($term_id, INTELINDEV_CATEGORY_ORDER_META);
        wp_send_json_error(['message' => __('Solo aplica para categorías padre.', 'intelindev')], 400);
    }

    $order_value = isset($_POST['order']) ? intval($_POST['order']) : 0;
    if ($order_value < 0) {
        $order_value = 0;
    }

    update_term_meta($term_id, INTELINDEV_CATEGORY_ORDER_META, $order_value);
    wp_send_json_success(['order' => $order_value]);
}

function intelindev_make_category_order_column_sortable($columns)
{
    $columns['intelindev_category_order'] = 'intelindev_category_order';
    return $columns;
}

function intelindev_handle_category_order_column_sorting($query): void
{
    if (!is_admin()) {
        return;
    }

    global $pagenow;
    if ($pagenow !== 'edit-tags.php') {
        return;
    }

    $taxonomy = isset($_GET['taxonomy']) ? sanitize_key((string) $_GET['taxonomy']) : '';
    if ($taxonomy !== 'category') {
        return;
    }

    if (($query->query_vars['orderby'] ?? '') !== 'intelindev_category_order') {
        return;
    }

    $query->query_vars['meta_key'] = INTELINDEV_CATEGORY_ORDER_META;
    $query->query_vars['orderby'] = 'meta_value_num';
}

function intelindev_hide_default_category_description_field(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->taxonomy !== 'category') {
        return;
    }
    echo '<style>
        .term-description-wrap{display:none !important;}
        .intelindev-translations-grid{display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:16px; align-items:start; margin-top:8px;}
        .intelindev-translations-col{min-width:0;}
        .intelindev-name-col label,
        .intelindev-slug-col label{display:block; font-weight:600; margin-bottom:4px;}
        .intelindev-name-col input,
        .intelindev-slug-col input{width:100%;}
        .intelindev-translations-col textarea,
        .intelindev-translations-col .large-text{width:100%; box-sizing:border-box;}
        @media(max-width:960px){
            .intelindev-translations-grid{grid-template-columns:1fr;}
        }
    </style>';
}

function intelindev_sanitize_taxonomy_rich_text($raw_value): string
{
    $value = wp_unslash((string) $raw_value);

    if (current_user_can('unfiltered_html')) {
        return $value;
    }

    return wp_kses_post($value);
}

function intelindev_render_category_translation_fields_add(): void
{
    wp_nonce_field('intelindev_category_translation_fields_save', 'intelindev_category_translation_nonce');
    ?>
    <div class="form-field intelindev-category-order-wrap">
        <label for="intelindev_category_order"><?php esc_html_e('Order', 'intelindev'); ?></label>
        <input type="number" id="intelindev_category_order" name="intelindev_category_order" min="0" step="1" value="0" />
        <p><?php esc_html_e('Solo aplica para categorías padre.', 'intelindev'); ?></p>
    </div>

    <div class="form-field intelindev-category-image-wrap">
        <label for="intelindev_category_featured_image_id"><?php esc_html_e('Imagen destacada de categoría', 'intelindev'); ?></label>
        <input type="hidden" id="intelindev_category_featured_image_id" name="intelindev_category_featured_image_id" class="intelindev-category-image-id" value="" />
        <input type="text" class="intelindev-category-image-url" value="" readonly />
        <p style="margin-top:8px; display:flex; gap:8px;">
            <button type="button" class="button intelindev-category-image-upload"><?php esc_html_e('Seleccionar imagen', 'intelindev'); ?></button>
            <button type="button" class="button intelindev-category-image-remove"><?php esc_html_e('Eliminar', 'intelindev'); ?></button>
        </p>
        <img src="" alt="" class="intelindev-category-image-preview" style="max-width:96px; height:auto; border-radius:6px; display:none;" />
    </div>

    <div class="form-field" id="intelindev-name-translations-add">
        <div class="intelindev-name-col">
            <label for="intelindev_category_name_en"><?php esc_html_e('Name EN', 'intelindev'); ?></label>
            <input type="text" id="intelindev_category_name_en" name="intelindev_category_name[en]" class="regular-text" value="" />
        </div>
        <div class="intelindev-name-col">
            <label for="intelindev_category_name_pt"><?php esc_html_e('Name PT', 'intelindev'); ?></label>
            <input type="text" id="intelindev_category_name_pt" name="intelindev_category_name[pt]" class="regular-text" value="" />
        </div>
    </div>

    <div class="form-field" id="intelindev-slug-translations-add">
        <div class="intelindev-slug-col">
            <label for="intelindev_category_slug_en"><?php esc_html_e('Slug EN', 'intelindev'); ?></label>
            <input type="text" id="intelindev_category_slug_en" name="intelindev_category_slug[en]" class="regular-text" value="" />
        </div>
        <div class="intelindev-slug-col">
            <label for="intelindev_category_slug_pt"><?php esc_html_e('Slug PT', 'intelindev'); ?></label>
            <input type="text" id="intelindev_category_slug_pt" name="intelindev_category_slug[pt]" class="regular-text" value="" />
        </div>
    </div>

    <div class="form-field">
        <label><strong><?php esc_html_e('Contenido traducido de la categoría', 'intelindev'); ?></strong></label>
        <div class="intelindev-translations-grid">
            <div class="intelindev-translations-col">
                <label for="intelindev_category_content_es"><?php esc_html_e('Content ES', 'intelindev'); ?></label>
                <textarea id="intelindev_category_content_es" name="intelindev_category_content[es]" rows="6"></textarea>

                <label for="intelindev_category_excerpt_es" style="margin-top:10px; display:block;"><?php esc_html_e('Excerpt ES', 'intelindev'); ?></label>
                <textarea id="intelindev_category_excerpt_es" name="intelindev_category_excerpt[es]" rows="4"></textarea>
            </div>

            <div class="intelindev-translations-col">
                <label for="intelindev_category_content_en"><?php esc_html_e('Content EN', 'intelindev'); ?></label>
                <textarea id="intelindev_category_content_en" name="intelindev_category_content[en]" rows="6"></textarea>

                <label for="intelindev_category_excerpt_en" style="margin-top:10px; display:block;"><?php esc_html_e('Excerpt EN', 'intelindev'); ?></label>
                <textarea id="intelindev_category_excerpt_en" name="intelindev_category_excerpt[en]" rows="4"></textarea>
            </div>

            <div class="intelindev-translations-col">
                <label for="intelindev_category_content_pt"><?php esc_html_e('Content PT', 'intelindev'); ?></label>
                <textarea id="intelindev_category_content_pt" name="intelindev_category_content[pt]" rows="6"></textarea>

                <label for="intelindev_category_excerpt_pt" style="margin-top:10px; display:block;"><?php esc_html_e('Excerpt PT', 'intelindev'); ?></label>
                <textarea id="intelindev_category_excerpt_pt" name="intelindev_category_excerpt[pt]" rows="4"></textarea>
            </div>
        </div>
    </div>
    <?php
}

function intelindev_render_category_translation_fields_edit(WP_Term $term): void
{
    wp_nonce_field('intelindev_category_translation_fields_save', 'intelindev_category_translation_nonce');

    $content = get_term_meta($term->term_id, INTELINDEV_CATEGORY_TRANSLATED_CONTENT_META, true);
    $excerpt = get_term_meta($term->term_id, INTELINDEV_CATEGORY_TRANSLATED_EXCERPT_META, true);
    $featured_image_id = (int) get_term_meta($term->term_id, INTELINDEV_CATEGORY_FEATURED_IMAGE_ID_META, true);
    $featured_image_url = $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'thumbnail') : '';

    $content = is_array($content) ? $content : [];
    $excerpt = is_array($excerpt) ? $excerpt : [];

    $content_es = (string) ($content['es'] ?? '');
    $content_en = (string) ($content['en'] ?? '');
    $content_pt = (string) ($content['pt'] ?? '');
    $excerpt_es = (string) ($excerpt['es'] ?? '');
    $excerpt_en = (string) ($excerpt['en'] ?? '');
    $excerpt_pt = (string) ($excerpt['pt'] ?? '');
    $order_value = (int) get_term_meta($term->term_id, INTELINDEV_CATEGORY_ORDER_META, true);

    $name_translations = get_term_meta($term->term_id, INTELINDEV_CATEGORY_TRANSLATED_NAME_META, true);
    $name_translations = is_array($name_translations) ? $name_translations : [];
    $name_en = (string) ($name_translations['en'] ?? '');
    $name_pt = (string) ($name_translations['pt'] ?? '');

    $slug_en = (string) get_term_meta($term->term_id, intelindev_category_translated_slug_meta_key('en'), true);
    $slug_pt = (string) get_term_meta($term->term_id, intelindev_category_translated_slug_meta_key('pt'), true);
    ?>
    <?php if ((int) $term->parent === 0): ?>
    <tr class="form-field intelindev-category-order-wrap">
        <th scope="row"><label for="intelindev_category_order"><?php esc_html_e('Order', 'intelindev'); ?></label></th>
        <td>
            <input type="number" id="intelindev_category_order" name="intelindev_category_order" min="0" step="1" value="<?php echo esc_attr($order_value); ?>" />
            <p class="description"><?php esc_html_e('Solo aplica para categorías padre.', 'intelindev'); ?></p>
        </td>
    </tr>
    <?php endif; ?>

    <tr class="form-field intelindev-category-image-wrap">
        <th scope="row"><label for="intelindev_category_featured_image_id"><?php esc_html_e('Imagen destacada de categoría', 'intelindev'); ?></label></th>
        <td>
            <input type="hidden" id="intelindev_category_featured_image_id" name="intelindev_category_featured_image_id" class="intelindev-category-image-id" value="<?php echo esc_attr($featured_image_id); ?>" />
            <input type="text" class="intelindev-category-image-url large-text" value="<?php echo esc_url($featured_image_url); ?>" readonly />
            <p style="margin-top:8px; display:flex; gap:8px;">
                <button type="button" class="button intelindev-category-image-upload"><?php esc_html_e('Seleccionar imagen', 'intelindev'); ?></button>
                <button type="button" class="button intelindev-category-image-remove"><?php esc_html_e('Eliminar', 'intelindev'); ?></button>
            </p>
            <img src="<?php echo esc_url($featured_image_url); ?>" alt="" class="intelindev-category-image-preview" style="max-width:96px; height:auto; border-radius:6px; <?php echo $featured_image_url ? '' : 'display:none;'; ?>" />
        </td>
    </tr>

    <tr class="form-field" id="intelindev-name-translations-row">
        <th scope="row"><?php esc_html_e('Nombre traducido', 'intelindev'); ?></th>
        <td>
            <div class="intelindev-name-col">
                <label for="intelindev_category_name_en"><strong><?php esc_html_e('Name EN', 'intelindev'); ?></strong></label>
                <input type="text" id="intelindev_category_name_en" name="intelindev_category_name[en]" class="regular-text" value="<?php echo esc_attr($name_en); ?>" />
            </div>
            <div class="intelindev-name-col">
                <label for="intelindev_category_name_pt"><strong><?php esc_html_e('Name PT', 'intelindev'); ?></strong></label>
                <input type="text" id="intelindev_category_name_pt" name="intelindev_category_name[pt]" class="regular-text" value="<?php echo esc_attr($name_pt); ?>" />
            </div>
        </td>
    </tr>

    <tr class="form-field" id="intelindev-slug-translations-row">
        <th scope="row"><?php esc_html_e('Slug traducido', 'intelindev'); ?></th>
        <td>
            <div class="intelindev-slug-col">
                <label for="intelindev_category_slug_en"><strong><?php esc_html_e('Slug EN', 'intelindev'); ?></strong></label>
                <input type="text" id="intelindev_category_slug_en" name="intelindev_category_slug[en]" class="regular-text" value="<?php echo esc_attr($slug_en); ?>" />
            </div>
            <div class="intelindev-slug-col">
                <label for="intelindev_category_slug_pt"><strong><?php esc_html_e('Slug PT', 'intelindev'); ?></strong></label>
                <input type="text" id="intelindev_category_slug_pt" name="intelindev_category_slug[pt]" class="regular-text" value="<?php echo esc_attr($slug_pt); ?>" />
            </div>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"><?php esc_html_e('Contenido traducido', 'intelindev'); ?></th>
        <td>
            <div class="intelindev-translations-grid">
                <div class="intelindev-translations-col">
                    <label for="intelindev_category_content_es"><strong><?php esc_html_e('Content ES', 'intelindev'); ?></strong></label>
                    <textarea id="intelindev_category_content_es" name="intelindev_category_content[es]" rows="6" class="large-text"><?php echo esc_textarea($content_es); ?></textarea>

                    <label for="intelindev_category_excerpt_es" style="margin-top:10px; display:block;"><strong><?php esc_html_e('Excerpt ES', 'intelindev'); ?></strong></label>
                    <textarea id="intelindev_category_excerpt_es" name="intelindev_category_excerpt[es]" rows="4" class="large-text"><?php echo esc_textarea($excerpt_es); ?></textarea>
                </div>

                <div class="intelindev-translations-col">
                    <label for="intelindev_category_content_en"><strong><?php esc_html_e('Content EN', 'intelindev'); ?></strong></label>
                    <textarea id="intelindev_category_content_en" name="intelindev_category_content[en]" rows="6" class="large-text"><?php echo esc_textarea($content_en); ?></textarea>

                    <label for="intelindev_category_excerpt_en" style="margin-top:10px; display:block;"><strong><?php esc_html_e('Excerpt EN', 'intelindev'); ?></strong></label>
                    <textarea id="intelindev_category_excerpt_en" name="intelindev_category_excerpt[en]" rows="4" class="large-text"><?php echo esc_textarea($excerpt_en); ?></textarea>
                </div>

                <div class="intelindev-translations-col">
                    <label for="intelindev_category_content_pt"><strong><?php esc_html_e('Content PT', 'intelindev'); ?></strong></label>
                    <textarea id="intelindev_category_content_pt" name="intelindev_category_content[pt]" rows="6" class="large-text"><?php echo esc_textarea($content_pt); ?></textarea>

                    <label for="intelindev_category_excerpt_pt" style="margin-top:10px; display:block;"><strong><?php esc_html_e('Excerpt PT', 'intelindev'); ?></strong></label>
                    <textarea id="intelindev_category_excerpt_pt" name="intelindev_category_excerpt[pt]" rows="4" class="large-text"><?php echo esc_textarea($excerpt_pt); ?></textarea>
                </div>
            </div>
        </td>
    </tr>
    <?php
}

function intelindev_save_category_translation_fields($term_id): void
{
    if (!current_user_can('manage_categories')) {
        return;
    }

    if (!isset($_POST['intelindev_category_translation_nonce']) || !wp_verify_nonce((string) $_POST['intelindev_category_translation_nonce'], 'intelindev_category_translation_fields_save')) {
        return;
    }

    $term = get_term((int) $term_id, 'category');
    if ($term instanceof WP_Term && (int) $term->parent === 0) {
        $order_value = isset($_POST['intelindev_category_order']) ? intval($_POST['intelindev_category_order']) : 0;
        if ($order_value < 0) {
            $order_value = 0;
        }
        update_term_meta($term_id, INTELINDEV_CATEGORY_ORDER_META, $order_value);
    } else {
        delete_term_meta($term_id, INTELINDEV_CATEGORY_ORDER_META);
    }

    $featured_image_id = isset($_POST['intelindev_category_featured_image_id']) ? (int) $_POST['intelindev_category_featured_image_id'] : 0;
    if ($featured_image_id > 0 && 'attachment' !== get_post_type($featured_image_id)) {
        $featured_image_id = 0;
    }

    if ($featured_image_id > 0) {
        update_term_meta($term_id, INTELINDEV_CATEGORY_FEATURED_IMAGE_ID_META, $featured_image_id);
    } else {
        delete_term_meta($term_id, INTELINDEV_CATEGORY_FEATURED_IMAGE_ID_META);
    }

    $submitted_name = isset($_POST['intelindev_category_name']) && is_array($_POST['intelindev_category_name'])
        ? $_POST['intelindev_category_name']
        : [];

    $sanitized_name = [
        'en' => sanitize_text_field(wp_unslash((string) ($submitted_name['en'] ?? ''))),
        'pt' => sanitize_text_field(wp_unslash((string) ($submitted_name['pt'] ?? ''))),
    ];

    if ($sanitized_name['en'] === '' && $sanitized_name['pt'] === '') {
        delete_term_meta($term_id, INTELINDEV_CATEGORY_TRANSLATED_NAME_META);
    } else {
        update_term_meta($term_id, INTELINDEV_CATEGORY_TRANSLATED_NAME_META, $sanitized_name);
    }

    $submitted_slug = isset($_POST['intelindev_category_slug']) && is_array($_POST['intelindev_category_slug'])
        ? $_POST['intelindev_category_slug']
        : [];

    foreach (['en', 'pt'] as $slug_lang) {
        $slug_value = sanitize_title(wp_unslash((string) ($submitted_slug[$slug_lang] ?? '')));
        $meta_key = intelindev_category_translated_slug_meta_key($slug_lang);

        if ($slug_value === '') {
            delete_term_meta($term_id, $meta_key);
        } else {
            update_term_meta($term_id, $meta_key, $slug_value);
        }
    }

    $submitted_content = isset($_POST['intelindev_category_content']) && is_array($_POST['intelindev_category_content'])
        ? $_POST['intelindev_category_content']
        : [];

    $submitted_excerpt = isset($_POST['intelindev_category_excerpt']) && is_array($_POST['intelindev_category_excerpt'])
        ? $_POST['intelindev_category_excerpt']
        : [];

    $sanitized_content = [
        'es' => intelindev_sanitize_taxonomy_rich_text($submitted_content['es'] ?? ''),
        'en' => intelindev_sanitize_taxonomy_rich_text($submitted_content['en'] ?? ''),
        'pt' => intelindev_sanitize_taxonomy_rich_text($submitted_content['pt'] ?? ''),
    ];

    $sanitized_excerpt = [
        'es' => intelindev_sanitize_taxonomy_rich_text($submitted_excerpt['es'] ?? ''),
        'en' => intelindev_sanitize_taxonomy_rich_text($submitted_excerpt['en'] ?? ''),
        'pt' => intelindev_sanitize_taxonomy_rich_text($submitted_excerpt['pt'] ?? ''),
    ];

    if ($sanitized_content['es'] === '' && $sanitized_content['en'] === '' && $sanitized_content['pt'] === '') {
        delete_term_meta($term_id, INTELINDEV_CATEGORY_TRANSLATED_CONTENT_META);
    } else {
        update_term_meta($term_id, INTELINDEV_CATEGORY_TRANSLATED_CONTENT_META, $sanitized_content);
    }

    if ($sanitized_excerpt['es'] === '' && $sanitized_excerpt['en'] === '' && $sanitized_excerpt['pt'] === '') {
        delete_term_meta($term_id, INTELINDEV_CATEGORY_TRANSLATED_EXCERPT_META);
        return;
    }

    update_term_meta($term_id, INTELINDEV_CATEGORY_TRANSLATED_EXCERPT_META, $sanitized_excerpt);
}
