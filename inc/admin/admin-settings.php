<?php
/**
 * Intelindev Settings (Apariencia → Intelindev Settings).
 *
 * Panel por pestañas portado de Sanasana Settings (Sanasana-multilanguage,
 * inc/admin/admin-settings.php) sobre la option `intelindev_settings`. Se
 * conservan la option, el grupo, el slug de página y las claves que ya existían,
 * así que los valores guardados siguen leyéndose sin migración.
 *
 * Regla del panel: cada campo tiene un consumidor en el theme. Los valores se
 * leen con intelindev_get_setting() (helpers.php):
 *   - favicon / favicon_id, theme_color, theme_brand → header.php y este panel
 *   - force_noindex, canonical_domain, gsc/bing, manifest_url, preconnect_hosts
 *     → seo-analytics.php
 *   - gtm_container_id, ga4_measurement_id, *_scripts → seo-analytics.php
 *   - image_quality, enable_webp, max_image_width → image-optimization.php
 * Los campos heredados sin consumidor (contact_email, featured_default,
 * archive_thumbs, enable_breadcrumbs, critical_images, optimize_images_list,
 * bot_guard_*) se retiraron y sanitize() los purga de la option al guardar.
 *
 * @package intelindev
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('intelindev_get_theme_brand_name')) {
  function intelindev_get_theme_brand_name(): string {
    $opts  = get_option('intelindev_settings', []);
    $brand = is_array($opts) ? trim((string) ($opts['theme_brand'] ?? '')) : '';
    return $brand !== '' ? $brand : 'Intelindev';
  }
}

add_action('admin_menu', function() {
  $brand = intelindev_get_theme_brand_name();
  add_theme_page(
    sprintf(__('%s Settings', 'intelindev'), $brand),
    sprintf(__('%s Settings', 'intelindev'), $brand),
    'manage_options',
    'intelindev-settings',
    'intelindev_settings_page_html'
  );
});

add_action('admin_enqueue_scripts', function($hook) {
  if ($hook !== 'appearance_page_intelindev-settings') {
    return;
  }

  wp_enqueue_media();

  // file_exists() + filemtime(): el nombre del archivo y el del enqueue deben coincidir
  // exactamente o el panel se renderiza sin pestañas (todo apilado) sin ningún error.
  $css_path = get_template_directory() . '/assets/css/intelindev-settings-tabs.css';
  if (file_exists($css_path)) {
    wp_enqueue_style(
      'intelindev-settings-tabs',
      get_template_directory_uri() . '/assets/css/intelindev-settings-tabs.css',
      [],
      filemtime($css_path)
    );
  }

  $js_path = get_template_directory() . '/assets/js/intelindev-settings-tabs.js';
  if (file_exists($js_path)) {
    wp_enqueue_script(
      'intelindev-settings-tabs',
      get_template_directory_uri() . '/assets/js/intelindev-settings-tabs.js',
      ['jquery'],
      filemtime($js_path),
      true
    );
  }
}, 20);

add_action('admin_init', function() {
  register_setting('intelindev_settings_group', 'intelindev_settings', 'intelindev_settings_sanitize');

  // TAB 1: GENERAL
  add_settings_section('intelindev_general_section', __('General Site Configuration', 'intelindev'), function() {
    echo '<p>' . esc_html__('Basic site settings (favicon, colors, theme name).', 'intelindev') . '</p>';
  }, 'intelindev-settings-general');

  add_settings_field('intelindev_theme_brand', __('Nombre del theme', 'intelindev'), 'intelindev_field_theme_brand_cb', 'intelindev-settings-general', 'intelindev_general_section');
  add_settings_field('intelindev_favicon', __('Favicon', 'intelindev'), 'intelindev_field_favicon_cb', 'intelindev-settings-general', 'intelindev_general_section');
  add_settings_field('intelindev_theme_color', __('Theme Color (hex)', 'intelindev'), 'intelindev_field_theme_color_cb', 'intelindev-settings-general', 'intelindev_general_section');

  // TAB 2: SEO & META
  add_settings_section('intelindev_seo_section', __('SEO & Search Engines', 'intelindev'), function() {
    echo '<p>' . esc_html__('Configure SEO settings, search engine verification, and metadata.', 'intelindev') . '</p>';
  }, 'intelindev-settings-seo');

  add_settings_field('intelindev_force_noindex', __('Force noindex', 'intelindev'), 'intelindev_field_force_noindex_cb', 'intelindev-settings-seo', 'intelindev_seo_section');
  add_settings_field('intelindev_canonical_domain', __('Canonical Domain', 'intelindev'), 'intelindev_field_canonical_domain_cb', 'intelindev-settings-seo', 'intelindev_seo_section');
  add_settings_field('intelindev_gsc_verification', __('Google Search Console Verification', 'intelindev'), 'intelindev_field_gsc_verification_cb', 'intelindev-settings-seo', 'intelindev_seo_section');
  add_settings_field('intelindev_bing_verification', __('Bing Verification', 'intelindev'), 'intelindev_field_bing_verification_cb', 'intelindev-settings-seo', 'intelindev_seo_section');
  add_settings_field('intelindev_manifest_url', __('Manifest URL', 'intelindev'), 'intelindev_field_manifest_url_cb', 'intelindev-settings-seo', 'intelindev_seo_section');
  add_settings_field('intelindev_preconnect_hosts', __('Preconnect Hosts', 'intelindev'), 'intelindev_field_preconnect_hosts_cb', 'intelindev-settings-seo', 'intelindev_seo_section');

  // TAB 3: ANALYTICS
  add_settings_section('intelindev_analytics_section', __('Analytics & Tracking', 'intelindev'), function() {
    echo '<p>' . esc_html__('Configure Google Tag Manager and Google Analytics. Solo se imprimen cuando wp_get_environment_type() es "production".', 'intelindev') . '</p>';
  }, 'intelindev-settings-analytics');

  add_settings_field('intelindev_gtm_container_id', __('GTM Container ID', 'intelindev'), 'intelindev_field_gtm_container_id_cb', 'intelindev-settings-analytics', 'intelindev_analytics_section');
  add_settings_field('intelindev_ga4_measurement_id', __('GA4 Measurement ID', 'intelindev'), 'intelindev_field_ga4_measurement_id_cb', 'intelindev-settings-analytics', 'intelindev_analytics_section');

  // TAB 4: PERFORMANCE (consumidor: image-optimization.php; aplica a subidas nuevas)
  add_settings_section('intelindev_image_optimization_section', __('Image Optimization', 'intelindev'), function() {
    echo '<p>' . esc_html__('Compresión y optimización de imágenes al subirlas. No modifica las imágenes ya subidas.', 'intelindev') . '</p>';
  }, 'intelindev-settings-performance');

  add_settings_field('intelindev_image_quality', __('Compression Quality', 'intelindev'), 'intelindev_field_image_quality_cb', 'intelindev-settings-performance', 'intelindev_image_optimization_section');
  add_settings_field('intelindev_enable_webp', __('Enable WebP Conversion', 'intelindev'), 'intelindev_field_enable_webp_cb', 'intelindev-settings-performance', 'intelindev_image_optimization_section');
  add_settings_field('intelindev_max_image_width', __('Max Image Width', 'intelindev'), 'intelindev_field_max_image_width_cb', 'intelindev-settings-performance', 'intelindev_image_optimization_section');

  // TAB 5: SCRIPTS
  add_settings_section('intelindev_scripts_section', __('Custom Scripts (Deferred)', 'intelindev'), function() {
    echo '<p>' . esc_html__('Scripts loaded after 3s or user interaction (scroll/click/touch).', 'intelindev') . '</p>';
  }, 'intelindev-settings-scripts');

  add_settings_field('intelindev_header_scripts', __('Header Scripts', 'intelindev'), 'intelindev_field_header_scripts_cb', 'intelindev-settings-scripts', 'intelindev_scripts_section');
  add_settings_field('intelindev_body_scripts', __('Body Scripts', 'intelindev'), 'intelindev_field_body_scripts_cb', 'intelindev-settings-scripts', 'intelindev_scripts_section');
  add_settings_field('intelindev_footer_scripts', __('Footer Scripts', 'intelindev'), 'intelindev_field_footer_scripts_cb', 'intelindev-settings-scripts', 'intelindev_scripts_section');

  // TAB 6: IMMEDIATE SCRIPTS
  add_settings_section('intelindev_immediate_section', __('Immediate Scripts', 'intelindev'), function() {
    echo '<p>' . esc_html__('Scripts loaded immediately (not deferred). Use carefully for critical functionality only.', 'intelindev') . '</p>';
  }, 'intelindev-settings-immediate');

  add_settings_field('intelindev_immediate_footer', __('Footer Scripts (Immediate)', 'intelindev'), 'intelindev_field_immediate_footer_cb', 'intelindev-settings-immediate', 'intelindev_immediate_section');
});

/* ------------------------------------------------------------------ */
/* Field callbacks                                                      */
/* ------------------------------------------------------------------ */

