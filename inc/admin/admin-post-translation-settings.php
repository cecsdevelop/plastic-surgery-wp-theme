<?php
/**
 * Contenido traducido de posts, pages y CPTs del theme.
 *
 * El post_title/post_name nativos son el idioma por defecto; el resto de
 * idiomas vive en meta (título, slug, bloques HTML de contenido, excerpt) y se
 * resuelve con filtros (the_title, the_content, post_link/page_link/
 * post_type_link). Los tipos cubiertos salen de
 * pswpt_translatable_post_types(): 'post' y 'page' más lo que agreguen
 * los módulos CPT por el filtro 'pswpt_translatable_post_types'. Los
 * CPT públicos declaran además su base de URL por idioma
 * ('pswpt_post_type_lang_slugs') para armar /{lang}/{base}/{slug}/.
 *
 * @package pswpt
 */

if (!defined('ABSPATH')) {
    exit;
}

const pswpt_POST_TRANSLATED_CONTENT_META = '_pswpt_post_translated_content';
const pswpt_POST_TRANSLATED_EXCERPT_META = '_pswpt_post_translated_excerpt';
const pswpt_POST_TRANSLATED_TITLE_META = '_pswpt_post_translated_title';
const pswpt_POST_FEATURED_META = '_pswpt_post_is_featured';

/**
 * Tipos cuyo título/contenido/excerpt (y slug si son públicos) se traducen
 * con el metabox "Contenido traducido". Los módulos CPT se suman por filtro.
 */
function pswpt_translatable_post_types(): array
{
    $types = apply_filters('pswpt_translatable_post_types', ['post', 'page']);

    return array_values(array_unique(array_filter(array_map('strval', (array) $types))));
}

/** Tipos traducibles con URL propia (los que llevan slug por idioma). */
function pswpt_public_translatable_post_types(): array
{
    return array_values(array_filter(pswpt_translatable_post_types(), 'is_post_type_viewable'));
}

/** Idiomas que llevan campos de traducción: todos menos el por defecto. */
function pswpt_translation_languages(): array
{
    return array_values(array_diff(idml_get_languages(), [idml_get_default_language()]));
}

/**
 * Base de URL por idioma de un CPT público ([post_type => [lang => base]],
 * ej. pswpt_service => [es => servicios, en => services]). Para el idioma
 * por defecto se usa el slug de rewrite nativo; para los demás, lo declarado
 * por el módulo o, si falta, el nativo.
 */
function pswpt_post_type_lang_slug(string $post_type, string $lang): string
{
    $lang = idml_normalize_lang($lang);
    $map  = apply_filters('pswpt_post_type_lang_slugs', []);
    $base = (string) ($map[$post_type][$lang] ?? '');
    if ($base !== '' && $lang !== idml_get_default_language()) {
        return sanitize_title($base);
    }

    $object = get_post_type_object($post_type);
    if (!$object) {
        return '';
    }
    $native = is_array($object->rewrite) && !empty($object->rewrite['slug']) ? $object->rewrite['slug'] : $post_type;

    return sanitize_title((string) $native);
}

/** Tipo de post cuya base de URL en $lang es $base, o '' si ninguno. */
function pswpt_post_type_by_lang_slug(string $base, string $lang): string
{
    $base = sanitize_title($base);
    foreach (pswpt_public_translatable_post_types() as $type) {
        if (in_array($type, ['post', 'page'], true)) {
            continue;
        }
        if ($base !== '' && pswpt_post_type_lang_slug($type, $lang) === $base) {
            return $type;
        }
    }

    return '';
}

/** URL del archivo de un CPT en un idioma (/{lang}/{base}/ o la nativa en el default). */
function pswpt_get_post_type_archive_url(string $post_type, $lang = null): string
{
    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    if ($lang === '' || $lang === idml_get_default_language()) {
        return (string) get_post_type_archive_link($post_type);
    }
    $base = pswpt_post_type_lang_slug($post_type, $lang);

    return $base !== '' ? home_url('/' . rawurlencode($lang) . '/' . rawurlencode($base) . '/') : idml_get_language_home_url($lang);
}

