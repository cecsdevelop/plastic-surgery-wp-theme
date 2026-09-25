<?php
/**
 * Presentación pura del CPT `patient_gallery`: tarjeta y filtros del listado,
 * y el detalle del caso en el single. Sin estado propio (métodos estáticos)
 * — todo sale de WP_Post/taxonomías/$_GET, así que se puede llamar directo
 * desde archive-patient_gallery.php / single-patient_gallery.php.
 *
 * El single reproduce el HTML real de producción en femsculpt.com al
 * detalle — mismas clases (`pgd-*`, `pg-lb-*`) y mismo orden, tal como el
 * cliente lo pegó del DOM en vivo el 2026-09-25 (`.pgd-save`, el lightbox
 * `.pg-lb` que recorre TODAS las fotos del caso con flechas, la mobile bar,
 * el JSON-LD ImageGallery) — no una interpretación con nombres BEM propios
 * como la primera versión de este módulo. El comportamiento (lightbox,
 * guardado local) vive en patient-gallery-single.js.
 *
 * @package pswpt
 */

namespace pswptInit\PatientGallery;

use WP_Post;

class PatientGalleryRenderer
{
    /* ------------------------------------------------------------------ */
    /* Archive: filtros + tarjeta                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Filtro real de dos niveles: pestañas de categoría (.pg-cat) que
     * revelan una fila de chips de sub-procedimiento (.pg-chip), más una
     * fila de chips de cirujano — mismas clases/orden que producción
     * (navegado en vivo el 2026-09-25). El comportamiento (mostrar/ocultar,
     * deep-link ?pgcat=, contador) vive en patient-gallery-archive.js;
     * PHP solo imprime la estructura y las opciones reales.
     *
     * El orden de los chips dentro de cada grupo es alfabético (orderby
     * name, default de get_terms()): producción usa un orden manual propio
     * que esta consulta no puede reproducir sin esa misma fuente de datos —
     * ver conversación.
     */
    public static function filter_box_html(): string
    {
        $groups = PatientGalleryController::get_procedure_groups();
        if (!$groups) {
            return '';
        }

        $tabs    = '<button type="button" role="tab" aria-selected="true" class="pg-cat active" data-filter="all" data-label="' . esc_attr__('All', 'pswpt') . '">' . esc_html__('All', 'pswpt') . '</button>';
        $subrows = '';
        foreach ($groups as $group) {
            $label = PatientGalleryController::strip_group_prefix($group->name);
            $tabs .= '<button type="button" role="tab" aria-selected="false" class="pg-cat" data-filter="' . (int) $group->term_id . '" data-label="' . esc_attr($label) . '">' . esc_html($label) . '</button>';

            $children = get_terms(['taxonomy' => 'patient_category', 'parent' => $group->term_id, 'hide_empty' => true]);
            if (!$children || is_wp_error($children)) {
                continue;
            }
            $chips = '';
            foreach ($children as $child) {
                $chips .= '<button type="button" class="pg-chip" data-sub-filter="' . (int) $child->term_id . '">' . esc_html($child->name) . '</button>';
            }
            $subrows .= '<div class="pg-subrow pg-subs-group" data-parent="' . (int) $group->term_id . '" role="group" aria-label="' . esc_attr(sprintf(__('Filter %s procedures', 'pswpt'), $label)) . '" hidden>' . $chips . '</div>';
        }

        $surgeons    = get_terms(['taxonomy' => 'patient_surgeon', 'hide_empty' => true]);
        $surgeon_row = '';
        if ($surgeons && !is_wp_error($surgeons) && $surgeons) {
            $chips = '';
            foreach ($surgeons as $surgeon) {
                $chips .= '<button type="button" class="pg-chip" data-surgeon-filter="' . esc_attr($surgeon->name) . '">' . esc_html($surgeon->name) . '</button>';
            }
            $surgeon_row = '<div class="pg-subrow" id="pg-surgeons" role="group" aria-label="' . esc_attr__('Filter by surgeon', 'pswpt') . '"><span class="pg-sublabel">' . esc_html__('Surgeon', 'pswpt') . '</span>' . $chips . '</div>';
        }

        // Plantillas para patient-gallery-archive.js, en data-* (no variables
        // globales, ver AGENTS.md "CSS and JS"): %d/%visible%/%total% los
        // reemplaza el JS al recalcular el contador.
        $count_tpl = '<p class="pg-count" id="pg-count" aria-live="polite"'
            . ' data-tpl-all="' . esc_attr__('Showing %d cases', 'pswpt') . '"'
            . ' data-tpl-one="' . esc_attr__('Showing 1 case', 'pswpt') . '"'
            . ' data-tpl-filtered="' . esc_attr__('Showing %visible% of %total%', 'pswpt') . '"'
            . ' data-clear-label="' . esc_attr__('Clear all', 'pswpt') . '"'
            . ' data-favorites-label="' . esc_attr__('Favorites', 'pswpt') . '"></p>';

        return '<div class="pg-filterbox">'
            . '<div class="pg-sheet-head"><span>' . esc_html__('Filter Cases', 'pswpt') . '</span><button type="button" class="pg-sheet-close" id="pg-sheet-close" aria-label="' . esc_attr__('Close filters', 'pswpt') . '">&times;</button></div>'
            . '<div class="pg-cats" role="tablist" aria-label="' . esc_attr__('Filter by procedure', 'pswpt') . '">' . $tabs . '</div>'
            . '<hr class="pg-divider" id="pg-divider">'
            . $subrows
            . $surgeon_row
            . '<p class="pg-hint" id="pg-hint">' . esc_html__('Tap a category to filter. Tap again to clear.', 'pswpt') . '</p>'
            . $count_tpl
            . '<button type="button" class="pg-sheet-apply" id="pg-sheet-apply" data-tpl="' . esc_attr__('Show %d Cases', 'pswpt') . '"></button>'
            . '</div>';
    }

