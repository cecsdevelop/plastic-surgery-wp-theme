<?php
/**
 * pswpt Settings (Apariencia → pswpt Settings).
 *
 * Panel por pestañas portado de Sanasana Settings (Sanasana-multilanguage,
 * inc/admin/admin-settings.php) sobre la option `pswpt_settings`. Se
 * conservan la option, el grupo, el slug de página y las claves que ya existían,
 * así que los valores guardados siguen leyéndose sin migración.
 *
 * Regla del panel: cada campo tiene un consumidor en el theme. Los valores se
 * leen con pswpt_get_setting() (helpers.php):
 *   - favicon / favicon_id, theme_color, theme_brand → header.php y este panel
 *   - force_noindex, canonical_domain, gsc/bing, manifest_url, preconnect_hosts
 *     → seo-analytics.php
 *   - gtm_container_id, ga4_measurement_id, *_scripts → seo-analytics.php
 *   - image_quality, enable_webp, max_image_width → image-optimization.php
 *   - login_slug → login-url.php (Ajustes de Seguridad; portado del theme
 *     Intelindev original, que sí lo tenía — no formaba parte de este)
 * Los campos heredados sin consumidor (contact_email, featured_default,
 * archive_thumbs, enable_breadcrumbs, critical_images, optimize_images_list,
 * bot_guard_*) se retiraron y sanitize() los purga de la option al guardar.
 *
 * @package pswpt
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('pswpt_get_theme_brand_name')) {
  function pswpt_get_theme_brand_name(): string {
    $opts  = get_option('pswpt_settings', []);
    $brand = is_array($opts) ? trim((string) ($opts['theme_brand'] ?? '')) : '';
    return $brand !== '' ? $brand : 'pswpt';
  }
}

add_action('admin_menu', function() {
  $brand = pswpt_get_theme_brand_name();
  add_theme_page(
    sprintf(__('%s Settings', 'pswpt'), $brand),
    sprintf(__('%s Settings', 'pswpt'), $brand),
    'manage_options',
    'pswpt-settings',
    'pswpt_settings_page_html'
  );
});

add_action('admin_enqueue_scripts', function($hook) {
  if ($hook !== 'appearance_page_pswpt-settings') {
    return;
  }

  wp_enqueue_media();

  // file_exists() + filemtime(): el nombre del archivo y el del enqueue deben coincidir
  // exactamente o el panel se renderiza sin pestañas (todo apilado) sin ningún error.
  $css_path = get_template_directory() . '/assets/css/pswpt-settings-tabs.css';
  if (file_exists($css_path)) {
    wp_enqueue_style(
      'pswpt-settings-tabs',
      get_template_directory_uri() . '/assets/css/pswpt-settings-tabs.css',
      [],
      filemtime($css_path)
    );
  }

  $js_path = get_template_directory() . '/assets/js/pswpt-settings-tabs.js';
  if (file_exists($js_path)) {
    wp_enqueue_script(
      'pswpt-settings-tabs',
      get_template_directory_uri() . '/assets/js/pswpt-settings-tabs.js',
      ['jquery'],
      filemtime($js_path),
      true
    );
  }
}, 20);

add_action('admin_init', function() {
  register_setting('pswpt_settings_group', 'pswpt_settings', 'pswpt_settings_sanitize');

  // TAB 1: GENERAL
  add_settings_section('pswpt_general_section', __('General Site Configuration', 'pswpt'), function() {
    echo '<p>' . esc_html__('Basic site settings (favicon, colors, theme name).', 'pswpt') . '</p>';
  }, 'pswpt-settings-general');

  add_settings_field('pswpt_theme_brand', __('Theme name', 'pswpt'), 'pswpt_field_theme_brand_cb', 'pswpt-settings-general', 'pswpt_general_section');
  add_settings_field('pswpt_favicon', __('Favicon', 'pswpt'), 'pswpt_field_favicon_cb', 'pswpt-settings-general', 'pswpt_general_section');
  add_settings_field('pswpt_theme_color', __('Theme Color (hex)', 'pswpt'), 'pswpt_field_theme_color_cb', 'pswpt-settings-general', 'pswpt_general_section');

  // TAB 2: SEO & META
  add_settings_section('pswpt_seo_section', __('SEO & Search Engines', 'pswpt'), function() {
    echo '<p>' . esc_html__('Configure SEO settings, search engine verification, and metadata.', 'pswpt') . '</p>';
  }, 'pswpt-settings-seo');

  add_settings_field('pswpt_force_noindex', __('Force noindex', 'pswpt'), 'pswpt_field_force_noindex_cb', 'pswpt-settings-seo', 'pswpt_seo_section');
  add_settings_field('pswpt_canonical_domain', __('Canonical Domain', 'pswpt'), 'pswpt_field_canonical_domain_cb', 'pswpt-settings-seo', 'pswpt_seo_section');
  add_settings_field('pswpt_gsc_verification', __('Google Search Console Verification', 'pswpt'), 'pswpt_field_gsc_verification_cb', 'pswpt-settings-seo', 'pswpt_seo_section');
  add_settings_field('pswpt_bing_verification', __('Bing Verification', 'pswpt'), 'pswpt_field_bing_verification_cb', 'pswpt-settings-seo', 'pswpt_seo_section');
  add_settings_field('pswpt_manifest_url', __('Manifest URL', 'pswpt'), 'pswpt_field_manifest_url_cb', 'pswpt-settings-seo', 'pswpt_seo_section');
  add_settings_field('pswpt_preconnect_hosts', __('Preconnect Hosts', 'pswpt'), 'pswpt_field_preconnect_hosts_cb', 'pswpt-settings-seo', 'pswpt_seo_section');

  // TAB 3: ANALYTICS
  add_settings_section('pswpt_analytics_section', __('Analytics & Tracking', 'pswpt'), function() {
    echo '<p>' . esc_html__('Configure Google Tag Manager and Google Analytics. Only output when wp_get_environment_type() is "production".', 'pswpt') . '</p>';
  }, 'pswpt-settings-analytics');

  add_settings_field('pswpt_gtm_container_id', __('GTM Container ID', 'pswpt'), 'pswpt_field_gtm_container_id_cb', 'pswpt-settings-analytics', 'pswpt_analytics_section');
  add_settings_field('pswpt_ga4_measurement_id', __('GA4 Measurement ID', 'pswpt'), 'pswpt_field_ga4_measurement_id_cb', 'pswpt-settings-analytics', 'pswpt_analytics_section');

  // TAB 4: PERFORMANCE (consumidor: image-optimization.php; aplica a subidas nuevas)
  add_settings_section('pswpt_image_optimization_section', __('Image Optimization', 'pswpt'), function() {
    echo '<p>' . esc_html__('Image compression and optimization on upload. Does not modify images already uploaded.', 'pswpt') . '</p>';
  }, 'pswpt-settings-performance');

  add_settings_field('pswpt_image_quality', __('Compression Quality', 'pswpt'), 'pswpt_field_image_quality_cb', 'pswpt-settings-performance', 'pswpt_image_optimization_section');
  add_settings_field('pswpt_enable_webp', __('Enable WebP Conversion', 'pswpt'), 'pswpt_field_enable_webp_cb', 'pswpt-settings-performance', 'pswpt_image_optimization_section');
  add_settings_field('pswpt_max_image_width', __('Max Image Width', 'pswpt'), 'pswpt_field_max_image_width_cb', 'pswpt-settings-performance', 'pswpt_image_optimization_section');

  // TAB 5: SCRIPTS
  add_settings_section('pswpt_scripts_section', __('Custom Scripts (Deferred)', 'pswpt'), function() {
    echo '<p>' . esc_html__('Scripts loaded after 3s or user interaction (scroll/click/touch).', 'pswpt') . '</p>';
  }, 'pswpt-settings-scripts');

  add_settings_field('pswpt_header_scripts', __('Header Scripts', 'pswpt'), 'pswpt_field_header_scripts_cb', 'pswpt-settings-scripts', 'pswpt_scripts_section');
  add_settings_field('pswpt_body_scripts', __('Body Scripts', 'pswpt'), 'pswpt_field_body_scripts_cb', 'pswpt-settings-scripts', 'pswpt_scripts_section');
  add_settings_field('pswpt_footer_scripts', __('Footer Scripts', 'pswpt'), 'pswpt_field_footer_scripts_cb', 'pswpt-settings-scripts', 'pswpt_scripts_section');

  // TAB 6: IMMEDIATE SCRIPTS
  add_settings_section('pswpt_immediate_section', __('Immediate Scripts', 'pswpt'), function() {
    echo '<p>' . esc_html__('Scripts loaded immediately (not deferred). Use carefully for critical functionality only.', 'pswpt') . '</p>';
  }, 'pswpt-settings-immediate');

  add_settings_field('pswpt_immediate_footer', __('Footer Scripts (Immediate)', 'pswpt'), 'pswpt_field_immediate_footer_cb', 'pswpt-settings-immediate', 'pswpt_immediate_section');

  // TAB 7: SECURITY (consumidor: login-url.php)
  add_settings_section('pswpt_security_section', __('Site access', 'pswpt'), function() {
    echo '<p>' . esc_html__('Custom path to reach the dashboard. With a path set, /wp-admin/ and /wp-login.php return 404 to anyone without an active session.', 'pswpt') . '</p>';
  }, 'pswpt-settings-security');

  add_settings_field('pswpt_login_slug', __('Access path', 'pswpt'), 'pswpt_field_login_slug_cb', 'pswpt-settings-security', 'pswpt_security_section');
});

/* ------------------------------------------------------------------ */
/* Field callbacks                                                      */
/* ------------------------------------------------------------------ */

