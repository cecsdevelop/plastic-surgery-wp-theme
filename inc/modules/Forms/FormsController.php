<?php
/**
 * Formularios (CPT intelindev_form): el admin arma el formulario desde el
 * dashboard (campos con etiqueta/placeholder/opciones por idioma, ancho con
 * el grid del theme, obligatorio) y lo inserta con [form slug="contacto"] en
 * el modal del header, páginas, Componentes o widgets del footer.
 *
 * Render: FormRenderer. Recepción (REST, antispam, validación, correo,
 * webhook): FormHandler. Registro de envíos: SubmissionsController.
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Forms;

use IntelindevInit\General\BaseController;
use WP_Post;
use WP_Screen;

class FormsController extends BaseController
{
    public const POST_TYPE     = 'intelindev_form';
    public const META_FIELDS   = '_intelindev_form_fields';
    public const META_SETTINGS = '_intelindev_form_settings';
    public const NONCE_ACTION  = 'intelindev_form_save';
    public const NONCE_FIELD   = 'intelindev_form_nonce';
    public const FIELD         = 'intelindev_form'; // name= raíz del formulario de edición
    public const SHORTCODE     = 'form';

    public const TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'hidden'];
    /** HTML permitido en etiquetas (link a política de privacidad, énfasis). */
    public const LABEL_TAGS = ['a' => ['href' => [], 'target' => [], 'rel' => []], 'strong' => [], 'em' => []];
    public const WIDTHS = ['col-12', 'col-12 col-md-6', 'col-12 col-md-4', 'col-12 col-md-8', 'col-12 col-md-3'];

    private FormHandler $handler;

    public function __construct()
    {
        parent::__construct();
        $this->handler = new FormHandler();
    }

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type'], 5);
        add_action('init', [$this, 'register_shortcode'], 20);
        add_action('rest_api_init', [$this->handler, 'register_routes']);

        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'add_meta_boxes']);
        add_filter('default_hidden_meta_boxes', [$this, 'show_slug_meta_box'], 10, 2);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_column'], 10, 2);
    }

    /* ------------------------------------------------------------------ */
    /* CPT                                                                  */
    /* ------------------------------------------------------------------ */

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'               => __('Formularios', 'intelindev'),
                'singular_name'      => __('Formulario', 'intelindev'),
                'menu_name'          => __('Formularios', 'intelindev'),
                'add_new'            => __('Agregar formulario', 'intelindev'),
                'add_new_item'       => __('Agregar formulario', 'intelindev'),
                'edit_item'          => __('Editar formulario', 'intelindev'),
                'all_items'          => __('Todos los formularios', 'intelindev'),
                'search_items'       => __('Buscar formularios', 'intelindev'),
                'not_found'          => __('No hay formularios todavía.', 'intelindev'),
                'not_found_in_trash' => __('No hay formularios en la papelera.', 'intelindev'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'menu_position'       => 26,
            'menu_icon'           => 'dashicons-feedback',
            'supports'            => ['title'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                              */
    /* ------------------------------------------------------------------ */

    /** Campos normalizados (name, type, required, width, label[], placeholder[], options[], value). */
    public static function get_fields(int $form_id): array
    {
        $fields = get_post_meta($form_id, self::META_FIELDS, true);
        return is_array($fields) ? array_values($fields) : [];
    }

    public static function get_settings(int $form_id): array
    {
        $settings = get_post_meta($form_id, self::META_SETTINGS, true);
        return is_array($settings) ? $settings : [];
    }

    /** Opciones de un select para un idioma (una por línea), con fallback al idioma por defecto. */
    public static function field_options(array $field, string $lang): array
    {
        $text = intelindev_resolve_lang_text($field['options'] ?? [], $lang);
        $options = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)));
        return array_values(array_unique($options));
    }

    public static function get_by_slug(string $slug): ?WP_Post
    {
        $slug = sanitize_title($slug);
        if ($slug === '') return null;
        $post = get_page_by_path($slug, OBJECT, self::POST_TYPE);
        return $post instanceof WP_Post && $post->post_status === 'publish' ? $post : null;
    }

    /* ------------------------------------------------------------------ */
    /* Shortcode                                                            */
    /* ------------------------------------------------------------------ */

    public function register_shortcode(): void
    {
        if (!shortcode_exists(self::SHORTCODE)) {
            add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
        }
    }

    public function render_shortcode($atts): string
    {
        $atts = shortcode_atts(['slug' => '', 'id' => 0], is_array($atts) ? $atts : [], self::SHORTCODE);
        $form = null;
        if ((int) $atts['id'] > 0) {
            $candidate = get_post((int) $atts['id']);
            $form = $candidate instanceof WP_Post && $candidate->post_type === self::POST_TYPE && $candidate->post_status === 'publish' ? $candidate : null;
        } elseif ($atts['slug'] !== '') {
            $form = self::get_by_slug((string) $atts['slug']);
        }
        if (!$form) {
            return '';
        }
        return FormRenderer::render($form, $this->get_current_lang());
    }

    /* ------------------------------------------------------------------ */
    /* Admin: metaboxes                                                     */
    /* ------------------------------------------------------------------ */

    public function enqueue_admin(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || get_current_screen()?->post_type !== self::POST_TYPE) {
            return;
        }
        $path = '/assets/js/intelindev-form-editor.js';
        if (file_exists($this->plugin_path . ltrim($path, '/'))) {
            wp_enqueue_script('intelindev-form-editor', $this->plugin_url . ltrim($path, '/'), ['jquery', 'jquery-ui-sortable'], filemtime($this->plugin_path . ltrim($path, '/')), true);
        }
        $css = '/assets/css/intelindev-admin-fields.css';
        if (file_exists($this->plugin_path . ltrim($css, '/'))) {
            wp_enqueue_style('intelindev-admin-fields', $this->plugin_url . ltrim($css, '/'), [], filemtime($this->plugin_path . ltrim($css, '/')));
        }
    }

    public function add_meta_boxes(): void
    {
        add_meta_box('slugdiv', __('Slug = nombre en el shortcode', 'intelindev'), 'post_slug_meta_box', self::POST_TYPE, 'normal', 'high');
        add_meta_box('intelindev_form_fields', __('Campos', 'intelindev'), [$this, 'render_fields_meta_box'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('intelindev_form_settings', __('Envío', 'intelindev'), [$this, 'render_settings_meta_box'], self::POST_TYPE, 'normal', 'default');
    }

    public function show_slug_meta_box($hidden, $screen)
    {
        if ($screen instanceof WP_Screen && $screen->post_type === self::POST_TYPE) {
            $hidden = array_values(array_diff((array) $hidden, ['slugdiv']));
        }
        return $hidden;
    }

    public function render_fields_meta_box(WP_Post $post): void
    {
        $fields = self::get_fields($post->ID);
        $langs  = idml_get_languages();
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        if ($post->post_status === 'publish' && $post->post_name !== '') {
            echo '<p><strong>' . esc_html__('Shortcode:', 'intelindev') . '</strong> <code>[' . esc_html(self::SHORTCODE) . ' slug="' . esc_html($post->post_name) . '"]</code> — '
                . esc_html__('usalo en el modal del header, en una página, en un Componente o en un widget del footer.', 'intelindev') . '</p>';
        } else {
            echo '<p class="description">' . esc_html__('Al publicar, el shortcode será [form slug="…"] con el slug de la caja de arriba.', 'intelindev') . '</p>';
        }
        ?>
        <table class="widefat intelindev-form-fields" id="intelindev-form-fields">
            <thead>
                <tr>
                    <th style="width:28px"></th>
                    <th><?php esc_html_e('Campo', 'intelindev'); ?></th>
                    <th><?php esc_html_e('Textos por idioma', 'intelindev'); ?></th>
                    <th style="width:70px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $i => $field) { $this->render_field_row($i, $field, $langs); } ?>
            </tbody>
        </table>
        <p>
            <button type="button" class="button" id="intelindev-form-field-add"><?php esc_html_e('+ Agregar campo', 'intelindev'); ?></button>
            <span class="description"><?php esc_html_e('El nombre interno es el que llega en el correo y el webhook (solo minúsculas, números y _). El ancho usa el grid del theme.', 'intelindev'); ?></span>
        </p>
        <template id="intelindev-form-field-template"><?php $this->render_field_row(-1, ['type' => 'text', 'width' => 'col-12'], $langs); ?></template>
        <?php
    }

    private function render_field_row(int $index, array $field, array $langs): void
    {
        $i    = $index === -1 ? '__i__' : (string) $index;
        $base = self::FIELD . '[fields][' . $i . ']';
        $type = (string) ($field['type'] ?? 'text');
        $widths = [
            'col-12'          => __('Ancho completo', 'intelindev'),
            'col-12 col-md-6' => __('1/2 (md+)', 'intelindev'),
            'col-12 col-md-4' => __('1/3 (md+)', 'intelindev'),
            'col-12 col-md-8' => __('2/3 (md+)', 'intelindev'),
            'col-12 col-md-3' => __('1/4 (md+)', 'intelindev'),
        ];
        ?>
        <tr class="intelindev-form-field" data-type="<?php echo esc_attr($type); ?>">
            <td class="intelindev-form-field__handle" title="<?php esc_attr_e('Arrastrar para ordenar', 'intelindev'); ?>">☰</td>
            <td>
                <p><label><?php esc_html_e('Nombre interno', 'intelindev'); ?><br><input type="text" name="<?php echo esc_attr($base); ?>[name]" value="<?php echo esc_attr((string) ($field['name'] ?? '')); ?>" class="regular-text code" placeholder="nombre" pattern="[a-z0-9_]+" /></label></p>
                <p><label><?php esc_html_e('Tipo', 'intelindev'); ?><br>
                    <select name="<?php echo esc_attr($base); ?>[type]" class="intelindev-form-field__type">
                        <?php foreach (self::TYPES as $t) : ?><option value="<?php echo esc_attr($t); ?>"<?php selected($type, $t); ?>><?php echo esc_html($t); ?></option><?php endforeach; ?>
                    </select></label>
                </p>
                <p><label><?php esc_html_e('Ancho', 'intelindev'); ?><br>
                    <select name="<?php echo esc_attr($base); ?>[width]">
                        <?php foreach ($widths as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"<?php selected((string) ($field['width'] ?? 'col-12'), $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                    </select></label>
                </p>
                <p><label><input type="checkbox" name="<?php echo esc_attr($base); ?>[required]" value="1"<?php checked(!empty($field['required'])); ?> /> <?php esc_html_e('Obligatorio', 'intelindev'); ?></label></p>
                <p class="intelindev-form-field__only-hidden"><label><?php esc_html_e('Valor (hidden)', 'intelindev'); ?><br><input type="text" name="<?php echo esc_attr($base); ?>[value]" value="<?php echo esc_attr((string) ($field['value'] ?? '')); ?>" class="regular-text" /></label></p>
            </td>
            <td>
                <table class="intelindev-form-field__langs">
                    <?php foreach ($langs as $lang) : ?>
                    <tr>
                        <th><?php echo esc_html(strtoupper($lang)); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($base); ?>[label][<?php echo esc_attr($lang); ?>]" value="<?php echo esc_attr((string) ($field['label'][$lang] ?? '')); ?>" class="regular-text intelindev-form-field__not-hidden" placeholder="<?php esc_attr_e('Etiqueta', 'intelindev'); ?>" />
                            <input type="text" name="<?php echo esc_attr($base); ?>[placeholder][<?php echo esc_attr($lang); ?>]" value="<?php echo esc_attr((string) ($field['placeholder'][$lang] ?? '')); ?>" class="regular-text intelindev-form-field__not-hidden intelindev-form-field__not-checkbox" placeholder="<?php esc_attr_e('Placeholder', 'intelindev'); ?>" />
                            <textarea name="<?php echo esc_attr($base); ?>[options][<?php echo esc_attr($lang); ?>]" rows="3" class="regular-text intelindev-form-field__only-select" placeholder="<?php esc_attr_e('Opciones (una por línea)', 'intelindev'); ?>"><?php echo esc_textarea((string) ($field['options'][$lang] ?? '')); ?></textarea>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </td>
            <td><button type="button" class="button-link-delete intelindev-form-field-remove"><?php esc_html_e('Quitar', 'intelindev'); ?></button></td>
        </tr>
        <?php
    }

    public function render_settings_meta_box(WP_Post $post): void
    {
        $s      = self::get_settings($post->ID);
        $option = self::FIELD . '[settings]';
        $is_new = $post->post_status === 'auto-draft';
        $placeholders_button  = []; $placeholders_success = [];
        foreach (idml_get_languages() as $lang) {
            $placeholders_button[$lang]  = idml_t('form.default_button', $lang);
            $placeholders_success[$lang] = idml_t('form.default_success', $lang);
        }
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="intelindev_form_recipients"><?php esc_html_e('Destinatarios del correo', 'intelindev'); ?></label></th>
                <td>
                    <input type="text" id="intelindev_form_recipients" name="<?php echo esc_attr($option); ?>[recipients]" value="<?php echo esc_attr((string) ($s['recipients'] ?? '')); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" />
                    <p class="description"><?php esc_html_e('Separados por coma. Vacío = correo del administrador. El reply-to es el email del remitente si el formulario tiene un campo email.', 'intelindev'); ?></p>
                </td>
            </tr>
            <?php
            intelindev_admin_lang_text_fields($option, 'subject', __('Asunto del correo (%s)', 'intelindev'), $s, __('Admite {site} y {form}. Vacío = "[Sitio] Nombre del formulario".', 'intelindev'));
            intelindev_admin_lang_text_fields($option, 'button', __('Texto del botón (%s)', 'intelindev'), $s, '', '', $placeholders_button);
            intelindev_admin_lang_text_fields($option, 'success', __('Mensaje de éxito (%s)', 'intelindev'), $s, __('Vacío = default del theme (clave form.default_success en Traducciones).', 'intelindev'), '', $placeholders_success);
            ?>
            <tr>
                <th scope="row"><label for="intelindev_form_redirect"><?php esc_html_e('Redirigir al enviar', 'intelindev'); ?></label></th>
                <td><input type="url" id="intelindev_form_redirect" name="<?php echo esc_attr($option); ?>[redirect]" value="<?php echo esc_attr((string) ($s['redirect'] ?? '')); ?>" class="regular-text" placeholder="https://… (opcional)" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="intelindev_form_webhook"><?php esc_html_e('Webhook (CRM)', 'intelindev'); ?></label></th>
                <td>
                    <input type="url" id="intelindev_form_webhook" name="<?php echo esc_attr($option); ?>[webhook]" value="<?php echo esc_attr((string) ($s['webhook'] ?? '')); ?>" class="regular-text" placeholder="https://… (opcional)" />
                    <p class="description"><?php esc_html_e('Se hace POST con JSON {form, lang, page, submitted_at, fields} a esta URL en cada envío (GoHighLevel, Zapier, Make, etc.). El resultado queda registrado en el envío.', 'intelindev'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Guardar envíos', 'intelindev'); ?></th>
                <td><label><input type="checkbox" name="<?php echo esc_attr($option); ?>[store]" value="1"<?php checked($is_new || !empty($s['store'])); ?> /> <?php esc_html_e('Registrar cada envío en Formularios → Envíos', 'intelindev'); ?></label></td>
            </tr>
        </table>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /* Admin: guardado                                                      */
    /* ------------------------------------------------------------------ */

    public function save($post_id): void
    {
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $input = isset($_POST[self::FIELD]) && is_array($_POST[self::FIELD]) ? wp_unslash($_POST[self::FIELD]) : [];
        update_post_meta($post_id, self::META_FIELDS, self::sanitize_fields((array) ($input['fields'] ?? [])));
        update_post_meta($post_id, self::META_SETTINGS, self::sanitize_settings((array) ($input['settings'] ?? [])));
    }

    public static function sanitize_fields(array $rows): array
    {
        $langs  = idml_get_languages();
        $fields = [];
        $names  = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $name = preg_replace('/[^a-z0-9_]/', '', $name);
            $name = ltrim((string) $name, '_'); // los nombres con _ inicial son internos (_lang, _ts…)
            if ($name === '' || in_array($name, $names, true)) continue;
            $names[] = $name;

            $type  = in_array($row['type'] ?? '', self::TYPES, true) ? (string) $row['type'] : 'text';
            $width = in_array($row['width'] ?? '', self::WIDTHS, true) ? (string) $row['width'] : 'col-12';

            // 'label' admite <a>/<strong>/<em> (ej. "Acepto la <a href=…>política</a>");
            // placeholder es texto plano y options es multilínea.
            $lang_text = function ($raw, string $kind = 'text') use ($langs): array {
                $out = [];
                if (!is_array($raw)) return $out;
                foreach ($langs as $lang) {
                    $value = (string) ($raw[$lang] ?? '');
                    if ($kind === 'options') {
                        $value = sanitize_textarea_field($value);
                    } elseif ($kind === 'label') {
                        $value = trim(wp_kses($value, self::LABEL_TAGS));
                    } else {
                        $value = sanitize_text_field($value);
                    }
                    if (trim($value) !== '') $out[$lang] = $value;
                }
                return $out;
            };

            $fields[] = [
                'name'        => $name,
                'type'        => $type,
                'required'    => !empty($row['required']) ? 1 : 0,
                'width'       => $width,
                'label'       => $lang_text($row['label'] ?? [], 'label'),
                'placeholder' => $lang_text($row['placeholder'] ?? []),
                'options'     => $type === 'select' ? $lang_text($row['options'] ?? [], 'options') : [],
                'value'       => $type === 'hidden' ? sanitize_text_field((string) ($row['value'] ?? '')) : '',
            ];
        }
        return $fields;
    }

    public static function sanitize_settings(array $input): array
    {
        $emails = array_filter(array_map('sanitize_email', preg_split('/[,;\s]+/', (string) ($input['recipients'] ?? ''))));
        $emails = array_values(array_filter($emails, 'is_email'));
        $url = function ($raw): string {
            $url = esc_url_raw(trim((string) $raw));
            return preg_match('#^https?://#i', $url) ? $url : '';
        };
        return [
            'recipients' => implode(', ', $emails),
            'subject'    => intelindev_sanitize_lang_text($input['subject'] ?? []),
            'button'     => intelindev_sanitize_lang_text($input['button'] ?? []),
            'success'    => intelindev_sanitize_lang_text($input['success'] ?? []),
            'redirect'   => $url($input['redirect'] ?? ''),
            'webhook'    => $url($input['webhook'] ?? ''),
            'store'      => !empty($input['store']) ? 1 : 0,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Admin: listado                                                       */
    /* ------------------------------------------------------------------ */

    public function columns($columns)
    {
        $out = [];
        foreach ($columns as $key => $label) {
            $out[$key] = $label;
            if ($key === 'title') {
                $out['intelindev_shortcode'] = __('Shortcode', 'intelindev');
                $out['intelindev_fields']    = __('Campos', 'intelindev');
                $out['intelindev_entries']   = __('Envíos', 'intelindev');
            }
        }
        return $out;
    }

    public function render_column($column, $post_id): void
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) return;
        switch ($column) {
            case 'intelindev_shortcode':
                echo $post->post_status === 'publish'
                    ? '<code>[' . esc_html(self::SHORTCODE) . ' slug="' . esc_html($post->post_name) . '"]</code>'
                    : '<span class="description">' . esc_html__('Se activa al publicar', 'intelindev') . '</span>';
                break;
            case 'intelindev_fields':
                echo esc_html(implode(', ', array_column(self::get_fields($post->ID), 'name')));
                break;
            case 'intelindev_entries':
                $count = (int) (new \WP_Query([
                    'post_type' => SubmissionsController::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids',
                    'meta_key' => SubmissionsController::META_FORM, 'meta_value' => (int) $post->ID,
                ]))->found_posts;
                echo '<a href="' . esc_url(admin_url('edit.php?post_type=' . SubmissionsController::POST_TYPE . '&intelindev_form=' . (int) $post->ID)) . '">' . (int) $count . '</a>';
                break;
        }
    }
}
