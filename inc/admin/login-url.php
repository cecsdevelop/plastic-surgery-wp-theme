<?php
/**
 * Ruta de acceso personalizada (Apariencia → pswpt Settings → Security).
 * Portado del theme Intelindev original (inc/admin/login-url.php), que sí
 * tenía esta pestaña — se había quedado afuera en el renombre a pswpt.
 *
 * Con una ruta configurada (p. ej. "la-ruta-loca"):
 *   - /la-ruta-loca/ sirve el login real de WordPress, sin cambiar la URL.
 *   - /wp-login.php y /wp-register.php responden 404 (el 404 del theme).
 *   - /wp-admin/… responde 404 a quien no tenga sesión (admin-ajax.php y
 *     admin-post.php siguen abiertos: son endpoints públicos legítimos).
 *   - Todos los enlaces que WordPress genera a wp-login.php (login, logout,
 *     recuperar contraseña, formulario de entradas protegidas y los correos)
 *     apuntan a la ruta nueva.
 *
 * Campo vacío = comportamiento estándar de WordPress (nada de esto se aplica).
 *
 * Esto es una capa de ruido, no una barrera: sigue siendo imprescindible tener
 * contraseñas fuertes y 2FA. Al ser código del theme, con otro theme activo
 * vuelve el /wp-admin normal.
 *
 * Salida de emergencia: definir en wp-config.php
 *     define('PSWPT_LOGIN_SLUG_DISABLED', true);
 * deja el login estándar aunque haya ruta guardada.
 *
 * @package pswpt
 */

if (!defined('ABSPATH')) exit;

/** Rutas que no se pueden usar como acceso (chocan con WordPress o con el sitio). */
function pswpt_login_reserved_slugs(): array
{
    $reserved = [
        'wp-admin', 'wp-login', 'wp-login-php', 'wp-register', 'wp-content', 'wp-includes',
        'wp-json', 'wp-cron', 'xmlrpc', 'feed', 'rss', 'embed', 'sitemap', 'robots',
        'admin', 'dashboard', 'comments', 'author', 'page', 'search', 'attachment',
    ];

    if (function_exists('idml_get_languages')) {
        $reserved = array_merge($reserved, idml_get_languages());
    }
    // Bases de archivo de los CPT públicos en cada idioma (servicios, portafolio…).
    foreach ((array) apply_filters('pswpt_post_type_lang_slugs', []) as $slugs) {
        $reserved = array_merge($reserved, array_values((array) $slugs));
    }

    return array_values(array_unique(array_filter(array_map('sanitize_title', $reserved))));
}

/**
 * Valida la ruta escrita en el panel: '' la apaga; una ruta reservada o que ya
 * ocupa una página publicada se rechaza (con aviso) y se conserva la anterior.
 */
function pswpt_sanitize_login_slug($raw, string $current = ''): string
{
    $slug = sanitize_title((string) $raw);
    if ($slug === '') {
        return '';
    }

    $error = '';
    if (in_array($slug, pswpt_login_reserved_slugs(), true)) {
        $error = sprintf(__('"%s" is a path reserved by WordPress or by the site.', 'pswpt'), $slug);
    } elseif (($page = get_page_by_path($slug)) instanceof WP_Post && $page->post_status === 'publish') {
        $error = sprintf(__('A published page already exists at /%s/.', 'pswpt'), $slug);
    } elseif (strlen($slug) < 3) {
        $error = __('The path needs at least 3 characters.', 'pswpt');
    }

    if ($error !== '') {
        if (function_exists('add_settings_error')) {
            add_settings_error('pswpt_settings', 'pswpt_login_slug', $error . ' ' . __('The previous path was kept.', 'pswpt'), 'error');
        }
        return $current;
    }

    return $slug;
}

/** Ruta activa, o '' si la función está apagada (campo vacío, constante o multisitio). */
function pswpt_login_slug(): string
{
    if ((defined('PSWPT_LOGIN_SLUG_DISABLED') && PSWPT_LOGIN_SLUG_DISABLED) || is_multisite()) {
        return '';
    }
    $slug = function_exists('pswpt_get_setting') ? (string) pswpt_get_setting('login_slug', '') : '';

    return sanitize_title((string) apply_filters('pswpt_login_slug', $slug));
}

/** URL del login: la ruta personalizada o la de WordPress si no hay ninguna. */
function pswpt_login_url(string $query = ''): string
{
    $slug = pswpt_login_slug();
    $url  = $slug === '' ? wp_login_url() : home_url('/' . $slug . '/');
    $query = ltrim($query, '?');

    return $query !== '' ? $url . (strpos($url, '?') !== false ? '&' : '?') . $query : $url;
}

/** Ruta pedida, normalizada y sin el subdirectorio de la instalación ni barras extra. */
function pswpt_login_request_path(): string
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $base = (string) parse_url(home_url('/'), PHP_URL_PATH);
    if ($base !== '' && $base !== '/' && strpos($path, rtrim($base, '/')) === 0) {
        $path = substr($path, strlen(rtrim($base, '/')));
    }

    return '/' . trim((string) $path, '/');
}

