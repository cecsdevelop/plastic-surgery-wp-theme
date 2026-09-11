<?php
/**
 * Regular Posts Translation Fields
 *
 * @package intelindev
 */

if (!defined('ABSPATH')) {
    exit;
}

const INTELINDEV_POST_TRANSLATED_CONTENT_META = '_intelindev_post_translated_content';
const INTELINDEV_POST_TRANSLATED_EXCERPT_META = '_intelindev_post_translated_excerpt';
const INTELINDEV_POST_TRANSLATED_TITLE_META = '_intelindev_post_translated_title';
const INTELINDEV_POST_FEATURED_META = '_intelindev_post_is_featured';

function intelindev_is_post_featured(WP_Post $post): bool
{
    return (bool) get_post_meta($post->ID, INTELINDEV_POST_FEATURED_META, true);
}

/**
 * El post_title nativo es el título en el idioma por defecto (es).
 * Esta meta solo guarda los overrides para los demás idiomas.
 *
 * Usa $post->post_title (crudo) en vez de get_the_title() para no disparar
 * el filtro 'the_title' que enganchamos abajo y causar recursión infinita.
 */
function intelindev_get_post_translated_title(WP_Post $post, $lang = null): string
{
    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default_lang = idml_get_default_language();

    if ($lang === '' || $lang === $default_lang) {
        return $post->post_title;
    }

    $translations = get_post_meta($post->ID, INTELINDEV_POST_TRANSLATED_TITLE_META, true);
    $translations = is_array($translations) ? $translations : [];
    $value = (string) ($translations[$lang] ?? '');

    return $value !== '' ? $value : $post->post_title;
}

add_filter('the_title', function ($title, $post_id = 0) {
    if (is_admin() || !$post_id || get_post_type($post_id) !== 'post') {
        return $title;
    }

    $post = get_post($post_id);
    if (!($post instanceof WP_Post)) {
        return $title;
    }

    return intelindev_get_post_translated_title($post);
}, 10, 2);

/**
 * El contenido del post es un repeater de bloques HTML crudos (uno por idioma).
 * Compat: versiones previas guardaban un solo string por idioma; se normaliza
 * a un array de 1 bloque para no perder contenido ya cargado.
 */
function intelindev_normalize_post_content_blocks($value): array
{
    if (is_array($value)) {
        $blocks = $value;
    } elseif (is_string($value) && trim($value) !== '') {
        $blocks = [$value];
    } else {
        return [];
    }

    $normalized = [];
    foreach ($blocks as $block) {
        $block = trim((string) $block);
        if ($block === '') {
            continue;
        }
        $normalized[] = $block;
    }

    return $normalized;
}

function intelindev_get_post_translated_content_blocks(WP_Post $post, $lang = null): array
{
    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default_lang = idml_get_default_language();

    $translations = get_post_meta($post->ID, INTELINDEV_POST_TRANSLATED_CONTENT_META, true);
    $translations = is_array($translations) ? $translations : [];

    $blocks = $lang !== '' ? intelindev_normalize_post_content_blocks($translations[$lang] ?? []) : [];
    if (!empty($blocks)) {
        return $blocks;
    }

    return intelindev_normalize_post_content_blocks($translations[$default_lang] ?? []);
}

/**
 * El excerpt traducido sigue siendo un solo campo de texto por idioma.
 */
function intelindev_get_post_translated_excerpt(WP_Post $post, $lang = null): string
{
    return intelindev_get_post_translated_field($post, INTELINDEV_POST_TRANSLATED_EXCERPT_META, $lang);
}

function intelindev_get_post_translated_field(WP_Post $post, string $meta_key, $lang = null): string
{
    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default_lang = idml_get_default_language();

    $translations = get_post_meta($post->ID, $meta_key, true);
    $translations = is_array($translations) ? $translations : [];

    if ($lang !== '' && trim((string) ($translations[$lang] ?? '')) !== '') {
        return (string) $translations[$lang];
    }

    return (string) ($translations[$default_lang] ?? '');
}

/**
 * El post_name nativo es el slug en el idioma por defecto (es).
 * Esta meta solo guarda los overrides de slug para los demás idiomas.
 */
function intelindev_post_translated_slug_meta_key(string $lang): string
{
    return '_intelindev_post_translated_slug_' . sanitize_key($lang);
}

function intelindev_get_post_slug_for_lang(WP_Post $post, string $lang): string
{
    $lang = idml_normalize_lang($lang);
    $default_lang = idml_get_default_language();

    if ($lang === '' || $lang === $default_lang) {
        return sanitize_title($post->post_name);
    }

    $translated = sanitize_title((string) get_post_meta($post->ID, intelindev_post_translated_slug_meta_key($lang), true));

    return $translated !== '' ? $translated : sanitize_title($post->post_name);
}