function intelindev_field_theme_brand_cb() {
  $val = esc_attr((string) intelindev_get_setting('theme_brand', ''));
  echo '<input id="intelindev_theme_brand" name="intelindev_settings[theme_brand]" type="text" value="' . $val . '" class="regular-text" placeholder="Intelindev" />';
  echo '<p class="description">' . esc_html__('Nombre que se muestra en el panel y textos del sitio. Por defecto: Intelindev.', 'intelindev') . '</p>';
}

function intelindev_field_favicon_cb() {
  $opts = get_option('intelindev_settings', []);
  $val  = esc_attr($opts['favicon'] ?? '');
  $id   = intval($opts['favicon_id'] ?? 0);
  echo '<div style="display:flex;align-items:center;gap:12px">';
  echo '<input id="intelindev_favicon" name="intelindev_settings[favicon]" type="text" value="' . $val . '" class="regular-text" />';
  echo '<input id="intelindev_favicon_id" name="intelindev_settings[favicon_id]" type="hidden" value="' . $id . '" />';
  echo '<button type="button" class="button intelindev-media-upload" data-target="#intelindev_favicon" data-target-id="#intelindev_favicon_id" data-preview="#intelindev_favicon_preview">' . esc_html__('Seleccionar imagen', 'intelindev') . '</button>';
  echo '<button type="button" class="button intelindev-media-remove" data-target="#intelindev_favicon" data-target-id="#intelindev_favicon_id" data-preview="#intelindev_favicon_preview">' . esc_html__('Eliminar', 'intelindev') . '</button>';
  echo '<img id="intelindev_favicon_preview" src="' . esc_url($val) . '" alt="" style="max-height:32px;border-radius:4px;' . ($val ? 'display:block;' : 'display:none;') . '" />';
  echo '</div>';
  echo '<p class="description">' . esc_html__('Favicon del sitio (aparece en la pestaña del navegador). Soporta .ico, .png, .jpg, .webp.', 'intelindev') . '</p>';
}

