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
 *   {nombre:html}   atributo con HTML inline permitido (em/strong/br/a/span) y
 *                   *texto* → <em>texto</em>: para acentos en títulos
 *   {nombre:url}    atributo como URL (esc_url)
 *   {nombre:icon}   atributo como ícono: URL, o nombre de assets/img/icons/{nombre}.svg
 *   {t:clave}                                  texto de Apariencia → Traducciones
 *   {content}                                  contenido envolvente [hero]…[/hero]

 *

 * Los shortcodes que haya dentro de la plantilla también se procesan (un
 * componente puede usar otro); hay guardia anti-recursión. Un slug que
 * coincide con un shortcode ya existente (ej. gallery) no lo pisa: el listado
 * lo marca como "en conflicto".
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Components;

use IntelindevInit\General\BaseController;
use WP_Post;
use WP_Screen;

class ComponentsController extends BaseController
{
    public const POST_TYPE     = 'intelindev_component';
    public const META_TEMPLATE = '_intelindev_component_template';
    public const TRANSIENT     = 'intelindev_component_slugs';
    public const NONCE_ACTION  = 'intelindev_component_save';
    public const NONCE_FIELD   = 'intelindev_component_nonce';
    public const FIELD_TEMPLATE = 'intelindev_component_template';

    /** Placeholders resueltos por el theme (no son atributos del shortcode). */
    public const RESERVED_PLACEHOLDERS = ['title', 'excerpt', 'permalink', 'site_name', 'lang', 'featured_image', 'content', 't'];

    /** IDs en render (guardia anti-recursión). */
    private array $render_stack = [];

    /* ------------------------------------------------------------------ */
    /* Registro de hooks                                                    */
    /* ------------------------------------------------------------------ */

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type'], 5);
        add_action('init', [$this, 'register_shortcodes'], 20);

