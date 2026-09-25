<?php
/**
 * Formularios (CPT pswpt_form): el admin arma el formulario desde el
 * dashboard (campos con etiqueta/placeholder/opciones por idioma, ancho con
 * el grid del theme, obligatorio) y lo inserta con [form slug="contacto"] en
 * el modal del header, páginas, Componentes o widgets del footer.
 *
 * Render: FormRenderer. Recepción (REST, antispam, validación, correo,
 * webhook): FormHandler. Registro de envíos: SubmissionsController.
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package pswpt
 */

namespace pswptInit\Forms;

use pswptInit\General\BaseController;
use WP_Post;
use WP_Screen;

class FormsController extends BaseController
{
    public const POST_TYPE     = 'pswpt_form';
    public const META_FIELDS   = '_pswpt_form_fields';
    public const META_SETTINGS = '_pswpt_form_settings';
    public const NONCE_ACTION  = 'pswpt_form_save';
    public const NONCE_FIELD   = 'pswpt_form_nonce';
    public const FIELD         = 'pswpt_form'; // name= raíz del formulario de edición
    public const SHORTCODE     = 'form';
    public const OPTION        = 'pswpt_forms_settings'; // ajustes globales (Turnstile)
    public const SETTINGS_PAGE = 'pswpt-forms-settings';

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
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

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
                'name'               => __('Forms', 'pswpt'),
                'singular_name'      => __('Form', 'pswpt'),
                'menu_name'          => __('Forms', 'pswpt'),
                'add_new'            => __('Add form', 'pswpt'),
                'add_new_item'       => __('Add form', 'pswpt'),
                'edit_item'          => __('Edit form', 'pswpt'),
                'all_items'          => __('All forms', 'pswpt'),
                'search_items'       => __('Search forms', 'pswpt'),
                'not_found'          => __('No forms yet.', 'pswpt'),
                'not_found_in_trash' => __('No forms found in Trash.', 'pswpt'),
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
        $text = pswpt_resolve_lang_text($field['options'] ?? [], $lang);
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
    /* Ajustes globales (Cloudflare Turnstile)                              */
    /* ------------------------------------------------------------------ */

    public static function get_global_settings(): array
    {
        $opts = get_option(self::OPTION, []);
        return is_array($opts) ? $opts : [];
    }

    /** true si hay site key y secret configurados. */
    public static function turnstile_available(): bool
    {
        $opts = self::get_global_settings();
        return trim((string) ($opts['turnstile_site_key'] ?? '')) !== '' && trim((string) ($opts['turnstile_secret'] ?? '')) !== '';
    }

    /** true si este formulario debe mostrar y verificar Turnstile. */
    public static function form_uses_turnstile(int $form_id): bool
    {
        return !empty(self::get_settings($form_id)['captcha']) && self::turnstile_available();
    }

    public function add_settings_page(): void
    {
        add_submenu_page('edit.php?post_type=' . self::POST_TYPE, __('Form Settings', 'pswpt'), __('Settings', 'pswpt'), 'manage_options', self::SETTINGS_PAGE, [$this, 'render_settings_page']);
    }

    public function register_settings(): void
    {
        register_setting(self::OPTION . '_group', self::OPTION, [$this, 'sanitize_global_settings']);
    }

