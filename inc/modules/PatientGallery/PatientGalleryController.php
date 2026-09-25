<?php
/**
 * Galería de pacientes (CPT `patient_gallery`, sin prefijo `pswpt_` porque el
 * post type y las 7 taxonomías ya existen en producción con ~700+ casos
 * reales — se preservan los slugs tal cual para no perder URLs/datos al
 * migrar el plugin `patient-gallery` al theme). Cada caso es un par (o
 * varios) de fotos antes/después con atributos (procedimiento, edad,
 * género, altura, peso, BMI, cirujano) para filtrar el listado público.
 *
 * `patient_category` es jerárquica de verdad (procedimiento padre → hijo,
 * ver PatientGalleryRenderer::term_names() con $children_only); las otras 6
 * son valores planos — el plugin original las registraba todas como
 * jerárquicas por copiar/pegar, aquí se corrige el widget del admin
 * (checkbox-tree → tags) sin tocar los términos ni las relaciones ya
 * guardadas (el nombre de la taxonomía es lo único que WP usa para
 * resolverlas).
 *
 * Las fotos (`_patient_images`, JSON `[{before,after}]` con URLs de la
 * media library, no IDs), el resumen de tratamiento público
 * (`_patient_treatment_summary`, ej. "4 MOXI laser sessions...", se ve en la
 * ficha del single cuando está cargado) y la nota interna (`_case_notes`,
 * nunca publicada) no encajan en los tipos de campo genéricos de
 * ContentTypeController, así que viven en un metabox propio de este módulo
 * con su propio nonce/save; el resto (CPT, columnas, página de archivo) lo
 * hereda de la base, igual que Team/Surgeons/Testimonials.
 *
 * El HTML legado de `post_content` (~700 casos migrados desde el sitio
 * anterior en Astra + Beaver Builder) trae, escrito a mano y de forma
 * inconsistente entre casos (a veces `<strong>`, a veces `<b>`/`<span>`,
 * "Number of Treatments" en unos y "Post Op Duration" en otros, algunos con
 * enlaces de dominios de clínicas hermanas copiados por error), el resumen
 * del procedimiento, el dato de tratamiento y CTAs/enlaces de navegación
 * duplicados. Dada esa inconsistencia real (verificada leyendo varios casos),
 * este módulo NO intenta parsear esas etiquetas con regex — solo
 * `get_display_procedures()` (abajo) hace una limpieza puntual y segura
 * porque el problema ahí es estructural (taxonomía), no de texto libre.
 * `the_content()` se imprime tal cual (mismo criterio que cualquier otro
 * post) para no perder ni inventar información; el resumen de tratamiento
 * público es un campo nuevo, dedicado y opcional para casos nuevos/editados.
 *
 * Módulo CPT estándar del theme: ver la skill wp-theme-cpt-module.
 *
 * @package pswpt
 */

namespace pswptInit\PatientGallery;

use pswptInit\General\ContentTypeController;
use WP_Post;
use WP_Query;

class PatientGalleryController extends ContentTypeController
{
    public const POST_TYPE = 'patient_gallery';

    public const IMAGES_META_KEY    = '_patient_images';
    public const NOTES_META_KEY     = '_case_notes';
    public const TREATMENT_META_KEY = '_patient_treatment_summary';

    public const NONCE_ACTION = 'pswpt_patient_gallery_save';
    public const NONCE_FIELD  = 'pswpt_patient_gallery_nonce';

    public const CONSULT_PAGE_OPTION = 'pswpt_patient_gallery_consult_page';