function pswpt_field_theme_brand_cb() {
  $val = esc_attr((string) pswpt_get_setting('theme_brand', ''));
  echo '<input id="pswpt_theme_brand" name="pswpt_settings[theme_brand]" type="text" value="' . $val . '" class="regular-text" placeholder="pswpt" />';
  echo '<p class="description">' . esc_html__('Name shown in the dashboard and site texts. Default: pswpt.', 'pswpt') . '</p>';
}

function pswpt_field_favicon_cb() {
  $opts = get_option('pswpt_settings', []);
  $val  = esc_attr($opts['favicon'] ?? '');
  $id   = intval($opts['favicon_id'] ?? 0);
  echo '<div style="display:flex;align-items:center;gap:12px">';
  echo '<input id="pswpt_favicon" name="pswpt_settings[favicon]" type="text" value="' . $val . '" class="regular-text" />';
  echo '<input id="pswpt_favicon_id" name="pswpt_settings[favicon_id]" type="hidden" value="' . $id . '" />';
  echo '<button type="button" class="button pswpt-media-upload" data-target="#pswpt_favicon" data-target-id="#pswpt_favicon_id" data-preview="#pswpt_favicon_preview">' . esc_html__('Select image', 'pswpt') . '</button>';
  echo '<button type="button" class="button pswpt-media-remove" data-target="#pswpt_favicon" data-target-id="#pswpt_favicon_id" data-preview="#pswpt_favicon_preview">' . esc_html__('Remove', 'pswpt') . '</button>';
  echo '<img id="pswpt_favicon_preview" src="' . esc_url($val) . '" alt="" style="max-height:32px;border-radius:4px;' . ($val ? 'display:block;' : 'display:none;') . '" />';
  echo '</div>';
  echo '<p class="description">' . esc_html__('Site favicon (shown in the browser tab). Supports .ico, .png, .jpg, .webp.', 'pswpt') . '</p>';
}