/** Etiqueta (nombre plural) de un CPT por idioma, declarada por el módulo; cae al label nativo. */
add_filter('post_type_archive_title', function ($title, $post_type = '') {
    $lang = idml_get_current_language();
    $map  = apply_filters('pswpt_post_type_lang_labels', []);
    $label = (string) ($map[$post_type][$lang] ?? '');

    return $label !== '' ? $label : $title;
}, 10, 2);

function pswpt_is_post_featured(WP_Post $post): bool
{
    return (bool) get_post_meta($post->ID, pswpt_POST_FEATURED_META, true);
}

/**
 * El post_title nativo es el título en el idioma por defecto (es).
 * Esta meta solo guarda los overrides para los demás idiomas.
 *
 * Usa $post->post_title (crudo) en vez de get_the_title() para no disparar
 * el filtro 'the_title' que enganchamos abajo y causar recursión infinita.
 */
function pswpt_get_post_translated_title(WP_Post $post, $lang = null): string
{
    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default_lang = idml_get_default_language();

    if ($lang === '' || $lang === $default_lang) {
        return $post->post_title;
    }

    $translations = get_post_meta($post->ID, pswpt_POST_TRANSLATED_TITLE_META, true);
    $translations = is_array($translations) ? $translations : [];
    $value = (string) ($translations[$lang] ?? '');

    return $value !== '' ? $value : $post->post_title;
}

add_filter('the_title', function ($title, $post_id = 0) {
    if (is_admin() || !$post_id || !in_array(get_post_type($post_id), pswpt_translatable_post_types(), true)) {
        return $title;
    }

    $post = get_post($post_id);
    if (!($post instanceof WP_Post)) {
        return $title;
    }

    return pswpt_get_post_translated_title($post);
}, 10, 2);

/**
 * El contenido del post es un repeater de bloques HTML crudos (uno por idioma).
 * Compat: versiones previas guardaban un solo string por idioma; se normaliza
 * a un array de 1 bloque para no perder contenido ya cargado.
 */
function pswpt_normalize_post_content_blocks($value): array
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

function pswpt_get_post_translated_content_blocks(WP_Post $post, $lang = null): array
{
    $lang = $lang !== null ? idml_normalize_lang($lang) : idml_get_current_language();
    $default_lang = idml_get_default_language();

    $translations = get_post_meta($post->ID, pswpt_POST_TRANSLATED_CONTENT_META, true);
    $translations = is_array($translations) ? $translations : [];

    $blocks = $lang !== '' ? pswpt_normalize_post_content_blocks($translations[$lang] ?? []) : [];
    if (!empty($blocks)) {
        return $blocks;
    }

    return pswpt_normalize_post_content_blocks($translations[$default_lang] ?? []);
}

/**
 * El excerpt traducido sigue siendo un solo campo de texto por idioma.
 */
function pswpt_get_post_translated_excerpt(WP_Post $post, $lang = null): string
{
    return pswpt_get_post_translated_field($post, pswpt_POST_TRANSLATED_EXCERPT_META, $lang);
}

function pswpt_get_post_translated_field(WP_Post $post, string $meta_key, $lang = null): string
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
function pswpt_post_translated_slug_meta_key(string $lang): string
{
    return '_pswpt_post_translated_slug_' . sanitize_key($lang);
}

function pswpt_get_post_slug_for_lang(WP_Post $post, string $lang): string
{
    $lang = idml_normalize_lang($lang);
    $default_lang = idml_get_default_language();

    if ($lang === '' || $lang === $default_lang) {
        return sanitize_title($post->post_name);
    }

    $translated = sanitize_title((string) get_post_meta($post->ID, pswpt_post_translated_slug_meta_key($lang), true));

    return $translated !== '' ? $translated : sanitize_title($post->post_name);
}

/**
 * Busca por slug traducido en $post_types (por defecto 'post' y 'page' a la
 * vez, que comparten el espacio /{lang}/{slug}/; los CPT públicos viven en
 * /{lang}/{base}/{slug}/ y se consultan por tipo). Si un Post y una Page
 * llegaran a compartir el mismo slug traducido para el mismo idioma, gana el que
 * WP devuelva primero con el orden por defecto (post_date DESC) — no hay
 * desempate explícito ni validación de unicidad al guardar. Caso raro (dos
 * tipos de contenido distintos con el mismo slug en el mismo idioma) que se
 * documenta acá en vez de resolverse, para no sumar alcance no pedido.
 */