    /** taxonomía => [hierarchical, param de filtro en el archive, labels es/en]. */
    public const TAXONOMIES = [
        'patient_category' => [
            'hierarchical' => true,
            'param'        => 'pgcat', // por term_id, no slug — mismo nombre y formato que ya usa production (y usaba cbb-gallery-override para su propio sitio).
            'labels'       => ['es' => ['name' => 'Categorías', 'singular' => 'Categoría'], 'en' => ['name' => 'Categories', 'singular' => 'Category']],
        ],
        'patient_age' => [
            'hierarchical' => false,
            'param'        => 'age',
            'labels'       => ['es' => ['name' => 'Edad', 'singular' => 'Edad'], 'en' => ['name' => 'Age', 'singular' => 'Age']],
        ],
        'patient_gender' => [
            'hierarchical' => false,
            'param'        => 'gender',
            'labels'       => ['es' => ['name' => 'Género', 'singular' => 'Género'], 'en' => ['name' => 'Gender', 'singular' => 'Gender']],
        ],
        'patient_height' => [
            'hierarchical' => false,
            'param'        => 'height',
            'labels'       => ['es' => ['name' => 'Altura', 'singular' => 'Altura'], 'en' => ['name' => 'Height', 'singular' => 'Height']],
        ],
        'patient_weight' => [
            'hierarchical' => false,
            'param'        => 'weight',
            'labels'       => ['es' => ['name' => 'Peso', 'singular' => 'Peso'], 'en' => ['name' => 'Weight', 'singular' => 'Weight']],
        ],
        'patient_bmi' => [
            'hierarchical' => false,
            'param'        => 'bmi',
            'labels'       => ['es' => ['name' => 'IMC', 'singular' => 'IMC'], 'en' => ['name' => 'BMI', 'singular' => 'BMI']],
        ],
        'patient_surgeon' => [
            'hierarchical' => false,
            'param'        => 'surgeon',
            'labels'       => ['es' => ['name' => 'Cirujano', 'singular' => 'Cirujano'], 'en' => ['name' => 'Surgeon', 'singular' => 'Surgeon']],
        ],
    ];

    /* ------------------------------------------------------------------ */
    /* Registro de hooks                                                    */
    /* ------------------------------------------------------------------ */

