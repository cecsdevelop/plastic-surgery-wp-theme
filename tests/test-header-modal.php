<?php
/**
 * Modal del header bajo demanda: la página no lleva el contenido; lo sirve
 * GET intelindev/v1/modal?lang= con shortcodes resueltos en ese idioma.
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-header-modal.php
 */
use IntelindevInit\Components\ComponentsController as C;
use IntelindevInit\Forms\FormsController as F;

$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/'; define('WP_USE_THEMES', false);
require dirname(__DIR__, 4) . '/wp-load.php';
wp_set_current_user(1);
$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));
$rest = function (string $lang): array {
    $out = (string) shell_exec('curl -s -D - ' . escapeshellarg('http://localhost:8888/Intelindev/wp-json/intelindev/v1/modal?lang=' . $lang));
    [$headers, $body] = explode("\r\n\r\n", $out, 2) + ['', ''];
    preg_match('/^HTTP\/\S+ (\d+)/', $headers, $m);
    return [(int) ($m[1] ?? 0), $headers, json_decode($body, true) ?: []];
};

$orig_header = get_option('intelindev_header_settings', null);
$form_id = 0; $comp_id = 0;

try {
    $form_id = wp_insert_post(['post_type' => F::POST_TYPE, 'post_title' => 'Modal', 'post_name' => 'contacto-modal', 'post_status' => 'publish']);
    update_post_meta($form_id, F::META_FIELDS, F::sanitize_fields([['name' => 'nombre', 'type' => 'text', 'required' => '1', 'label' => ['es' => 'Nombre', 'en' => 'Name']]]));
    update_post_meta($form_id, F::META_SETTINGS, F::sanitize_settings(['store' => '1']));
    $comp_id = wp_insert_post(['post_type' => C::POST_TYPE, 'post_title' => 'Modal intro', 'post_name' => 'modal-intro', 'post_status' => 'publish']);
    update_post_meta($comp_id, C::META_TEMPLATE, '<p class="intro">{t:nav.prev_page} · {lang}</p>');
    delete_transient(C::TRANSIENT);
    update_option('intelindev_header_settings', ['cta_type' => 'modal', 'cta_text' => ['es' => 'Hablemos', 'en' => "Let's talk"], 'cta_modal_title' => ['es' => 'Escribinos'], 'cta_modal_content' => '[modal-intro]<script>window.__crm = 1;</script>[form slug="contacto-modal"]']);

    echo "1) la página no lleva el contenido del modal\n";
    $es = $curl('/'); $en = $curl('/en/');
    check('sin <template> ni formulario ni script del CRM en el HTML', strpos($es, 'header-cta-modal-template') === false && strpos($es, 'intelindev-form') === false && strpos($es, 'window.__crm') === false);
    check('botón y dialog vacío con data-cta-modal-src por idioma', strpos($es, '>Hablemos</button>') !== false && preg_match('#<dialog id="header-cta-modal"[^>]* data-cta-modal-src="http://localhost:8888/Intelindev/wp-json/intelindev/v1/modal\?lang=es"#', $es) === 1 && preg_match('#data-cta-modal-src="[^"]*lang=en"#', $en) === 1);
    check('textos de estado por idioma', strpos($es, 'data-loading="Cargando…"') !== false && strpos($en, 'data-error="Could not load. Please try again."') !== false);
    check('contenedor de contenido vacío', preg_match('#<div class="header-cta-modal__content" data-cta-modal-content></div>#', $es) === 1);

    echo "2) endpoint REST\n";
    [$code, $headers, $body] = $rest('en');
    check('EN: 200 con formulario en inglés y componente en inglés', $code === 200 && strpos($body['html'] ?? '', '>Name <span class="intelindev-form__req"') !== false && strpos($body['html'], 'name="_lang" value="en"') !== false && strpos($body['html'], '<p class="intro">Previous · en</p>') !== false);
    check('EN: script del CRM incluido y token fresco', strpos($body['html'], '<script>window.__crm = 1;</script>') !== false && preg_match('/name="_ts" value="(\d+)"/', $body['html'], $m) === 1 && time() - (int) $m[1] < 5);
    check('Cache-Control: no-store', stripos($headers, 'Cache-Control: no-store') !== false);
    [$code, , $body] = $rest('es');
    check('ES: formulario y componente en español', $code === 200 && strpos($body['html'], '>Nombre <span') !== false && strpos($body['html'], '<p class="intro">Anteriores · es</p>') !== false);
    [$code, , $body] = $rest('xx');
    check('idioma desconocido → idioma por defecto (ES)', $code === 200 && strpos($body['html'], 'Anteriores') !== false);
    update_option('intelindev_header_settings', ['cta_type' => 'url', 'cta_url' => 'https://x.com', 'cta_text' => ['es' => 'x'], 'cta_modal_content' => '<p>oculto</p>']);
    [$code, , $body] = $rest('es');
    check('CTA no-modal → 404 sin html', $code === 404 && ($body['html'] ?? 'x') === '');
} finally {
    if ($orig_header === null) delete_option('intelindev_header_settings'); else update_option('intelindev_header_settings', $orig_header);
    if ($form_id) wp_delete_post($form_id, true);
    if ($comp_id) wp_delete_post($comp_id, true);
    delete_transient(C::TRANSIENT);
    echo "   (limpieza: option del header, formulario y componente)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
