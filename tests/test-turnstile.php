<?php
/**
 * Cloudflare Turnstile en Formularios: ajustes globales, widget solo donde
 * corresponde y verificación server-side (claves de prueba oficiales de
 * Cloudflare: 1x… siempre pasa, 2x… siempre falla; requiere salida a internet).
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-turnstile.php
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
$clear_rl = function () { foreach (['127.0.0.1', '::1'] as $ip) delete_transient('intelindev_form_rl_' . md5($ip)); };

$PASS_SITE = '1x00000000000000000000AA'; $PASS_SECRET = '1x0000000000000000000000000000000AA'; $FAIL_SECRET = '2x0000000000000000000000000000000AA';
$PAGE = 18; $META = INTELINDEV_POST_TRANSLATED_CONTENT_META;
$orig_blocks = get_post_meta($PAGE, $META, true); $orig_opts = get_option(F::OPTION, null);
$protected = 0; $plain = 0;

try {
    echo "1) ajustes globales\n";
    $c = new F();
    check('sanitize: solo site key y secret, texto plano', $c->sanitize_global_settings(['turnstile_site_key' => ' <b>k</b> ', 'turnstile_secret' => 's', 'x' => 1]) === ['turnstile_site_key' => 'k', 'turnstile_secret' => 's']);
    update_option(F::OPTION, ['turnstile_site_key' => '', 'turnstile_secret' => '']);
    check('sin claves → no disponible', !F::turnstile_available());
    ob_start(); $c->render_settings_page(); $page = ob_get_clean();
    check('página Ajustes con los dos campos', strpos($page, 'name="' . F::OPTION . '[turnstile_site_key]"') !== false && strpos($page, 'type="password"') !== false);

    echo "2) fixtures\n";
    $mk = function (string $slug, bool $captcha) { $id = wp_insert_post(['post_type' => F::POST_TYPE, 'post_title' => $slug, 'post_name' => $slug, 'post_status' => 'publish']); update_post_meta($id, F::META_FIELDS, F::sanitize_fields([['name' => 'nombre', 'type' => 'text', 'required' => '1', 'label' => ['es' => 'Nombre']]])); update_post_meta($id, F::META_SETTINGS, F::sanitize_settings(['store' => '1', 'captcha' => $captcha ? '1' : ''])); return $id; };
    $protected = $mk('con-captcha', true); $plain = $mk('sin-captcha', false);
    check('captcha guardado por formulario', F::get_settings($protected)['captcha'] === 1 && F::get_settings($plain)['captcha'] === 0);

    echo "3) render\n";
    $blocks = is_array($orig_blocks) ? $orig_blocks : []; $blocks['es'] = ['[form slug="con-captcha"]', '[form slug="sin-captcha"]']; update_post_meta($PAGE, $META, $blocks);
    $html = $curl('/contactanos/');
    check('sin claves: ni widget ni script aunque el form lo pida', strpos($html, 'cf-turnstile') === false && strpos($html, 'challenges.cloudflare.com') === false);
    update_option(F::OPTION, ['turnstile_site_key' => $PASS_SITE, 'turnstile_secret' => $PASS_SECRET]);
    $html = $curl('/contactanos/');
    check('con claves: widget con sitekey e idioma solo en el form protegido', substr_count($html, 'class="cf-turnstile"') === 1 && strpos($html, 'data-sitekey="' . $PASS_SITE . '" data-language="es"') !== false && strpos($html, 'data-error-for="_captcha"') !== false);
    check('script de Cloudflare con render explícito, una vez', substr_count($html, 'challenges.cloudflare.com/turnstile/v0/api.js?onload=intelindevTurnstileRender&render=explicit') === 1);
    check('página sin formularios protegidos no carga el script', strpos($curl('/'), 'challenges.cloudflare.com') === false);

    echo "4) verificación server-side (claves de prueba de Cloudflare)\n";
    $clear_rl();
    $ts = time() - 5;
    $base = fn(int $id) => ['_lang' => 'es', '_ts' => $ts, '_sig' => R::sign($id, $ts), 'nombre' => 'Ana'];
    [$code, $body] = $send($protected, $base($protected));
    check('protegido sin token → 422 con error _captcha en español', $code === 422 && ($body['errors']['_captcha'] ?? '') === 'No pudimos verificar que sos humano. Intentá de nuevo.');
    [$code, $body] = $send($protected, $base($protected) + ['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX']);
    check('protegido con token y secret "siempre pasa" → 200', $code === 200 && ($body['ok'] ?? false) === true);
    update_option(F::OPTION, ['turnstile_site_key' => $PASS_SITE, 'turnstile_secret' => $FAIL_SECRET]);
    [$code, $body] = $send($protected, $base($protected) + ['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX']);
    check('secret "siempre falla" → 422 _captcha', $code === 422 && isset($body['errors']['_captcha']));
    [$code, $body] = $send($plain, $base($plain));
    check('formulario sin captcha no exige token', $code === 200);
    check('solo 2 envíos guardados (los aceptados)', count(get_posts(['post_type' => S::POST_TYPE, 'posts_per_page' => -1, 'fields' => 'ids'])) === 2);
} finally {
    if ($orig_blocks === '') delete_post_meta($PAGE, $META); else update_post_meta($PAGE, $META, $orig_blocks);
    if ($orig_opts === null) delete_option(F::OPTION); else update_option(F::OPTION, $orig_opts);
    foreach (get_posts(['post_type' => S::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids']) as $id) wp_delete_post($id, true);
    foreach ([$protected, $plain] as $id) if ($id) wp_delete_post($id, true);
    $clear_rl();
    echo "   (limpieza: bloques, ajustes, formularios, envíos, rate limit)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
