<?php
// Página "Theme Settings" (Apariencia): favicon, color, SEO y analytics.
// Los valores se leen con intelindev_get_setting() (helpers.php) desde header.php y seo-analytics.php.
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
  add_theme_page(
    __('Theme Settings', 'intelindev'),
    __('Theme Settings', 'intelindev'),
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
}, 20);

add_action('admin_post_intelindev_flush_cache', function() {
  if (!current_user_can('manage_options')) wp_die('Unauthorized');
  check_admin_referer('intelindev_flush_cache_nonce');
  $count = function_exists('intelindev_cache_flush') ? intelindev_cache_flush() : 0;
  wp_safe_redirect(add_query_arg([
    'page' => 'intelindev-settings',
    'intelindev_cache_flushed' => $count,
  ], admin_url('themes.php')));
  exit;
});

add_action('admin_init', function() {
  register_setting('intelindev_settings_group', 'intelindev_settings', 'intelindev_settings_sanitize');

  add_settings_section('intelindev_general_section', __('General', 'intelindev'), '__return_null', 'intelindev-settings');
  add_settings_field('intelindev_favicon', __('Favicon', 'intelindev'), 'intelindev_field_favicon_cb', 'intelindev-settings', 'intelindev_general_section');
  add_settings_field('intelindev_theme_color', __('Theme Color (hex)', 'intelindev'), 'intelindev_field_theme_color_cb', 'intelindev-settings', 'intelindev_general_section');

  add_settings_section('intelindev_seo_section', __('SEO & Search Engines', 'intelindev'), '__return_null', 'intelindev-settings');
  add_settings_field('intelindev_force_noindex', __('Force noindex', 'intelindev'), 'intelindev_field_force_noindex_cb', 'intelindev-settings', 'intelindev_seo_section');
  add_settings_field('intelindev_canonical_domain', __('Canonical Domain', 'intelindev'), 'intelindev_field_canonical_domain_cb', 'intelindev-settings', 'intelindev_seo_section');
  add_settings_field('intelindev_gsc_verification', __('Google Search Console Verification', 'intelindev'), 'intelindev_field_gsc_verification_cb', 'intelindev-settings', 'intelindev_seo_section');
  add_settings_field('intelindev_bing_verification', __('Bing Verification', 'intelindev'), 'intelindev_field_bing_verification_cb', 'intelindev-settings', 'intelindev_seo_section');
  add_settings_field('intelindev_manifest_url', __('Manifest URL', 'intelindev'), 'intelindev_field_manifest_url_cb', 'intelindev-settings', 'intelindev_seo_section');

  add_settings_section('intelindev_analytics_section', __('Analytics & Tracking', 'intelindev'), function() {
    echo '<p>' . esc_html__('Solo se imprimen cuando wp_get_environment_type() es "production".', 'intelindev') . '</p>';
  }, 'intelindev-settings');
  add_settings_field('intelindev_gtm_container_id', __('GTM Container ID', 'intelindev'), 'intelindev_field_gtm_container_id_cb', 'intelindev-settings', 'intelindev_analytics_section');
  add_settings_field('intelindev_ga4_measurement_id', __('GA4 Measurement ID', 'intelindev'), 'intelindev_field_ga4_measurement_id_cb', 'intelindev-settings', 'intelindev_analytics_section');
});

function intelindev_field_favicon_cb() {
  $opts = get_option('intelindev_settings', []);
  $val = esc_attr($opts['favicon'] ?? '');
  $id  = intval($opts['favicon_id'] ?? 0);
  echo '<div style="display:flex;align-items:center;gap:12px">';
  echo '<input id="intelindev_favicon" name="intelindev_settings[favicon]" type="text" value="' . $val . '" class="regular-text" />';
  echo '<input id="intelindev_favicon_id" name="intelindev_settings[favicon_id]" type="hidden" value="' . $id . '" />';
  echo '<button class="button intelindev-media-upload" data-target="#intelindev_favicon">' . esc_html__('Seleccionar imagen', 'intelindev') . '</button>';
  if ($val) echo '<img src="' . esc_url($val) . '" alt="" style="max-height:32px;display:block;border-radius:4px;" />';
  echo '</div>';
  echo '<p class="description">' . esc_html__('Favicon del sitio (.ico, .png, .webp).', 'intelindev') . '</p>';
}

function intelindev_field_theme_color_cb() {
  $opts = get_option('intelindev_settings', []);
  echo '<input id="intelindev_theme_color" name="intelindev_settings[theme_color]" type="text" value="' . esc_attr($opts['theme_color'] ?? '') . '" placeholder="#1E40AF" />';
}

function intelindev_field_force_noindex_cb() {
  $opts = get_option('intelindev_settings', []);
  echo '<label><input name="intelindev_settings[force_noindex]" type="checkbox" value="1" ' . checked(1, !empty($opts['force_noindex']), false) . ' /> ' . esc_html__('Activar noindex (staging/preview)', 'intelindev') . '</label>';
}

