<?php
if (!defined('ABSPATH')) exit;

$current_lang = function_exists('idml_get_current_language') ? idml_get_current_language() : get_locale();
?>
<!doctype html>
<html lang="<?php echo esc_attr($current_lang); ?>">
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php
  if (function_exists('intelindev_get_setting')) {
    $favicon_url = intelindev_get_setting('favicon');
    if (!empty($favicon_url)) {
      $favicon_type = wp_check_filetype($favicon_url);
      printf('<link rel="icon" href="%s" type="%s">' . "\n", esc_url($favicon_url), esc_attr($favicon_type['type'] ?: 'image/x-icon'));
    }
  }
  ?>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header" data-scroll-threshold="80">
  <div class="site-branding">
    <?php if (has_custom_logo()) : ?>
      <?php the_custom_logo(); ?>
    <?php else : ?>
      <a class="site-title" href="<?php echo esc_url(function_exists('idml_get_language_home_url') ? idml_get_language_home_url($current_lang) : home_url('/')); ?>" rel="home"><?php bloginfo('name'); ?></a>
    <?php endif; ?>
  </div>

  <nav class="site-nav" aria-label="<?php esc_attr_e('Primary', 'intelindev'); ?>">
    <?php
    wp_nav_menu([
      'theme_location' => 'primary',
      'container'      => false,
      'fallback_cb'    => false,
      'menu_class'     => 'nav-list',
      'walker'         => class_exists('IDML_Walker_Nav_Menu') ? new IDML_Walker_Nav_Menu() : null,
    ]);
    ?>
  </nav>

  <?php
  // CTA configurable desde Apariencia → Intelindev Header. Vacío = sin CTA (a
  // diferencia de Sanasana, este theme no tiene un portal/login fijo al que
  // apuntar por defecto, así que sin URL configurada simplemente no se imprime).
  $header_cta_url  = function_exists('intelindev_get_header_setting') ? trim((string) intelindev_get_header_setting('cta_url', '')) : '';
  $header_cta_text = function_exists('intelindev_get_header_setting') ? trim((string) intelindev_get_header_setting('cta_text', '')) : '';
  if ($header_cta_url !== '' && preg_match('#^https?://#i', $header_cta_url) && $header_cta_text !== '') :
  ?>
    <a href="<?php echo esc_url($header_cta_url); ?>" class="header-cta"><?php echo esc_html($header_cta_text); ?></a>
  <?php endif; ?>
</header>