    public function sanitize_global_settings($input): array
    {
        $input = is_array($input) ? $input : [];
        return [
            'turnstile_site_key' => sanitize_text_field((string) ($input['turnstile_site_key'] ?? '')),
            'turnstile_secret'   => sanitize_text_field((string) ($input['turnstile_secret'] ?? '')),
        ];
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) return;
        $opts = self::get_global_settings();
        if (isset($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'pswpt') . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Form Settings', 'pswpt'); ?></h1>
            <form action="options.php" method="post">
                <?php settings_fields(self::OPTION . '_group'); ?>
                <h2><?php esc_html_e('Cloudflare Turnstile (spam protection with verification)', 'pswpt'); ?></h2>
                <p class="description"><?php printf(esc_html__('Free and without Google cookies. Create a widget in %s ("Managed" type), paste its keys and enable "Protect with Turnstile" on each form that needs it. The Cloudflare script only loads where there is a protected form.', 'pswpt'), '<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">Cloudflare → Turnstile</a>'); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="pswpt_turnstile_site_key"><?php esc_html_e('Site key', 'pswpt'); ?></label></th>
                        <td><input type="text" id="pswpt_turnstile_site_key" name="<?php echo esc_attr(self::OPTION); ?>[turnstile_site_key]" value="<?php echo esc_attr((string) ($opts['turnstile_site_key'] ?? '')); ?>" class="regular-text code" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pswpt_turnstile_secret"><?php esc_html_e('Secret key', 'pswpt'); ?></label></th>
                        <td><input type="password" id="pswpt_turnstile_secret" name="<?php echo esc_attr(self::OPTION); ?>[turnstile_secret]" value="<?php echo esc_attr((string) ($opts['turnstile_secret'] ?? '')); ?>" class="regular-text code" autocomplete="off" /></td>
                    </tr>
                </table>
                <?php submit_button(__('Save', 'pswpt')); ?>
            </form>
        </div>
        <?php
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
        $path = '/assets/js/pswpt-form-editor.js';
        if (file_exists($this->plugin_path . ltrim($path, '/'))) {
            wp_enqueue_script('pswpt-form-editor', $this->plugin_url . ltrim($path, '/'), ['jquery', 'jquery-ui-sortable'], filemtime($this->plugin_path . ltrim($path, '/')), true);
        }
        $css = '/assets/css/pswpt-admin-fields.css';
        if (file_exists($this->plugin_path . ltrim($css, '/'))) {
            wp_enqueue_style('pswpt-admin-fields', $this->plugin_url . ltrim($css, '/'), [], filemtime($this->plugin_path . ltrim($css, '/')));
        }
    }