    /**
     * Barra sticky de mobile del ARCHIVE (no confundir con el
     * mobilebar_html() privado de más abajo, que es el del single —
     * `.pgd-mobilebar` — mismo nombre de concepto, HTML distinto: ver
     * archive_mobilebar_html()). Navegada en vivo el 2026-09-25 en 375px:
     * "Filters" abre `.pg-filterbox` como hoja de pantalla completa
     * (`.pg-rebuild.pg-sheet-open`, ya resuelto por los botones
     * `.pg-sheet-close`/`.pg-sheet-apply` de filter_box_html()), el contador
     * refleja los casos visibles, y "Saved (N)" es un atajo a la misma
     * vista "solo guardados" que `#pg-view-saved`. Ojo: la franja pegada
     * abajo del todo con teléfono + "Book Consultation" que se ve en la
     * misma captura (`.fsc-bar`) es el módulo Sticky CTA del theme, global y
     * ya existente — no es parte de esto.
     */
    public static function archive_mobilebar_html(): string
    {
        return '<div class="pg-mobilebar" id="pg-mobilebar">'
            . '<button type="button" class="pg-mb-filters" id="pg-mb-filters">' . esc_html__('Filters', 'pswpt') . '</button>'
            . '<span class="pg-mb-count" id="pg-mb-count" data-tpl="' . esc_attr__('%d cases', 'pswpt') . '"></span>'
            . '<button type="button" class="pg-mb-saved" id="pg-mb-saved">' . esc_html__('Saved', 'pswpt') . ' (<span id="pg-mb-savedn">0</span>)</button>'
            . '</div>';
    }

