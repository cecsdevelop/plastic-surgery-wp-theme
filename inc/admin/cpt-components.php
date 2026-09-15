<?php
/**
 * Componentes (CPT intelindev_component): plantillas HTML reutilizables con
 * placeholders, expuestas como shortcodes cuyo nombre es el slug del
 * componente ([hero …], [precios …]). Se usan en los bloques por idioma de
 * pages/posts, en el modal del header y en las áreas de widgets del footer.
 *
 * La plantilla es monolingüe a propósito: lo que cambia por idioma entra por
 * (a) el contexto del post actual ({title}, {featured_image}, {excerpt},
 * {permalink}), que ya llega traducido por los filtros del theme; (b) los
 * atributos del shortcode, escritos en el bloque de cada idioma
 * ([hero subtitle="…"]); (c) el diccionario ({t:clave} → idml_t()).
 *
 * Placeholders:
 *   {title} {excerpt} {permalink} {site_name} {lang}
 *   {featured_image} / {featured_image:large}   URL de la imagen destacada
 *   {nombre} / {nombre|default}                atributo del shortcode (escapado)
 *   {t:clave}                                  texto de Apariencia → Traducciones
 *   {content}                                  contenido envolvente [hero]…[/hero]
 *
 * Los shortcodes que haya dentro de la plantilla también se procesan (un
 * componente puede usar otro); hay guardia anti-recursión. Un slug que
 * coincide con un shortcode ya existente (ej. gallery) no lo pisa: el listado
 * lo marca como "en conflicto".
 *
 * @package intelindev
 */

if (!defined('ABSPATH')) exit;

const INTELINDEV_COMPONENT_CPT       = 'intelindev_component';
const INTELINDEV_COMPONENT_META      = '_intelindev_component_template';
const INTELINDEV_COMPONENT_TRANSIENT = 'intelindev_component_slugs';

/** Placeholders resueltos por el theme (no son atributos del shortcode). */
function intelindev_component_reserved_placeholders(): array {
    return ['title', 'excerpt', 'permalink', 'site_name', 'lang', 'featured_image', 'content', 't'];
}

/* ------------------------------------------------------------------ */
/* CPT                                                                  */
/* ------------------------------------------------------------------ */

add_action('init', 'intelindev_register_component_cpt', 5);

function intelindev_register_component_cpt(): void {
    register_post_type(INTELINDEV_COMPONENT_CPT, [
        'labels' => [
            'name'               => __('Componentes', 'intelindev'),
            'singular_name'      => __('Componente', 'intelindev'),
            'menu_name'          => __('Componentes', 'intelindev'),
            'add_new'            => __('Agregar componente', 'intelindev'),
            'add_new_item'       => __('Agregar componente', 'intelindev'),
            'edit_item'          => __('Editar componente', 'intelindev'),
            'new_item'           => __('Nuevo componente', 'intelindev'),
            'all_items'          => __('Todos los componentes', 'intelindev'),
            'search_items'       => __('Buscar componentes', 'intelindev'),
            'not_found'          => __('No hay componentes todavía.', 'intelindev'),
            'not_found_in_trash' => __('No hay componentes en la papelera.', 'intelindev'),
        ],
        'description'         => __('Plantillas HTML reutilizables que se insertan como shortcodes.', 'intelindev'),
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-layout',
        // Sin editor: la plantilla va en el metabox propio, como el contenido de
        // pages/posts. El slug (= nombre del shortcode) se edita en la caja nativa
        // "Slug", que abajo se fuerza visible y se retitula.
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ]);
}

/* ------------------------------------------------------------------ */
/* Registro de shortcodes                                               */
/* ------------------------------------------------------------------ */

/**
 * slug => ID de los componentes publicados. Transient sin expiración (vive
 * en options, autoload) invalidado al guardar/borrar/(des)enviar a papelera.
 */