function intelindev_get_post_id_by_translated_slug(string $slug, string $lang): int
{
    $slug = sanitize_title($slug);
    $lang = idml_normalize_lang($lang);
    $default_lang = idml_get_default_language();

    if ($slug === '' || $lang === '' || $lang === $default_lang) {
        return 0;
    }

    $ids = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => intelindev_post_translated_slug_meta_key($lang),
        'meta_value'     => $slug,
    ]);

    return !empty($ids) ? (int) $ids[0] : 0;
}

/**
 * Cuando se navega en un idioma no-default, hace que get_permalink()/the_permalink()
 * del post apunten a /{lang}/{slug-traducido-o-nativo}/ en vez del slug en español.
 */
add_filter('post_link', function ($url, $post) {
    if (is_admin() || !($post instanceof WP_Post) || $post->post_type !== 'post') {
        return $url;
    }

    $lang = idml_get_current_language();
    $default_lang = idml_get_default_language();

    if ($lang === '' || $lang === $default_lang) {
        return $url;
    }

    $slug = intelindev_get_post_slug_for_lang($post, $lang);
    if ($slug === '') {
        return $url;
    }

    return home_url('/' . rawurlencode($lang) . '/' . rawurlencode($slug) . '/');
}, 10, 2);

add_action('add_meta_boxes', 'intelindev_add_post_translation_metabox');
add_action('add_meta_boxes', 'intelindev_remove_default_post_excerpt_metabox', 20);
add_action('init', 'intelindev_remove_default_post_content_editor');

function intelindev_remove_default_post_content_editor(): void
{
    remove_post_type_support('post', 'editor');
}

function intelindev_remove_default_post_excerpt_metabox(): void
{
    remove_meta_box('postexcerpt', 'post', 'normal');
}

function intelindev_sanitize_translated_rich_text($raw_value): string
{
    $value = wp_unslash((string) $raw_value);

    if (current_user_can('unfiltered_html')) {
        return $value;
    }

    return wp_kses_post($value);
}

function intelindev_add_post_translation_metabox(): void
{
    add_meta_box(
        'intelindev_post_translation_content',
        __('Contenido traducido del post', 'intelindev'),
        'intelindev_render_post_translation_metabox',
        'post',
        'normal',
        'high'
    );
}