    /**
     * Barra de "casos guardados": cuenta + acciones (ver todos los guardados,
     * pedir el email, ir a consulta, vaciar la lista) y el form de envío por
     * email — mismas clases/orden que producción (navegado en vivo el
     * 2026-09-25, con 0 y con 1 guardado, para confirmar qué cambia). Los
     * botones de acción y el form arrancan `hidden`: patient-gallery-archive.js
     * los destapa cuando `pswptSavedGalleryCases` (localStorage) deja de estar
     * vacío, igual que en producción. El backend del email vive en
     * FavoritesHandler (REST pswpt/v1/patient-gallery/favorites).
     */
    public static function saved_bar_html(): string
    {
        $ts          = time();
        $sig         = FavoritesHandler::sign($ts);
        $consult_url = PatientGalleryController::get_consult_base_url();

        $hint_empty  = __('Tap <strong>Save</strong> on any photo to build your list — we\'ll keep it here for you.', 'pswpt');
        $hint_filled = __('Email your list to yourself or bring it to your consultation.', 'pswpt');

        return '<div class="pg-saved-bar" id="pg-saved-bar" data-endpoint="' . esc_url(rest_url('pswpt/v1/patient-gallery/favorites')) . '">'
            . '<div class="pg-saved-head">'
            . '<span class="pg-saved-title"><svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true" focusable="false"><path d="M6 2h12v20l-6-4.5L6 22V2z" fill="#7A3230"></path></svg>' . esc_html__('Saved Cases', 'pswpt') . ' (<span id="pg-fav-count">0</span>)</span>'
            . '<div class="pg-saved-btns">'
            . '<button type="button" class="pg-fav-cta" id="pg-view-saved" hidden>' . esc_html__('View Saved', 'pswpt') . '</button>'
            . '<button type="button" class="pg-fav-cta" id="pg-fav-emailbtn" hidden>' . esc_html__('Email Me My Favorites', 'pswpt') . '</button>'
            . ($consult_url !== '' ? '<a class="pg-fav-cta" id="pg-fav-consult" href="' . esc_url($consult_url) . '" hidden>' . esc_html__('Book a Consultation', 'pswpt') . '</a>' : '')
            . '<button type="button" class="pg-clear-saved" id="pg-clear-saved" hidden>' . esc_html__('Clear saved list', 'pswpt') . '</button>'
            . '</div></div>'
            . '<p class="pg-saved-hint" id="pg-saved-hint" data-hint-empty="' . esc_attr($hint_empty) . '" data-hint-filled="' . esc_attr($hint_filled) . '">' . wp_kses($hint_empty, ['strong' => []]) . '</p>'
            . self::favorites_form_html($ts, $sig)
            . '</div>';
    }

    private static function favorites_form_html(int $ts, string $sig): string
    {
        return '<form class="pg-fav-email" id="pg-fav-email" hidden novalidate data-error="' . esc_attr__('Something went wrong. Please try again.', 'pswpt') . '">'
            . '<div class="pg-fav-email-row">'
            . '<input type="text" id="pg-fee-name" name="first_name" placeholder="' . esc_attr__('First name', 'pswpt') . '" required autocomplete="given-name" aria-label="' . esc_attr__('First name', 'pswpt') . '">'
            . '<input type="text" id="pg-fee-lname" name="last_name" placeholder="' . esc_attr__('Last name', 'pswpt') . '" required autocomplete="family-name" aria-label="' . esc_attr__('Last name', 'pswpt') . '">'
            . '</div>'
            . '<div class="pg-fav-email-row">'
            . '<input type="email" id="pg-fee-mail" name="email" placeholder="' . esc_attr__('Email address', 'pswpt') . '" required autocomplete="email" aria-label="' . esc_attr__('Email address', 'pswpt') . '">'
            . '<input type="tel" id="pg-fee-phone" name="phone" placeholder="' . esc_attr__('Phone (optional)', 'pswpt') . '" autocomplete="tel" aria-label="' . esc_attr__('Phone (optional)', 'pswpt') . '">'
            . '</div>'
            . '<div class="pg-fav-email-row"><label class="pg-fee-consult-row"><input type="checkbox" id="pg-fee-wantconsult" name="want_consult"> <span>' . esc_html__('Yes — I\'d like to schedule a consultation about my saved cases', 'pswpt') . '</span></label></div>'
            . '<input type="hidden" name="_ts" value="' . (int) $ts . '">'
            . '<input type="hidden" name="_sig" value="' . esc_attr($sig) . '">'
            . '<div class="pswpt-form__hp" aria-hidden="true"><label>Website <input type="text" name="_website" tabindex="-1" autocomplete="off"></label></div>'
            . '<div class="pg-fav-email-row"><button type="submit" class="pg-fee-send" id="pg-fee-send">' . esc_html__('Send My Favorites', 'pswpt') . '</button></div>'
            . '<p class="pg-fee-note">' . esc_html__('We\'ll email you a private link to your saved cases.', 'pswpt') . '</p>'
            . '<p class="pg-fee-status" id="pg-fee-status" role="status" aria-live="polite"></p>'
            . '</form>';
    }