function intelindev_get_component_slugs(): array {
    $slugs = get_transient(INTELINDEV_COMPONENT_TRANSIENT);
    if (is_array($slugs)) {
        return $slugs;
    }

    $slugs = [];
    $posts = get_posts([
        'post_type'              => INTELINDEV_COMPONENT_CPT,
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'orderby'                => 'title',
        'order'                  => 'ASC',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);
    foreach ($posts as $post) {
        if ($post->post_name !== '') {
            $slugs[$post->post_name] = (int) $post->ID;
        }
    }

    set_transient(INTELINDEV_COMPONENT_TRANSIENT, $slugs);
    return $slugs;
}

function intelindev_flush_component_slugs($post_id): void {
    if (get_post_type($post_id) === INTELINDEV_COMPONENT_CPT) {
        delete_transient(INTELINDEV_COMPONENT_TRANSIENT);
    }
}
foreach (['save_post_' . INTELINDEV_COMPONENT_CPT, 'trashed_post', 'untrashed_post', 'deleted_post'] as $hook) {
    add_action($hook, 'intelindev_flush_component_slugs');
}

/** Slugs registrados efectivamente como shortcode en este request. */
function intelindev_registered_component_slugs(): array {
    static $registered = [];
    if (func_num_args() === 1) {
        $registered[] = (string) func_get_arg(0);
    }
    return $registered;
}

add_action('init', function () {
    foreach (intelindev_get_component_slugs() as $slug => $id) {
        // No pisar shortcodes ajenos (core o plugins): el listado lo marca.
        if (shortcode_exists($slug)) {
            continue;
        }
        add_shortcode($slug, 'intelindev_render_component_shortcode');
        intelindev_registered_component_slugs($slug);
    }
}, 20);

/* ------------------------------------------------------------------ */
/* Render                                                               */
/* ------------------------------------------------------------------ */

function intelindev_render_component_shortcode($atts, $content = null, $tag = ''): string {
    static $stack = [];

    $slugs = intelindev_get_component_slugs();
    $id    = (int) ($slugs[(string) $tag] ?? 0);
    if ($id <= 0 || in_array($id, $stack, true)) {
        return ''; // desconocido o recursión ([hero] dentro de hero)
    }

    $template = (string) get_post_meta($id, INTELINDEV_COMPONENT_META, true);
    if (trim($template) === '') {
        return '';
    }

    $stack[] = $id;
    $html = intelindev_render_component_template($template, is_array($atts) ? $atts : [], (string) $content);
    $html = do_shortcode($html); // shortcodes dentro de la plantilla (otros componentes)
    array_pop($stack);

    return $html;
}

/** Post cuyo contexto alimenta {title}, {featured_image}, etc. */
function intelindev_component_context_post(): ?WP_Post {
    if (in_the_loop()) {
        $post = get_post();
        return $post instanceof WP_Post ? $post : null;
    }
    if (is_singular()) {
        $queried = get_queried_object();
        return $queried instanceof WP_Post ? $queried : null;
    }
    return null;
}

/**
 * Reemplaza los placeholders de la plantilla. Los valores de contexto llegan
 * ya listos para HTML (get_the_title, esc_url); los atributos del shortcode y
 * los textos del diccionario se escapan; {content} y los defaults inline son
 * parte de la plantilla y van tal cual.
 */
function intelindev_render_component_template(string $template, array $atts, string $content = ''): string {
    $post = intelindev_component_context_post();
    $lang = idml_get_current_language();

    $pattern = '/\{(t:[a-z0-9_.\-]+|featured_image(?::[a-z0-9_\-]+)?|[a-z0-9_]+)(?:\|([^{}]*))?\}/i';

    return (string) preg_replace_callback($pattern, function ($m) use ($post, $lang, $atts, $content) {
        $token   = strtolower($m[1]);
        $default = $m[2] ?? '';

        if (strpos($token, 't:') === 0) {
            $key = substr($token, 2);
            $text = idml_t($key, $lang);
            return $text !== $key ? esc_html($text) : $default;
        }

        if (strpos($token, 'featured_image') === 0) {
            if (!$post) return $default;
            $size = strpos($token, ':') !== false ? substr($token, strlen('featured_image:')) : 'full';
            $url  = get_the_post_thumbnail_url($post, $size);
            return $url ? esc_url($url) : $default;
        }

        switch ($token) {
            case 'title':
                return $post ? (string) get_the_title($post) : $default;
            case 'excerpt':
                if (!$post) return $default;
                $excerpt = function_exists('intelindev_get_post_translated_excerpt') ? intelindev_get_post_translated_excerpt($post, $lang) : '';
                if ($excerpt === '') $excerpt = (string) $post->post_excerpt;
                return $excerpt !== '' ? esc_html($excerpt) : $default;
            case 'permalink':
                return $post ? esc_url((string) get_permalink($post)) : $default;
            case 'site_name':
                return (string) get_bloginfo('name');
            case 'lang':
                return esc_html($lang);
            case 'content':
                return $content !== '' ? do_shortcode($content) : $default;
        }

        // Atributo del shortcode (WP los pasa en minúsculas).
        if (array_key_exists($token, $atts) && (string) $atts[$token] !== '') {
            return esc_html((string) $atts[$token]);
        }
        return $default;
    }, $template);
}

/** Atributos que acepta una plantilla (placeholders no reservados), ordenados. */
function intelindev_component_template_attributes(string $template): array {
    preg_match_all('/\{([a-z0-9_]+)(?:\|[^{}]*)?\}/i', $template, $m);
    $attrs = array_unique(array_map('strtolower', $m[1]));
    $attrs = array_diff($attrs, intelindev_component_reserved_placeholders());
    sort($attrs);
    return array_values($attrs);
}

/** Ejemplo de uso listo para copiar: [slug attr="" …]. */
function intelindev_component_shortcode_example(WP_Post $post): string {
    $attrs = intelindev_component_template_attributes((string) get_post_meta($post->ID, INTELINDEV_COMPONENT_META, true));
    $parts = [$post->post_name];
    foreach ($attrs as $attr) {
        $parts[] = $attr . '=""';
    }
    return '[' . implode(' ', $parts) . ']';
}

/* ------------------------------------------------------------------ */
/* Admin: metabox                                                       */
/* ------------------------------------------------------------------ */

add_action('add_meta_boxes_' . INTELINDEV_COMPONENT_CPT, function () {
    // La caja nativa "Slug" (slugdiv) es donde se define el nombre del shortcode:
    // se re-registra con título explícito y por encima de la plantilla.
    add_meta_box('slugdiv', __('Slug = nombre del shortcode', 'intelindev'), 'post_slug_meta_box', INTELINDEV_COMPONENT_CPT, 'normal', 'high');
    add_meta_box('intelindev_component_template', __('Plantilla del componente', 'intelindev'), 'intelindev_component_metabox_html', INTELINDEV_COMPONENT_CPT, 'normal', 'high');
});

// WP oculta slugdiv por defecto (Opciones de pantalla); acá es imprescindible.
add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if ($screen instanceof WP_Screen && $screen->post_type === INTELINDEV_COMPONENT_CPT) {
        $hidden = array_values(array_diff((array) $hidden, ['slugdiv']));
    }
    return $hidden;
}, 10, 2);

