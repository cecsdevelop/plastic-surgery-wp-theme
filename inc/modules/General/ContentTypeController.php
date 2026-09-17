<?php
/**
 * Base de los CPT de contenido del sitio (Servicios, Portafolio, Testimonios,
 * Clientes, Equipo): cada módulo solo declara su config() y hereda el
 * registro del post type, el metabox de campos propios, el guardado con
 * sanitize por tipo de campo, las columnas del listado y los helpers de
 * lectura para plantillas y componentes.
 *
 * Multilenguaje: el título, el contenido (bloques HTML), el excerpt y —si el
 * tipo es público— el slug se traducen con el metabox "Contenido traducido"
 * del núcleo (admin-post-translation-settings.php): el módulo se suma a
 * intelindev_translatable_post_types() y declara la base de URL por idioma
 * (/{lang}/{base}/{slug}/) y el nombre plural por idioma para el título del
 * archivo. Los campos propios cortos que cambian por idioma se declaran como
 * 'lang_text' / 'lang_textarea' (array lang => texto, resueltos con
 * intelindev_resolve_lang_text()).
 *
 * Tipos de campo: text, url, number, select, media (ID de adjunto),
 * gallery (IDs), lang_text, lang_textarea. Sin editor de bloques: show_in_rest
 * false y el contenido va por el metabox de traducción, como pages/posts.
 *
 * @package Intelindev
 */

namespace IntelindevInit\General;

use WP_Post;

abstract class ContentTypeController extends BaseController
{
    public const NONCE_ACTION = 'intelindev_content_type_save';
    public const NONCE_FIELD  = 'intelindev_content_type_nonce';
    public const FIELD_PREFIX = 'intelindev_ct';

    private array $config = [];

    /**
     * post_type (≤ 20 chars), labels [lang => [name, singular]], menu_icon,
     * menu_position, public (archivo + single con URL), slugs [lang => base],
     * supports (extra a title/thumbnail), fields [key => [label, type,
     * description?, options?, column?]], orderby, order.
     */
    abstract protected function config(): array;

    /* ------------------------------------------------------------------ */
    /* Registro de hooks                                                    */
    /* ------------------------------------------------------------------ */

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type'], 5);
        add_filter('intelindev_translatable_post_types', [$this, 'translatable_types']);
        add_filter('intelindev_post_type_lang_slugs', [$this, 'lang_slugs']);
        add_filter('intelindev_post_type_lang_labels', [$this, 'lang_labels']);

        if ($this->fields()) {
            add_action('add_meta_boxes_' . $this->post_type(), [$this, 'add_meta_boxes']);
            add_action('save_post_' . $this->post_type(), [$this, 'save']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }
        add_filter('manage_' . $this->post_type() . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . $this->post_type() . '_posts_custom_column', [$this, 'render_column'], 10, 2);
    }

    /* ------------------------------------------------------------------ */
    /* Config                                                               */
    /* ------------------------------------------------------------------ */

    protected function cfg(string $key, $default = null)
    {
        if (!$this->config) {
            $this->config = $this->config();
        }

        return $this->config[$key] ?? $default;
    }

    public function post_type(): string
    {
        return (string) $this->cfg('post_type');
    }

    public function fields(): array
    {
        return (array) $this->cfg('fields', []);
    }

    public function is_public(): bool
    {
        return (bool) $this->cfg('public', false);
    }

    /** Nombre plural/singular en un idioma (cae al idioma por defecto). */
    public function label(string $which = 'name', $lang = null): string
    {
        $lang   = $lang !== null ? idml_normalize_lang($lang) : $this->get_current_lang();
        $labels = (array) $this->cfg('labels', []);
        $set    = $labels[$lang] ?? $labels[idml_get_default_language()] ?? reset($labels) ?: [];

        return (string) ($set[$which] ?? $set['name'] ?? $this->post_type());
    }

    /** Base de URL (slug del archivo) en un idioma; en el por defecto es la de rewrite. */
    public function slug(string $lang): string
    {
        $slugs = (array) $this->cfg('slugs', []);

        return sanitize_title((string) ($slugs[$lang] ?? $slugs[idml_get_default_language()] ?? $this->post_type()));
    }