/**
 * Reemplaza wp-login.php por la ruta personalizada en cualquier URL que arme
 * WordPress (site_url, redirecciones, correos de recuperación).
 */
function pswpt_login_filter_url($url)
{
    $slug = pswpt_login_slug();
    if ($slug === '' || !is_string($url) || strpos($url, 'wp-login.php') === false) {
        return $url;
    }
    $query = (string) parse_url($url, PHP_URL_QUERY);

    return home_url('/' . $slug . '/') . ($query !== '' ? '?' . $query : '');
}

/** Pinta el 404 del theme y corta la petición (misma respuesta que una URL inexistente). */
function pswpt_login_render_404(): void
{
    global $pagenow;

    if (!defined('WP_USE_THEMES')) {
        define('WP_USE_THEMES', true);
    }
    $pagenow = 'index.php';

    // En /wp-admin la petición ya se marcó como admin (WP_ADMIN) y ahí la barra
    // de administración se pinta siempre: al renderizar el 404 pediría pantallas
    // de wp-admin que todavía no se cargaron (get_current_screen()). Se quita su
    // render (en un 404 no pinta nada útil de todos modos).
    remove_action('wp_body_open', 'wp_admin_bar_render', 0);
    remove_action('wp_footer', 'wp_admin_bar_render', 1000);
    remove_action('in_admin_header', 'wp_admin_bar_render', 0);

    // Se monta una petición a una ruta que no existe para que WP arme el 404
    // normal del sitio: misma respuesta, byte a byte, que cualquier URL rota.
    $base = rtrim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    $_SERVER['REQUEST_URI'] = $base . '/' . md5('pswpt-login-404') . '/';
    $_GET  = [];
    $_POST = [];
    $_REQUEST = [];

    wp();
    status_header(404);
    nocache_headers();
    require_once ABSPATH . WPINC . '/template-loader.php';
    exit;
}

/**
 * Sirve el login real de WordPress en la ruta personalizada, sin redirigir.
 *
 * wp-login.php está escrito para ejecutarse como script principal: sus
 * variables viven en el ámbito global y varias de sus funciones las leen con
 * `global` ($error e $interim_login en login_header(), $user_login que el core
 * inicializa en setup_userdata()). Al incluirlo desde una función habría que
 * declararlas o quedarían como locales sin definir ("Undefined variable
 * $user_login"), así que se declaran todas las que el archivo usa en su nivel
 * superior y el include se comporta igual que la carga normal.
 */
function pswpt_login_serve(): void
{
    global $pagenow, $wp, $wp_query, $wp_version, $current_site, $error, $errors, $wp_error,
        $action, $user, $user_login, $user_ID, $user_email, $user_url, $user_identity, $user_level, $userdata,
        $interim_login, $customize_login, $redirect_to, $requested_redirect_to, $registration_redirect,
        $lostpassword_redirect, $secure_cookie, $rememberme, $reauth, $http_post, $shake_error_codes,
        $login_header_url, $login_header_title, $login_header_text, $login_title, $login_script, $login_link_separator,
        $message, $messages, $key, $hasher, $rp_cookie, $result, $expire, $secure,
        $admin_email, $admin_email_check_interval, $admin_email_lifespan, $admin_email_help_url,
        $request_id, $remind_interval, $registration_url, $languages, $has_errors, $aria_describedby, $classes;

    $pagenow = 'wp-login.php';

    // wp-login.php vuelve a pedir wp-load.php, que ya está cargado: sus require
    // internos son require_once, así que no se reejecuta nada.
    require_once ABSPATH . 'wp-login.php';
    exit;
}

/**
 * Guardia de rutas. En 'wp_loaded' todo está registrado (idiomas, CPT, plugins)
 * y la consulta principal todavía no corrió: es el punto donde se puede servir
 * el login o cortar con un 404 sin que nada haya impreso salida.
 */
function pswpt_login_route_guard(): void
{
    $slug = pswpt_login_slug();
    // Sin REQUEST_METHOD no hay petición HTTP que proteger (CLI, WP-CLI, cron):
    // el guardia solo tiene sentido con un visitante al otro lado.
    if ($slug === '' || empty($_SERVER['REQUEST_METHOD']) || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
        return;
    }

    $path = pswpt_login_request_path();

    if ($path === '/' . $slug) {
        pswpt_login_serve();
    }

    if ($path === '/wp-login.php' || $path === '/wp-register.php') {
        pswpt_login_render_404();
    }

    if (strpos($path, '/wp-admin') === 0
        && !is_user_logged_in()
        && !preg_match('#^/wp-admin/(admin-ajax|admin-post)\.php$#', $path)) {
        pswpt_login_render_404();
    }
}

add_action('wp_loaded', 'pswpt_login_route_guard', 0);

foreach (['site_url', 'network_site_url', 'wp_redirect', 'login_url', 'logout_url', 'lostpassword_url', 'register_url'] as $pswpt_login_hook) {
    add_filter($pswpt_login_hook, 'pswpt_login_filter_url', 20);
}
unset($pswpt_login_hook);