function pswpt_field_theme_color_cb() {
  $val = esc_attr((string) pswpt_get_setting('theme_color', ''));
  echo '<input id="pswpt_theme_color" name="pswpt_settings[theme_color]" type="text" value="' . $val . '" placeholder="#1E40AF" />';
}

function pswpt_field_login_slug_cb() {
  $slug     = (string) pswpt_get_setting('login_slug', '');
  $disabled = defined('PSWPT_LOGIN_SLUG_DISABLED') && PSWPT_LOGIN_SLUG_DISABLED;
  echo '<input id="pswpt_login_slug" name="pswpt_settings[login_slug]" type="text" value="' . esc_attr($slug) . '" class="regular-text" placeholder="' . esc_attr__('e.g. femsculpt-access', 'pswpt') . '" autocomplete="off" />';
  echo '<p class="description">' . esc_html(trailingslashit(home_url('/'))) . '<strong>' . esc_html($slug !== '' ? $slug : __('your-path', 'pswpt')) . '</strong>/</p>';

  if ($slug !== '' && !$disabled) {
    echo '<p class="description" style="margin-top:8px"><strong>' . esc_html__('Active.', 'pswpt') . '</strong> ';
    printf(
      /* translators: %s: access URL */
      esc_html__('Save this link somewhere safe: %s', 'pswpt'),
      '<a href="' . esc_url(pswpt_login_url()) . '"><code>' . esc_html(pswpt_login_url()) . '</code></a>'
    );
    echo '</p>';
  }
  if ($disabled) {
    echo '<p class="description" style="color:#b32d2e"><strong>' . esc_html__('Disabled by PSWPT_LOGIN_SLUG_DISABLED in wp-config.php.', 'pswpt') . '</strong></p>';
  }

  echo '<p class="description" style="margin-top:8px">' . esc_html__('Empty = standard WordPress access. If you lose the link, add this to wp-config.php: ', 'pswpt')
    . '<code>define(\'PSWPT_LOGIN_SLUG_DISABLED\', true);</code></p>';
  echo '<p class="description">' . esc_html__('This is a layer of obscurity, not a substitute for strong passwords or 2FA, and only applies while this theme is active.', 'pswpt') . '</p>';
}