function intelindev_field_theme_color_cb() {
  $val = esc_attr((string) intelindev_get_setting('theme_color', ''));
  echo '<input id="intelindev_theme_color" name="intelindev_settings[theme_color]" type="text" value="' . $val . '" placeholder="#1E40AF" />';
}

function intelindev_field_force_noindex_cb() {
  $val = !empty(intelindev_get_setting('force_noindex'));
  echo '<label><input name="intelindev_settings[force_noindex]" type="checkbox" value="1" ' . checked(1, $val, false) . ' /> ' . esc_html__('Activar noindex (staging/preview)', 'intelindev') . '</label>';
}

function intelindev_field_canonical_domain_cb() {
  $val = esc_attr((string) intelindev_get_setting('canonical_domain', ''));
  echo '<input id="intelindev_canonical_domain" name="intelindev_settings[canonical_domain]" type="text" value="' . $val . '" class="regular-text" placeholder="https://example.com" />';
}

function intelindev_field_gsc_verification_cb() {
  $val = esc_attr((string) intelindev_get_setting('gsc_verification', ''));
  echo '<input id="intelindev_gsc_verification" name="intelindev_settings[gsc_verification]" type="text" value="' . $val . '" class="regular-text" />';
}

function intelindev_field_bing_verification_cb() {
  $val = esc_attr((string) intelindev_get_setting('bing_verification', ''));
  echo '<input id="intelindev_bing_verification" name="intelindev_settings[bing_verification]" type="text" value="' . $val . '" class="regular-text" />';
}

