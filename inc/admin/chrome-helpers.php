<?php
/**
 * Puente entre las plantillas y el módulo Chrome.
 *
 * Las plantillas necesitan funciones globales; el módulo es una clase con
 * namespace. Estos tres helpers son el único punto de contacto, para que
 * header.php y footer.php no tengan que conocer el namespace.
 *
 * El prefijo es `pswpt_` como el resto de globales del theme: el namespace
 * y los prefijos PHP no se han renombrado, y mezclar convenciones sería peor.
 *
 * @package pswpt
 */

use pswptInit\Chrome\ChromeController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ¿Se usa la cabecera y el pie del módulo Chrome en vez de los del theme?
 *
 * Opt-in a propósito: el theme trae su propia cabecera con sticky, logo y CTA
 * configurables, y activar la nueva por defecto rompería los sitios que ya
 * usan esa.
 */
function pswpt_chrome_active(): bool
{
    if (!class_exists(ChromeController::class)) {
        return false;
    }
    $settings = get_option(ChromeController::OPTION, []);
    return is_array($settings) && !empty($settings['enabled']);
}

function pswpt_chrome_header(): string
{
    return class_exists(ChromeController::class) ? (new ChromeController())->render_header() : '';
}

function pswpt_chrome_footer(): string
{
    return class_exists(ChromeController::class) ? (new ChromeController())->render_footer() : '';
}