function intelindev_component_metabox_html(WP_Post $post): void {
    $template = (string) get_post_meta($post->ID, INTELINDEV_COMPONENT_META, true);
    $slugs    = intelindev_get_component_slugs();
    $conflict = $post->post_name !== '' && $post->post_status === 'publish'
        && !in_array($post->post_name, intelindev_registered_component_slugs(), true)
        && isset($slugs[$post->post_name]);
    wp_nonce_field('intelindev_component_save', 'intelindev_component_nonce');
    ?>
    <?php if ($post->post_status === 'publish' && $post->post_name !== '') : ?>
        <p>
            <strong><?php esc_html_e('Shortcode:', 'intelindev'); ?></strong>
            <code><?php echo esc_html(intelindev_component_shortcode_example($post)); ?></code>
            <?php if ($conflict) : ?>
                <span class="description" style="color:#b32d2e"><?php esc_html_e('— en conflicto: ya existe un shortcode con ese nombre (WordPress o un plugin). Cambiá el slug.', 'intelindev'); ?></span>
            <?php endif; ?>
        </p>
    <?php else : ?>
        <p class="description"><?php esc_html_e('Al publicar, el slug (caja "Slug" de abajo) pasa a ser el nombre del shortcode: [slug …].', 'intelindev'); ?></p>
    <?php endif; ?>

    <textarea name="intelindev_component_template" rows="18" class="large-text code" spellcheck="false" style="font-family:monospace"><?php echo esc_textarea($template); ?></textarea>

    <p class="description">
        <?php esc_html_e('HTML con placeholders. Del post donde se inserta (ya traducidos):', 'intelindev'); ?>
        <code>{title}</code> <code>{excerpt}</code> <code>{permalink}</code> <code>{featured_image}</code> <code>{featured_image:large}</code> <code>{site_name}</code> <code>{lang}</code>.
        <?php esc_html_e('Atributos del shortcode:', 'intelindev'); ?> <code>{nombre}</code> <?php esc_html_e('o con valor por defecto', 'intelindev'); ?> <code>{nombre|texto}</code>.
        <?php esc_html_e('Texto del diccionario (Apariencia → Traducciones):', 'intelindev'); ?> <code>{t:clave}</code>.
        <?php esc_html_e('Contenido envolvente', 'intelindev'); ?> <code>[slug]…[/slug]</code> → <code>{content}</code>.
        <?php esc_html_e('Los shortcodes dentro de la plantilla también se procesan.', 'intelindev'); ?>
    </p>
    <?php
}