function intelindev_render_post_translation_metabox($post): void
{
    if (!($post instanceof WP_Post)) {
        return;
    }

    wp_nonce_field('intelindev_post_translation_content_save', 'intelindev_post_translation_nonce');

    $content = get_post_meta($post->ID, INTELINDEV_POST_TRANSLATED_CONTENT_META, true);
    $content = is_array($content) ? $content : [];
    $excerpt = get_post_meta($post->ID, INTELINDEV_POST_TRANSLATED_EXCERPT_META, true);
    $excerpt = is_array($excerpt) ? $excerpt : [];
    $is_featured = intelindev_is_post_featured($post);

    $title_translations = get_post_meta($post->ID, INTELINDEV_POST_TRANSLATED_TITLE_META, true);
    $title_translations = is_array($title_translations) ? $title_translations : [];
    $title_en = (string) ($title_translations['en'] ?? '');
    $title_pt = (string) ($title_translations['pt'] ?? '');

    $slug_en = (string) get_post_meta($post->ID, intelindev_post_translated_slug_meta_key('en'), true);
    $slug_pt = (string) get_post_meta($post->ID, intelindev_post_translated_slug_meta_key('pt'), true);

    $title_by_lang = ['en' => $title_en, 'pt' => $title_pt];
    $slug_by_lang = ['en' => $slug_en, 'pt' => $slug_pt];

    \IntelindevInit\General\MultilanguageTabsRenderer::render('intelindev_post_translation', function ($lang_code, $lang_label) use ($content, $excerpt, $title_by_lang, $slug_by_lang) {
        if ($lang_code === 'es') {
            ?>
            <p class="description"><?php esc_html_e('El título y el slug nativos del post (arriba, en la edición principal) se usan como título y slug en español.', 'intelindev'); ?></p>
            <?php
        } else {
            ?>
            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px;">
                <div style="flex:1 1 300px; min-width:240px;">
                    <label for="intelindev_post_title_<?php echo esc_attr($lang_code); ?>"><strong><?php echo esc_html(sprintf(__('Título %s', 'intelindev'), $lang_label)); ?></strong></label>
                    <input type="text" id="intelindev_post_title_<?php echo esc_attr($lang_code); ?>" name="intelindev_post_title[<?php echo esc_attr($lang_code); ?>]" class="widefat" value="<?php echo esc_attr($title_by_lang[$lang_code] ?? ''); ?>" />
                </div>
                <div style="flex:1 1 300px; min-width:240px;">
                    <label for="intelindev_post_slug_<?php echo esc_attr($lang_code); ?>"><strong><?php echo esc_html(sprintf(__('Slug %s', 'intelindev'), $lang_label)); ?></strong></label>
                    <input type="text" id="intelindev_post_slug_<?php echo esc_attr($lang_code); ?>" name="intelindev_post_slug[<?php echo esc_attr($lang_code); ?>]" class="widefat" value="<?php echo esc_attr($slug_by_lang[$lang_code] ?? ''); ?>" />
                    <span class="description"><?php esc_html_e('Deja en blanco para usar el slug nativo. Cambia la URL a /en|pt/slug/.', 'intelindev'); ?></span>
                </div>
            </div>
            <?php
        }

        $content_blocks = intelindev_normalize_post_content_blocks($content[$lang_code] ?? []);
        if (empty($content_blocks)) {
            $content_blocks = [''];
        }
        $excerpt_val = (string) ($excerpt[$lang_code] ?? '');
        $container_id = 'intelindev-post-content-blocks-' . $lang_code;
        $template_id = 'intelindev-post-content-block-template-' . $lang_code;
        ?>
        <p>
            <label><strong><?php echo esc_html(sprintf(__('Content %s (bloques HTML)', 'intelindev'), $lang_label)); ?></strong></label>
        </p>
        <div id="<?php echo esc_attr($container_id); ?>" class="intelindev-content-blocks">
            <?php foreach ($content_blocks as $index => $block): ?>
                <div class="intelindev-content-block" style="margin-bottom:12px;padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:6px;">
                        <strong class="intelindev-content-block-label"><?php echo esc_html(sprintf(__('Bloque %d', 'intelindev'), $index + 1)); ?></strong>
                        <button type="button" class="button-link-delete intelindev-content-block-remove"><?php esc_html_e('Eliminar bloque', 'intelindev'); ?></button>
                    </div>
                    <textarea
                        name="intelindev_post_content_blocks[<?php echo esc_attr($lang_code); ?>][]"
                        rows="10"
                        style="width:100%;min-height:200px;font-family:monospace;"
                    ><?php echo esc_textarea($block); ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary intelindev-content-block-add" data-target="<?php echo esc_attr($container_id); ?>" data-template="<?php echo esc_attr($template_id); ?>"><?php esc_html_e('Agregar bloque', 'intelindev'); ?></button>
        </p>
        <p class="description"><?php esc_html_e('Cada bloque acepta HTML. Se renderizan en el orden en que aparecen aquí. No se ejecuta PHP.', 'intelindev'); ?></p>
        <template id="<?php echo esc_attr($template_id); ?>">
            <div class="intelindev-content-block" style="margin-bottom:12px;padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:6px;">
                    <strong class="intelindev-content-block-label"><?php esc_html_e('Bloque nuevo', 'intelindev'); ?></strong>
                    <button type="button" class="button-link-delete intelindev-content-block-remove"><?php esc_html_e('Eliminar bloque', 'intelindev'); ?></button>
                </div>
                <textarea name="intelindev_post_content_blocks[<?php echo esc_attr($lang_code); ?>][]" rows="10" style="width:100%;min-height:200px;font-family:monospace;"></textarea>
            </div>
        </template>

        <p style="margin-top:12px;">
            <label for="intelindev_post_excerpt_<?php echo esc_attr($lang_code); ?>"><strong><?php echo esc_html(sprintf(__('Excerpt %s', 'intelindev'), $lang_label)); ?></strong></label>
            <textarea
                id="intelindev_post_excerpt_<?php echo esc_attr($lang_code); ?>"
                name="intelindev_post_excerpt[<?php echo esc_attr($lang_code); ?>]"
                rows="4"
                class="widefat"
            ><?php echo esc_textarea($excerpt_val); ?></textarea>
        </p>
        <?php
    });
    ?>
    <script>
    (function () {
        function refreshLabels(container) {
            container.querySelectorAll('.intelindev-content-block').forEach(function (block, index) {
                var label = block.querySelector('.intelindev-content-block-label');
                if (label) {
                    label.textContent = '<?php echo esc_js(__('Bloque', 'intelindev')); ?> ' + (index + 1);
                }
            });
        }

        document.addEventListener('click', function (e) {
            var addBtn = e.target.closest('.intelindev-content-block-add');
            if (addBtn) {
                var container = document.getElementById(addBtn.getAttribute('data-target'));
                var template = document.getElementById(addBtn.getAttribute('data-template'));
                if (!container || !template) {
                    return;
                }
                var wrapper = document.createElement('div');
                wrapper.innerHTML = template.innerHTML.trim();
                var block = wrapper.firstElementChild;
                if (block) {
                    container.appendChild(block);
                    refreshLabels(container);
                }
                return;
            }

            var removeBtn = e.target.closest('.intelindev-content-block-remove');
            if (removeBtn) {
                var removedBlock = removeBtn.closest('.intelindev-content-block');
                var removedContainer = removedBlock ? removedBlock.closest('.intelindev-content-blocks') : null;
                if (removedBlock) {
                    removedBlock.remove();
                }
                if (removedContainer) {
                    refreshLabels(removedContainer);
                }
            }
        });
    })();
    </script>

    <p>
        <label for="intelindev_post_is_featured">
            <input type="checkbox" id="intelindev_post_is_featured" name="intelindev_post_is_featured" value="1" <?php checked($is_featured); ?> />
            <strong><?php esc_html_e('Post destacado (aparece en el bloque de destacados del blog)', 'intelindev'); ?></strong>
        </label>
    </p>

    </p>
    <?php
}