    /**
     * Intro clara del archive (sin página asignada en Ajustes → Lectura):
     * mismo texto/clases que femsculpt.com/patient-gallery/ en producción —
     * "Before & After Gallery" es copy propio de esta página, no el nombre
     * del post type (que sigue siendo "Patient Gallery" en el admin).
     */
    public static function intro_html(): string
    {
        return '<div class="pg-intro"><h1>' . esc_html__('Before & After Gallery', 'pswpt') . '</h1>'
            . '<p>' . esc_html__('Real results from real FemSculpt patients in Chicago. Browse by procedure to see what\'s possible.', 'pswpt') . '</p></div>';
    }

    /**
     * Tarjeta del listado — mismas clases/orden que producción (.pg-card,
     * .pgi/.pgb/.pga, .pgf, .pgm/.pgt, .pgl) y los mismos data-* que
     * alimentan su filtro de dos niveles, aunque ese filtro todavía no está
     * conectado acá (ver docblock de archive-patient_gallery.php):
     * data-parents/data-cats = TODOS los términos hijo de patient_category
     * del caso, sin filtrar por "procedimiento real" (a diferencia de
     * .pgt/el <h1> del single, que sí usan get_display_procedures() — el
     * filtro por categoría necesita poder encontrar el caso incluso por una
     * categoría "paraguas" que no es la que se describe en el texto).
     * .pgf reusa el mismo guardado local que el single (misma clave de
     * localStorage, ver patient-gallery-archive.js).
     */
    public static function card_html(WP_Post $post, int $index): string
    {
        $slug      = (string) $post->post_name;
        $title     = get_the_title($post);
        $permalink = (string) get_permalink($post);

        $all_terms  = get_the_terms($post, 'patient_category') ?: [];
        $cat_ids    = [];
        $parent_ids = [];
        foreach ($all_terms as $term) {
            if ((int) $term->parent === 0) {
                continue;
            }
            $cat_ids[]    = $term->term_id;
            $parent_ids[] = $term->parent;
        }

        $surgeon = implode(', ', wp_list_pluck(get_the_terms($post, 'patient_surgeon') ?: [], 'name'));

        $procedures     = PatientGalleryController::get_display_procedures($post);
        $procedureTitle = $procedures ? implode(' & ', wp_list_pluck($procedures, 'name')) : $title;
        $tag            = $procedures ? $procedures[0]->name : '';

        $pairs  = PatientGalleryController::get_image_pairs($post);
        $pair   = $pairs[0] ?? ['before' => '', 'after' => ''];
        $before = self::image_attrs((string) $pair['before'], 'medium_large');
        $after  = self::image_attrs((string) $pair['after'], 'medium_large');
        // Solo la primera fila (visible sin scroll) va eager; el resto lazy
        // — a diferencia del HTML real (todo eager, porque ahí las 211
        // fichas ya están en el DOM sin paginar). Ver AGENTS.md "Performance first".
        $loading = $index < 6 ? 'eager' : 'lazy';

        $photo_html = static function (array $image, string $side, string $class) use ($procedureTitle, $title, $loading): string {
            if ($image['src'] === '') {
                return '<span class="' . esc_attr($class) . '"></span>';
            }
            $alt = $image['alt'] !== '' ? $image['alt'] : sprintf(
                /* translators: 1: procedure title, 2: before/after, 3: case title */
                __('%1$s %2$s photo, case %3$s, FemSculpt Chicago', 'pswpt'),
                $procedureTitle,
                $side,
                $title
            );

            return '<span class="' . esc_attr($class) . '"><img src="' . esc_url($image['src']) . '"'
                . ($image['srcset'] !== '' ? ' srcset="' . esc_attr($image['srcset']) . '"' : '')
                . ' width="400" height="500" loading="' . $loading . '" decoding="async" alt="' . esc_attr($alt) . '" /></span>';
        };

        $meta = ($tag !== '' ? '<span class="pgt">' . esc_html($tag) . '</span>' : '')
            . sprintf(esc_html__('Case #%s', 'pswpt'), esc_html($title))
            . ($surgeon !== '' ? '<br>' . esc_html($surgeon) : '');

        return '<article class="pg-card" data-i="' . $index . '" data-parents="' . esc_attr(implode(' ', array_unique($parent_ids))) . '" data-cats="' . esc_attr(implode(' ', $cat_ids)) . '" data-surgeon="' . esc_attr($surgeon) . '">'
            . '<a class="pgi" href="' . esc_url($permalink) . '" aria-label="' . esc_attr(sprintf(__('View case %s', 'pswpt'), $title)) . '">'
            . $photo_html($before, __('before', 'pswpt'), 'pgb')
            . $photo_html($after, __('after', 'pswpt'), 'pga')
            . '</a>'
            . '<button type="button" class="pgf" aria-pressed="false" aria-label="' . esc_attr(sprintf(__('Save case %s', 'pswpt'), $slug)) . '" data-cid="' . esc_attr($slug) . '" data-saved-label="' . esc_attr__('Saved', 'pswpt') . '">' . esc_html__('Save', 'pswpt') . '</button>'
            . '<p class="pgm" data-cid="' . esc_attr($slug) . '">' . $meta . '</p>'
            . '<a class="pgl" href="' . esc_url($permalink) . '">' . esc_html__('View full case', 'pswpt') . ' &rarr;</a>'
            . '</article>';
    }

