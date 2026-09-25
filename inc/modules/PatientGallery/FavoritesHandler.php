<?php
/**
 * "Send My Favorites": endpoint REST POST pswpt/v1/patient-gallery/favorites
 * detrás de `.pg-fav-email` (PatientGalleryRenderer::saved_bar_html(),
 * patient-gallery-archive.js). Los "casos guardados" viven solo en el
 * localStorage del visitante (ver STORAGE_KEY en patient-gallery-archive.js)
 * — no hay sesión ni cuenta de usuario del lado del servidor — así que el
 * cliente manda la lista de slugs en cada envío y el email que se contesta
 * incluye un link con `?favorited=` para que, al abrirlo, JS repueble el
 * mismo localStorage (ver initFavoritesDeepLink en el JS).
 *
 * Mismo esquema anti-spam que Forms\FormHandler (sin nonces de WP: caducan
 * con la sesión y rompen en páginas cacheadas): honeypot `_website` + token
 * firmado con la hora de render (`_ts`/`_sig`, ventana de 3s–24h) + límite
 * por IP. Sin CPT propio para guardar el envío (no hay nada que revisar
 * después en el admin — es solo un email al propio visitante, más uno de
 * aviso si pide consulta).
 *
 * @package pswpt
 */

namespace pswptInit\PatientGallery;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class FavoritesHandler
{
    public const MIN_SECONDS = 3;
    public const MAX_SECONDS = 86400;
    public const RATE_LIMIT  = 5;   // envíos por IP…
    public const RATE_WINDOW = 600; // …cada 10 minutos

    public static function sign(int $ts): string
    {
        return hash_hmac('sha256', 'pg-favorites|' . $ts, wp_salt('nonce'));
    }

    public function register_routes(): void
    {
        register_rest_route('pswpt/v1', '/patient-gallery/favorites', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_body_params();

        // Honeypot: se responde como éxito para no dar pistas, sin enviar nada.
        if (trim((string) ($params['_website'] ?? '')) !== '') {
            return new WP_REST_Response(['ok' => true, 'message' => $this->success_message()], 200);
        }

        $ts  = (int) ($params['_ts'] ?? 0);
        $sig = (string) ($params['_sig'] ?? '');
        $age = time() - $ts;
        if ($ts <= 0 || !hash_equals(self::sign($ts), $sig) || $age < self::MIN_SECONDS || $age > self::MAX_SECONDS) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Something went wrong. Please try again.', 'pswpt')], 400);
        }

        if ($this->rate_limited()) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Too many requests. Please try again in a few minutes.', 'pswpt')], 429);
        }

        $email = sanitize_email((string) ($params['email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            return new WP_REST_Response(['ok' => false, 'errors' => ['email' => __('Enter a valid email address.', 'pswpt')], 'message' => __('Please fix the highlighted fields.', 'pswpt')], 422);
        }
        $first_name = sanitize_text_field((string) ($params['first_name'] ?? ''));
        $last_name  = sanitize_text_field((string) ($params['last_name'] ?? ''));
        if ($first_name === '' || $last_name === '') {
            return new WP_REST_Response(['ok' => false, 'message' => __('Please fill in your first and last name.', 'pswpt')], 422);
        }
        $phone = sanitize_text_field((string) ($params['phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^[0-9+()\s.\-]{6,30}$/', $phone)) {
            $phone = '';
        }
        $want_consult = !empty($params['want_consult']);

        $slugs = array_values(array_filter(array_map('sanitize_title', (array) ($params['cases'] ?? []))));
        $posts = $slugs ? get_posts([
            'post_type'      => PatientGalleryController::POST_TYPE,
            'post_status'    => 'publish',
            'numberposts'    => -1,
            'post_name__in'  => $slugs,
            'orderby'        => 'post_name__in',
        ]) : [];
        if (!$posts) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Your saved list is empty.', 'pswpt')], 422);
        }

        if ($this->send_favorites_email($email, $first_name, $posts) && $want_consult) {
            $this->send_consult_lead($first_name, $last_name, $email, $phone, $posts);
        }

        return new WP_REST_Response(['ok' => true, 'message' => $this->success_message()], 200);
    }

    private function success_message(): string
    {
        return __('Sent! Check your email for your saved cases.', 'pswpt');
    }

    private function rate_limited(): bool
    {
        $key   = 'pswpt_pg_fav_rl_' . md5($this->client_ip());
        $count = (int) get_transient($key);
        if ($count >= self::RATE_LIMIT) {
            return true;
        }
        set_transient($key, $count + 1, self::RATE_WINDOW);

        return false;
    }

    private function client_ip(): string
    {
        return sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    /** @param \WP_Post[] $posts */
    private function send_favorites_email(string $email, string $first_name, array $posts): bool
    {
        $archive_url = (string) get_post_type_archive_link(PatientGalleryController::POST_TYPE);
        $slugs       = wp_list_pluck($posts, 'post_name');
        $list_url    = add_query_arg('favorited', implode(',', $slugs), $archive_url);

        $lines   = [];
        $lines[] = sprintf(__('Hi %s,', 'pswpt'), $first_name);
        $lines[] = '';
        $lines[] = __('Here are the before & after cases you saved:', 'pswpt');
        $lines[] = '';
        foreach ($posts as $post) {
            $procedures = PatientGalleryController::get_display_procedures($post);
            $label      = $procedures ? implode(' & ', wp_list_pluck($procedures, 'name')) : get_the_title($post);
            $lines[]    = '- ' . $label . ' (' . sprintf(__('Case #%s', 'pswpt'), get_the_title($post)) . '): ' . get_permalink($post);
        }
        $lines[] = '';
        $lines[] = __('View them all together anytime:', 'pswpt') . ' ' . $list_url;
        $lines[] = '';
        $lines[] = sprintf(__('— %s', 'pswpt'), get_bloginfo('name'));

        $subject = sprintf(__('Your saved cases from %s', 'pswpt'), get_bloginfo('name'));

        return (bool) wp_mail($email, $subject, implode("\n", $lines));
    }

    /** Aviso interno cuando el visitante pide consulta junto con su lista — mismo destinatario de fallback que Forms\FormHandler. @param \WP_Post[] $posts */
    private function send_consult_lead(string $first_name, string $last_name, string $email, string $phone, array $posts): void
    {
        $recipient = (string) get_option('admin_email');
        $subject   = sprintf('[%s] %s', get_bloginfo('name'), __('Consultation request from Patient Gallery', 'pswpt'));

        $lines   = [];
        $lines[] = __('Name:', 'pswpt') . ' ' . $first_name . ' ' . $last_name;
        $lines[] = __('Email:', 'pswpt') . ' ' . $email;
        if ($phone !== '') {
            $lines[] = __('Phone:', 'pswpt') . ' ' . $phone;
        }
        $lines[] = '';
        $lines[] = __('Saved cases:', 'pswpt');
        foreach ($posts as $post) {
            $lines[] = '- ' . sprintf(__('Case #%s', 'pswpt'), get_the_title($post)) . ': ' . get_permalink($post);
        }

        wp_mail($recipient, $subject, implode("\n", $lines), ['Reply-To: ' . $email]);
    }
}
