<?php
/**
 * Exportación CSV de envíos (SubmissionsController::build_csv, sin headers ni exit).
 *
 *   /Applications/MAMP/bin/php/php8.5.2/bin/php tests/test-entries-csv.php
 */
use pswptInit\Forms\FormsController as F;
use pswptInit\Forms\SubmissionsController as S;

$_SERVER['HTTP_HOST'] = 'localhost:8888'; $_SERVER['REQUEST_URI'] = '/WPfemsculpt/wp-admin/'; define('WP_USE_THEMES', false); define('WP_ADMIN', true);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user(1);
$fails = 0;
function check(string $label, bool $ok): void { global $fails; echo ($ok ? "  ok   " : "  FAIL ") . "$label\n"; if (!$ok) $fails++; }
$parse = function (string $csv): array { $rows = []; $h = fopen('php://temp', 'r+'); fwrite($h, preg_replace('/^\xEF\xBB\xBF/', '', $csv)); rewind($h); while (($r = fgetcsv($h, null, ",", '"', "\\")) !== false) $rows[] = $r; fclose($h); return $rows; };
$forms = [];

try {
    $forms['a'] = wp_insert_post(['post_type' => F::POST_TYPE, 'post_title' => 'Contacto', 'post_name' => 'csv-a', 'post_status' => 'publish']);
    update_post_meta($forms['a'], F::META_FIELDS, F::sanitize_fields([['name' => 'nombre', 'type' => 'text', 'label' => ['es' => 'Nombre', 'en' => 'Name']], ['name' => 'email', 'type' => 'email', 'label' => ['es' => 'Email']], ['name' => 'acepto', 'type' => 'checkbox', 'label' => ['es' => 'Acepto la <a href="/p/">política</a>']]]));
    $forms['b'] = wp_insert_post(['post_type' => F::POST_TYPE, 'post_title' => 'Newsletter', 'post_name' => 'csv-b', 'post_status' => 'publish']);
    update_post_meta($forms['b'], F::META_FIELDS, F::sanitize_fields([['name' => 'email', 'type' => 'email', 'label' => ['es' => 'Correo']], ['name' => 'ciudad', 'type' => 'text', 'label' => ['es' => 'Ciudad']]]));
    $ctx = ['lang' => 'es', 'page' => 'http://localhost:8888/WPfemsculpt/contacto/', 'ip' => '127.0.0.1', 'user_agent' => 'test', 'mail' => ['sent' => true, 'to' => 'x@y.com'], 'webhook' => ['status' => 200, 'error' => '']];
    $e1 = S::store(get_post($forms['a']), ['nombre' => 'Ana, "la" Pérez', 'email' => 'ana@example.com', 'acepto' => '1'], $ctx);
    $e2 = S::store(get_post($forms['a']), ['nombre' => '=HYPERLINK("http://evil")', 'email' => 'bob@example.com', 'acepto' => ''], array_merge($ctx, ['lang' => 'en', 'mail' => ['sent' => false, 'to' => 'x@y.com'], 'webhook' => null]));
    $e3 = S::store(get_post($forms['b']), ['email' => 'c@example.com', 'ciudad' => 'Bogotá'], $ctx);

    echo "1) un formulario\n";
    $rows = $parse(S::build_csv($forms['a']));
    check('BOM + cabecera fija + etiquetas de campos (sin HTML)', strpos(S::build_csv($forms['a']), "\xEF\xBB\xBF") === 0 && $rows[0] === ['ID', 'Fecha', 'Formulario', 'Idioma', 'Página', 'IP', 'Correo enviado', 'Webhook', 'Nombre', 'Email', 'Acepto la política']);
    check('2 filas, más reciente primero, valores con coma y comillas intactos', count($rows) === 3 && (int) $rows[1][0] === $e2 && (int) $rows[2][0] === $e1 && $rows[2][8] === 'Ana, "la" Pérez' && $rows[2][9] === 'ana@example.com' && $rows[2][10] === '1');
    check('contexto: idioma, página, correo sí/no, webhook', $rows[2][3] === 'ES' && $rows[2][4] === 'http://localhost:8888/WPfemsculpt/contacto/' && $rows[2][6] === 'yes' && $rows[2][7] === '200' && $rows[1][3] === 'EN' && $rows[1][6] === 'no' && $rows[1][7] === '');
    check('inyección de fórmula neutralizada', $rows[1][8] === "'=HYPERLINK(\"http://evil\")");
    check('sin envíos del otro formulario', !in_array('Bogotá', array_merge(...$rows), true));

    echo "2) todos los formularios\n";
    $all = $parse(S::build_csv(0));
    check('unión de columnas (Nombre, Email, Acepto…, Ciudad) y 3 filas', count($all) === 4 && array_slice($all[0], 8) === ['Nombre', 'Email', 'Acepto la política', 'Ciudad'] && $all[1][2] === 'Newsletter' && $all[1][11] === 'Bogotá' && $all[1][8] === '');

    echo "3) botón y URL\n";
    $url = S::export_url($forms['a']);
    check('URL con action, form y nonce', strpos($url, 'admin-post.php?action=' . S::EXPORT_ACTION) !== false && strpos($url, 'form=' . $forms['a']) !== false && strpos($url, '_wpnonce=') !== false);
    $_GET['pswpt_form'] = (string) $forms['a']; set_current_screen('edit-' . S::POST_TYPE);
    ob_start(); (new S())->export_button('top'); $btn = ob_get_clean();
    check('botón Exportar CSV con el filtro actual', strpos($btn, 'Export CSV') !== false && strpos($btn, 'form=' . $forms['a']) !== false);
    ob_start(); (new S())->export_button('bottom'); $none = ob_get_clean();
    check('solo arriba del listado', $none === '');

    echo "4) handler admin-post (subproceso: hace exit)\n";
    $sub = fn(string $code) => (string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg('$_SERVER["HTTP_HOST"]="localhost:8888"; $_SERVER["REQUEST_URI"]="/WPfemsculpt/wp-admin/admin-post.php"; define("WP_USE_THEMES",false); define("WP_ADMIN",true); require "' . ABSPATH . 'wp-load.php"; wp_set_current_user(1); ' . $code) . ' 2>/dev/null');
    $out = $sub('$_GET["form"] = "' . $forms['a'] . '"; $_REQUEST["_wpnonce"] = wp_create_nonce("' . S::EXPORT_ACTION . '"); (new \pswptInit\Forms\SubmissionsController())->handle_export();');
    check('con nonce válido devuelve el CSV', strpos($out, "\xEF\xBB\xBFID,Fecha,Formulario") === 0 && substr_count($out, "\n") === 3);
    $bad = $sub('$_GET["form"] = "0"; $_REQUEST["_wpnonce"] = "x"; (new \pswptInit\Forms\SubmissionsController())->handle_export();');
    check('nonce inválido → sin CSV', strpos($bad, "ID,Fecha") === false);
    $anon = $sub('wp_set_current_user(0); $_REQUEST["_wpnonce"] = wp_create_nonce("' . S::EXPORT_ACTION . '"); (new \pswptInit\Forms\SubmissionsController())->handle_export();');
    check('sin permisos → sin CSV', strpos($anon, "ID,Fecha") === false);
} finally {
    foreach (get_posts(['post_type' => S::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids']) as $id) wp_delete_post($id, true);
    foreach ($forms as $id) wp_delete_post($id, true);
    echo "   (limpieza: envíos y formularios)\n";
}
echo $fails ? "\nHAY FALLOS ($fails)\n" : "\nTODO OK\n";
exit($fails ? 1 : 0);