function intelindev_field_manifest_url_cb() {
  $val = esc_attr((string) intelindev_get_setting('manifest_url', ''));
  echo '<input id="intelindev_manifest_url" name="intelindev_settings[manifest_url]" type="url" value="' . $val . '" class="regular-text" />';
}

function intelindev_field_preconnect_hosts_cb() {
  $raw = intelindev_get_setting('preconnect_hosts', '');
  $val = esc_textarea(is_array($raw) ? implode("\n", $raw) : (string) $raw);
  echo '<textarea id="intelindev_preconnect_hosts" name="intelindev_settings[preconnect_hosts]" rows="4" cols="50" class="large-text">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Hosts separados por nueva línea o comas. Ejemplo: https://example.com', 'intelindev') . '</p>';
}

function intelindev_field_gtm_container_id_cb() {
  $val = esc_attr((string) intelindev_get_setting('gtm_container_id', ''));
  echo '<input id="intelindev_gtm_container_id" name="intelindev_settings[gtm_container_id]" type="text" value="' . $val . '" class="regular-text" placeholder="GTM-XXXXXXX" />';
}

function intelindev_field_ga4_measurement_id_cb() {
  $val = esc_attr((string) intelindev_get_setting('ga4_measurement_id', ''));
  echo '<input id="intelindev_ga4_measurement_id" name="intelindev_settings[ga4_measurement_id]" type="text" value="' . $val . '" class="regular-text" placeholder="G-XXXXXXXXXX" />';
}

function intelindev_field_image_quality_cb() {
  $val = intval(intelindev_get_setting('image_quality', 82));
  echo '<input id="intelindev_image_quality" name="intelindev_settings[image_quality]" type="number" min="60" max="100" value="' . $val . '" class="small-text" />';
  echo '<p class="description">' . esc_html__('Calidad de compresión para JPEG/WebP (60-100). Por defecto: 82. Menor = archivos más pequeños.', 'intelindev') . '</p>';
}

function intelindev_field_enable_webp_cb() {
  $val = intval(intelindev_get_setting('enable_webp', 1));
  echo '<label><input type="checkbox" id="intelindev_enable_webp" name="intelindev_settings[enable_webp]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('Generar versiones WebP automáticamente al subir imágenes (reduce peso 25-35%)', 'intelindev') . '</label>';
}

function intelindev_field_max_image_width_cb() {
  $val = intval(intelindev_get_setting('max_image_width', 2560));
  echo '<input id="intelindev_max_image_width" name="intelindev_settings[max_image_width]" type="number" min="1200" max="4000" step="100" value="' . $val . '" class="small-text" />';
  echo '<p class="description">' . esc_html__('Ancho máximo en píxeles para imágenes subidas. Las más grandes se redimensionan automáticamente. Por defecto: 2560px.', 'intelindev') . '</p>';
}

function intelindev_field_header_scripts_cb() {
  $val = esc_textarea((string) intelindev_get_setting('header_scripts', ''));
  echo '<textarea id="intelindev_header_scripts" name="intelindev_settings[header_scripts]" rows="8" cols="50" class="large-text code" spellcheck="false">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Scripts que se insertan en el <head> de forma diferida. Ejemplo: <script src="..."></script> o código inline.', 'intelindev') . '</p>';
}

function intelindev_field_body_scripts_cb() {
  $val = esc_textarea((string) intelindev_get_setting('body_scripts', ''));
  echo '<textarea id="intelindev_body_scripts" name="intelindev_settings[body_scripts]" rows="8" cols="50" class="large-text code" spellcheck="false">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Scripts que se insertan después de la etiqueta <body> de forma diferida.', 'intelindev') . '</p>';
}

function intelindev_field_footer_scripts_cb() {
  $val = esc_textarea((string) intelindev_get_setting('footer_scripts', ''));
  echo '<textarea id="intelindev_footer_scripts" name="intelindev_settings[footer_scripts]" rows="8" cols="50" class="large-text code" spellcheck="false">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Scripts que se insertan antes de cerrar </body> de forma diferida.', 'intelindev') . '</p>';
}