function pswpt_field_force_noindex_cb() {
  $val = !empty(pswpt_get_setting('force_noindex'));
  echo '<label><input name="pswpt_settings[force_noindex]" type="checkbox" value="1" ' . checked(1, $val, false) . ' /> ' . esc_html__('Enable noindex (staging/preview)', 'pswpt') . '</label>';
}

function pswpt_field_canonical_domain_cb() {
  $val = esc_attr((string) pswpt_get_setting('canonical_domain', ''));
  echo '<input id="pswpt_canonical_domain" name="pswpt_settings[canonical_domain]" type="text" value="' . $val . '" class="regular-text" placeholder="https://example.com" />';
}

function pswpt_field_gsc_verification_cb() {
  $val = esc_attr((string) pswpt_get_setting('gsc_verification', ''));
  echo '<input id="pswpt_gsc_verification" name="pswpt_settings[gsc_verification]" type="text" value="' . $val . '" class="regular-text" />';
}

function pswpt_field_bing_verification_cb() {
  $val = esc_attr((string) pswpt_get_setting('bing_verification', ''));
  echo '<input id="pswpt_bing_verification" name="pswpt_settings[bing_verification]" type="text" value="' . $val . '" class="regular-text" />';
}

function pswpt_field_manifest_url_cb() {
  $val = esc_attr((string) pswpt_get_setting('manifest_url', ''));
  echo '<input id="pswpt_manifest_url" name="pswpt_settings[manifest_url]" type="url" value="' . $val . '" class="regular-text" />';
}

function pswpt_field_preconnect_hosts_cb() {
  $raw = pswpt_get_setting('preconnect_hosts', '');
  $val = esc_textarea(is_array($raw) ? implode("\n", $raw) : (string) $raw);
  echo '<textarea id="pswpt_preconnect_hosts" name="pswpt_settings[preconnect_hosts]" rows="4" cols="50" class="large-text">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Hosts separated by new lines or commas. Example: https://example.com', 'pswpt') . '</p>';
}

