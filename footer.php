<?php
if (!defined('ABSPATH')) exit;

// Apariencia → Intelindev Footer: grilla de áreas de widgets por idioma + barra
// de copyright (ver admin-footer-settings.php).
$footer_lang = function_exists('idml_get_current_language') ? idml_get_current_language() : '';
$footer_grid = function_exists('intelindev_get_footer_grid') ? intelindev_get_footer_grid($footer_lang) : [];
$footer_bar  = function_exists('intelindev_get_footer_bar') ? intelindev_get_footer_bar($footer_lang) : null;
?>
<?php if (function_exists('intelindev_chrome_active') && intelindev_chrome_active()) : ?>
<?php echo intelindev_chrome_footer(); ?>
<?php else : ?>
<footer class="site-footer">
  <?php if ($footer_grid) : ?>
  <div class="site-footer__widgets">
    <div class="site-footer__inner">
      <?php foreach ($footer_grid as $row) : ?>
      <div class="footer-row" style="grid-template-columns: <?php echo esc_attr($row['template']); ?>">
        <?php foreach ($row['cells'] as $sidebar_id) : ?>
        <div class="footer-col"><?php if ($sidebar_id) dynamic_sidebar($sidebar_id); ?></div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($footer_bar) : ?>
  <div class="site-footer__bar site-footer__bar--<?php echo esc_attr($footer_bar['align']); ?>">
    <div class="site-footer__inner">
      <p class="site-copyright"><?php echo esc_html($footer_bar['text']); ?></p>
      <?php
      if ($footer_bar['menu']) {
        wp_nav_menu([
          'theme_location' => 'footer',
          'container'      => false,
          'menu_class'     => 'footer-menu',
          'depth'          => 1,
          'fallback_cb'    => false,
        ]);
      }
      ?>
    </div>
  </div>
  <?php endif; ?>
</footer>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