        foreach (['save_post_' . self::POST_TYPE, 'trashed_post', 'untrashed_post', 'deleted_post'] as $hook) {
            add_action($hook, [$this, 'flush_slugs']);
        }

        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'add_meta_boxes']);
        add_filter('default_hidden_meta_boxes', [$this, 'show_slug_meta_box'], 10, 2);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save']);

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
            'show_in_rest'        => false, // editor clásico: la plantilla va en el metabox propio
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-layout',
            // Sin editor: la plantilla va en el metabox propio, como el contenido de
            // pages/posts. El slug (= nombre del shortcode) se edita en la caja nativa
            // "Slug", que se fuerza visible y se retitula en add_meta_boxes().
            'supports'            => ['title'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Shortcodes                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * slug => ID de los componentes publicados. Transient sin expiración (vive
     * en options, autoload) invalidado al guardar/borrar/(des)enviar a papelera.
     */
    public function get_slugs(): array
    {
        $slugs = get_transient(self::TRANSIENT);
        if (is_array($slugs)) {
            return $slugs;
        }

        $slugs = [];
        $posts = get_posts([
            'post_type'              => self::POST_TYPE,
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

        set_transient(self::TRANSIENT, $slugs);
        return $slugs;
    }

    public function flush_slugs($post_id): void
    {
        if (get_post_type($post_id) === self::POST_TYPE) {
            delete_transient(self::TRANSIENT);
        }
    }

    public function register_shortcodes(): void
    {
        foreach ($this->get_slugs() as $slug => $id) {
            // No pisar shortcodes ajenos (core o plugins): el listado lo marca.
            if (shortcode_exists($slug)) {
                continue;
            }
            add_shortcode($slug, [$this, 'render_shortcode']);
        }
    }

    /**
     * true si el shortcode $slug está registrado por este módulo (y no por WP o
     * un plugin). Consulta el registro global, así no depende de la instancia.
     */
    public static function is_registered(string $slug): bool
    {
        global $shortcode_tags;
        $callback = $shortcode_tags[$slug] ?? null;
        return is_array($callback) && ($callback[0] ?? null) instanceof self;
    }

    /* ------------------------------------------------------------------ */
    /* Render                                                               */
    /* ------------------------------------------------------------------ */

    public function render_shortcode($atts, $content = null, $tag = ''): string
    {
        $slugs = $this->get_slugs();
        $id    = (int) ($slugs[(string) $tag] ?? 0);
        if ($id <= 0 || in_array($id, $this->render_stack, true)) {
            return ''; // desconocido o recursión ([hero] dentro de hero)
        }

        $template = (string) get_post_meta($id, self::META_TEMPLATE, true);
        if (trim($template) === '') {
            return '';
        }

        $this->render_stack[] = $id;
        $html = $this->render_template($template, is_array($atts) ? $atts : [], (string) $content);
        $html = do_shortcode($html); // shortcodes dentro de la plantilla (otros componentes)
        $html = self::strip_empty_background_image($html);
        array_pop($this->render_stack);

        return $html;
    }

    /** Post cuyo contexto alimenta {title}, {featured_image}, etc. */
    private function context_post(): ?WP_Post
    {
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
    public function render_template(string $template, array $atts, string $content = ''): string
    {
        $post = $this->context_post();
        $lang = $this->get_current_lang();

        // El default admite un placeholder anidado ({title:html|{title}}, {eyebrow|{t:clave}}).
        $pattern = '/\{(t:[a-z0-9_.\-]+|featured_image(?::[a-z0-9_\-]+)?|[a-z0-9_]+(?::(?:html|url|icon))?)(?:\|((?:[^{}]|\{[^{}]*\})*))?\}/i';

        return (string) preg_replace_callback($pattern, function ($m) use ($post, $lang, $atts, $content) {
            $token   = strtolower($m[1]);
            $default = $m[2] ?? '';
            if ($default !== '' && strpos($default, '{') !== false) {
                $default = $this->render_template($default, $atts, $content);
            }

            if (strpos($token, 't:') === 0) {
                $key  = substr($token, 2);
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

            // Atributo del shortcode (WP los pasa en minúsculas), con modificador opcional.
            $modifier = '';
            if (strpos($token, ':') !== false) {
                [$token, $modifier] = explode(':', $token, 2);
            }
            $value = array_key_exists($token, $atts) ? (string) $atts[$token] : '';
            if ($value === '') {
                // Default: crudo si era un placeholder anidado (ya viene resuelto/escapado);
                // si no, se trata como valor del modificador (nombre de ícono, URL, HTML).
                $nested = isset($m[2]) && strpos($m[2], '{') !== false;
                return $modifier !== '' && !$nested ? self::format_attribute($default, $modifier) : $default;
            }
            return self::format_attribute($value, $modifier);
        }, $template);
    }

    /**
     * Un placeholder de imagen sin valor ({image:url|{featured_image}} en una
     * página sin imagen) dejaría background-image:url('') en el style inline:
     * fuera la declaración (y el style si queda vacío), no una URL vacía.
     */
    public static function strip_empty_background_image(string $html): string
    {
        if (strpos($html, 'background-image') === false) {
            return $html;
        }
        $html = (string) preg_replace('#background-image\s*:\s*url\(\s*([\'"]?)\1\s*\)\s*;?\s*#i', '', $html);

        return (string) preg_replace('#\s+style=(["\'])\s*\1#', '', $html);
    }

    /** Escapa/convierte el valor de un atributo según su modificador. */
    public static function format_attribute(string $value, string $modifier): string
    {
        switch ($modifier) {
            case 'html':
                $value = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $value);
                return wp_kses($value, ['em' => [], 'strong' => [], 'br' => [], 'span' => ['class' => []], 'a' => ['href' => [], 'class' => [], 'target' => [], 'rel' => []]]);
            case 'url':
                return esc_url($value);
            case 'icon':
                if (preg_match('#^(https?:)?//|^/#', $value)) {
                    return esc_url($value);
                }
                $name = sanitize_key($value);
                return $name !== '' ? esc_url(get_template_directory_uri() . '/assets/img/icons/' . $name . '.svg') : '';
            default:
                return esc_html($value);
        }
    }

    /** Atributos que acepta una plantilla (placeholders no reservados), ordenados. */
    public function template_attributes(string $template): array
    {
        // Con modificador ({title:html}) siempre es atributo, aunque el nombre
        // coincida con un placeholder de contexto ({title} = título del post).
        preg_match_all('/\{([a-z0-9_]+)(:(?:html|url|icon))?(?:\|(?:[^{}]|\{[^{}]*\})*)?\}/i', $template, $m, PREG_SET_ORDER);
        $attrs = [];
        foreach ($m as $match) {
            $name = strtolower($match[1]);
            if (empty($match[2]) && in_array($name, self::RESERVED_PLACEHOLDERS, true)) {
                continue;
            }
            $attrs[$name] = true;
        }
        $attrs = array_keys($attrs);
        sort($attrs);
        return $attrs;
    }

    /** Ejemplo de uso listo para copiar: [slug attr="" …]. */
    public function shortcode_example(WP_Post $post): string
    {
        $attrs = $this->template_attributes((string) get_post_meta($post->ID, self::META_TEMPLATE, true));
        $parts = [$post->post_name];
        foreach ($attrs as $attr) {
            $parts[] = $attr . '=""';
        }
        return '[' . implode(' ', $parts) . ']';
    }

    /* ------------------------------------------------------------------ */
    /* Admin: metabox                                                       */
    /* ------------------------------------------------------------------ */

    public function add_meta_boxes(): void
    {
        // La caja nativa "Slug" (slugdiv) es donde se define el nombre del shortcode:
        // se re-registra con título explícito y por encima de la plantilla.
        add_meta_box('slugdiv', __('Slug = nombre del shortcode', 'intelindev'), 'post_slug_meta_box', self::POST_TYPE, 'normal', 'high');
        add_meta_box('intelindev_component_template', __('Plantilla del componente', 'intelindev'), [$this, 'render_meta_box'], self::POST_TYPE, 'normal', 'high');
    }

    /** WP oculta slugdiv por defecto (Opciones de pantalla); acá es imprescindible. */
    public function show_slug_meta_box($hidden, $screen)
    {
        if ($screen instanceof WP_Screen && $screen->post_type === self::POST_TYPE) {
            $hidden = array_values(array_diff((array) $hidden, ['slugdiv']));
        }
        return $hidden;
    }

    public function render_meta_box(WP_Post $post): void
    {
        $template = (string) get_post_meta($post->ID, self::META_TEMPLATE, true);
        $conflict = $post->post_name !== '' && $post->post_status === 'publish'
            && !self::is_registered($post->post_name)
            && isset($this->get_slugs()[$post->post_name]);
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        ?>
        <?php if ($post->post_status === 'publish' && $post->post_name !== '') : ?>
            <p>
                <strong><?php esc_html_e('Shortcode:', 'intelindev'); ?></strong>
                <code><?php echo esc_html($this->shortcode_example($post)); ?></code>
                <?php if ($conflict) : ?>
                    <span class="description" style="color:#b32d2e"><?php esc_html_e('— en conflicto: ya existe un shortcode con ese nombre (WordPress o un plugin). Cambiá el slug.', 'intelindev'); ?></span>
                <?php endif; ?>
            </p>
        <?php else : ?>
            <p class="description"><?php esc_html_e('Al publicar, el slug (caja "Slug" de arriba) pasa a ser el nombre del shortcode: [slug …].', 'intelindev'); ?></p>
        <?php endif; ?>

        <textarea name="<?php echo esc_attr(self::FIELD_TEMPLATE); ?>" rows="18" class="large-text code" spellcheck="false" style="font-family:monospace"><?php echo esc_textarea($template); ?></textarea>

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

    public function save($post_id): void
    {
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $raw = $_POST[self::FIELD_TEMPLATE] ?? '';
        // Misma política que el contenido de pages/posts: tal cual con
        // unfiltered_html, si no wp_kses_post.
        $template = function_exists('intelindev_sanitize_translated_rich_text')
            ? intelindev_sanitize_translated_rich_text($raw)
            : (current_user_can('unfiltered_html') ? wp_unslash((string) $raw) : wp_kses_post(wp_unslash((string) $raw)));

        if (trim($template) === '') {
            delete_post_meta($post_id, self::META_TEMPLATE);
        } else {
            update_post_meta($post_id, self::META_TEMPLATE, $template);
        }
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
            }
        }
        return $out;
    }

    public function render_column($column, $post_id): void
    {
        if ($column !== 'intelindev_shortcode') return;
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) return;

        if ($post->post_status !== 'publish') {
            echo '<span class="description">' . esc_html__('Se activa al publicar', 'intelindev') . '</span>';
            return;
        }
        echo '<code>' . esc_html($this->shortcode_example($post)) . '</code>';
        if (!self::is_registered($post->post_name)) {
            echo ' <span style="color:#b32d2e">' . esc_html__('(en conflicto con otro shortcode)', 'intelindev') . '</span>';
        }
    }
}
