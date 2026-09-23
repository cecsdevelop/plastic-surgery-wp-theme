<?php
/**
 * Cabecera y pie del sitio.
 *
 * Los enlaces salen de menús de WordPress, no del código: son contenido que el
 * cliente tiene que poder editar. El módulo registra dos ubicaciones y construye
 * el árbol a mano en vez de usar un Walker, porque el cajón móvil necesita
 * marcado `<details>` que el walker por defecto no produce.
 *
 * Sin JS. El cajón y sus acordeones son `<details>/<summary>` nativos: el
 * original usaba `<button>` más JavaScript para lo mismo, y `<details>` ya es
 * accesible por teclado y anunciable por lectores de pantalla sin código.
 *
 * Los datos de contacto y las redes viven en la opción `intelindev_chrome`.
 * El prefijo sigue siendo `intelindev_` a propósito: el namespace y los
 * prefijos PHP del theme no se han renombrado, y mezclar dos convenciones
 * sería peor que mantener la que hay.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Chrome;

use IntelindevInit\General\BaseController;
use WP_Post;

class ChromeController extends BaseController
{
    public const OPTION = 'intelindev_chrome';
    public const MENU_PRIMARY = 'psw_primary';
    public const MENU_FOOTER = 'psw_footer';

    /** Redes reconocidas, en el orden en que se pintan. */
    private const NETWORKS = ['instagram', 'facebook', 'youtube', 'tiktok', 'pinterest', 'x', 'linkedin'];

    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'register_menus']);
    }

    public function register_menus(): void
    {
        register_nav_menus([
            self::MENU_PRIMARY => __('Navegación principal (cabecera)', 'intelindev'),
            self::MENU_FOOTER  => __('Navegación del pie', 'intelindev'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Cabecera                                                           */
    /* ------------------------------------------------------------------ */

    public function render_header(): string
    {
        $lang     = $this->get_current_lang();
        $settings = $this->settings();
        $tree     = $this->menu_tree(self::MENU_PRIMARY);

        return '<header class="psw-header" id="psw-header">'
            . '<div class="psw-header__bar">'
            . $this->logo()
            . $this->desktop_nav($tree)
            . $this->header_actions($settings, $lang)
            . $this->drawer($tree, $settings, $lang)
            . '</div>'
            . '</header>';
    }

    private function logo(): string
    {
        if (function_exists('has_custom_logo') && has_custom_logo()) {
            return '<div class="psw-header__logo">' . get_custom_logo() . '</div>';
        }
        return '<a class="psw-header__logo" href="' . esc_url(home_url('/')) . '">'
            . esc_html(get_bloginfo('name')) . '</a>';
    }

    /**
     * Nav de escritorio: los submenús se abren por hover y foco, sin JS.
     *
     * Un item sin URL no se pinta como enlace: en el original es un disparador
     * de submenú, y un `<a href="#">` sería una trampa para el teclado.
     */
    private function desktop_nav(array $tree): string
    {
        if ($tree === []) {
            return '';
        }

        $items = '';
        foreach ($tree as $item) {
            $has_children = $item['children'] !== [];
            $label        = esc_html($item['label']);

            $trigger = $item['url'] !== ''
                ? '<a class="psw-header__link" href="' . esc_url($item['url']) . '">' . $label . '</a>'
                : '<span class="psw-header__link" role="presentation">' . $label . '</span>';

            $submenu = '';
            if ($has_children) {
                $links = '';
                foreach ($item['children'] as $child) {
                    if ($child['url'] === '') {
                        continue;
                    }
                    $links .= '<li><a href="' . esc_url($child['url']) . '"'
                        . $this->external_attrs($child['url']) . '>'
                        . esc_html($child['label']) . '</a></li>';
                }
                $submenu = '<ul class="psw-header__submenu">' . $links . '</ul>';
            }

            $items .= '<li class="psw-header__item' . ($has_children ? ' psw-header__item--has-children' : '') . '">'
                . $trigger . $submenu . '</li>';
        }

        return '<nav class="psw-header__nav" aria-label="' . esc_attr__('Navegación principal', 'intelindev') . '">'
            . '<ul class="psw-header__menu">' . $items . '</ul></nav>';
    }

    private function header_actions(array $settings, string $lang): string
    {
        $out = '';
        if ($settings['phone'] !== '') {
            $out .= '<a class="psw-header__tel" href="tel:' . esc_attr($settings['phone']) . '">'
                . esc_html($settings['phone_label'] !== '' ? $settings['phone_label'] : $settings['phone'])
                . '</a>';
        }
        if ($settings['cta_url'] !== '') {
            $out .= '<a class="psw-header__cta" href="' . esc_url($settings['cta_url']) . '">'
                . esc_html($this->label('chrome.book', $lang, $settings['cta_text'], 'Book a Consultation'))
                . '</a>';
        }
        return $out === '' ? '' : '<div class="psw-header__actions">' . $out . '</div>';
    }

    /**
     * Cajón móvil con `<details>`: cero JS.
     *
     * El `<summary>` es el botón de hamburguesa. Cada item con hijos es a su vez
     * un `<details>` anidado, que es lo que el original resolvía con JavaScript.
     */
    private function drawer(array $tree, array $settings, string $lang): string
    {
        $accordions = '';
        foreach ($tree as $item) {
            if ($item['children'] === []) {
                // Sin hijos y sin destino se pinta como rótulo, no se descarta:
                // un item del menú que desaparece en silencio es peor que uno
                // que no lleva a ninguna parte, porque nadie lo nota.
                if ($item['url'] === '') {
                    $accordions .= '<p class="psw-drawer__link psw-drawer__link--label">'
                        . esc_html($item['label']) . '</p>';
                    continue;
                }
                $accordions .= '<a class="psw-drawer__link" href="' . esc_url($item['url']) . '"'
                    . $this->external_attrs($item['url']) . '>' . esc_html($item['label']) . '</a>';
                continue;
            }

            $links = '';
            foreach ($item['children'] as $child) {
                if ($child['url'] === '') {
                    continue;
                }
                $links .= '<a href="' . esc_url($child['url']) . '"'
                    . $this->external_attrs($child['url']) . '>' . esc_html($child['label']) . '</a>';
            }

            $accordions .= '<details class="psw-drawer__group">'
                . '<summary class="psw-drawer__link">' . esc_html($item['label']) . '</summary>'
                . '<div class="psw-drawer__panel">' . $links . '</div>'
                . '</details>';
        }

        $foot = '';
        if ($settings['cta_url'] !== '') {
            $foot .= '<a class="psw-header__cta" href="' . esc_url($settings['cta_url']) . '">'
                . esc_html($this->label('chrome.book', $lang, $settings['cta_text'], 'Book a Consultation'))
                . '</a>';
        }
        if ($settings['phone'] !== '') {
            $foot .= '<a class="psw-drawer__tel" href="tel:' . esc_attr($settings['phone']) . '">'
                . esc_html($settings['phone_label']) . '</a>';
        }
        if ($settings['hours'] !== '') {
            $foot .= '<p class="psw-drawer__hours">' . esc_html($settings['hours']) . '</p>';
        }
        $foot .= $this->address_block('psw-drawer__addr', $settings);

        return '<details class="psw-drawer">'
            . '<summary class="psw-drawer__toggle" aria-label="' . esc_attr__('Abrir el menú', 'intelindev') . '">'
            . '<span class="psw-drawer__bars" aria-hidden="true"></span></summary>'
            . '<div class="psw-drawer__body">' . $accordions
            . '<div class="psw-drawer__foot">' . $foot . '</div>'
            . '</div></details>';
    }

    /* ------------------------------------------------------------------ */
    /* Pie                                                                */
    /* ------------------------------------------------------------------ */

    public function render_footer(): string
    {
        $settings = $this->settings();
        $lang     = $this->get_current_lang();

        $nav = '';
        foreach ($this->menu_tree(self::MENU_FOOTER) as $item) {
            if ($item['url'] === '') {
                continue;
            }
            $nav .= '<li><a href="' . esc_url($item['url']) . '"'
                . $this->external_attrs($item['url']) . '>' . esc_html($item['label']) . '</a></li>';
        }

        $card = '<div class="psw-footer__card">'
            . $this->address_block('psw-footer__addr', $settings);
        if ($settings['phone'] !== '') {
            $card .= '<a class="psw-footer__tel" href="tel:' . esc_attr($settings['phone']) . '">'
                . esc_html($settings['phone_label']) . '</a>';
        }
        if ($settings['hours'] !== '') {
            $card .= '<p class="psw-footer__hours">' . esc_html($settings['hours']) . '</p>';
        }
        if ($settings['map_url'] !== '') {
            $card .= '<a class="psw-footer__map" href="' . esc_url($settings['map_url'] ) . '" target="_blank" rel="noopener">'
                . esc_html($this->label('chrome.map', $lang, '', 'View on Google Maps')) . '</a>';
        }
        $card .= '</div>';

        return '<footer class="psw-footer" id="psw-footer">'
            . '<div class="psw-footer__main"><div class="psw-footer__inner">'
            . '<div class="psw-footer__brand">' . $this->logo() . $this->social($settings) . '</div>'
            . $card
            . ($nav === '' ? '' : '<nav class="psw-footer__nav" aria-label="'
                . esc_attr__('Navegación del pie', 'intelindev') . '"><ul>' . $nav . '</ul></nav>')
            . '</div></div>'
            . ($settings['mission'] === '' ? '' : '<div class="psw-footer__mission"><p>'
                . esc_html($settings['mission']) . '</p></div>')
            . '<div class="psw-footer__base"><p>&copy; ' . esc_html((string) gmdate('Y')) . ' '
            . esc_html(get_bloginfo('name')) . '</p></div>'
            . '</footer>';
    }

    private function social(array $settings): string
    {
        $links = '';
        foreach (self::NETWORKS as $network) {
            $url = $settings['social'][$network] ?? '';
            if ($url === '') {
                continue;
            }
            $links .= '<a class="psw-social psw-social--' . esc_attr($network) . '" href="' . esc_url($url)
                . '" target="_blank" rel="noopener">'
                . '<span class="screen-reader-text">' . esc_html(ucfirst($network)) . '</span></a>';
        }
        return $links === '' ? '' : '<div class="psw-footer__social">' . $links . '</div>';
    }

    private function address_block(string $class, array $settings): string
    {
        if ($settings['address'] === []) {
            return '';
        }
        $lines = array_map(static fn(string $line): string => esc_html($line), $settings['address']);
        return '<address class="' . esc_attr($class) . '">' . implode('<br>', $lines) . '</address>';
    }

    /* ------------------------------------------------------------------ */
    /* Datos                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Árbol de dos niveles desde un menú de WordPress.
     *
     * Se construye a mano porque el cajón necesita `<details>` anidados y el
     * Walker por defecto solo produce `<ul>` dentro de `<li>`.
     */
    private function menu_tree(string $location): array
    {
        $locations = get_nav_menu_locations();
        $menu_id   = (int) ($locations[$location] ?? 0);
        if ($menu_id === 0) {
            return [];
        }

        $items = wp_get_nav_menu_items($menu_id);
        if (!is_array($items)) {
            return [];
        }

        $by_parent = [];
        foreach ($items as $item) {
            if (!$item instanceof WP_Post) {
                continue;
            }
            $by_parent[(int) $item->menu_item_parent][] = [
                'label'    => (string) $item->title,
                // Un '#' en el original es un disparador de submenú, no un destino.
                'url'      => in_array((string) $item->url, ['', '#'], true) ? '' : (string) $item->url,
                'children' => [],
            ];
        }

        $top = $by_parent[0] ?? [];
        foreach ($top as $index => $item) {
            foreach ($items as $candidate) {
                if (!$candidate instanceof WP_Post || (string) $candidate->title !== $item['label']) {
                    continue;
                }
                $top[$index]['children'] = array_map(
                    static fn(array $child): array => $child,
                    $by_parent[(int) $candidate->ID] ?? []
                );
                break;
            }
        }

        return $top;
    }

    /** `target` y `rel` solo para enlaces a otro host. */
    private function external_attrs(string $url): string
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === '' || $host === wp_parse_url(home_url(), PHP_URL_HOST)) {
            return '';
        }
        return ' target="_blank" rel="noopener"';
    }

    /** @return array<string,mixed> */
    private function settings(): array
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        $social = [];
        foreach (self::NETWORKS as $network) {
            $url = esc_url_raw((string) ($stored['social'][$network] ?? ''));
            if ($url !== '') {
                $social[$network] = $url;
            }
        }

        $address = [];
        foreach ((array) ($stored['address'] ?? []) as $line) {
            $line = sanitize_text_field((string) $line);
            if ($line !== '') {
                $address[] = $line;
            }
        }

        return [
            'phone'       => preg_replace('/[^0-9+]/', '', (string) ($stored['phone'] ?? '')) ?? '',
            'phone_label' => sanitize_text_field((string) ($stored['phone_label'] ?? '')),
            'cta_url'     => esc_url_raw((string) ($stored['cta_url'] ?? '')),
            'cta_text'    => sanitize_text_field((string) ($stored['cta_text'] ?? '')),
            'hours'       => sanitize_text_field((string) ($stored['hours'] ?? '')),
            'map_url'     => esc_url_raw((string) ($stored['map_url'] ?? '')),
            'mission'     => sanitize_text_field((string) ($stored['mission'] ?? '')),
            'address'     => $address,
            'social'      => $social,
        ];
    }

    /** Ajuste del sitio, luego diccionario, luego el texto por defecto. */
    private function label(string $key, string $lang, string $setting, string $fallback): string
    {
        $setting = trim($setting);
        if ($setting !== '') {
            return $setting;
        }
        if (function_exists('idml_t')) {
            $translated = (string) idml_t($key, $lang);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        return $fallback;
    }
}