    /* ------------------------------------------------------------------ */
    /* Single: calcado del DOM real de producción (pgd-* / pg-lb-*)         */
    /* ------------------------------------------------------------------ */

    /** Bloque completo del detalle del caso — lo único que llama single-patient_gallery.php. */
    public static function detail_html(WP_Post $post): string
    {
        $slug       = (string) $post->post_name;
        $procedures = PatientGalleryController::get_display_procedures($post);
        $names      = wp_list_pluck($procedures, 'name');
        $title      = $names ? implode(' & ', $names) . ' ' . __('Before & After', 'pswpt') : get_the_title($post);
        $photos     = self::flat_photos($post, $title, $slug);
        $consult    = PatientGalleryController::get_consult_url($post);
        $narrative  = PatientGalleryController::parse_narrative($post, $procedures);

        $html = '<div class="pg-detail-wrap"><div class="pg-detail"><div class="pgd-col">'
            . self::crumb_html($slug)
            . self::title_html($title, $slug)
            . '<div class="pgd-card">'
            . self::pairs_html($photos)
            . self::panel_html($post)
            . self::cats_html($procedures)
            . self::narrative_html($narrative)
            . self::cta_html($procedures, $consult, $slug)
            . '</div>'
            . '</div>'
            . self::prevnext_html()
            . self::mobilebar_html($consult, $slug)
            . self::lightbox_html($photos, sprintf(__('Case #%s', 'pswpt'), $slug))
            . '</div></div>'
            . self::schema_json($title, $slug, $photos);

        return $html;
    }

    private static function crumb_html(string $slug): string
    {
        $archive_url = (string) get_post_type_archive_link(PatientGalleryController::POST_TYPE);

        return '<nav class="pgd-crumb"><a href="' . esc_url($archive_url) . '">&larr; ' . esc_html__('All cases', 'pswpt') . '</a>'
            . self::save_button_html($slug, '')
            . '</nav>';
    }