    public function meta_key(string $field): string
    {
        return '_' . $this->post_type() . '_' . sanitize_key($field);
    }

    /* ------------------------------------------------------------------ */
    /* Post type                                                            */
    /* ------------------------------------------------------------------ */

    public function register_post_type(): void
    {
        $default = idml_get_default_language();
        $name    = $this->label('name', $default);
        $single  = $this->label('singular', $default);
        $public  = $this->is_public();

        $args = [
            'labels' => [
                'name'               => $name,
                'singular_name'      => $single,
                'menu_name'          => $name,
                'add_new'            => sprintf(__('Agregar %s', 'intelindev'), mb_strtolower($single)),
                'add_new_item'       => sprintf(__('Agregar %s', 'intelindev'), mb_strtolower($single)),
                'edit_item'          => sprintf(__('Editar %s', 'intelindev'), mb_strtolower($single)),
                'new_item'           => sprintf(__('Nuevo %s', 'intelindev'), mb_strtolower($single)),
                'view_item'          => sprintf(__('Ver %s', 'intelindev'), mb_strtolower($single)),
                'all_items'          => sprintf(__('Todos: %s', 'intelindev'), mb_strtolower($name)),
                'search_items'       => sprintf(__('Buscar %s', 'intelindev'), mb_strtolower($name)),
                'not_found'          => sprintf(__('No hay %s todavía.', 'intelindev'), mb_strtolower($name)),
                'not_found_in_trash' => sprintf(__('No hay %s en la papelera.', 'intelindev'), mb_strtolower($name)),
                'featured_image'     => (string) $this->cfg('thumbnail_label', __('Imagen destacada', 'intelindev')),
            ],
            'description'         => (string) $this->cfg('description', ''),
            'public'              => $public,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => false, // editor clásico: contenido en el metabox de traducción
            'publicly_queryable'  => $public,
            'exclude_from_search' => !$public,
            'has_archive'         => $public ? $this->slug($default) : false,
            'rewrite'             => $public ? ['slug' => $this->slug($default), 'with_front' => false] : false,
            'query_var'           => $public,
            'menu_position'       => (int) $this->cfg('menu_position', 26),
            'menu_icon'           => (string) $this->cfg('menu_icon', 'dashicons-admin-post'),
            'map_meta_cap'        => true,
            // Sin 'editor' ni 'excerpt': los aporta el metabox "Contenido traducido".
            'supports'            => array_values(array_unique(array_merge(['title', 'thumbnail', 'page-attributes'], (array) $this->cfg('supports', [])))),
        ];

        register_post_type($this->post_type(), $args);
    }

    public function translatable_types(array $types): array
    {
        $types[] = $this->post_type();

        return $types;
    }

    public function lang_slugs(array $map): array
    {
        if ($this->is_public()) {
            foreach (idml_get_languages() as $lang) {
                $map[$this->post_type()][$lang] = $this->slug($lang);
            }
        }

        return $map;
    }

    public function lang_labels(array $map): array
    {
        foreach (idml_get_languages() as $lang) {
            $map[$this->post_type()][$lang] = $this->label('name', $lang);
        }

        return $map;
    }

    /* ------------------------------------------------------------------ */
    /* Lectura (plantillas y componentes)                                   */
    /* ------------------------------------------------------------------ */

    /** Publicados, en el orden del config (menu_order + título por defecto). */
    public function get_items(array $args = []): array
    {
        $args = array_merge([
            'post_type'        => $this->post_type(),
            'post_status'      => 'publish',
            'numberposts'      => -1,
            'orderby'          => (string) $this->cfg('orderby', 'menu_order title'),
            'order'            => (string) $this->cfg('order', 'ASC'),
            'suppress_filters' => false,
        ], $args);

        return get_posts($args);
    }

