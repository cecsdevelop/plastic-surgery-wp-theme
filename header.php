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

  <nav class="site-nav" aria-label="<?php echo esc_attr(idml_t('nav.primary_label')); ?>">
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

  <?php if ($header_cta['type'] === 'modal') : ?>
    <button type="button" class="header-cta" data-cta-modal="header-cta-modal"><?php echo esc_html($header_cta['text']); ?></button>
  <?php elseif ($header_cta['type'] !== 'none') : ?>
    <a href="<?php echo esc_url($header_cta['href']); ?>" class="header-cta"><?php echo esc_html($header_cta['text']); ?></a>
  <?php endif; ?>
</header>
<?php if ($header_cta['type'] === 'modal') : ?>
<dialog id="header-cta-modal" class="header-cta-modal"<?php echo $header_cta['modal_title'] !== '' ? ' aria-labelledby="header-cta-modal-title"' : ''; ?>>
  <div class="header-cta-modal__box">
    <button type="button" class="header-cta-modal__close" data-cta-modal-close aria-label="<?php echo esc_attr(idml_t('cta.modal_close_label')); ?>">&times;</button>
    <?php if ($header_cta['modal_title'] !== '') : ?>
      <h2 id="header-cta-modal-title" class="header-cta-modal__title"><?php echo esc_html($header_cta['modal_title']); ?></h2>
    <?php endif; ?>
    <div class="header-cta-modal__content" data-cta-modal-content></div>
  </div>
</dialog>
<?php
// Contenido libre del admin (script de CRM / HTML / shortcode ya procesado).
// Inerte hasta que scripts.js lo inyecta en el <dialog> al primer clic.
echo '<template id="header-cta-modal-template">' . $header_cta['modal_content'] . '</template>' . "\n";
endif;
