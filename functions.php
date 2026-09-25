<?php

$theme_bootstrap = get_template_directory() . '/inc/bootstrap/ThemeBootstrap.php';
if (file_exists($theme_bootstrap)) {
    require_once $theme_bootstrap;

    if (class_exists('pswptInit\\Bootstrap\\ThemeBootstrap')) {
        (new pswptInit\Bootstrap\ThemeBootstrap())->boot();
    }
}