function pswpt_get_post_id_by_translated_slug(string $slug, string $lang, $post_types = null): int
{
    $slug = sanitize_title($slug);
    $lang = idml_normalize_lang($lang);
    $default_lang = idml_get_default_language();

    if ($slug === '' || $lang === '' || $lang === $default_lang) {
        return 0;
    }

    $ids = get_posts([
        'post_type'      => $post_types !== null ? (array) $post_types : ['post', 'page'],
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => pswpt_post_translated_slug_meta_key($lang),
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

    return pswpt_translated_permalink($url, $post);
}, 10, 2);

/**
 * Mismo comportamiento que el filtro 'post_link' de arriba, pero para 'page': WP
 * dispara un hook distinto para páginas, con una firma distinta — 3 args,
 * $post_id como int (no WP_Post) y un $sample bool que 'post_link' no tiene
 * (true cuando el editor de permalinks arma la vista previa; ahí no se debe
 * reescribir nada o el preview queda confuso).
 */
add_filter('page_link', function ($link, $post_id, $sample) {
    if (is_admin() || $sample) {
        return $link;
    }

    $post = get_post($post_id);
    if (!($post instanceof WP_Post) || $post->post_type !== 'page') {
        return $link;
    }

    return pswpt_translated_permalink($link, $post);
}, 10, 3);

/**
 * CPTs públicos (WP dispara 'post_type_link', no 'post_link'): en idioma
 * no-default el permalink es /{lang}/{base-del-tipo-en-ese-idioma}/{slug}/.
 */
add_filter('post_type_link', function ($url, $post) {
    if (is_admin() || !($post instanceof WP_Post) || in_array($post->post_type, ['post', 'page'], true) || !in_array($post->post_type, pswpt_public_translatable_post_types(), true)) {
        return $url;
    }

    return pswpt_translated_permalink($url, $post);
}, 10, 2);

function pswpt_translated_permalink(string $url, WP_Post $post): string
{
    $lang = idml_get_current_language();
    $default_lang = idml_get_default_language();

    if ($lang === '' || $lang === $default_lang) {
        return $url;
    }

    // La page_on_front no lleva slug en su URL (get_page_link() ya la devuelve
    // como home_url('/') antes de este filtro) — anexarle el slug nativo o
    // traducido rompería el link de "inicio" del idioma.
    if ($post->post_type === 'page' && 'page' === get_option('show_on_front') && (int) get_option('page_on_front') === $post->ID) {
        return home_url('/' . rawurlencode($lang) . '/');
    }

    $slug = pswpt_get_post_slug_for_lang($post, $lang);
    if ($slug === '') {
        return $url;
    }

    if (!in_array($post->post_type, ['post', 'page'], true)) {
        $base = pswpt_post_type_lang_slug($post->post_type, $lang);
        if ($base !== '') {
            return home_url('/' . rawurlencode($lang) . '/' . rawurlencode($base) . '/' . rawurlencode($slug) . '/');
        }
    }

    return home_url('/' . rawurlencode($lang) . '/' . rawurlencode($slug) . '/');
}

/**
 * Cierra el hueco de renderizado: las pestañas guardan el contenido traducido en
 * meta, pero hasta ahora nada lo imprimía — single.php/page.php llaman al
 * the_content() nativo, que solo conoce post_content (que en 'post' ni se edita
 * mas, ver pswpt_remove_default_post_content_editor()). Sin esto, tanto
 * posts como pages quedan con el body vacío pase lo que pase en las pestañas.
 * Si nunca se guardó nada en las pestañas, cae al $content nativo para no
 * romper contenido que ya existía antes de este mecanismo.
 */
add_filter('the_content', function ($content) {
    if (is_admin() || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post = get_post();
    if (!($post instanceof WP_Post) || !in_array($post->post_type, pswpt_translatable_post_types(), true)) {
        return $content;
    }

    $blocks = pswpt_get_post_translated_content_blocks($post);
    if (empty($blocks)) {
        return $content;
    }

    return implode("\n\n", $blocks);
}, 10, 1);

add_action('add_meta_boxes', 'pswpt_add_post_translation_metabox');
add_action('add_meta_boxes', 'pswpt_remove_default_post_excerpt_metabox', 20);
add_action('init', 'pswpt_remove_default_post_content_editor', 20);

function pswpt_remove_default_post_content_editor(): void
{
    foreach (pswpt_translatable_post_types() as $type) {
        remove_post_type_support($type, 'editor');
    }
}

function pswpt_remove_default_post_excerpt_metabox(): void
{
    foreach (pswpt_translatable_post_types() as $type) {
        remove_meta_box('postexcerpt', $type, 'normal');
    }
}

function pswpt_sanitize_translated_rich_text($raw_value): string
{
    $value = wp_unslash((string) $raw_value);

    if (current_user_can('unfiltered_html')) {
        return $value;
    }

    return wp_kses_post($value);
}

function pswpt_add_post_translation_metabox(): void
{
    add_meta_box(
        'pswpt_post_translation_content',
        __('Translated content', 'pswpt'),
        'pswpt_render_post_translation_metabox',
        pswpt_translatable_post_types(),
        'normal',
        'high'
    );
}

function pswpt_render_post_translation_metabox($post): void
{
    if (!($post instanceof WP_Post)) {
        return;
    }

    wp_nonce_field('pswpt_post_translation_content_save', 'pswpt_post_translation_nonce');

    $content = get_post_meta($post->ID, pswpt_POST_TRANSLATED_CONTENT_META, true);
    $content = is_array($content) ? $content : [];
    $excerpt = get_post_meta($post->ID, pswpt_POST_TRANSLATED_EXCERPT_META, true);
    $excerpt = is_array($excerpt) ? $excerpt : [];
    $is_featured = pswpt_is_post_featured($post);

    $title_translations = get_post_meta($post->ID, pswpt_POST_TRANSLATED_TITLE_META, true);
    $title_translations = is_array($title_translations) ? $title_translations : [];

    $title_by_lang = [];
    $slug_by_lang  = [];
    foreach (pswpt_translation_languages() as $lang) {
        $title_by_lang[$lang] = (string) ($title_translations[$lang] ?? '');
        $slug_by_lang[$lang]  = (string) get_post_meta($post->ID, pswpt_post_translated_slug_meta_key($lang), true);
    }
    $has_url      = is_post_type_viewable($post->post_type);
    $default_lang = idml_get_default_language();

    \pswptInit\General\MultilanguageTabsRenderer::render('pswpt_post_translation', function ($lang_code, $lang_label) use ($content, $excerpt, $title_by_lang, $slug_by_lang, $has_url, $default_lang) {
        if ($lang_code === $default_lang) {
            ?>
            <p class="description"><?php echo esc_html($has_url ? __('The native title and slug (above, in the main editor) are the default language\'s.', 'pswpt') : __('The native title (above) is the default language\'s.', 'pswpt')); ?></p>
            <?php
        } else {
            ?>
            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px;">
                <div style="flex:1 1 300px; min-width:240px;">
                    <label for="pswpt_post_title_<?php echo esc_attr($lang_code); ?>"><strong><?php echo esc_html(sprintf(__('Title %s', 'pswpt'), $lang_label)); ?></strong></label>
                    <input type="text" id="pswpt_post_title_<?php echo esc_attr($lang_code); ?>" name="pswpt_post_title[<?php echo esc_attr($lang_code); ?>]" class="widefat" value="<?php echo esc_attr($title_by_lang[$lang_code] ?? ''); ?>" />
                </div>
                <?php if ($has_url) : ?>
                <div style="flex:1 1 300px; min-width:240px;">
                    <label for="pswpt_post_slug_<?php echo esc_attr($lang_code); ?>"><strong><?php echo esc_html(sprintf(__('Slug %s', 'pswpt'), $lang_label)); ?></strong></label>
                    <input type="text" id="pswpt_post_slug_<?php echo esc_attr($lang_code); ?>" name="pswpt_post_slug[<?php echo esc_attr($lang_code); ?>]" class="widefat" value="<?php echo esc_attr($slug_by_lang[$lang_code] ?? ''); ?>" />
                    <span class="description"><?php echo esc_html(sprintf(__('Leave blank to use the native slug. Changes the URL to /%s/…/', 'pswpt'), $lang_code)); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php
        }

        $content_blocks = pswpt_normalize_post_content_blocks($content[$lang_code] ?? []);
        if (empty($content_blocks)) {
            $content_blocks = [''];
        }
        $excerpt_val = (string) ($excerpt[$lang_code] ?? '');
        $container_id = 'pswpt-post-content-blocks-' . $lang_code;
        $template_id = 'pswpt-post-content-block-template-' . $lang_code;
        ?>
        <p>
            <label><strong><?php echo esc_html(sprintf(__('Content %s (HTML blocks)', 'pswpt'), $lang_label)); ?></strong></label>
        </p>
        <div id="<?php echo esc_attr($container_id); ?>" class="pswpt-content-blocks">
            <?php foreach ($content_blocks as $index => $block): ?>
                <div class="pswpt-content-block" style="margin-bottom:12px;padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:6px;">
                        <strong class="pswpt-content-block-label"><?php echo esc_html(sprintf(__('Block %d', 'pswpt'), $index + 1)); ?></strong>
                        <button type="button" class="button-link-delete pswpt-content-block-remove"><?php esc_html_e('Remove block', 'pswpt'); ?></button>
                    </div>
                    <textarea
                        name="pswpt_post_content_blocks[<?php echo esc_attr($lang_code); ?>][]"
                        rows="10"
                        style="width:100%;min-height:200px;font-family:monospace;"
                    ><?php echo esc_textarea($block); ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary pswpt-content-block-add" data-target="<?php echo esc_attr($container_id); ?>" data-template="<?php echo esc_attr($template_id); ?>"><?php esc_html_e('Add block', 'pswpt'); ?></button>
        </p>
        <p class="description"><?php esc_html_e('Each block accepts HTML. They render in the order shown here. PHP is not executed.', 'pswpt'); ?></p>
        <template id="<?php echo esc_attr($template_id); ?>">
            <div class="pswpt-content-block" style="margin-bottom:12px;padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:6px;">
                    <strong class="pswpt-content-block-label"><?php esc_html_e('New block', 'pswpt'); ?></strong>
                    <button type="button" class="button-link-delete pswpt-content-block-remove"><?php esc_html_e('Remove block', 'pswpt'); ?></button>
                </div>
                <textarea name="pswpt_post_content_blocks[<?php echo esc_attr($lang_code); ?>][]" rows="10" style="width:100%;min-height:200px;font-family:monospace;"></textarea>
            </div>
        </template>

        <p style="margin-top:12px;">
            <label for="pswpt_post_excerpt_<?php echo esc_attr($lang_code); ?>"><strong><?php echo esc_html(sprintf(__('Excerpt %s', 'pswpt'), $lang_label)); ?></strong></label>
            <textarea
                id="pswpt_post_excerpt_<?php echo esc_attr($lang_code); ?>"
                name="pswpt_post_excerpt[<?php echo esc_attr($lang_code); ?>]"
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
            container.querySelectorAll('.pswpt-content-block').forEach(function (block, index) {
                var label = block.querySelector('.pswpt-content-block-label');
                if (label) {
                    label.textContent = '<?php echo esc_js(__('Block', 'pswpt')); ?> ' + (index + 1);
                }
            });
        }

        document.addEventListener('click', function (e) {
            var addBtn = e.target.closest('.pswpt-content-block-add');
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

            var removeBtn = e.target.closest('.pswpt-content-block-remove');
            if (removeBtn) {
                var removedBlock = removeBtn.closest('.pswpt-content-block');
                var removedContainer = removedBlock ? removedBlock.closest('.pswpt-content-blocks') : null;
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

    <?php if ($post->post_type === 'post'): ?>
    <p>
        <label for="pswpt_post_is_featured">
            <input type="checkbox" id="pswpt_post_is_featured" name="pswpt_post_is_featured" value="1" <?php checked($is_featured); ?> />
            <strong><?php esc_html_e('Featured post (shown in the blog\'s featured block)', 'pswpt'); ?></strong>
        </label>
    </p>
    <?php endif; ?>
    <?php
}

add_action('save_post', 'pswpt_save_post_translation_metabox');

function pswpt_save_post_translation_metabox($post_id): void
{
    if (!in_array(get_post_type($post_id), pswpt_translatable_post_types(), true)) {
        return;
    }

    if (!isset($_POST['pswpt_post_translation_nonce']) || !wp_verify_nonce((string) $_POST['pswpt_post_translation_nonce'], 'pswpt_post_translation_content_save')) {
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

    $is_featured = isset($_POST['pswpt_post_is_featured']) && $_POST['pswpt_post_is_featured'] === '1';
    if ($is_featured) {
        update_post_meta($post_id, pswpt_POST_FEATURED_META, '1');
    } else {
        delete_post_meta($post_id, pswpt_POST_FEATURED_META);
    }


    $submitted_slug = isset($_POST['pswpt_post_slug']) && is_array($_POST['pswpt_post_slug'])
        ? $_POST['pswpt_post_slug']
        : [];

    $languages = pswpt_translation_languages();

    foreach ($languages as $slug_lang) {
        $slug_value = sanitize_title(wp_unslash((string) ($submitted_slug[$slug_lang] ?? '')));
        $meta_key = pswpt_post_translated_slug_meta_key($slug_lang);

        if ($slug_value === '') {
            delete_post_meta($post_id, $meta_key);
        } else {
            update_post_meta($post_id, $meta_key, $slug_value);
        }
    }

    $submitted_title = isset($_POST['pswpt_post_title']) && is_array($_POST['pswpt_post_title'])
        ? $_POST['pswpt_post_title']
        : [];

    $sanitized_title = [];
    foreach ($languages as $title_lang) {
        $value = sanitize_text_field(wp_unslash((string) ($submitted_title[$title_lang] ?? '')));
        if ($value !== '') {
            $sanitized_title[$title_lang] = $value;
        }
    }

    if (!$sanitized_title) {
        delete_post_meta($post_id, pswpt_POST_TRANSLATED_TITLE_META);
    } else {
        update_post_meta($post_id, pswpt_POST_TRANSLATED_TITLE_META, $sanitized_title);
    }

    $submitted_blocks = isset($_POST['pswpt_post_content_blocks']) && is_array($_POST['pswpt_post_content_blocks'])
        ? $_POST['pswpt_post_content_blocks']
        : [];
    $submitted_excerpt = isset($_POST['pswpt_post_excerpt']) && is_array($_POST['pswpt_post_excerpt'])
        ? $_POST['pswpt_post_excerpt']
        : [];

    $sanitized_content = [];
    $sanitized_excerpt = [];
    $has_content = false;
    $has_excerpt = false;

    foreach (\pswptInit\General\MultilanguageTabsRenderer::get_languages() as $lang_code => $lang_label) {
        $raw_blocks = isset($submitted_blocks[$lang_code]) && is_array($submitted_blocks[$lang_code])
            ? $submitted_blocks[$lang_code]
            : [];

        $blocks = [];
        foreach ($raw_blocks as $raw_block) {
            $block = pswpt_sanitize_translated_rich_text($raw_block);
            if ($block === '') {
                continue;
            }
            $blocks[] = $block;
        }

        $sanitized_content[$lang_code] = $blocks;
        $sanitized_excerpt[$lang_code] = pswpt_sanitize_translated_rich_text($submitted_excerpt[$lang_code] ?? '');

        if (!empty($blocks)) {
            $has_content = true;
        }
        if ($sanitized_excerpt[$lang_code] !== '') {
            $has_excerpt = true;
        }
    }

    if (!$has_content) {
        delete_post_meta($post_id, pswpt_POST_TRANSLATED_CONTENT_META);
    } else {
        update_post_meta($post_id, pswpt_POST_TRANSLATED_CONTENT_META, $sanitized_content);
    }

    if (!$has_excerpt) {
        delete_post_meta($post_id, pswpt_POST_TRANSLATED_EXCERPT_META);
        return;
    }

    update_post_meta($post_id, pswpt_POST_TRANSLATED_EXCERPT_META, $sanitized_excerpt);
}