function pswpt_field_gtm_container_id_cb() {
  $val = esc_attr((string) pswpt_get_setting('gtm_container_id', ''));
  echo '<input id="pswpt_gtm_container_id" name="pswpt_settings[gtm_container_id]" type="text" value="' . $val . '" class="regular-text" placeholder="GTM-XXXXXXX" />';
}

function pswpt_field_ga4_measurement_id_cb() {
  $val = esc_attr((string) pswpt_get_setting('ga4_measurement_id', ''));
  echo '<input id="pswpt_ga4_measurement_id" name="pswpt_settings[ga4_measurement_id]" type="text" value="' . $val . '" class="regular-text" placeholder="G-XXXXXXXXXX" />';
}

function pswpt_field_image_quality_cb() {
  $val = intval(pswpt_get_setting('image_quality', 82));
  echo '<input id="pswpt_image_quality" name="pswpt_settings[image_quality]" type="number" min="60" max="100" value="' . $val . '" class="small-text" />';
  echo '<p class="description">' . esc_html__('Compression quality for JPEG/WebP (60-100). Default: 82. Lower = smaller files.', 'pswpt') . '</p>';
}

function pswpt_field_enable_webp_cb() {
  $val = intval(pswpt_get_setting('enable_webp', 1));
  echo '<label><input type="checkbox" id="pswpt_enable_webp" name="pswpt_settings[enable_webp]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('Automatically generate WebP versions on upload (25-35% smaller files)', 'pswpt') . '</label>';
}

function pswpt_field_max_image_width_cb() {
  $val = intval(pswpt_get_setting('max_image_width', 2560));
  echo '<input id="pswpt_max_image_width" name="pswpt_settings[max_image_width]" type="number" min="1200" max="4000" value="' . $val . '" class="small-text" />';
  echo '<p class="description">' . esc_html__('Maximum width in pixels for uploaded images. Larger ones are resized automatically. Default: 2560px.', 'pswpt') . '</p>';
}

function pswpt_field_header_scripts_cb() {
  $val = esc_textarea((string) pswpt_get_setting('header_scripts', ''));
  echo '<textarea id="pswpt_header_scripts" name="pswpt_settings[header_scripts]" rows="8" cols="50" class="large-text code" spellcheck="false">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Scripts inserted in the <head>, deferred. Example: <script src="..."></script> or inline code.', 'pswpt') . '</p>';
}

function pswpt_field_body_scripts_cb() {
  $val = esc_textarea((string) pswpt_get_setting('body_scripts', ''));
  echo '<textarea id="pswpt_body_scripts" name="pswpt_settings[body_scripts]" rows="8" cols="50" class="large-text code" spellcheck="false">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Scripts inserted after the <body> tag, deferred.', 'pswpt') . '</p>';
}

function pswpt_field_footer_scripts_cb() {
  $val = esc_textarea((string) pswpt_get_setting('footer_scripts', ''));
  echo '<textarea id="pswpt_footer_scripts" name="pswpt_settings[footer_scripts]" rows="8" cols="50" class="large-text code" spellcheck="false">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Scripts inserted before the closing </body>, deferred.', 'pswpt') . '</p>';
}

function pswpt_field_immediate_footer_cb() {
  $val = esc_textarea((string) pswpt_get_setting('immediate_footer', ''));
  echo '<textarea id="pswpt_immediate_footer" name="pswpt_settings[immediate_footer]" rows="8" cols="50" class="large-text code" spellcheck="false">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Scripts loaded immediately before </body>, not deferred. Only for cookie banners or critical scripts.', 'pswpt') . '</p>';
}

/* ------------------------------------------------------------------ */
/* Sanitization                                                         */
/* ------------------------------------------------------------------ */

