<?php
/**
 * Envíos (CPT intelindev_entry): registro de cada envío de formulario,
 * solo lectura, bajo el menú Formularios. Guarda los datos limpios por campo
 * y el contexto (idioma, página, IP, resultado del correo y del webhook).
 *
 * @package Intelindev
 */

namespace IntelindevInit\Forms;

use IntelindevInit\General\BaseController;
use WP_Post;
use WP_Query;

class SubmissionsController extends BaseController
{
    public const POST_TYPE    = 'intelindev_entry'; // máx. 20 caracteres para un post type
    public const META_FORM    = '_intelindev_submission_form';
    public const META_DATA    = '_intelindev_submission_data';
    public const META_CONTEXT = '_intelindev_submission_context';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type'], 6);
        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'add_meta_boxes']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'filter_dropdown']);
        add_action('pre_get_posts', [$this, 'apply_filter']);
    }

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Envíos', 'intelindev'),
                'singular_name' => __('Envío', 'intelindev'),
                'menu_name'     => __('Envíos', 'intelindev'),
                'all_items'     => __('Envíos', 'intelindev'),
                'edit_item'     => __('Ver envío', 'intelindev'),
                'search_items'  => __('Buscar envíos', 'intelindev'),
                'not_found'     => __('Todavía no hay envíos.', 'intelindev'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=' . FormsController::POST_TYPE,
            'show_in_rest'        => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'supports'            => ['title'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'capabilities'        => ['create_posts' => 'do_not_allow'], // solo se crean desde el frontend
        ]);
    }

    /** Guarda un envío y devuelve su ID. */
    public static function store(WP_Post $form, array $data, array $context): int
    {
        $summary = '';
        foreach (FormsController::get_fields($form->ID) as $field) {
            if (in_array($field['type'], ['email', 'text'], true) && ($data[$field['name']] ?? '') !== '') {
                $summary = (string) $data[$field['name']];
                break;
            }
        }
        $title = $form->post_title . ' · ' . ($summary !== '' ? $summary : current_time('Y-m-d H:i'));

        $id = wp_insert_post([
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => sanitize_text_field($title),
        ], true);
        if (is_wp_error($id) || !$id) {
            return 0;
        }

        update_post_meta($id, self::META_FORM, (int) $form->ID);
        update_post_meta($id, self::META_DATA, $data);
        update_post_meta($id, self::META_CONTEXT, $context);

        return (int) $id;
    }

    /* ------------------------------------------------------------------ */
    /* Admin                                                                */
    /* ------------------------------------------------------------------ */

    public function add_meta_boxes(): void
    {
        remove_meta_box('slugdiv', self::POST_TYPE, 'normal');
        add_meta_box('intelindev_submission_data', __('Datos del envío', 'intelindev'), [$this, 'render_meta_box'], self::POST_TYPE, 'normal', 'high');
    }

    public function render_meta_box(WP_Post $post): void
    {
        $form_id = (int) get_post_meta($post->ID, self::META_FORM, true);
        $data    = (array) get_post_meta($post->ID, self::META_DATA, true);
        $context = (array) get_post_meta($post->ID, self::META_CONTEXT, true);
        $form    = $form_id ? get_post($form_id) : null;
        $fields  = $form instanceof WP_Post ? FormsController::get_fields($form_id) : [];
        $lang    = idml_get_default_language();
        ?>
        <table class="widefat striped">
            <tbody>
                <?php foreach ($data as $name => $value) :
                    $label = $name;
                    foreach ($fields as $field) {
                        if ($field['name'] === $name) {
                            $l = intelindev_resolve_lang_text($field['label'] ?? [], $lang);
                            if ($l !== '') $label = $l;
                            if ($field['type'] === 'checkbox') $value = $value === '1' ? __('Sí', 'intelindev') : __('No', 'intelindev');
                            break;
                        }
                    }
                ?>
                <tr><th scope="row" style="width:220px"><?php echo esc_html($label); ?></th><td><?php echo nl2br(esc_html((string) $value)); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <h3><?php esc_html_e('Contexto', 'intelindev'); ?></h3>
        <table class="widefat striped">
            <tbody>
                <tr><th scope="row" style="width:220px"><?php esc_html_e('Formulario', 'intelindev'); ?></th><td><?php echo $form instanceof WP_Post ? '<a href="' . esc_url(get_edit_post_link($form)) . '">' . esc_html($form->post_title) . '</a>' : '—'; ?></td></tr>
                <tr><th scope="row"><?php esc_html_e('Idioma', 'intelindev'); ?></th><td><?php echo esc_html(strtoupper((string) ($context['lang'] ?? ''))); ?></td></tr>
                <tr><th scope="row"><?php esc_html_e('Página', 'intelindev'); ?></th><td><?php echo !empty($context['page']) ? '<a href="' . esc_url($context['page']) . '">' . esc_html($context['page']) . '</a>' : '—'; ?></td></tr>
                <tr><th scope="row"><?php esc_html_e('IP', 'intelindev'); ?></th><td><?php echo esc_html((string) ($context['ip'] ?? '')); ?></td></tr>
                <tr><th scope="row"><?php esc_html_e('Correo', 'intelindev'); ?></th><td><?php echo isset($context['mail']['sent']) ? esc_html(($context['mail']['sent'] ? __('enviado a', 'intelindev') : __('FALLÓ hacia', 'intelindev')) . ' ' . $context['mail']['to']) : '—'; ?></td></tr>
                <tr><th scope="row"><?php esc_html_e('Webhook', 'intelindev'); ?></th><td><?php echo isset($context['webhook']['status']) ? esc_html('HTTP ' . $context['webhook']['status'] . ($context['webhook']['error'] !== '' ? ' — ' . $context['webhook']['error'] : '')) : '—'; ?></td></tr>
            </tbody>
        </table>
        <?php
    }

    public function columns($columns)
    {
        $out = [];
        foreach ($columns as $key => $label) {
            $out[$key] = $label;
            if ($key === 'title') {
                $out['intelindev_form'] = __('Formulario', 'intelindev');
                $out['intelindev_status'] = __('Correo / Webhook', 'intelindev');
            }
        }
        return $out;
    }

    public function render_column($column, $post_id): void
    {
        if ($column === 'intelindev_form') {
            $form = get_post((int) get_post_meta($post_id, self::META_FORM, true));
            echo $form instanceof WP_Post ? esc_html($form->post_title) : '—';
        }
        if ($column === 'intelindev_status') {
            $context = (array) get_post_meta($post_id, self::META_CONTEXT, true);
            $mail = isset($context['mail']['sent']) ? ($context['mail']['sent'] ? '✓' : '✗') : '—';
            $hook = isset($context['webhook']['status']) ? 'HTTP ' . (int) $context['webhook']['status'] : '—';
            echo esc_html($mail . ' / ' . $hook);
        }
    }

    public function filter_dropdown(string $post_type): void
    {
        if ($post_type !== self::POST_TYPE) return;
        $current = (int) ($_GET['intelindev_form'] ?? 0);
        $forms   = get_posts(['post_type' => FormsController::POST_TYPE, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        echo '<select name="intelindev_form"><option value="0">' . esc_html__('Todos los formularios', 'intelindev') . '</option>';
        foreach ($forms as $form) {
            printf('<option value="%d"%s>%s</option>', (int) $form->ID, selected($current, (int) $form->ID, false), esc_html($form->post_title));
        }
        echo '</select>';
    }

    public function apply_filter(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== self::POST_TYPE) return;
        $form_id = (int) ($_GET['intelindev_form'] ?? 0);
        if ($form_id > 0) {
            $query->set('meta_key', self::META_FORM);
            $query->set('meta_value', $form_id);
        }
    }
}
