<?php
/**
 * Test de integración del módulo Formularios (CLI + curl contra MAMP local).
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-forms.php
 *
 * Crea un formulario, un receptor de webhook temporal en htdocs/_claude-test,
 * bloques traducidos temporales en la página 18 y envíos; restaura todo al final.
 */
use IntelindevInit\Forms\FormsController as F;
use IntelindevInit\Forms\FormRenderer as R;
use IntelindevInit\Forms\SubmissionsController as S;

$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/Intelindev/'; define('WP_USE_THEMES', false);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
wp_set_current_user(1);

$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$curl = fn(string $path) => (string) shell_exec('curl -s ' . escapeshellarg('http://localhost:8888/Intelindev' . $path));
$send = function (int $id, array $data): array {
    $out = shell_exec('curl -s -w "\n%{http_code}" -X POST -H "Accept: application/json" --data ' . escapeshellarg(http_build_query($data)) . ' ' . escapeshellarg('http://localhost:8888/Intelindev/wp-json/intelindev/v1/form/' . $id));
    $lines = explode("\n", trim((string) $out)); $code = (int) array_pop($lines);
    return [$code, json_decode(implode("\n", $lines), true) ?: []];
};
$sub = fn(string $code) => trim((string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg('$_SERVER["HTTP_HOST"]="localhost:8888"; $_SERVER["REQUEST_URI"]="/Intelindev/wp-admin/"; define("WP_USE_THEMES",false); define("WP_ADMIN",true); require "' . ABSPATH . 'wp-load.php"; require_once ABSPATH."wp-admin/includes/template.php"; wp_set_current_user(1); ' . $code) . ' 2>/dev/null'));
$clear_rate_limit = function () { foreach (['127.0.0.1', '::1', 'localhost'] as $ip) delete_transient('intelindev_form_rl_' . md5($ip)); };

$PAGE = 18; $META = INTELINDEV_POST_TRANSLATED_CONTENT_META;
$orig_blocks = get_post_meta($PAGE, $META, true);
$hook_dir = '/Applications/MAMP/htdocs/_claude-test'; $hook_log = $hook_dir . '/hook.log';
$form_id = 0; $draft_id = 0;