    private static function save_button_html(string $slug, string $extra_class): string
    {
        $class = 'pgd-save' . ($extra_class !== '' ? ' ' . $extra_class : '');

        return '<button type="button" class="' . esc_attr($class) . '" data-cid="' . esc_attr($slug) . '" aria-pressed="false" aria-label="' . esc_attr(sprintf(__('Save case %s', 'pswpt'), $slug)) . '" data-saved-label="' . esc_attr__('Saved', 'pswpt') . '">' . esc_html__('Save', 'pswpt') . '</button>';
    }

    private static function title_html(string $title, string $slug): string
    {
        return '<h1 class="pgd-h1">' . esc_html($title)
            . '<span class="pgd-case">' . sprintf(esc_html__('Case #%s', 'pswpt'), esc_html($slug)) . '</span>'
            . '<span class="pgd-rule"></span></h1>';
    }

    /**
     * Todas las fotos del caso en una lista plana (before, after, before,
     * after…), en el mismo orden en que se van a pintar — la usan
     * pairs_html(), lightbox_html() y schema_json() para no resolver cada
     * URL más de una vez.
     */
    private static function flat_photos(WP_Post $post, string $title, string $slug): array
    {
        $photos = [];
        foreach (PatientGalleryController::get_image_pairs($post) as $pair) {
            foreach (['before' => __('before', 'pswpt'), 'after' => __('after', 'pswpt')] as $side => $side_label) {
                $image = self::image_attrs((string) $pair[$side], 'large');
                if ($image['src'] === '') {
                    continue;
                }
                $alt = $image['alt'] !== '' ? $image['alt'] : sprintf(
                    /* translators: 1: procedure title, 2: before/after, 3: case slug */
                    __('%1$s %2$s photo, case %3$s, FemSculpt Chicago', 'pswpt'),
                    $title,
                    $side_label,
                    $slug
                );
                $photos[] = ['src' => $image['src'], 'srcset' => $image['srcset'], 'alt' => $alt, 'side' => $side];
            }
        }

        return $photos;
    }

    /** Pares de fotos: cada foto es su propio botón (.pgb/.pga, data-idx global) que abre el lightbox .pg-lb en esa posición. */
    private static function pairs_html(array $photos): string
    {
        if (!$photos) {
            return '';
        }

        $rows = '';
        $total = count($photos);
        for ($i = 0; $i < $total; $i += 2) {
            $rows .= '<div class="pgd-pair">';
            for ($idx = $i; $idx < min($i + 2, $total); $idx++) {
                $photo   = $photos[$idx];
                $class   = $photo['side'] === 'before' ? 'pgb' : 'pga';
                $loading = $idx < 2 ? 'eager' : 'lazy'; // solo el primer par visible sin scroll va eager (Core Web Vitals).
                $rows .= '<button type="button" class="' . $class . '" data-idx="' . $idx . '">'
                    . '<img src="' . esc_url($photo['src']) . '"'
                    . ($photo['srcset'] !== '' ? ' srcset="' . esc_attr($photo['srcset']) . '"' : '')
                    . ' width="400" height="500" loading="' . $loading . '" decoding="async" alt="' . esc_attr($photo['alt']) . '" />'
                    . '</button>';
            }
            $rows .= '</div>';
        }

        return '<div class="pgd-pairs">' . $rows . '</div>';
    }

