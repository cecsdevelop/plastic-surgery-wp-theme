<?php
/**
 * Barra de llamada a la acción fija en móvil: teléfono + consulta.
 *
 * Se renderiza por hook (`wp_footer`) y no por shortcode, porque es un
 * elemento global del sitio: pegarlo en cada página lo convertiría en 37
 * copias que hay que mantener a mano. El shortcode `[sticky_cta]` queda solo
 * como salida de emergencia para colocarlo en un punto concreto.
 *
 * Sin JS: la visibilidad es una media query y `position: fixed`. El original
 * del que se migra tampoco usaba JS para esto.
 *
 * Los textos entran por el diccionario por idioma; el teléfono y la URL de
 * consulta por los ajustes del theme, para que no haya datos de contacto
 * incrustados en el código.
 *
 * @package Intelindev
 */

namespace IntelindevInit\StickyCta;

use IntelindevInit\General\BaseController;

class StickyCtaController extends BaseController
{
    /** Opción que guarda teléfono y URL de consulta. */
    public const OPTION = 'intelindev_sticky_cta';

    public function register(): void
    {
        add_action('wp_footer', [$this, 'render_global'], 20);
        add_action('init', [$this, 'register_shortcode'], 20);
    }

    public function register_shortcode(): void
    {
        add_shortcode('sticky_cta', [$this, 'shortcode']);
    }

    /** Salida global. No se imprime en el editor ni en feeds. */
    public function render_global(): void
    {
        if (is_admin() || is_feed() || is_embed()) {
            return;
        }
        echo $this->render();
    }

    public function shortcode($atts = []): string
    {
        $atts = shortcode_atts(
            ['phone' => '', 'phone_label' => '', 'cta_text' => '', 'cta_url' => '', 'lang' => ''],
            is_array($atts) ? $atts : [],
            'sticky_cta'
        );
        return $this->render($atts);
    }

    /**
     * Devuelve el HTML de la barra, o cadena vacía si no hay nada que ofrecer.
     *
     * Con un solo enlace la rejilla pasa a una columna: media barra vacía se
     * ve como un error de maquetación.
     */
    private function render(array $atts = []): string
    {
        $lang     = $this->resolve_lang((string) ($atts['lang'] ?? ''));
        $settings = $this->settings();

        $phone = $this->sanitize_phone((string) ($atts['phone'] ?? '') ?: $settings['phone']);
        $cta_url = (string) ($atts['cta_url'] ?? '') ?: $settings['cta_url'];

        // Orden: atributo -> ajuste del sitio -> diccionario -> default.
        // El ajuste va antes del diccionario porque estos textos son datos de
        // contacto de un sitio concreto, no cadenas de interfaz del theme.
        $phone_label = $this->first_non_empty(
            (string) ($atts['phone_label'] ?? ''),
            $settings['phone_label'],
            $this->translate('sticky_cta.call', $lang, '')
        );
        if ($phone_label === '' && $phone !== '') {
            $phone_label = $phone;
        }

        $cta_text = $this->first_non_empty(
            (string) ($atts['cta_text'] ?? ''),
            $settings['cta_text'],
            $this->translate('sticky_cta.book', $lang, ''),
            'Book Consultation'
        );

        $links = '';
        if ($phone !== '') {
            $links .= sprintf(
                '<a class="sticky-cta__link sticky-cta__link--call" href="tel:%s">%s</a>',
                esc_attr($phone),
                esc_html($phone_label)
            );
        }
        if ($cta_url !== '') {
            $links .= sprintf(
                '<a class="sticky-cta__link sticky-cta__link--book" href="%s">%s</a>',
                esc_url($cta_url),
                esc_html($cta_text)
            );
        }

        if ($links === '') {
            return '';
        }

        $count = (int) ($phone !== '') + (int) ($cta_url !== '');

        return sprintf(
            '<div class="sticky-cta sticky-cta--cols-%d" role="complementary" aria-label="%s">%s</div>',
            $count,
            esc_attr($this->translate('sticky_cta.aria', $lang, 'Quick actions')),
            $links
        );
    }

    /** @return array{phone:string,phone_label:string,cta_url:string,cta_text:string} */
    private function settings(): array
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        return [
            'phone'       => $this->sanitize_phone((string) ($stored['phone'] ?? '')),
            'phone_label' => sanitize_text_field((string) ($stored['phone_label'] ?? '')),
            'cta_url'     => esc_url_raw((string) ($stored['cta_url'] ?? '')),
            'cta_text'    => sanitize_text_field((string) ($stored['cta_text'] ?? '')),
        ];
    }

    /**
     * Deja solo lo que un `tel:` admite. Se conserva el `+` inicial, que es
     * lo que hace marcable un número internacional.
     */
    private function sanitize_phone(string $value): string
    {
        $value = preg_replace('/[^0-9+]/', '', $value) ?? '';
        if (strpos($value, '+') !== false) {
            $value = '+' . str_replace('+', '', $value);
        }
        return $value;
    }

    /** Primer valor no vacío de la lista. */
    private function first_non_empty(string ...$values): string
    {
        foreach ($values as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /** Diccionario del theme si está disponible; si no, el texto por defecto. */
    private function translate(string $key, string $lang, string $fallback): string
    {
        if (!function_exists('idml_t')) {
            return $fallback;
        }
        $translated = (string) idml_t($key, $lang);
        return ($translated === '' || $translated === $key) ? $fallback : $translated;
    }
}