try {
    echo "1) sanitize\n";
    $fields = F::sanitize_fields([
        ['name' => ' Nombre Completo ', 'type' => 'text', 'required' => '1', 'width' => 'col-12 col-md-6', 'label' => ['es' => ' Nombre ', 'en' => 'Name'], 'placeholder' => ['es' => '', 'en' => '']],
        ['name' => '_lang', 'type' => 'text'],   // _ inicial → "lang"
        ['name' => 'lang', 'type' => 'text'],    // duplicado → fuera
        ['name' => 'asunto', 'type' => 'select', 'options' => ['es' => "Ventas\n\nSoporte ", 'en' => "Sales\nSupport"], 'width' => 'hack'],
        ['name' => 'x', 'type' => 'hack', 'value' => 'v', 'options' => ['es' => 'no']],
        ['name' => 'origen', 'type' => 'hidden', 'value' => ' web '],
        ['name' => '', 'type' => 'text'],
    ]);
    check('nombres limpios, _ inicial quitado, duplicado y vacío fuera', array_column($fields, 'name') === ['nombrecompleto', 'lang', 'asunto', 'x', 'origen']);
    check('tipo/ancho inválidos → text/col-12; options solo en select; value solo en hidden', $fields[3]['type'] === 'text' && $fields[3]['options'] === [] && $fields[3]['value'] === '' && $fields[2]['width'] === 'col-12' && $fields[4]['value'] === 'web');
    check('label trim por idioma; opciones multilínea', $fields[0]['label'] === ['es' => 'Nombre', 'en' => 'Name'] && F::field_options($fields[2], 'es') === ['Ventas', 'Soporte'] && F::field_options($fields[2], 'en') === ['Sales', 'Support']);
    $settings = F::sanitize_settings(['recipients' => 'a@b.com, no-es-mail; c@d.org', 'subject' => ['es' => 'Hola {site}'], 'redirect' => 'javascript:alert(1)', 'webhook' => 'https://hook.example/x', 'store' => '']);
    check('settings: emails filtrados, redirect no-http fuera, webhook ok, store 0', $settings['recipients'] === 'a@b.com, c@d.org' && $settings['redirect'] === '' && $settings['webhook'] === 'https://hook.example/x' && $settings['store'] === 0 && $settings['subject'] === ['es' => 'Hola {site}']);

    echo "2) fixtures\n";
    @mkdir($hook_dir, 0755, true);
    file_put_contents($hook_dir . '/hook.php', '<?php file_put_contents(__DIR__ . "/hook.log", file_get_contents("php://input")); header("Content-Type: application/json"); echo "{\"received\":true}";');
    $form_id = wp_insert_post(['post_type' => F::POST_TYPE, 'post_title' => 'Contacto', 'post_name' => 'contacto', 'post_status' => 'publish']);
    update_post_meta($form_id, F::META_FIELDS, F::sanitize_fields([
        ['name' => 'nombre', 'type' => 'text', 'required' => '1', 'width' => 'col-12 col-md-6', 'label' => ['es' => 'Nombre', 'en' => 'Name'], 'placeholder' => ['es' => 'Tu nombre', 'en' => 'Your name']],
        ['name' => 'email', 'type' => 'email', 'required' => '1', 'width' => 'col-12 col-md-6', 'label' => ['es' => 'Email', 'en' => 'Email']],
        ['name' => 'telefono', 'type' => 'tel', 'label' => ['es' => 'Teléfono', 'en' => 'Phone']],
        ['name' => 'asunto', 'type' => 'select', 'label' => ['es' => 'Asunto', 'en' => 'Subject'], 'placeholder' => ['es' => 'Elegí…', 'en' => 'Choose…'], 'options' => ['es' => "Ventas\nSoporte", 'en' => "Sales\nSupport"]],
        ['name' => 'mensaje', 'type' => 'textarea', 'required' => '1', 'label' => ['es' => 'Mensaje', 'en' => 'Message']],
        ['name' => 'acepto', 'type' => 'checkbox', 'required' => '1', 'label' => ['es' => 'Acepto la <a href="/privacidad/">política</a>', 'en' => 'I accept the <a href="/en/privacy/">policy</a>']],
        ['name' => 'origen', 'type' => 'hidden', 'value' => 'web'],
    ]));
    update_post_meta($form_id, F::META_SETTINGS, F::sanitize_settings(['recipients' => 'ventas@example.com', 'subject' => ['es' => 'Contacto desde {site}', 'en' => 'Contact from {site}'], 'button' => ['es' => 'Enviar consulta', 'en' => 'Send inquiry'], 'success' => ['es' => 'Gracias, te respondemos pronto.', 'en' => 'Thanks, we will reply soon.'], 'webhook' => 'http://localhost:8888/_claude-test/hook.php', 'store' => '1']));
    $draft_id = wp_insert_post(['post_type' => F::POST_TYPE, 'post_title' => 'Borrador', 'post_name' => 'borrador', 'post_status' => 'draft']);
    update_post_meta($draft_id, F::META_FIELDS, F::sanitize_fields([['name' => 'a', 'type' => 'text']]));
    $blocks = is_array($orig_blocks) ? $orig_blocks : []; $blocks['es'] = ['[form slug="contacto"]', '[form slug="borrador"]']; $blocks['en'] = ['[form slug="contacto"]'];
    update_post_meta($PAGE, $META, $blocks);
    $clear_rate_limit();
    check('formulario y borrador creados', $form_id > 0 && $draft_id > 0);

    echo "3) render (curl ES/EN)\n";
    $es = $curl('/contacto/'); $en = $curl('/en/contact-us/');
    check('ES: form con action REST, data-success y _lang', preg_match('#<form class="intelindev-form" method="post" action="http://localhost:8888/Intelindev/wp-json/intelindev/v1/form/' . $form_id . '" data-intelindev-form="' . $form_id . '" data-success="Gracias, te respondemos pronto."#', $es) === 1 && strpos($es, 'name="_lang" value="es"') !== false);
    check('ES: nombre col-md-6 required + placeholder; textarea; select con opciones ES', strpos($es, '<div class="col-12 col-md-6 intelindev-form__field intelindev-form__field--text">') !== false && preg_match('/<input type="text" id="f' . $form_id . '-nombre" name="nombre" placeholder="Tu nombre" required>/', $es) === 1 && strpos($es, '<textarea id="f' . $form_id . '-mensaje" name="mensaje" rows="5" placeholder="" required>') !== false && strpos($es, '<option value="Ventas">Ventas</option>') !== false && strpos($es, '<option value="">Elegí…</option>') !== false);
    check('ES: checkbox con link, hidden, honeypot, token, botón', strpos($es, 'Acepto la <a href="/privacidad/">política</a> <span class="intelindev-form__req"') !== false && strpos($es, '<input type="hidden" name="origen" value="web">') !== false && strpos($es, 'name="_website"') !== false && preg_match('/name="_sig" value="[a-f0-9]{64}"/', $es) === 1 && strpos($es, '<button type="submit" class="intelindev-form__submit">Enviar consulta</button>') !== false);
    check('ES: borrador no renderiza', strpos($es, 'slug="borrador"') === false && substr_count($es, '<form class="intelindev-form"') === 1);
    check('EN: etiquetas, opciones, botón y success en inglés', strpos($en, '>Name <span class="intelindev-form__req"') !== false && strpos($en, '<option value="Sales">Sales</option>') !== false && strpos($en, '>Send inquiry</button>') !== false && strpos($en, 'data-success="Thanks, we will reply soon."') !== false && strpos($en, 'name="_lang" value="en"') !== false);

    echo "4) envíos por REST\n";
    $ts = time() - 5; $sig = R::sign($form_id, $ts);
    $valid = ['_lang' => 'es', '_ts' => $ts, '_sig' => $sig, '_page' => 'http://localhost:8888/Intelindev/contacto/', 'nombre' => 'Ana', 'email' => 'ana@example.com', 'telefono' => '+57 300 123 4567', 'asunto' => 'Ventas', 'mensaje' => "Hola\nQuiero info", 'acepto' => '1', 'origen' => 'web'];
    [$c, $b] = $send($form_id, array_merge($valid, ['_sig' => 'x']));
    check('firma inválida → 400', $c === 400 && $b['ok'] === false);
    [$c, $b] = $send($form_id, array_merge($valid, ['_ts' => time(), '_sig' => R::sign($form_id, time())]));
    check('demasiado rápido (<3 s) → 400', $c === 400);
    [$c, $b] = $send($form_id, array_merge($valid, ['_website' => 'http://spam']));
    $before = count(get_posts(['post_type' => S::POST_TYPE, 'posts_per_page' => -1, 'fields' => 'ids']));
    check('honeypot → 200 "ok" sin guardar nada', $c === 200 && $b['ok'] === true && $before === 0);
    [$c, $b] = $send($form_id, array_merge($valid, ['_lang' => 'en', 'nombre' => '', 'email' => 'no-es-mail', 'mensaje' => '', 'acepto' => '', 'asunto' => 'Otra', 'telefono' => 'abc']));
    check('validación → 422 con errores por campo en inglés', $c === 422 && ($b['errors']['nombre'] ?? '') === 'This field is required.' && ($b['errors']['email'] ?? '') === 'Enter a valid email.' && isset($b['errors']['mensaje'], $b['errors']['acepto']) && ($b['errors']['asunto'] ?? '') === 'Invalid value.' && ($b['errors']['telefono'] ?? '') === 'Invalid value.' && $b['message'] === 'Please check the highlighted fields.');
    check('select acepta opción del otro idioma', $send($form_id, array_merge($valid, ['asunto' => 'Sales']))[0] === 200);
    @unlink($hook_log);
    [$c, $b] = $send($form_id, $valid);
    check('envío válido → 200 con mensaje de éxito ES', $c === 200 && $b['ok'] === true && $b['message'] === 'Gracias, te respondemos pronto.' && $b['redirect'] === '');
    $entries = get_posts(['post_type' => S::POST_TYPE, 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'DESC']);
    $entry = $entries[0] ?? null; $data = $entry ? get_post_meta($entry->ID, S::META_DATA, true) : []; $ctx = $entry ? get_post_meta($entry->ID, S::META_CONTEXT, true) : [];
    check('envío guardado con datos limpios y título "Contacto · Ana"', $entry && $entry->post_title === 'Contacto · Ana' && $data['email'] === 'ana@example.com' && $data['acepto'] === '1' && $data['origen'] === 'web' && $data['mensaje'] === "Hola\nQuiero info" && (int) get_post_meta($entry->ID, S::META_FORM, true) === $form_id);
    check('contexto: lang, página, resultado del correo registrado (bool)', ($ctx['lang'] ?? '') === 'es' && ($ctx['page'] ?? '') === 'http://localhost:8888/Intelindev/contacto/' && isset($ctx['mail']['sent']) && is_bool($ctx['mail']['sent']) && ($ctx['mail']['to'] ?? '') === 'ventas@example.com');
    $received = file_exists($hook_log) ? json_decode((string) file_get_contents($hook_log), true) : null;
    check('webhook: POST JSON recibido (form, lang, fields) y HTTP 200 registrado', ($ctx['webhook']['status'] ?? 0) === 200 && is_array($received) && $received['form']['slug'] === 'contacto' && $received['lang'] === 'es' && $received['fields']['nombre'] === 'Ana' && $received['fields']['asunto'] === 'Ventas');
    for ($n = 0; $n < 3; $n++) $send($form_id, $valid); // 2 previos + 1 + 3 = 6 intentos válidos
    [$c, $b] = $send($form_id, $valid);
    check('límite por IP: 6º envío → 429', $c === 429 && $b['message'] === 'Demasiados intentos, esperá unos minutos.');
    $clear_rate_limit();
    check('borrador → 404', $send($draft_id, ['_lang' => 'es', '_ts' => $ts, '_sig' => R::sign($draft_id, $ts)])[0] === 404);

    echo "5) admin\n";
    $mb = $sub('ob_start(); (new \IntelindevInit\Forms\FormsController())->render_fields_meta_box(get_post(' . $form_id . ')); echo ob_get_clean();');
    check('metabox campos: 7 filas + template + shortcode', substr_count($mb, 'class="intelindev-form-field"') === 8 && strpos($mb, '[fields][__i__][name]') !== false && strpos($mb, '<code>[form slug="contacto"]</code>') !== false && strpos($mb, 'value="Tu nombre"') !== false);
    $ms = $sub('ob_start(); (new \IntelindevInit\Forms\FormsController())->render_settings_meta_box(get_post(' . $form_id . ')); echo ob_get_clean();');
    check('metabox envío: destinatarios, asunto por idioma, webhook, store', strpos($ms, 'value="ventas@example.com"') !== false && strpos($ms, 'name="intelindev_form[settings][subject][en]" value="Contact from {site}"') !== false && strpos($ms, 'value="http://localhost:8888/_claude-test/hook.php"') !== false && preg_match('/name="intelindev_form\[settings\]\[store\]" value="1"\s*checked/', $ms) === 1);
    $_POST = [F::NONCE_FIELD => wp_create_nonce(F::NONCE_ACTION), F::FIELD => ['fields' => [['name' => 'solo', 'type' => 'email', 'required' => '1', 'width' => 'col-12', 'label' => ['es' => 'Solo', 'en' => '']]], 'settings' => ['recipients' => 'x@y.com', 'store' => '1']]];
    do_action('save_post_' . F::POST_TYPE, $form_id, get_post($form_id), true);
    check('save con nonce: campos y ajustes reemplazados', count(F::get_fields($form_id)) === 1 && F::get_fields($form_id)[0]['name'] === 'solo' && F::get_settings($form_id)['recipients'] === 'x@y.com');
    $col = $sub('ob_start(); do_action("manage_' . F::POST_TYPE . '_posts_custom_column", "intelindev_shortcode", ' . $form_id . '); do_action("manage_' . F::POST_TYPE . '_posts_custom_column", "intelindev_entries", ' . $form_id . '); echo ob_get_clean();');
    $stored = count(get_posts(['post_type' => S::POST_TYPE, 'posts_per_page' => -1, 'fields' => 'ids']));
    check('columnas: shortcode y conteo de envíos (= DB) con link', strpos($col, '<code>[form slug="contacto"]</code>') !== false && preg_match('/intelindev_form=' . $form_id . '">(\d+)<\/a>/', $col, $m) === 1 && (int) $m[1] === $stored && $stored >= 4);
    $emb = $sub('ob_start(); (new \IntelindevInit\Forms\SubmissionsController())->render_meta_box(get_post(' . $entry->ID . ')); echo ob_get_clean();');
    check('detalle del envío: datos + contexto', strpos($emb, '<td>ana@example.com</td>') !== false && strpos($emb, 'Webhook') !== false && strpos($emb, 'HTTP 200') !== false);
} finally {
    if ($orig_blocks === '') delete_post_meta($PAGE, $META); else update_post_meta($PAGE, $META, $orig_blocks);
    foreach (get_posts(['post_type' => S::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids']) as $id) wp_delete_post($id, true);
    if ($form_id) wp_delete_post($form_id, true);
    if ($draft_id) wp_delete_post($draft_id, true);
    $clear_rate_limit();
    shell_exec('rm -rf ' . escapeshellarg($hook_dir));
    echo "   (limpieza: bloques, formularios, envíos, rate limit y receptor de webhook)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