    /**
     * Age / Gender / Surgeon / resumen de tratamiento — mismo set fijo que
     * en producción (Height/Weight/BMI existen como taxonomía para filtrar
     * el archive, pero el diseño real nunca los muestra en la ficha del
     * caso, con o sin datos: verificado en dos casos reales distintos, uno
     * con Height/Weight/BMI cargados y otro sin ellos, ninguno los imprime).
     * Cada fila solo aparece si hay valor.
     */
    private static function panel_html(WP_Post $post): string
    {
        $rows = [
            [self::taxonomy_label('patient_age'), self::term_names($post, 'patient_age')],
            [self::taxonomy_label('patient_gender'), self::term_names($post, 'patient_gender')],
            [self::taxonomy_label('patient_surgeon'), self::term_names($post, 'patient_surgeon')],
            [__('Number of Treatments', 'pswpt'), PatientGalleryController::get_treatment_summary($post)],
        ];

        $items = '';
        foreach ($rows as [$label, $value]) {
            if ($value === '') {
                continue;
            }
            $items .= '<div class="pgd-row"><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
        }

        return $items === '' ? '' : '<div class="pgd-panel"><dl>' . $items . '</dl></div>';
    }

    /** Píldoras de procedimiento (pgd-cats), cada una a ?pgcat={term_id} del archive — mismo formato que producción. */
    private static function cats_html(array $procedures): string
    {
        if (!$procedures) {
            return '';
        }

        $archive_url = (string) get_post_type_archive_link(PatientGalleryController::POST_TYPE);
        $links       = '';
        foreach ($procedures as $term) {
            $links .= '<a href="' . esc_url(add_query_arg('pgcat', $term->term_id, $archive_url)) . '">' . esc_html($term->name) . '</a>';
        }

        return '<div class="pgd-cats">' . $links . '</div>';
    }

    /** Intro + un párrafo de definición por procedimiento (ver PatientGalleryController::parse_narrative()). */
    private static function narrative_html(array $narrative): string
    {
        $html = '';
        if ($narrative['intro'] !== '') {
            $html .= '<p class="pgd-intro">' . esc_html($narrative['intro']) . '</p>';
        }
        foreach ($narrative['definitions'] as $definition) {
            $html .= '<p class="pgd-def">' . $definition . '</p>';
        }

        return $html === '' ? '' : '<div class="pgd-narrative">' . $html . '</div>';
    }

    /** "Schedule a Consultation" (cta_url del header, con el caso marcado para el form de contacto) + "More {Procedure} Cases". */
    private static function cta_html(array $procedures, string $consult_url, string $slug): string
    {
        $buttons = '';
        if ($consult_url !== '') {
            $buttons .= self::consult_button_html($consult_url, $slug);
        }
        if ($procedures) {
            $first       = $procedures[0];
            $archive_url = (string) get_post_type_archive_link(PatientGalleryController::POST_TYPE);
            $buttons .= '<a class="pgd-btn pgd-btn-secondary" href="' . esc_url(add_query_arg('pgcat', $first->term_id, $archive_url)) . '">'
                . sprintf(esc_html__('More %s Cases', 'pswpt'), esc_html($first->name)) . '</a>';
        }

        return $buttons === '' ? '' : '<div class="pgd-cta">' . $buttons . '</div>';
    }

    private static function consult_button_html(string $consult_url, string $slug): string
    {
        return '<a class="pgd-btn pgd-btn-primary pgd-consult" data-cid="' . esc_attr($slug) . '" href="' . esc_url($consult_url) . '">' . esc_html__('Schedule a Consultation', 'pswpt') . '</a>';
    }

    /** Caso siguiente/anterior (orden nativo de publicación). Lado sin caso ⇒ <span></span> vacío, igual que producción, para no romper el layout flex. */
    private static function prevnext_html(): string
    {
        $previous = get_previous_post();
        $next     = get_next_post();
        if (!$previous && !$next) {
            return '';
        }

        return '<nav class="pgd-prevnext">'
            . self::adjacent_link_html($previous, 'pgd-prev', '&larr; ')
            . self::adjacent_link_html($next, 'pgd-next', '', ' &rarr;')
            . '</nav>';
    }

    private static function adjacent_link_html($adjacent, string $class, string $prefix = '', string $suffix = ''): string
    {
        if (!$adjacent instanceof WP_Post) {
            return '<span></span>';
        }
        $slug = (string) $adjacent->post_name;

        return '<a class="' . esc_attr($class) . '" href="' . esc_url(get_permalink($adjacent)) . '">'
            . $prefix . esc_html(get_the_title($adjacent))
            . ' <span>' . sprintf(esc_html__('Case #%s', 'pswpt'), esc_html($slug)) . '</span>' . $suffix
            . '</a>';
    }

