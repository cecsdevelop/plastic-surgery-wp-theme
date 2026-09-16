<?php
/**
 * Recepción de envíos: endpoint REST POST intelindev/v1/form/{id}.
 *
 * Pipeline: formulario publicado → honeypot → token firmado con ventana de
 * tiempo (≥3 s desde el render, ≤24 h) → límite por IP → validación por campo
 * (obligatorio, email, tel, select ∈ opciones, checkbox) → filtro
 * intelindev_form_validate (para enganchar captcha u otras reglas) → guardar
 * envío (CPT) → correo (wp_mail, reply-to al email del remitente) → webhook
 * (POST JSON, pensado para CRMs como GoHighLevel) → respuesta JSON.
 *
 * Los mensajes salen del diccionario (form.*) en el idioma del formulario.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Forms;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

class FormHandler
{
    public const MIN_SECONDS      = 3;
    public const MAX_SECONDS      = 86400;
    public const RATE_LIMIT       = 5;   // envíos por IP…
    public const RATE_WINDOW      = 600; // …cada 10 minutos

    public function register_routes(): void
    {
        register_rest_route('intelindev/v1', '/form/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
            'args'                => ['id' => ['validate_callback' => fn($v) => is_numeric($v)]],
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $form = get_post((int) $request['id']);
        if (!$form instanceof WP_Post || $form->post_type !== FormsController::POST_TYPE || $form->post_status !== 'publish') {
            return new WP_REST_Response(['ok' => false, 'message' => __('Formulario no disponible.', 'intelindev')], 404);
        }

        $params = $request->get_body_params();
        $lang   = idml_normalize_lang((string) ($params['_lang'] ?? ''));
        if ($lang === '' || !in_array($lang, idml_get_languages(), true)) {
            $lang = idml_get_default_language();
        }
        $t = fn(string $key) => idml_t($key, $lang);

        // Honeypot: se responde como éxito para no dar pistas, sin guardar nada.
        if (trim((string) ($params['_website'] ?? '')) !== '') {
            return new WP_REST_Response(['ok' => true, 'message' => $this->success_message($form, $lang)], 200);
        }

        // Token firmado + ventana de tiempo.
        $ts  = (int) ($params['_ts'] ?? 0);
        $sig = (string) ($params['_sig'] ?? '');
        $age = time() - $ts;
        if ($ts <= 0 || !hash_equals(FormRenderer::sign((int) $form->ID, $ts), $sig) || $age < self::MIN_SECONDS || $age > self::MAX_SECONDS) {
            return new WP_REST_Response(['ok' => false, 'message' => $t('form.error_send')], 400);
        }

        if ($this->rate_limited()) {
            return new WP_REST_Response(['ok' => false, 'message' => $t('form.too_many')], 429);
        }

        [$data, $errors] = $this->validate($form, $params, $lang);
        $errors = (array) apply_filters('intelindev_form_validate', $errors, $data, $form, $params, $lang);
        if ($errors) {
            return new WP_REST_Response(['ok' => false, 'errors' => $errors, 'message' => $t('form.fix_errors')], 422);
        }

        $settings = FormsController::get_settings($form->ID);
        $context  = [
            'lang'       => $lang,
            'page'       => esc_url_raw((string) ($params['_page'] ?? '')),
            'ip'         => $this->client_ip(),
            'user_agent' => substr(sanitize_text_field((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
            'mail'       => null,
            'webhook'    => null,
        ];

        $context['mail'] = $this->send_mail($form, $settings, $data, $lang);
        if (!empty($settings['webhook'])) {
            $context['webhook'] = $this->send_webhook((string) $settings['webhook'], $form, $data, $context);
        }

        $submission_id = 0;
        if (!empty($settings['store'])) {
            $submission_id = SubmissionsController::store($form, $data, $context);
        }

        do_action('intelindev_form_submitted', $form, $data, $context, $submission_id);

        return new WP_REST_Response([
            'ok'       => true,
            'message'  => $this->success_message($form, $lang),
            'redirect' => !empty($settings['redirect']) ? esc_url_raw((string) $settings['redirect']) : '',
        ], 200);
    }

    private function success_message(WP_Post $form, string $lang): string
    {
        $settings = FormsController::get_settings($form->ID);
        $message  = intelindev_resolve_lang_text($settings['success'] ?? [], $lang);
        return $message !== '' ? $message : idml_t('form.default_success', $lang);
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [datos limpios por nombre de campo, errores por nombre] */
    public function validate(WP_Post $form, array $params, string $lang): array
    {
        $data = [];
        $errors = [];
        $t = fn(string $key) => idml_t($key, $lang);

        foreach (FormsController::get_fields($form->ID) as $field) {
            $name     = $field['name'];
            $type     = $field['type'];
            $required = !empty($field['required']);
            $raw      = $params[$name] ?? '';
            $raw      = is_array($raw) ? '' : (string) $raw;

            switch ($type) {
                case 'checkbox':
                    $value = $raw !== '' && $raw !== '0' ? '1' : '';
                    break;
                case 'textarea':
                    $value = sanitize_textarea_field($raw);
                    break;
                case 'email':
                    $trimmed = trim($raw);
                    $value   = sanitize_email($trimmed);
                    // sanitize_email() vacía lo inválido: se valida lo tipeado para
                    // distinguir "email inválido" de "campo vacío".
                    if ($trimmed !== '' && ($value === '' || !is_email($trimmed))) {
                        $errors[$name] = $t('form.invalid_email');
                    }
                    break;
                case 'tel':
                    $value = sanitize_text_field($raw);
                    if ($value !== '' && !preg_match('/^[0-9+()\s.\-]{6,30}$/', $value)) {
                        $errors[$name] = $t('form.invalid');
                    }
                    break;
                case 'select':
                    $value = sanitize_text_field($raw);
                    // Válido si es una opción en cualquier idioma (el select se
                    // renderizó en uno, pero se acepta el valor de cualquiera).
                    $valid = [];
                    foreach (idml_get_languages() as $l) {
                        $valid = array_merge($valid, FormsController::field_options($field, $l));
                    }
                    if ($value !== '' && !in_array($value, $valid, true)) {
                        $errors[$name] = $t('form.invalid');
                    }
                    break;
                case 'hidden':
                default:
                    $value = sanitize_text_field($raw);
            }

            if ($required && $value === '' && !isset($errors[$name])) {
                $errors[$name] = $t('form.required');
            }

            $data[$name] = $value;
        }

        return [$data, $errors];
    }

    private function rate_limited(): bool
    {
        $key   = 'intelindev_form_rl_' . md5($this->client_ip());
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

    /** @return array{sent: bool, to: string} */
    private function send_mail(WP_Post $form, array $settings, array $data, string $lang): array
    {
        $recipients = array_filter(array_map('sanitize_email', preg_split('/[,;\s]+/', (string) ($settings['recipients'] ?? ''))));
        $recipients = array_values(array_filter($recipients, 'is_email'));
        if (!$recipients) {
            $recipients = [get_option('admin_email')];
        }

        $subject = intelindev_resolve_lang_text($settings['subject'] ?? [], $lang);
        if ($subject === '') {
            $subject = sprintf('[%s] %s', get_bloginfo('name'), $form->post_title);
        }
        $subject = strtr($subject, ['{site}' => get_bloginfo('name'), '{form}' => $form->post_title]);

        $lines = [];
        $reply_to = '';
        foreach (FormsController::get_fields($form->ID) as $field) {
            $label = wp_strip_all_tags(intelindev_resolve_lang_text($field['label'] ?? [], $lang));
            $value = (string) ($data[$field['name']] ?? '');
            if ($field['type'] === 'checkbox') {
                $value = $value === '1' ? __('Sí', 'intelindev') : __('No', 'intelindev');
            }
            if ($field['type'] === 'email' && $reply_to === '' && $value !== '') {
                $reply_to = $value;
            }
            $lines[] = ($label !== '' ? $label : $field['name']) . ': ' . $value;
        }
        $lines[] = '';
        $lines[] = sprintf(__('Enviado desde %s (%s)', 'intelindev'), get_bloginfo('name'), strtoupper($lang));

        $headers = [];
        if ($reply_to !== '') {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        $sent = (bool) wp_mail($recipients, wp_specialchars_decode($subject), implode("\n", $lines), $headers);
        return ['sent' => $sent, 'to' => implode(', ', $recipients)];
    }

    /** @return array{status: int, error: string} */
    private function send_webhook(string $url, WP_Post $form, array $data, array $context): array
    {
        $payload = [
            'form'         => ['id' => (int) $form->ID, 'slug' => $form->post_name, 'title' => $form->post_title],
            'lang'         => $context['lang'],
            'page'         => $context['page'],
            'submitted_at' => current_time('c'),
            'fields'       => $data,
        ];
        $response = wp_remote_post($url, [
            'timeout' => 8,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
        ]);
        if ($response instanceof WP_Error) {
            return ['status' => 0, 'error' => $response->get_error_message()];
        }
        return ['status' => (int) wp_remote_retrieve_response_code($response), 'error' => ''];
    }
}