function intelindev_field_immediate_footer_cb() {
  $val = esc_textarea((string) intelindev_get_setting('immediate_footer', ''));
  echo '<textarea id="intelindev_immediate_footer" name="intelindev_settings[immediate_footer]" rows="8" cols="50" class="large-text code" spellcheck="false">' . $val . '</textarea>';
  echo '<p class="description">' . esc_html__('Scripts que se cargan inmediatamente antes de </body>, sin diferir. Solo para cookie banners o scripts críticos.', 'intelindev') . '</p>';
}

/* ------------------------------------------------------------------ */
/* Sanitization                                                         */
/* ------------------------------------------------------------------ */

/**
 * Parte de la option existente y sobreescribe solo las claves que gestiona este
 * formulario: cualquier clave que otro código guarde en intelindev_settings
 * sobrevive al guardado. Los checkboxes no viajan cuando están desmarcados, así
 * que se resuelven siempre a 0/1; los campos de texto opcionales se quitan si
 * llegan vacíos para que el usuario pueda "borrar" un valor.
 */
function intelindev_settings_sanitize($input) {
  $input    = is_array($input) ? $input : [];
  $existing = get_option('intelindev_settings', []);
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
  $out = apply_filters('intelindev_settings_sanitize', $out, $input);

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

function intelindev_settings_page_html() {
  if (!current_user_can('manage_options')) return;

  $tabs = [
    'general'     => ['⚙️',  __('General', 'intelindev')],
    'typography'  => ['🔤', __('Tipografía', 'intelindev')],
    'seo'         => ['🔍', __('SEO & Meta', 'intelindev')],
    'analytics'   => ['📊', __('Analytics', 'intelindev')],
    'performance' => ['⚡',  __('Performance', 'intelindev')],
    'scripts'     => ['📜', __('Scripts', 'intelindev')],
    'immediate'   => ['⚡⚡', __('Immediate', 'intelindev')],
  ];
  ?>
  <div class="wrap">
    <h1 style="margin-bottom: 10px;"><?php echo esc_html(sprintf(__('%s Settings', 'intelindev'), intelindev_get_theme_brand_name())); ?></h1>
    <p style="color: #666; margin-bottom: 20px;"><?php esc_html_e('Manage your site configuration, SEO, analytics, and performance settings.', 'intelindev'); ?></p>

    <form action="options.php" method="post" class="intelindev-settings-form">
      <?php settings_fields('intelindev_settings_group'); ?>

      <ul class="nav nav-tabs intelindev-tabs-nav" role="tablist" style="margin-top: 20px;">
        <?php $first = true; foreach ($tabs as $slug => [$icon, $label]): ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $first ? ' active' : ''; ?>" id="tab-<?php echo esc_attr($slug); ?>" data-bs-toggle="tab" data-bs-target="#content-<?php echo esc_attr($slug); ?>" type="button" role="tab">
              <span style="font-size: 16px; margin-right: 6px;"><?php echo $icon; ?></span> <?php echo esc_html($label); ?>
            </button>
          </li>
        <?php $first = false; endforeach; ?>
      </ul>

      <div class="tab-content intelindev-tabs-container">
        <?php $first = true; foreach (array_keys($tabs) as $slug): ?>
          <div class="tab-pane fade<?php echo $first ? ' show active' : ''; ?>" id="content-<?php echo esc_attr($slug); ?>" role="tabpanel">
            <?php do_settings_sections('intelindev-settings-' . $slug); ?>
          </div>
        <?php $first = false; endforeach; ?>
      </div>

      <?php submit_button(__('Save Settings', 'intelindev'), 'primary', 'submit', true); ?>
    </form>
  </div>

  <script>
  (function($){
    $(document).on('click', '.intelindev-media-upload', function(e){
      e.preventDefault();
      var btn = $(this);
      var frame = wp.media({
        title: <?php echo wp_json_encode(__('Selecciona una imagen', 'intelindev')); ?>,
        library: { type: 'image' },
        button: { text: <?php echo wp_json_encode(__('Usar esta imagen', 'intelindev')); ?> },
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

    $(document).on('click', '.intelindev-media-remove', function(e){
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
