<?php
if (!defined('ABSPATH')) exit;
?>
<footer class="site-footer">
  <p class="site-copyright"><?php echo esc_html(idml_t_vars('footer.copyright', ['year' => date_i18n('Y'), 'site' => get_bloginfo('name')])); ?></p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