    /**
     * Valor resuelto de un campo propio: lang_* → texto del idioma (con
     * fallback), media → int, gallery → int[], number → int, resto → string.
     */
    public function get_field($post, string $key, $lang = null)
    {
        $post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
        $type    = (string) ($this->fields()[$key]['type'] ?? 'text');
        $value   = get_post_meta($post_id, $this->meta_key($key), true);

        switch ($type) {
            case 'lang_text':
            case 'lang_textarea':
                return intelindev_resolve_lang_text($value, $lang);
            case 'media':
            case 'number':
                return (int) $value;
            case 'gallery':
                return array_values(array_filter(array_map('intval', (array) $value)));
            default:
                return (string) $value;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Admin: metabox, guardado, columnas                                   */
    /* ------------------------------------------------------------------ */

    public function enqueue_admin_assets(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->base === 'post' && $screen->post_type === $this->post_type()) {
            intelindev_admin_enqueue_field_assets();
        }
    }

    public function add_meta_boxes(): void
    {
        add_meta_box(
            $this->post_type() . '_fields',
            sprintf(__('Datos de %s', 'intelindev'), mb_strtolower($this->label('singular', idml_get_default_language()))),
            [$this, 'render_meta_box'],
            $this->post_type(),
            'normal',
            'high'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        $languages = \IntelindevInit\General\MultilanguageTabsRenderer::get_languages();
        ?>
        <div class="intelindev-panel-form intelindev-ct-fields">
            <table class="form-table" role="presentation">
                <?php foreach ($this->fields() as $key => $field) : ?>
                    <?php $this->render_field($post, (string) $key, (array) $field, $languages); ?>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    private function render_field(WP_Post $post, string $key, array $field, array $languages): void
    {
        $type  = (string) ($field['type'] ?? 'text');
        $label = (string) ($field['label'] ?? $key);
        $desc  = (string) ($field['description'] ?? '');
        $name  = self::FIELD_PREFIX . '[' . $key . ']';
        $id    = self::FIELD_PREFIX . '_' . $key;
        $value = get_post_meta($post->ID, $this->meta_key($key), true);
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <?php if ($type === 'lang_text' || $type === 'lang_textarea') : ?>
                    <?php $value = is_array($value) ? $value : []; ?>
                    <?php foreach ($languages as $lang => $lang_label) : ?>
                        <p style="margin:0 0 8px;">
                            <label for="<?php echo esc_attr($id . '_' . $lang); ?>" style="display:inline-block;min-width:80px;"><?php echo esc_html($lang_label); ?></label>
                            <?php if ($type === 'lang_text') : ?>
                                <input type="text" class="regular-text" id="<?php echo esc_attr($id . '_' . $lang); ?>" name="<?php echo esc_attr($name . '[' . $lang . ']'); ?>" value="<?php echo esc_attr((string) ($value[$lang] ?? '')); ?>" />
                            <?php else : ?>
                                <textarea class="large-text" rows="3" id="<?php echo esc_attr($id . '_' . $lang); ?>" name="<?php echo esc_attr($name . '[' . $lang . ']'); ?>"><?php echo esc_textarea((string) ($value[$lang] ?? '')); ?></textarea>
                            <?php endif; ?>
                        </p>
                    <?php endforeach; ?>
                <?php elseif ($type === 'media') : ?>
                    <?php $media_id = (int) $value; $preview = $media_id > 0 ? (string) wp_get_attachment_image_url($media_id, 'medium') : ''; ?>
                    <div class="intelindev-media-field">
                        <img class="intelindev-media-preview" src="<?php echo esc_url($preview); ?>" alt="" <?php echo $preview === '' ? 'hidden' : ''; ?> />
                        <input type="hidden" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo $media_id; ?>" />
                        <button type="button" class="button intelindev-media-upload"><?php esc_html_e('Seleccionar imagen', 'intelindev'); ?></button>
                        <button type="button" class="button-link-delete intelindev-media-remove" <?php echo $media_id > 0 ? '' : 'hidden'; ?>><?php esc_html_e('Quitar', 'intelindev'); ?></button>
                    </div>
                <?php elseif ($type === 'gallery') : ?>
                    <?php $ids = array_values(array_filter(array_map('intval', (array) $value))); ?>
                    <div class="intelindev-gallery-field">
                        <input type="hidden" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr(implode(',', $ids)); ?>" />
                        <ul class="intelindev-gallery-list">
                            <?php foreach ($ids as $img_id) : ?>
                                <li data-id="<?php echo $img_id; ?>"><?php echo wp_get_attachment_image($img_id, 'thumbnail'); ?><button type="button" class="intelindev-gallery-remove" aria-label="<?php esc_attr_e('Quitar', 'intelindev'); ?>">&times;</button></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="button intelindev-gallery-add"><?php esc_html_e('Agregar imágenes', 'intelindev'); ?></button>
                    </div>
                <?php elseif ($type === 'select') : ?>
                    <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>">
                        <?php foreach ((array) ($field['options'] ?? []) as $opt_value => $opt_label) : ?>
                            <option value="<?php echo esc_attr((string) $opt_value); ?>" <?php selected((string) $value, (string) $opt_value); ?>><?php echo esc_html((string) $opt_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'number') : ?>
                    <input type="number" class="small-text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>" step="1" />
                <?php else : ?>
                    <input type="<?php echo $type === 'url' ? 'url' : 'text'; ?>" class="regular-text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>" <?php echo $type === 'url' ? 'placeholder="https://"' : ''; ?> />
                <?php endif; ?>
                <?php if ($desc !== '') : ?><p class="description"><?php echo esc_html($desc); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public function save($post_id): void
    {
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $input = isset($_POST[self::FIELD_PREFIX]) && is_array($_POST[self::FIELD_PREFIX]) ? wp_unslash($_POST[self::FIELD_PREFIX]) : [];

        foreach ($this->fields() as $key => $field) {
            $value = $this->sanitize_field((string) ($field['type'] ?? 'text'), $input[$key] ?? null, (array) $field);
            if ($value === '' || $value === 0 || $value === []) {
                delete_post_meta($post_id, $this->meta_key((string) $key));
            } else {
                update_post_meta($post_id, $this->meta_key((string) $key), $value);
            }
        }
    }

    /** Sanitize por tipo; devuelve '' / 0 / [] cuando no hay valor (⇒ delete_post_meta). */
    public function sanitize_field(string $type, $raw, array $field = [])
    {
        switch ($type) {
            case 'lang_text':
                return intelindev_sanitize_lang_text($raw);
            case 'lang_textarea':
                $out = [];
                foreach (idml_get_languages() as $lang) {
                    $text = sanitize_textarea_field((string) (is_array($raw) ? ($raw[$lang] ?? '') : ''));
                    if ($text !== '') {
                        $out[$lang] = $text;
                    }
                }
                return $out;
            case 'media':
            case 'number':
                return max(0, (int) $raw);
            case 'gallery':
                $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
                return array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
            case 'url':
                return esc_url_raw(trim((string) $raw));
            case 'select':
                $raw = (string) $raw;
                return array_key_exists($raw, (array) ($field['options'] ?? [])) ? $raw : '';
            default:
                return sanitize_text_field((string) $raw);
        }
    }

    public function columns($columns)
    {
        $out = [];
        foreach ((array) $columns as $key => $label) {
            $out[$key] = $label;
            if ($key === 'title') {
                foreach ($this->fields() as $field_key => $field) {
                    if (!empty($field['column'])) {
                        $out['intelindev_ct_' . $field_key] = (string) ($field['label'] ?? $field_key);
                    }
                }
            }
        }

        return $out;
    }

    public function render_column($column, $post_id): void
    {
        if (strpos((string) $column, 'intelindev_ct_') !== 0) {
            return;
        }
        $key   = substr((string) $column, strlen('intelindev_ct_'));
        $type  = (string) ($this->fields()[$key]['type'] ?? 'text');
        $value = $this->get_field((int) $post_id, $key, idml_get_default_language());

        if ($type === 'media') {
            echo $value ? wp_get_attachment_image((int) $value, [40, 40]) : '—';
        } elseif ($type === 'gallery') {
            echo esc_html(sprintf(_n('%d imagen', '%d imágenes', count($value), 'intelindev'), count($value)));
        } else {
            echo $value !== '' && $value !== 0 ? esc_html((string) $value) : '—';
        }
    }
}