add_action('save_post_post', 'intelindev_save_post_translation_metabox');

function intelindev_save_post_translation_metabox($post_id): void
{
    if (!isset($_POST['intelindev_post_translation_nonce']) || !wp_verify_nonce((string) $_POST['intelindev_post_translation_nonce'], 'intelindev_post_translation_content_save')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $is_featured = isset($_POST['intelindev_post_is_featured']) && $_POST['intelindev_post_is_featured'] === '1';
    if ($is_featured) {
        update_post_meta($post_id, INTELINDEV_POST_FEATURED_META, '1');
    } else {
        delete_post_meta($post_id, INTELINDEV_POST_FEATURED_META);
    }


    $submitted_slug = isset($_POST['intelindev_post_slug']) && is_array($_POST['intelindev_post_slug'])
        ? $_POST['intelindev_post_slug']
        : [];

    foreach (['en', 'pt'] as $slug_lang) {
        $slug_value = sanitize_title(wp_unslash((string) ($submitted_slug[$slug_lang] ?? '')));
        $meta_key = intelindev_post_translated_slug_meta_key($slug_lang);

        if ($slug_value === '') {
            delete_post_meta($post_id, $meta_key);
        } else {
            update_post_meta($post_id, $meta_key, $slug_value);
        }
    }

    $submitted_title = isset($_POST['intelindev_post_title']) && is_array($_POST['intelindev_post_title'])
        ? $_POST['intelindev_post_title']
        : [];

    $sanitized_title = [
        'en' => sanitize_text_field(wp_unslash((string) ($submitted_title['en'] ?? ''))),
        'pt' => sanitize_text_field(wp_unslash((string) ($submitted_title['pt'] ?? ''))),
    ];

    if ($sanitized_title['en'] === '' && $sanitized_title['pt'] === '') {
        delete_post_meta($post_id, INTELINDEV_POST_TRANSLATED_TITLE_META);
    } else {
        update_post_meta($post_id, INTELINDEV_POST_TRANSLATED_TITLE_META, $sanitized_title);
    }

    $submitted_blocks = isset($_POST['intelindev_post_content_blocks']) && is_array($_POST['intelindev_post_content_blocks'])
        ? $_POST['intelindev_post_content_blocks']
        : [];
    $submitted_excerpt = isset($_POST['intelindev_post_excerpt']) && is_array($_POST['intelindev_post_excerpt'])
        ? $_POST['intelindev_post_excerpt']
        : [];

    $sanitized_content = [];
    $sanitized_excerpt = [];
    $has_content = false;
    $has_excerpt = false;

    foreach (\IntelindevInit\General\MultilanguageTabsRenderer::get_languages() as $lang_code => $lang_label) {
        $raw_blocks = isset($submitted_blocks[$lang_code]) && is_array($submitted_blocks[$lang_code])
            ? $submitted_blocks[$lang_code]
            : [];

        $blocks = [];
        foreach ($raw_blocks as $raw_block) {
            $block = intelindev_sanitize_translated_rich_text($raw_block);
            if ($block === '') {
                continue;
            }
            $blocks[] = $block;
        }

        $sanitized_content[$lang_code] = $blocks;
        $sanitized_excerpt[$lang_code] = intelindev_sanitize_translated_rich_text($submitted_excerpt[$lang_code] ?? '');

        if (!empty($blocks)) {
            $has_content = true;
        }
        if ($sanitized_excerpt[$lang_code] !== '') {
            $has_excerpt = true;
        }
    }

    if (!$has_content) {
        delete_post_meta($post_id, INTELINDEV_POST_TRANSLATED_CONTENT_META);
    } else {
        update_post_meta($post_id, INTELINDEV_POST_TRANSLATED_CONTENT_META, $sanitized_content);
    }

    if (!$has_excerpt) {
        delete_post_meta($post_id, INTELINDEV_POST_TRANSLATED_EXCERPT_META);
        return;
    }

    update_post_meta($post_id, INTELINDEV_POST_TRANSLATED_EXCERPT_META, $sanitized_excerpt);
}