    public function register(): void
    {
        parent::register();

        add_action('init', [$this, 'register_taxonomies'], 6);

        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'add_case_meta_box']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_case_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('pre_get_posts', [$this, 'filter_archive_query']);
        add_action('template_redirect', [$this, 'redirect_legacy_urls']);
        add_action('admin_init', [$this, 'register_consult_page_setting']);
        add_action('rest_api_init', [new FavoritesHandler(), 'register_routes']);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_photos_column']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_photos_column'], 10, 2);

        add_filter('rank_math/sitemap/urlimages', [$this, 'add_sitemap_images'], 10, 2);
    }

    protected function config(): array
    {
        return [
            'post_type'       => self::POST_TYPE,
            'labels'          => [
                'es' => ['name' => 'Galería de pacientes', 'singular' => 'Caso'],
                'en' => ['name' => 'Patient Gallery', 'singular' => 'Case'],
            ],
            'description'     => __('Before/after patient cases: photos, procedure and attributes for the public gallery.', 'pswpt'),
            'public'          => true,
            'slugs'           => ['es' => 'galeria-de-pacientes', 'en' => 'patient-gallery'],
            'menu_icon'       => 'dashicons-format-gallery',
            'menu_position'   => 27,
            'thumbnail_label' => __('Cover photo', 'pswpt'),
            'fields'          => [],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Taxonomías                                                           */
    /* ------------------------------------------------------------------ */

    public function register_taxonomies(): void
    {
        $default = idml_get_default_language();

        foreach (self::TAXONOMIES as $taxonomy => $tax_cfg) {
            $labels = $tax_cfg['labels'][$default] ?? $tax_cfg['labels']['en'];

            register_taxonomy($taxonomy, self::POST_TYPE, [
                'labels' => [
                    'name'          => $labels['name'],
                    'singular_name' => $labels['singular'],
                ],
                'hierarchical' => (bool) $tax_cfg['hierarchical'],
                'public'       => true,
                'show_ui'      => true,
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Fotos antes/después y nota interna                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Pares antes/después de un caso, ya validados (nunca un par vacío).
     * Usado por el metabox del admin y por PatientGalleryRenderer.
     */
    public static function get_image_pairs($post): array
    {
        $post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
        $raw     = get_post_meta($post_id, self::IMAGES_META_KEY, true);
        $decoded = $raw ? json_decode((string) $raw, true) : [];
        if (!is_array($decoded)) {
            return [];
        }

        $pairs = [];
        foreach ($decoded as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $before = isset($pair['before']) ? (string) $pair['before'] : '';
            $after  = isset($pair['after']) ? (string) $pair['after'] : '';
            if ($before === '' && $after === '') {
                continue;
            }
            $pairs[] = ['before' => $before, 'after' => $after];
        }

        return $pairs;
    }

    /** Resumen de tratamiento público del campo dedicado (vacío si no se cargó). */
    public static function get_treatment_summary($post): string
    {
        $post_id = $post instanceof WP_Post ? $post->ID : (int) $post;

        return (string) get_post_meta($post_id, self::TREATMENT_META_KEY, true);
    }

    /**
     * Procedimientos reales de `patient_category` para este caso (para el
     * <h1>, las píldoras y el filtro "More … Cases"): términos hijo, es
     * decir con padre, igual que el admin original solo dejaba filtrar por
     * el hijo. Se excluye el grupo "Surgeon" — en los datos reales,
     * `patient_category` tiene un padre de nivel superior literalmente
     * llamado "5) Surgeon" (numerado a propósito para ordenar el checklist
     * del admin) cuyos hijos son nombres de cirujanos, no procedimientos
     * (confirmado con datos reales: el caso RE2326351 tiene "Genital
     * Lightening" Y "Dr. Sue Kafali" como hijos de `patient_category` — el
     * cirujano ya está en `patient_surgeon`, así que aquí sobra). Esto es
     * una limpieza segura porque el problema es estructural (un grupo con
     * nombre reconocible), no texto libre inconsistente entre casos.
     *
     * Segundo problema, distinto: un caso puede tener MÁS hijos asignados de
     * los que en realidad describe — verificado en el caso 2390489, que
     * carga 4 (Labiaplasty, Cleft Narrowing, Labia Majoraplasty, Vaginal
     * Rejuvenation, las 2 últimas son categorías "paraguas" de navegación/SEO
     * del archive) pero cuyo contenido y el diseño real en producción solo
     * hablan de 2 (Labiaplasty, Cleft Narrowing). No hay una bandera para
     * "cuáles son los procedimientos reales de este caso" más allá del propio
     * texto: cuando hay más de un candidato, se filtra a los que el nombre
     * del término aparece efectivamente en el contenido (contains, no exige
     * la etiqueta `<strong>` exacta — el markup varía demasiado entre casos
     * legados para exigir más, ver el docblock del archivo); si ninguno
     * aparece (caso nuevo/corto sin ese texto todavía) se listan todos los
     * candidatos en vez de dejar el título/píldoras vacíos.
     *
     * @return \WP_Term[]
     */
    public static function get_display_procedures($post): array
    {
        $terms = get_the_terms($post, 'patient_category');
        if (!$terms || is_wp_error($terms)) {
            return [];
        }

        $candidates = array_values(array_filter($terms, [self::class, 'is_real_procedure_term']));
        if (count($candidates) <= 1) {
            return $candidates;
        }

        $post_obj = $post instanceof WP_Post ? $post : get_post($post);
        $haystack = $post_obj instanceof WP_Post ? wp_strip_all_tags((string) $post_obj->post_content) : '';
        if ($haystack === '') {
            return $candidates;
        }

        $mentioned = array_values(array_filter($candidates, static fn($term) => stripos($haystack, $term->name) !== false));

        return $mentioned ?: $candidates;
    }

    /**
     * "Contenido narrativo" del caso: intro tomada del `post_content` legado
     * (primera línea/párrafo sin etiquetas), y para cada procedimiento
     * mostrado su definición. La definición sale, en orden de preferencia,
     * de la `description` del término `patient_category` (fuente única por
     * procedimiento, editable desde el admin de la taxonomía — confirmado
     * que 16 de 17 procedimientos reales ya la tienen redactada) y solo si
     * el término no tiene description cae al minado de `post_content` que
     * usaba este método antes (mismo criterio "contains" que
     * get_display_procedures(), por la inconsistencia de markup entre casos
     * legados — ver el docblock del archivo). Un procedimiento sin
     * description NI párrafo que lo mencione simplemente no aparece aquí
     * (no se inventa texto).
     *
     * @param \WP_Term[] $procedures
     * @return array{intro: string, definitions: array<int, string>} definitions: term_id => HTML saneado (wp_kses_post)
     */
    public static function parse_narrative($post, array $procedures): array
    {
        $post_obj = $post instanceof WP_Post ? $post : get_post($post);
        $content  = $post_obj instanceof WP_Post ? (string) $post_obj->post_content : '';

        $result = ['intro' => '', 'definitions' => []];
        if ($content !== '') {
            $paragraphs = preg_split('/\n\s*\n/', trim($content)) ?: [];
            if (isset($paragraphs[0])) {
                $result['intro'] = trim(wp_strip_all_tags($paragraphs[0]));
            }
        }

        $needs_content_mining = [];
        foreach ($procedures as $term) {
            if ($term->description !== '') {
                $result['definitions'][$term->term_id] = wp_kses_post($term->description);
            } else {
                $needs_content_mining[] = $term;
            }
        }

        if ($needs_content_mining && $content !== '') {
            // Arranca en el párrafo 1, no en el 0: la intro suele mencionar el
            // procedimiento en prosa ("... underwent genital lightening
            // treatment...") y quedaría también seleccionada como su propia
            // definición, duplicando el mismo texto dos veces en la narrativa.
            $rest = array_slice($paragraphs ?? [], 1);

            foreach ($needs_content_mining as $term) {
                foreach ($rest as $paragraph) {
                    if (stripos(wp_strip_all_tags($paragraph), $term->name) !== false) {
                        $result['definitions'][$term->term_id] = wp_kses_post($paragraph);
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * "Página de contacto de Patient Gallery" en Ajustes → Lectura (como
     * page_for_posts): un <select> con las páginas del sitio, propio de este
     * módulo — no el cta_url genérico del header, que puede apuntar a un
     * modal o una URL externa y no sirve para anexarle
     * ?favorited_gallery_cases=. Mismo patrón que
     * ContentTypeController::register_archive_page_setting().
     */
    public function register_consult_page_setting(): void
    {
        register_setting('reading', self::CONSULT_PAGE_OPTION, ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
        add_settings_field(self::CONSULT_PAGE_OPTION, __('Patient Gallery consultation page', 'pswpt'), function () {
            wp_dropdown_pages([
                'name'              => self::CONSULT_PAGE_OPTION,
                'id'                => self::CONSULT_PAGE_OPTION,
                'show_option_none'  => __('— Select —', 'pswpt'),
                'option_none_value' => 0,
                'selected'          => (int) get_option(self::CONSULT_PAGE_OPTION),
            ]);
            echo '<p class="description">' . esc_html__('The "Schedule a Consultation" button on each case page links here, with ?favorited_gallery_cases={case} appended so the form knows which case the visitor came from.', 'pswpt') . '</p>';
        }, 'reading', 'default');
    }

    /** Página elegida en Ajustes → Lectura, sin ningún caso marcado. '' si no hay página elegida (o está borrada/despublicada) — ahí el botón no se imprime. */
    public static function get_consult_base_url(): string
    {
        $page_id = (int) get_option(self::CONSULT_PAGE_OPTION);
        if ($page_id <= 0 || get_post_type($page_id) !== 'page' || get_post_status($page_id) !== 'publish') {
            return '';
        }

        return (string) get_permalink($page_id);
    }

    /** URL del CTA "Schedule a Consultation" de un caso puntual: la página elegida + ese caso marcado para el formulario de contacto. */
    public static function get_consult_url($post): string
    {
        $base = self::get_consult_base_url();
        if ($base === '') {
            return '';
        }

        $post_obj = $post instanceof WP_Post ? $post : get_post($post);

        return $post_obj instanceof WP_Post ? add_query_arg('favorited_gallery_cases', $post_obj->post_name, $base) : $base;
    }

    /** Término hijo de patient_category cuyo padre no es el grupo "Surgeon" (ver get_display_procedures()). */
    public static function is_real_procedure_term($term): bool
    {
        if (!($term instanceof \WP_Term) || (int) $term->parent === 0) {
            return false;
        }
        $parent = get_term($term->parent, 'patient_category');

        return !($parent instanceof \WP_Term && self::is_surgeon_group_name($parent->name));
    }

    /** "5) Surgeon" (u otro grupo cuyo nombre incluya "surgeon") no es un grupo de procedimientos. */
    private static function is_surgeon_group_name(string $name): bool
    {
        return stripos($name, 'surgeon') !== false;
    }

    /**
     * Grupos de nivel superior de `patient_category` (para las pestañas
     * .pg-cat del filtro real): igual que producción, sin el grupo
     * "Surgeon" (el cirujano se filtra aparte, con sus propios chips —
     * confirmado navegando el sitio real: sus 4 pestañas son "Labiaplasty &
     * Vaginal Rejuvenation", "Non Surgical Procedures", "Injectables",
     * "Other", nunca "Surgeon").
     *
     * @return \WP_Term[]
     */
    public static function get_procedure_groups(): array
    {
        $groups = get_terms(['taxonomy' => 'patient_category', 'parent' => 0, 'hide_empty' => true]);
        if (!$groups || is_wp_error($groups)) {
            return [];
        }

        return array_values(array_filter($groups, static fn($term) => !self::is_surgeon_group_name($term->name)));
    }

    /**
     * Nombre del grupo sin el prefijo numérico administrativo ("1) Labiaplasty
     * & Vaginal Rejuvenation" → "Labiaplasty & Vaginal Rejuvenation") — ese
     * número solo existe para ordenar el checklist del admin (y, de paso, ya
     * ordena las pestañas igual que en producción: 1,2,3,4 alfabético =
     * numérico), nunca se muestra en el frontend.
     */
    public static function strip_group_prefix(string $name): string
    {
        return (string) preg_replace('/^\d+\)\s*/', '', $name);
    }

    public function add_case_meta_box(): void
    {
        add_meta_box(
            'pswpt_patient_gallery_case',
            __('Case details', 'pswpt'),
            [$this, 'render_case_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_case_meta_box(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        $pairs     = self::get_image_pairs($post);
        $treatment = self::get_treatment_summary($post);
        $notes     = (string) get_post_meta($post->ID, self::NOTES_META_KEY, true);
        ?>
        <div class="pswpt-pg-pairs" data-pswpt-pg-pairs>
            <p class="description"><?php esc_html_e('One row per before/after photo pair shown on the case page.', 'pswpt'); ?></p>
            <div class="pswpt-pg-pairs__list">
                <?php foreach ($pairs as $index => $pair) : ?>
                    <?php $this->render_pair_row($index, $pair); ?>
                <?php endforeach; ?>
            </div>
            <p><button type="button" class="button pswpt-pg-pairs__add"><?php esc_html_e('Add photo pair', 'pswpt'); ?></button></p>
            <template class="pswpt-pg-pair-template"><?php $this->render_pair_row(0, ['before' => '', 'after' => '']); ?></template>
        </div>
        <p class="pswpt-pg-treatment">
            <label for="pswpt_pg_treatment"><strong><?php esc_html_e('Treatment summary', 'pswpt'); ?></strong></label><br />
            <input type="text" id="pswpt_pg_treatment" name="pswpt_pg_treatment" class="large-text" value="<?php echo esc_attr($treatment); ?>" placeholder="<?php esc_attr_e('e.g. 4 MOXI laser sessions and topical lightening cream', 'pswpt'); ?>" /><br />
            <span class="description"><?php esc_html_e('Optional. Shown publicly on the case page next to Age/Gender/Surgeon. Leave blank to hide that row.', 'pswpt'); ?></span>
        </p>
        <p class="pswpt-pg-notes">
            <label for="pswpt_pg_notes"><strong><?php esc_html_e('Internal notes', 'pswpt'); ?></strong></label><br />
            <textarea id="pswpt_pg_notes" name="pswpt_pg_notes" rows="3" class="large-text"><?php echo esc_textarea($notes); ?></textarea><br />
            <span class="description"><?php esc_html_e('For staff reference only — never shown on the public case page.', 'pswpt'); ?></span>
        </p>
        <?php
    }

    private function render_pair_row(int $index, array $pair): void
    {
        $before = (string) ($pair['before'] ?? '');
        $after  = (string) ($pair['after'] ?? '');
        ?>
        <div class="pswpt-pg-pair" data-pswpt-pg-pair>
            <?php foreach (['before' => $before, 'after' => $after] as $side => $url) : ?>
                <div class="pswpt-pg-pair__image" data-side="<?php echo esc_attr($side); ?>">
                    <img class="pswpt-pg-pair__preview" src="<?php echo esc_url($url); ?>" alt="" <?php echo $url === '' ? 'hidden' : ''; ?> />
                    <input type="hidden" class="pswpt-pg-pair__url" name="pswpt_pg_pairs[<?php echo (int) $index; ?>][<?php echo esc_attr($side); ?>]" value="<?php echo esc_attr($url); ?>" />
                    <p>
                        <button type="button" class="button pswpt-pg-pair__upload"><?php echo esc_html($side === 'before' ? __('Before photo', 'pswpt') : __('After photo', 'pswpt')); ?></button>
                        <button type="button" class="button-link-delete pswpt-pg-pair__clear" <?php echo $url === '' ? 'hidden' : ''; ?>><?php esc_html_e('Remove', 'pswpt'); ?></button>
                    </p>
                </div>
            <?php endforeach; ?>
            <button type="button" class="button-link-delete pswpt-pg-pair__remove"><?php esc_html_e('Remove pair', 'pswpt'); ?></button>
        </div>
        <?php
    }

    public function save_case_meta($post_id): void
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

        $pairs_input = isset($_POST['pswpt_pg_pairs']) && is_array($_POST['pswpt_pg_pairs']) ? wp_unslash($_POST['pswpt_pg_pairs']) : [];
        $pairs = [];
        foreach ($pairs_input as $pair) {
            $before = esc_url_raw((string) ($pair['before'] ?? ''));
            $after  = esc_url_raw((string) ($pair['after'] ?? ''));
            if ($before === '' && $after === '') {
                continue;
            }
            $pairs[] = ['before' => $before, 'after' => $after];
        }

        if ($pairs) {
            update_post_meta($post_id, self::IMAGES_META_KEY, wp_json_encode($pairs));
        } else {
            delete_post_meta($post_id, self::IMAGES_META_KEY);
        }

        $treatment = isset($_POST['pswpt_pg_treatment']) ? sanitize_text_field(wp_unslash($_POST['pswpt_pg_treatment'])) : '';
        if ($treatment !== '') {
            update_post_meta($post_id, self::TREATMENT_META_KEY, $treatment);
        } else {
            delete_post_meta($post_id, self::TREATMENT_META_KEY);
        }

        $notes = isset($_POST['pswpt_pg_notes']) ? sanitize_textarea_field(wp_unslash($_POST['pswpt_pg_notes'])) : '';
        if ($notes !== '') {
            update_post_meta($post_id, self::NOTES_META_KEY, $notes);
        } else {
            delete_post_meta($post_id, self::NOTES_META_KEY);
        }
    }

    public function enqueue_admin_assets(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== self::POST_TYPE) {
            return;
        }

        wp_enqueue_media();

        $css = $this->plugin_path . 'assets/css/pswpt-patient-gallery-admin.css';
        if (file_exists($css)) {
            wp_enqueue_style('pswpt-patient-gallery-admin', $this->plugin_url . 'assets/css/pswpt-patient-gallery-admin.css', [], filemtime($css));
        }

        $js = $this->plugin_path . 'assets/js/pswpt-patient-gallery-admin.js';
        if (file_exists($js)) {
            wp_enqueue_script('pswpt-patient-gallery-admin', $this->plugin_url . 'assets/js/pswpt-patient-gallery-admin.js', ['jquery'], filemtime($js), true);
            wp_localize_script('pswpt-patient-gallery-admin', 'pswptPatientGallery', [
                'mediaTitle'  => __('Select photo', 'pswpt'),
                'mediaButton' => __('Use this photo', 'pswpt'),
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Frontend: assets condicionales y filtro del archive                  */
    /* ------------------------------------------------------------------ */

    /**
     * Igual que SurgeonsController::enqueue_hero_css(): sin detección de
     * shortcodes por página (no existe todavía, ver AGENTS.md "CSS and JS"),
     * pero el archive/single de un CPT es siempre esta misma plantilla, así
     * que is_post_type_archive()/is_singular() ya es la detección exacta.
     */
    public function enqueue_frontend_assets(): void
    {
        if (is_post_type_archive(self::POST_TYPE)) {
            $this->enqueue_section('patient-gallery-archive');
        } elseif (is_singular(self::POST_TYPE)) {
            $this->enqueue_section('patient-gallery-single');
        }
    }

    private function enqueue_section(string $slug): void
    {
        $css = $this->plugin_path . 'assets/css/sections/' . $slug . '.css';
        if (file_exists($css)) {
            wp_enqueue_style('pswpt-section-' . $slug, $this->plugin_url . 'assets/css/sections/' . $slug . '.css', [], filemtime($css));
        }

        $js = $this->plugin_path . 'assets/js/sections/' . $slug . '.js';
        if (file_exists($js)) {
            wp_enqueue_script('pswpt-section-' . $slug, $this->plugin_url . 'assets/js/sections/' . $slug . '.js', [], filemtime($js), ['in_footer' => true, 'strategy' => 'defer']);
        }
    }

    /**
     * Sin paginar ni filtrar server-side: igual que producción, las ~211
     * fichas se entregan todas y el filtro por categoría/cirujano
     * (PatientGalleryRenderer::filter_box_html() + patient-gallery-archive.js)
     * las muestra/oculta 100% client-side, incluido el deep-link `?pgcat=`
     * (leído por JS del propio location.search, no server-side). Confirmado
     * navegando producción: filtrar no cambia la URL ni recarga la página.
     */
    public function filter_archive_query(WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive(self::POST_TYPE)) {
            return;
        }

        $query->set('posts_per_page', -1);
    }

    /**
     * Consolidación de URLs viejas (portado de `fs-gallery-override`, el
     * plugin que servía esto en producción antes de que el archive/single
     * vivieran en el theme): sin esto, estas rutas legadas mostrarían
     * contenido duplicado o un 404 en vez de aterrizar en el listado real.
     *
     * - `/patient-gallery/page/2/`: el archive ya entrega todos los casos sin
     *   paginar (filter_archive_query()), así que una página 2 solo repetiría
     *   la misma grilla completa.
     * - `/patient_category/{slug}/`: el archive de la taxonomía (pública para
     *   que `pgcat` funcione) es contenido duplicado del listado filtrado;
     *   se colapsa al mismo `?pgcat={term_id}` que ya resuelve el filtro real.
     * - `/patient-gallery-menu/`: página-hub de navegación por categoría del
     *   sitio anterior (Astra + Beaver Builder), sustituida por el filtro
     *   de dos niveles del archive actual.
     */
    public function redirect_legacy_urls(): void
    {
        if (is_post_type_archive(self::POST_TYPE) && !is_feed() && get_query_var('paged') > 1) {
            wp_safe_redirect((string) get_post_type_archive_link(self::POST_TYPE), 301);
            exit;
        }

        if (is_tax('patient_category') && !is_feed()) {
            $target = (string) get_post_type_archive_link(self::POST_TYPE);
            $term   = get_queried_object();
            if ($term instanceof \WP_Term) {
                $target = add_query_arg('pgcat', $term->term_id, $target);
            }
            wp_safe_redirect($target, 301);
            exit;
        }

        if (is_page('patient-gallery-menu')) {
            wp_safe_redirect((string) get_post_type_archive_link(self::POST_TYPE), 301);
            exit;
        }
    }

    /**
     * Fotos de cada caso al sitemap de imágenes de Rank Math (portado de
     * `fs-gallery-override`): sin este filtro, Rank Math no lee
     * `_patient_images` por su cuenta y las fotos de antes/después quedan
     * invisibles para el sitemap — no las indexa Google Images.
     */
    public function add_sitemap_images(array $images, int $post_id): array
    {
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return $images;
        }

        $title = get_the_title($post_id);
        foreach (self::get_image_pairs($post_id) as $pair) {
            foreach (['before' => $pair['before'], 'after' => $pair['after']] as $label => $url) {
                if ($url !== '') {
                    $images[] = ['src' => $url, 'title' => $title . ' ' . ucfirst($label)];
                }
            }
        }

        return $images;
    }

    /* ------------------------------------------------------------------ */
    /* Listado del admin                                                    */
    /* ------------------------------------------------------------------ */

    public function add_photos_column($columns)
    {
        $out = [];
        foreach ((array) $columns as $key => $label) {
            $out[$key] = $label;
            if ($key === 'title') {
                $out['pswpt_pg_photos'] = __('Photos', 'pswpt');
            }
        }

        return $out;
    }

    public function render_photos_column($column, $post_id): void
    {
        if ($column !== 'pswpt_pg_photos') {
            return;
        }
        $count = count(self::get_image_pairs((int) $post_id));
        echo $count > 0 ? esc_html(sprintf(_n('%d pair', '%d pairs', $count, 'pswpt'), $count)) : '&mdash;';
    }
}
