<?php
if (!defined('ABSPATH')) exit;

$current_lang = function_exists('idml_get_current_language') ? idml_get_current_language() : get_locale();
$home_url     = function_exists('idml_get_language_home_url') ? idml_get_language_home_url($current_lang) : home_url('/');

// Apariencia → Intelindev Header: sticky, logo, CTA (ver admin-header-settings.php).
$header_is_sticky = function_exists('intelindev_header_is_sticky') && intelindev_header_is_sticky();
$header_cta       = function_exists('intelindev_get_header_cta') ? intelindev_get_header_cta($current_lang) : ['type' => 'none'];
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
<header class="site-header<?php echo $header_is_sticky ? ' site-header--sticky' : ''; ?>"<?php if ($header_is_sticky) printf(' data-scroll-threshold="%d"', intelindev_get_header_sticky_threshold()); ?>>
  <div class="site-branding">
    <?php
    if (function_exists('intelindev_render_header_logo')) {
      intelindev_render_header_logo($home_url);
    } elseif (has_custom_logo()) {
      the_custom_logo();
    } else {
      echo '<a class="site-title" href="' . esc_url($home_url) . '" rel="home">' . esc_html(get_bloginfo('name')) . '</a>';
    }
    ?>
  </div>

  <button type="button" class="site-nav-toggle" aria-controls="site-nav" aria-expanded="false" aria-label="<?php echo esc_attr(idml_t('nav.menu_toggle_label')); ?>">
    <span class="site-nav-toggle__bar"></span><span class="site-nav-toggle__bar"></span><span class="site-nav-toggle__bar"></span>
  </button>

  <nav id="site-nav" class="site-nav" aria-label="<?php echo esc_attr(idml_t('nav.primary_label')); ?>">
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
  // CTA: píldora con texto o cuadrado con ícono (Apariencia → Intelindev Header → CTA → Estilo).
  if ($header_cta['type'] !== 'none') :
    $cta_is_icon = ($header_cta['style'] ?? 'text') === 'icon';
    $cta_class   = 'header-cta' . ($cta_is_icon ? ' header-cta--icon' : '');
    $cta_inner   = $cta_is_icon
      ? '<span class="screen-reader-text">' . esc_html($header_cta['text']) . '</span><svg class="header-cta__icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="8" cy="12" r="1" fill="currentColor"/><circle cx="12" cy="12" r="1" fill="currentColor"/><circle cx="16" cy="12" r="1" fill="currentColor"/></svg>'
      : esc_html($header_cta['text']);
    if ($header_cta['type'] === 'modal') :
  ?>
    <button type="button" class="<?php echo esc_attr($cta_class); ?>" data-cta-modal="header-cta-modal"><?php echo $cta_inner; ?></button>
  <?php else : ?>
    <a href="<?php echo esc_url($header_cta['href']); ?>" class="<?php echo esc_attr($cta_class); ?>"><?php echo $cta_inner; ?></a>
  <?php endif; endif; ?>
</header>
<?php if ($header_cta['type'] === 'modal') : ?>
<dialog id="header-cta-modal" class="header-cta-modal"<?php echo $header_cta['modal_title'] !== '' ? ' aria-labelledby="header-cta-modal-title"' : ''; ?> data-cta-modal-src="<?php echo esc_url(add_query_arg('lang', $current_lang, rest_url('intelindev/v1/modal'))); ?>" data-loading="<?php echo esc_attr(idml_t('cta.modal_loading')); ?>" data-error="<?php echo esc_attr(idml_t('cta.modal_error')); ?>">
  <div class="header-cta-modal__box">
    <button type="button" class="header-cta-modal__close" data-cta-modal-close aria-label="<?php echo esc_attr(idml_t('cta.modal_close_label')); ?>">&times;</button>
    <?php if ($header_cta['modal_title'] !== '') : ?>
      <h2 id="header-cta-modal-title" class="header-cta-modal__title"><?php echo esc_html($header_cta['modal_title']); ?></h2>
    <?php endif; ?>
    <div class="header-cta-modal__content" data-cta-modal-content></div>
  </div>
</dialog>
<?php endif;