    public function add_meta_boxes(): void
    {
        add_meta_box('slugdiv', __('Slug = name in the shortcode', 'pswpt'), 'post_slug_meta_box', self::POST_TYPE, 'normal', 'high');
        add_meta_box('pswpt_form_fields', __('Fields', 'pswpt'), [$this, 'render_fields_meta_box'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('pswpt_form_settings', __('Submission', 'pswpt'), [$this, 'render_settings_meta_box'], self::POST_TYPE, 'normal', 'default');
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
            echo '<p><strong>' . esc_html__('Shortcode:', 'pswpt') . '</strong> <code>[' . esc_html(self::SHORTCODE) . ' slug="' . esc_html($post->post_name) . '"]</code> — '
                . esc_html__('use it in the header modal, on a page, in a Component or in a footer widget.', 'pswpt') . '</p>';
        } else {
            echo '<p class="description">' . esc_html__('Once published, the shortcode will be [form slug="…"] using the slug from the box above.', 'pswpt') . '</p>';
        }
        ?>
        <table class="widefat pswpt-form-fields" id="pswpt-form-fields">
            <thead>
                <tr>
                    <th style="width:28px"></th>
                    <th><?php esc_html_e('Field', 'pswpt'); ?></th>
                    <th><?php esc_html_e('Text per language', 'pswpt'); ?></th>
                    <th style="width:70px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $i => $field) { $this->render_field_row($i, $field, $langs); } ?>
            </tbody>
        </table>
        <p>
            <button type="button" class="button" id="pswpt-form-field-add"><?php esc_html_e('+ Add field', 'pswpt'); ?></button>
            <span class="description"><?php esc_html_e('The internal name is what appears in the email and webhook (lowercase letters, numbers and _ only). Width uses the theme grid.', 'pswpt'); ?></span>
        </p>
        <template id="pswpt-form-field-template"><?php $this->render_field_row(-1, ['type' => 'text', 'width' => 'col-12'], $langs); ?></template>
        <?php
    }

    private function render_field_row(int $index, array $field, array $langs): void
    {
        $i    = $index === -1 ? '__i__' : (string) $index;
        $base = self::FIELD . '[fields][' . $i . ']';
        $type = (string) ($field['type'] ?? 'text');
        $widths = [
            'col-12'          => __('Full width', 'pswpt'),
            'col-12 col-md-6' => __('1/2 (md+)', 'pswpt'),
            'col-12 col-md-4' => __('1/3 (md+)', 'pswpt'),
            'col-12 col-md-8' => __('2/3 (md+)', 'pswpt'),
            'col-12 col-md-3' => __('1/4 (md+)', 'pswpt'),
        ];
        ?>
        <tr class="pswpt-form-field" data-type="<?php echo esc_attr($type); ?>">
            <td class="pswpt-form-field__handle" title="<?php esc_attr_e('Drag to reorder', 'pswpt'); ?>">☰</td>
            <td>
                <p><label><?php esc_html_e('Internal name', 'pswpt'); ?><br><input type="text" name="<?php echo esc_attr($base); ?>[name]" value="<?php echo esc_attr((string) ($field['name'] ?? '')); ?>" class="regular-text code" placeholder="nombre" pattern="[a-z0-9_]+" /></label></p>
                <p><label><?php esc_html_e('Type', 'pswpt'); ?><br>
                    <select name="<?php echo esc_attr($base); ?>[type]" class="pswpt-form-field__type">
                        <?php foreach (self::TYPES as $t) : ?><option value="<?php echo esc_attr($t); ?>"<?php selected($type, $t); ?>><?php echo esc_html($t); ?></option><?php endforeach; ?>
                    </select></label>
                </p>
                <p><label><?php esc_html_e('Width', 'pswpt'); ?><br>
                    <select name="<?php echo esc_attr($base); ?>[width]">
                        <?php foreach ($widths as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"<?php selected((string) ($field['width'] ?? 'col-12'), $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                    </select></label>
                </p>
                <p><label><input type="checkbox" name="<?php echo esc_attr($base); ?>[required]" value="1"<?php checked(!empty($field['required'])); ?> /> <?php esc_html_e('Required', 'pswpt'); ?></label></p>
                <p class="pswpt-form-field__only-hidden"><label><?php esc_html_e('Value (hidden)', 'pswpt'); ?><br><input type="text" name="<?php echo esc_attr($base); ?>[value]" value="<?php echo esc_attr((string) ($field['value'] ?? '')); ?>" class="regular-text" /></label></p>
            </td>
            <td>
                <table class="pswpt-form-field__langs">
                    <?php foreach ($langs as $lang) : ?>
                    <tr>
                        <th><?php echo esc_html(strtoupper($lang)); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($base); ?>[label][<?php echo esc_attr($lang); ?>]" value="<?php echo esc_attr((string) ($field['label'][$lang] ?? '')); ?>" class="regular-text pswpt-form-field__not-hidden" placeholder="<?php esc_attr_e('Label', 'pswpt'); ?>" />
                            <input type="text" name="<?php echo esc_attr($base); ?>[placeholder][<?php echo esc_attr($lang); ?>]" value="<?php echo esc_attr((string) ($field['placeholder'][$lang] ?? '')); ?>" class="regular-text pswpt-form-field__not-hidden pswpt-form-field__not-checkbox" placeholder="<?php esc_attr_e('Placeholder', 'pswpt'); ?>" />
                            <textarea name="<?php echo esc_attr($base); ?>[options][<?php echo esc_attr($lang); ?>]" rows="3" class="regular-text pswpt-form-field__only-select" placeholder="<?php esc_attr_e('Options (one per line)', 'pswpt'); ?>"><?php echo esc_textarea((string) ($field['options'][$lang] ?? '')); ?></textarea>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </td>
            <td><button type="button" class="button-link-delete pswpt-form-field-remove"><?php esc_html_e('Remove', 'pswpt'); ?></button></td>
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
                <th scope="row"><label for="pswpt_form_recipients"><?php esc_html_e('Email recipients', 'pswpt'); ?></label></th>
                <td>
                    <input type="text" id="pswpt_form_recipients" name="<?php echo esc_attr($option); ?>[recipients]" value="<?php echo esc_attr((string) ($s['recipients'] ?? '')); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" />
                    <p class="description"><?php esc_html_e('Comma separated. Empty = admin email. Reply-to is the sender\'s email if the form has an email field.', 'pswpt'); ?></p>
                </td>
            </tr>
            <?php
            pswpt_admin_lang_text_fields($option, 'subject', __('Email subject (%s)', 'pswpt'), $s, __('Supports {site} and {form}. Empty = "[Site] Form name".', 'pswpt'));
            pswpt_admin_lang_text_fields($option, 'button', __('Button text (%s)', 'pswpt'), $s, '', '', $placeholders_button);
            pswpt_admin_lang_text_fields($option, 'success', __('Success message (%s)', 'pswpt'), $s, __('Empty = theme default (key form.default_success in Translations).', 'pswpt'), '', $placeholders_success);
            ?>
            <tr>
                <th scope="row"><label for="pswpt_form_redirect"><?php esc_html_e('Redirect on submit', 'pswpt'); ?></label></th>
                <td><input type="url" id="pswpt_form_redirect" name="<?php echo esc_attr($option); ?>[redirect]" value="<?php echo esc_attr((string) ($s['redirect'] ?? '')); ?>" class="regular-text" placeholder="https://… (opcional)" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="pswpt_form_webhook"><?php esc_html_e('Webhook (CRM)', 'pswpt'); ?></label></th>
                <td>
                    <input type="url" id="pswpt_form_webhook" name="<?php echo esc_attr($option); ?>[webhook]" value="<?php echo esc_attr((string) ($s['webhook'] ?? '')); ?>" class="regular-text" placeholder="https://… (opcional)" />
                    <p class="description"><?php esc_html_e('A JSON POST {form, lang, page, submitted_at, fields} is sent to this URL on every submission (GoHighLevel, Zapier, Make, etc.). The result is logged in the submission.', 'pswpt'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Turnstile', 'pswpt'); ?></th>
                <td>
                    <label><input type="checkbox" name="<?php echo esc_attr($option); ?>[captcha]" value="1"<?php checked(!empty($s['captcha'])); ?> /> <?php esc_html_e('Protect with Cloudflare Turnstile', 'pswpt'); ?></label>
                    <?php if (!self::turnstile_available()) : ?>
                        <p class="description"><?php printf(esc_html__('No effect until the keys are entered in %s.', 'pswpt'), '<a href="' . esc_url(admin_url('edit.php?post_type=' . self::POST_TYPE . '&page=' . self::SETTINGS_PAGE)) . '">' . esc_html__('Forms → Settings', 'pswpt') . '</a>'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Store submissions', 'pswpt'); ?></th>
                <td><label><input type="checkbox" name="<?php echo esc_attr($option); ?>[store]" value="1"<?php checked($is_new || !empty($s['store'])); ?> /> <?php esc_html_e('Log every submission in Forms → Submissions', 'pswpt'); ?></label></td>
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
            'subject'    => pswpt_sanitize_lang_text($input['subject'] ?? []),
            'button'     => pswpt_sanitize_lang_text($input['button'] ?? []),
            'success'    => pswpt_sanitize_lang_text($input['success'] ?? []),
            'redirect'   => $url($input['redirect'] ?? ''),
            'webhook'    => $url($input['webhook'] ?? ''),
            'store'      => !empty($input['store']) ? 1 : 0,
            'captcha'    => !empty($input['captcha']) ? 1 : 0,
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
                $out['pswpt_shortcode'] = __('Shortcode', 'pswpt');
                $out['pswpt_fields']    = __('Fields', 'pswpt');
                $out['pswpt_entries']   = __('Submissions', 'pswpt');
            }
        }
        return $out;
    }

    public function render_column($column, $post_id): void
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) return;
        switch ($column) {
            case 'pswpt_shortcode':
                echo $post->post_status === 'publish'
                    ? '<code>[' . esc_html(self::SHORTCODE) . ' slug="' . esc_html($post->post_name) . '"]</code>'
                    : '<span class="description">' . esc_html__('Activated on publish', 'pswpt') . '</span>';
                break;
            case 'pswpt_fields':
                echo esc_html(implode(', ', array_column(self::get_fields($post->ID), 'name')));
                break;
            case 'pswpt_entries':
                $count = (int) (new \WP_Query([
                    'post_type' => SubmissionsController::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids',
                    'meta_key' => SubmissionsController::META_FORM, 'meta_value' => (int) $post->ID,
                ]))->found_posts;
                echo '<a href="' . esc_url(admin_url('edit.php?post_type=' . SubmissionsController::POST_TYPE . '&pswpt_form=' . (int) $post->ID)) . '">' . (int) $count . '</a>';
                if ($count > 0 && current_user_can('manage_options')) {
                    echo ' · <a href="' . esc_url(SubmissionsController::export_url((int) $post->ID)) . '">CSV</a>';
                }
                break;
        }
    }
}
