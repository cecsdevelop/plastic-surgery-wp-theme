<?php
if (!defined('ABSPATH')) {
    exit;
}

// Oculta los endpoints REST de usuarios: evitan enumeracion anonima de logins/emails.
add_filter('rest_endpoints', function (array $endpoints): array {
    unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    return $endpoints;
});

// Desactiva XML-RPC (no se usa; es un vector clasico de fuerza bruta/DoS via system.multicall).
add_filter('xmlrpc_enabled', '__return_false');