function intelindev_field_canonical_domain_cb() {
  $opts = get_option('intelindev_settings', []);
  echo '<input id="intelindev_canonical_domain" name="intelindev_settings[canonical_domain]" type="text" value="' . esc_attr($opts['canonical_domain'] ?? '') . '" class="regular-text" placeholder="https://example.com" />';
}

function intelindev_field_gsc_verification_cb() {
  $opts = get_option('intelindev_settings', []);
  echo '<input id="intelindev_gsc_verification" name="intelindev_settings[gsc_verification]" type="text" value="' . esc_attr($opts['gsc_verification'] ?? '') . '" class="regular-text" />';
}

function intelindev_field_bing_verification_cb() {
  $opts = get_option('intelindev_settings', []);
  echo '<input id="intelindev_bing_verification" name="intelindev_settings[bing_verification]" type="text" value="' . esc_attr($opts['bing_verification'] ?? '') . '" class="regular-text" />';
}

function intelindev_field_manifest_url_cb() {
  $opts = get_option('intelindev_settings', []);
  echo '<input id="intelindev_manifest_url" name="intelindev_settings[manifest_url]" type="url" value="' . esc_attr($opts['manifest_url'] ?? '') . '" class="regular-text" />';
}

function intelindev_field_gtm_container_id_cb() {
  $opts = get_option('intelindev_settings', []);
  echo '<input id="intelindev_gtm_container_id" name="intelindev_settings[gtm_container_id]" type="text" value="' . esc_attr($opts['gtm_container_id'] ?? '') . '" class="regular-text" placeholder="GTM-XXXXXXX" />';
}

function intelindev_field_ga4_measurement_id_cb() {
  $opts = get_option('intelindev_settings', []);
  echo '<input id="intelindev_ga4_measurement_id" name="intelindev_settings[ga4_measurement_id]" type="text" value="' . esc_attr($opts['ga4_measurement_id'] ?? '') . '" class="regular-text" placeholder="G-XXXXXXXXXX" />';
}

function intelindev_settings_sanitize($input) {
  $out = [];

  if (isset($input['favicon'])) {
    $out['favicon'] = esc_url_raw($input['favicon']);
  }
  if (isset($input['favicon_id'])) {
    $out['favicon_id'] = intval($input['favicon_id']);
    if (empty($out['favicon']) && $out['favicon_id']) {
      $url = wp_get_attachment_url($out['favicon_id']);
      if ($url) $out['favicon'] = esc_url_raw($url);
    }
  }
  if (isset($input['theme_color'])) {
    $out['theme_color'] = sanitize_text_field($input['theme_color']);
  }
  $out['force_noindex'] = !empty($input['force_noindex']) ? 1 : 0;
  if (!empty($input['canonical_domain'])) {
    $out['canonical_domain'] = untrailingslashit(sanitize_text_field($input['canonical_domain']));
  }
  if (!empty($input['gsc_verification'])) {
    $out['gsc_verification'] = sanitize_text_field($input['gsc_verification']);
  }
  if (!empty($input['bing_verification'])) {
    $out['bing_verification'] = sanitize_text_field($input['bing_verification']);
  }
  if (!empty($input['manifest_url'])) {
    $out['manifest_url'] = esc_url_raw($input['manifest_url']);
  }
  if (!empty($input['gtm_container_id'])) {
    $out['gtm_container_id'] = sanitize_text_field($input['gtm_container_id']);
  }
  if (!empty($input['ga4_measurement_id'])) {
    $out['ga4_measurement_id'] = sanitize_text_field($input['ga4_measurement_id']);
  }

  return $out;
}

function intelindev_settings_page_html() {
  if (!current_user_can('manage_options')) return;
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Theme Settings', 'intelindev'); ?></h1>

    <?php if (isset($_GET['intelindev_cache_flushed'])): ?>
      <div class="notice notice-success is-dismissible"><p><?php printf(esc_html__('Cache flushed: %d item(s) removed.', 'intelindev'), intval($_GET['intelindev_cache_flushed'])); ?></p></div>
    <?php endif; ?>

    <form action="options.php" method="post">
      <?php settings_fields('intelindev_settings_group'); ?>
      <?php do_settings_sections('intelindev-settings'); ?>
      <?php submit_button(); ?>
    </form>

    <hr />
    <h2><?php esc_html_e('Cache', 'intelindev'); ?></h2>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
      <input type="hidden" name="action" value="intelindev_flush_cache" />
      <?php wp_nonce_field('intelindev_flush_cache_nonce'); ?>
      <?php submit_button(__('Flush Theme Cache', 'intelindev'), 'secondary', 'submit', false); ?>
    </form>
  </div>

  <script>
  (function($){
    $(document).on('click', '.intelindev-media-upload', function(e){
      e.preventDefault();
      var target = $($(this).data('target'));
      var frame = wp.media({ title: 'Selecciona una imagen', library: { type: 'image' }, multiple: false });
      frame.on('select', function(){
        var attachment = frame.state().get('selection').first().toJSON();
        target.val(attachment.url);
        $('#intelindev_favicon_id').val(attachment.id);
      });
      frame.open();
    });
  })(jQuery);
  </script>
  <?php
}