    /** Mobile bar del SINGLE (`.pgd-mobilebar`) — no confundir con archive_mobilebar_html(), la del archive. */
    private static function mobilebar_html(string $consult_url, string $slug): string
    {
        $html = self::save_button_html($slug, 'pgd-save--bar');
        if ($consult_url !== '') {
            $html .= self::consult_button_html($consult_url, $slug);
        }

        return '<div class="pgd-mobilebar">' . $html . '</div>';
    }

    /**
     * Lightbox único de la página (patient-gallery-single.js): recorre TODAS
     * las fotos del caso con flechas, no solo el par en el que se hizo clic
     * — igual que en producción (data-idx global en pairs_html()). Se
     * precarga con la primera foto para que no arranque en blanco al abrir.
     */
    private static function lightbox_html(array $photos, string $case_label): string
    {
        if (!$photos) {
            return '';
        }
        $first = $photos[0];

        return '<div class="pg-lb" hidden>'
            . '<div class="pg-lb-backdrop" data-close></div>'
            . '<div class="pg-lb-dialog" role="dialog" aria-modal="true" aria-labelledby="pg-lb-cap">'
            . '<button type="button" class="pg-lb-close" data-close aria-label="' . esc_attr__('Close', 'pswpt') . '">&times;</button>'
            . '<button type="button" class="pg-lb-prev" aria-label="' . esc_attr__('Previous photo', 'pswpt') . '">&lsaquo;</button>'
            . '<img class="pg-lb-img" alt="' . esc_attr($first['alt']) . '" src="' . esc_url($first['src']) . '" />'
            . '<button type="button" class="pg-lb-next" aria-label="' . esc_attr__('Next photo', 'pswpt') . '">&rsaquo;</button>'
            . '<p class="pg-lb-info"><span class="pg-lb-cap" id="pg-lb-cap">' . esc_html($case_label) . '</span> &middot; <span class="pg-lb-count">1 / ' . count($photos) . '</span></p>'
            . '</div></div>';
    }

    /** ImageGallery JSON-LD (SEO) — mismo shape que producción. */
    private static function schema_json(string $title, string $slug, array $photos): string
    {
        if (!$photos) {
            return '';
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'ImageGallery',
            'name'     => sprintf('%s Photos Case %s', $title, $slug),
            'image'    => wp_list_pluck($photos, 'src'),
        ];

        return '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES) . '</script>';
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    /** Nombres de los términos de un caso en una taxonomía, unidos por coma. */
    private static function term_names(WP_Post $post, string $taxonomy): string
    {
        $terms = get_the_terms($post, $taxonomy);
        if (!$terms || is_wp_error($terms)) {
            return '';
        }

        return implode(', ', wp_list_pluck($terms, 'name'));
    }

    private static function taxonomy_label(string $taxonomy): string
    {
        $tax = get_taxonomy($taxonomy);

        return $tax ? $tax->labels->singular_name : $taxonomy;
    }

    /**
     * src/srcset/alt de una foto guardada como URL: si resuelve a un adjunto
     * real de la media library (attachment_url_to_postid(), igual que ya
     * hacía el plugin original para el alt text) se sirve con tamaño y
     * srcset propios; si no, la URL cruda tal cual se guardó.
     */
    private static function image_attrs(string $url, string $size): array
    {
        if ($url === '') {
            return ['src' => '', 'srcset' => '', 'alt' => ''];
        }

        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id > 0) {
            $src = wp_get_attachment_image_url($attachment_id, $size);
            if ($src) {
                return [
                    'src'    => $src,
                    'srcset' => (string) wp_get_attachment_image_srcset($attachment_id, $size),
                    'alt'    => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                ];
            }
        }

        return ['src' => $url, 'srcset' => '', 'alt' => ''];
    }
}