/**
 * Parte de la option existente y sobreescribe solo las claves que gestiona este
 * formulario: cualquier clave que otro código guarde en pswpt_settings
 * sobrevive al guardado. Los checkboxes no viajan cuando están desmarcados, así
 * que se resuelven siempre a 0/1; los campos de texto opcionales se quitan si
 * llegan vacíos para que el usuario pueda "borrar" un valor.
 */
function pswpt_settings_sanitize($input) {
  $input    = is_array($input) ? $input : [];
  $existing = get_option('pswpt_settings', []);
  $out      = is_array($existing) ? $existing : [];

  $set_text = static function (string $key) use (&$out, $input) {
    if (!empty($input[$key])) {
      $out[$key] = sanitize_text_field($input[$key]);
    } else {
      unset($out[$key]);
    }
  };

  // General
  if (isset($input['theme_brand'])) {
    $out['theme_brand'] = sanitize_text_field($input['theme_brand']);
  }
  $out['favicon']    = isset($input['favicon']) ? esc_url_raw($input['favicon']) : '';
  $out['favicon_id'] = intval($input['favicon_id'] ?? 0);
  if ($out['favicon'] === '' && $out['favicon_id']) {
    $url = wp_get_attachment_url($out['favicon_id']);
    if ($url) $out['favicon'] = esc_url_raw($url);
  }
  if (isset($input['theme_color'])) {
    $out['theme_color'] = sanitize_text_field($input['theme_color']);
  }

  // Security (ver login-url.php): ruta inválida ⇒ aviso y se conserva la anterior.
  if (array_key_exists('login_slug', $input) && function_exists('pswpt_sanitize_login_slug')) {
    $slug = pswpt_sanitize_login_slug($input['login_slug'], (string) ($out['login_slug'] ?? ''));
    if ($slug === '') {
      unset($out['login_slug']);
    } else {
      $out['login_slug'] = $slug;
    }
  }

  // SEO
  $out['force_noindex'] = !empty($input['force_noindex']) ? 1 : 0;
  if (!empty($input['canonical_domain'])) {
    $out['canonical_domain'] = untrailingslashit(sanitize_text_field($input['canonical_domain']));
  } else {
    unset($out['canonical_domain']);
  }
  $set_text('gsc_verification');
  $set_text('bing_verification');
  if (!empty($input['manifest_url'])) {
    $out['manifest_url'] = esc_url_raw($input['manifest_url']);
  } else {
    unset($out['manifest_url']);
  }
  if (!empty($input['preconnect_hosts'])) {
    $raw   = is_array($input['preconnect_hosts']) ? implode(',', $input['preconnect_hosts']) : (string) $input['preconnect_hosts'];
    $clean = [];
    foreach (preg_split('/[\r\n,]+/', $raw) as $host) {
      $host = trim($host);
      if ($host !== '') $clean[] = esc_url_raw($host);
    }
    $out['preconnect_hosts'] = $clean;
  } else {
    unset($out['preconnect_hosts']);
  }

  // Analytics
  $set_text('gtm_container_id');
  $set_text('ga4_measurement_id');

  // Pestañas registradas desde otros archivos (ej. admin-typography.php).
  $out = apply_filters('pswpt_settings_sanitize', $out, $input);

  // Performance (image-optimization.php)
  if (isset($input['image_quality'])) {
    $out['image_quality'] = max(60, min(100, intval($input['image_quality'])));
  }
  $out['enable_webp'] = !empty($input['enable_webp']) ? 1 : 0;
  if (isset($input['max_image_width'])) {
    $out['max_image_width'] = max(1200, min(4000, intval($input['max_image_width'])));
  }

  // Claves heredadas de Sanasana sin consumidor en este theme: fuera de la option.
  foreach (['contact_email', 'featured_default', 'archive_thumbs', 'enable_breadcrumbs', 'critical_images', 'optimize_images_list', 'bot_guard_enabled', 'bot_guard_mode', 'bot_guard_requests_per_minute', 'bot_guard_allowlisted_agents'] as $legacy) {
    unset($out[$legacy]);
  }

  // Scripts: son código que el admin (manage_options, unfiltered_html) inyecta a
  // propósito; solo se recorta. Mismo criterio que Sanasana.
  foreach (['header_scripts', 'body_scripts', 'footer_scripts', 'immediate_footer'] as $key) {
    if (isset($input[$key])) {
      $out[$key] = trim((string) $input[$key]);
    }
  }

  return $out;
}

