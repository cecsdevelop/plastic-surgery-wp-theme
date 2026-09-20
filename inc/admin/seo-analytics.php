<?php
/**
 * SEO & Analytics
 * Meta tags, resource hints, GTM/GA4, custom scripts. Lee la pestaña SEO y
 * Analytics de Intelindev Settings.
 *
 * @package intelindev
 */

if (!defined('ABSPATH')) exit;

// Helper ACF fallback
function intelindev_acf_or_setting($key, $default = null) {
  $get_acf = function_exists('get_field') ? get_field($key, 'option') : null;
  return intelindev_get_setting($key, $get_acf) ?? $default;
}

// SEO/Meta
add_action('wp_head', function () {
  if (is_admin()) return;
  $force_noindex = (bool) intval(intelindev_acf_or_setting('force_noindex'));
  $canonical = rtrim((string)intelindev_acf_or_setting('canonical_domain'), '/');
  $gsc = trim((string)intelindev_acf_or_setting('gsc_verification'));
  $bing = trim((string)intelindev_acf_or_setting('bing_verification'));
  $manifest = esc_url(intelindev_acf_or_setting('manifest_url'));
  $theme_color = sanitize_text_field(intelindev_acf_or_setting('theme_color'));

  if ($force_noindex) echo '<meta name="robots" content="noindex,nofollow" />' . PHP_EOL;
  if ($canonical && !is_404()) {
    $url = esc_url($canonical . $_SERVER['REQUEST_URI']);
    echo '<link rel="canonical" href="' . $url . '" />' . PHP_EOL;
  }
  if ($gsc)      echo '<meta name="google-site-verification" content="' . esc_attr($gsc) . '">' . PHP_EOL;
  if ($bing)     echo '<meta name="msvalidate.01" content="' . esc_attr($bing) . '">' . PHP_EOL;
  if ($manifest) echo '<link rel="manifest" href="' . esc_url($manifest) . '">' . PHP_EOL;
  if ($theme_color) echo '<meta name="theme-color" content="' . esc_attr($theme_color) . '">' . PHP_EOL;
}, 5);

// Preconnect (pestaña SEO → "Preconnect Hosts"): por el mecanismo nativo de
// resource hints, que el core imprime en wp_head (prioridad 2) y deduplica.
add_filter('wp_resource_hints', function (array $urls, string $relation): array {
  if ($relation !== 'preconnect') return $urls;
  $hosts = intelindev_get_setting('preconnect_hosts', []);
  foreach ((array) $hosts as $host) {
    $host = trim((string) $host);
    if ($host !== '') $urls[] = $host;
  }
  return $urls;
}, 10, 2);

// GTM/GA4 diferidos
add_action('wp_head', function () {
  if (is_admin()) return;
  $is_production = wp_get_environment_type() === 'production';
  if (!$is_production) return;
  $gtm = trim((string)intelindev_acf_or_setting('gtm_container_id'));
  $ga4 = trim((string)intelindev_acf_or_setting('ga4_measurement_id'));
  if ($gtm) : ?>
    <script>
    (function(w,d,s,l,i){
      w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
      function loadGTM(){ if(d.getElementById('gtm-script'))return;
        var f=d.getElementsByTagName(s)[0], j=d.createElement(s);
        j.async=true; j.id='gtm-script'; j.src='https://www.googletagmanager.com/gtm.js?id='+i;
        f.parentNode.insertBefore(j,f);
      }
      function init(){loadGTM(); cleanup();}
      function cleanup(){ d.removeEventListener('scroll',init); d.removeEventListener('mousemove',init); d.removeEventListener('touchstart',init); }
      setTimeout(init,3000);
      d.addEventListener('scroll',init,{once:true});
      d.addEventListener('mousemove',init,{once:true});
      d.addEventListener('touchstart',init,{once:true});
    })(window,document,'script','dataLayer','<?php echo esc_js($gtm); ?>');
    </script>
  <?php
  elseif ($ga4) : ?>
    <script>
    (function(w,d,s,i){
      function loadGA(){ if(d.getElementById('ga4-script'))return;
        var f=d.getElementsByTagName(s)[0], j=d.createElement(s);
        j.async=true; j.id='ga4-script'; j.src='https://www.googletagmanager.com/gtag/js?id='+i;
        f.parentNode.insertBefore(j,f);
        w.dataLayer=w.dataLayer||[]; w.gtag=function(){dataLayer.push(arguments);}
        gtag('js', new Date()); gtag('config', i);
      }
      function init(){loadGA(); cleanup();}
      function cleanup(){ d.removeEventListener('scroll',init); d.removeEventListener('mousemove',init); d.removeEventListener('touchstart',init); }
      setTimeout(init,3000);
      d.addEventListener('scroll',init,{once:true});
      d.addEventListener('mousemove',init,{once:true});
      d.addEventListener('touchstart',init,{once:true});
    })(window,document,'script','<?php echo esc_js($ga4); ?>');
    </script>
  <?php endif;
}, 5);

// GTM Noscript
add_action('wp_body_open', function () {
  if (is_admin()) return;
  $is_production = wp_get_environment_type() === 'production';
  if (!$is_production) return;
  if ($gtm = trim((string)intelindev_acf_or_setting('gtm_container_id'))) {
    echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id='.esc_attr($gtm).'" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . PHP_EOL;
  }
}, 5);