add_action('save_post_' . INTELINDEV_COMPONENT_CPT, function ($post_id) {
    if (!isset($_POST['intelindev_component_nonce']) || !wp_verify_nonce($_POST['intelindev_component_nonce'], 'intelindev_component_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $raw = $_POST['intelindev_component_template'] ?? '';
    $template = function_exists('intelindev_sanitize_translated_rich_text')
        ? intelindev_sanitize_translated_rich_text($raw)
        : (current_user_can('unfiltered_html') ? wp_unslash((string) $raw) : wp_kses_post(wp_unslash((string) $raw)));

    if (trim($template) === '') {
        delete_post_meta($post_id, INTELINDEV_COMPONENT_META);
    } else {
        update_post_meta($post_id, INTELINDEV_COMPONENT_META, $template);
    }
});

/* ------------------------------------------------------------------ */
/* Admin: listado                                                       */
/* ------------------------------------------------------------------ */

add_filter('manage_' . INTELINDEV_COMPONENT_CPT . '_posts_columns', function ($columns) {
    $out = [];
    foreach ($columns as $key => $label) {
        $out[$key] = $label;
        if ($key === 'title') {
            $out['intelindev_shortcode'] = __('Shortcode', 'intelindev');
        }
    }
    return $out;
});

add_action('manage_' . INTELINDEV_COMPONENT_CPT . '_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'intelindev_shortcode') return;
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) return;

    if ($post->post_status !== 'publish') {
        echo '<span class="description">' . esc_html__('Se activa al publicar', 'intelindev') . '</span>';
        return;
    }
    echo '<code>' . esc_html(intelindev_component_shortcode_example($post)) . '</code>';
    if (!in_array($post->post_name, intelindev_registered_component_slugs(), true)) {
        echo ' <span style="color:#b32d2e">' . esc_html__('(en conflicto con otro shortcode)', 'intelindev') . '</span>';
    }
}, 10, 2);