/* ------------------------------------------------------------------ */
/* Page HTML                                                            */
/* ------------------------------------------------------------------ */

function pswpt_settings_page_html() {
  if (!current_user_can('manage_options')) return;

  $tabs = [
    'general'     => ['⚙️',  __('General', 'pswpt')],
    'typography'  => ['🔤', __('Typography', 'pswpt')],
    'seo'         => ['🔍', __('SEO & Meta', 'pswpt')],
    'analytics'   => ['📊', __('Analytics', 'pswpt')],
    'performance' => ['⚡',  __('Performance', 'pswpt')],
    'scripts'     => ['📜', __('Scripts', 'pswpt')],
    'immediate'   => ['⚡⚡', __('Immediate', 'pswpt')],
    'security'    => ['🔐', __('Security', 'pswpt')],
  ];
  ?>
  <div class="wrap">
    <h1 style="margin-bottom: 10px;"><?php echo esc_html(sprintf(__('%s Settings', 'pswpt'), pswpt_get_theme_brand_name())); ?></h1>
    <p style="color: #666; margin-bottom: 20px;"><?php esc_html_e('Manage your site configuration, SEO, analytics, and performance settings.', 'pswpt'); ?></p>

    <form action="options.php" method="post" class="pswpt-settings-form">
      <?php settings_fields('pswpt_settings_group'); ?>

      <ul class="nav nav-tabs pswpt-tabs-nav" role="tablist" style="margin-top: 20px;">
        <?php $first = true; foreach ($tabs as $slug => [$icon, $label]): ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $first ? ' active' : ''; ?>" id="tab-<?php echo esc_attr($slug); ?>" data-bs-toggle="tab" data-bs-target="#content-<?php echo esc_attr($slug); ?>" type="button" role="tab">
              <span style="font-size: 16px; margin-right: 6px;"><?php echo $icon; ?></span> <?php echo esc_html($label); ?>
            </button>
          </li>
        <?php $first = false; endforeach; ?>
      </ul>

      <div class="tab-content pswpt-tabs-container">
        <?php $first = true; foreach (array_keys($tabs) as $slug): ?>
          <div class="tab-pane fade<?php echo $first ? ' show active' : ''; ?>" id="content-<?php echo esc_attr($slug); ?>" role="tabpanel">
            <?php do_settings_sections('pswpt-settings-' . $slug); ?>
          </div>
        <?php $first = false; endforeach; ?>
      </div>

      <?php submit_button(__('Save Settings', 'pswpt'), 'primary', 'submit', true); ?>
    </form>
  </div>

  <script>
  (function($){
    $(document).on('click', '.pswpt-media-upload', function(e){
      e.preventDefault();
      var btn = $(this);
      var frame = wp.media({
        title: <?php echo wp_json_encode(__('Select an image', 'pswpt')); ?>,
        library: { type: 'image' },
        button: { text: <?php echo wp_json_encode(__('Use this image', 'pswpt')); ?> },
        multiple: false
      });
      frame.on('select', function(){
        var att = frame.state().get('selection').first().toJSON();
        $(btn.data('target')).val(att.url);
        $(btn.data('target-id')).val(att.id);
        $(btn.data('preview')).attr('src', att.url).show();
      });
      frame.open();
    });

    $(document).on('click', '.pswpt-media-remove', function(e){
      e.preventDefault();
      var btn = $(this);
      $(btn.data('target')).val('');
      $(btn.data('target-id')).val('');
      $(btn.data('preview')).attr('src', '').hide();
    });
  })(jQuery);
  </script>
  <?php
}
