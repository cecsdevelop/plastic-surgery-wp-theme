<?php
/**
 * HTML de un formulario (shortcode [form slug="…"]). Sin JS propio en el
 * markup: scripts.js intercepta el submit de cualquier form.intelindev-form y
 * envía por fetch al endpoint REST (ver FormHandler). Usa el grid del theme
 * (row / col-*) para el ancho de cada campo.
 *
 * Antispam embebido: honeypot (_website) + token firmado con la hora de
 * render (_ts/_sig) que FormHandler valida con ventana de tiempo. No usa
 * nonces de WP: caducan con la sesión y rompen en páginas cacheadas.
 *
 * @package Intelindev
 */

namespace IntelindevInit\Forms;

use WP_Post;

class FormRenderer
{
    public static function sign(int $form_id, int $ts): string
    {
        return hash_hmac('sha256', $form_id . '|' . $ts, wp_salt('nonce'));
    }

    /** URL de la página actual (home_url(add_query_arg()) duplica la subcarpeta si WP vive en una). */
    public static function current_url(): string
    {
        $host = sanitize_text_field((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return $host === '' ? home_url('/') : set_url_scheme('http://' . $host . add_query_arg([]));
    }

    public static function render(WP_Post $form, string $lang): string
    {
        $fields   = FormsController::get_fields($form->ID);
        $settings = FormsController::get_settings($form->ID);
        if (!$fields) {
            return '';
        }

        $ts      = time();
        $id      = (int) $form->ID;
        $prefix  = 'f' . $id . '-';
        $button  = intelindev_resolve_lang_text($settings['button'] ?? [], $lang);
        $success = intelindev_resolve_lang_text($settings['success'] ?? [], $lang);
        if ($button === '')  $button  = idml_t('form.default_button', $lang);
        if ($success === '') $success = idml_t('form.default_success', $lang);

        ob_start();
        ?>
<form class="intelindev-form" method="post" action="<?php echo esc_url(rest_url('intelindev/v1/form/' . $id)); ?>" data-intelindev-form="<?php echo $id; ?>" data-success="<?php echo esc_attr($success); ?>" data-error="<?php echo esc_attr(idml_t('form.error_send', $lang)); ?>" novalidate>
  <div class="row">
    <?php foreach ($fields as $field) : ?>
      <?php if ($field['type'] === 'hidden') : ?>
        <input type="hidden" name="<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr((string) ($field['value'] ?? '')); ?>">
        <?php continue; ?>
      <?php endif; ?>
      <?php
      $name        = $field['name'];
      $input_id    = $prefix . $name;
      $label       = intelindev_resolve_lang_text($field['label'] ?? [], $lang);
      $placeholder = intelindev_resolve_lang_text($field['placeholder'] ?? [], $lang);
      $required    = !empty($field['required']);
      $width       = (string) ($field['width'] ?? 'col-12');
      ?>
    <div class="<?php echo esc_attr($width); ?> intelindev-form__field intelindev-form__field--<?php echo esc_attr($field['type']); ?>">
      <?php if ($field['type'] === 'checkbox') : ?>
        <label class="intelindev-form__check" for="<?php echo esc_attr($input_id); ?>">
          <input type="checkbox" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($name); ?>" value="1"<?php echo $required ? ' required' : ''; ?>>
          <span><?php echo wp_kses($label, FormsController::LABEL_TAGS); ?><?php echo $required ? ' <span class="intelindev-form__req" aria-hidden="true">*</span>' : ''; ?></span>
        </label>
      <?php else : ?>
        <?php if ($label !== '') : ?>
          <label class="intelindev-form__label" for="<?php echo esc_attr($input_id); ?>"><?php echo wp_kses($label, FormsController::LABEL_TAGS); ?><?php echo $required ? ' <span class="intelindev-form__req" aria-hidden="true">*</span>' : ''; ?></label>
        <?php endif; ?>
        <?php if ($field['type'] === 'textarea') : ?>
          <textarea id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($name); ?>" rows="5" placeholder="<?php echo esc_attr($placeholder); ?>"<?php echo $required ? ' required' : ''; ?>></textarea>
        <?php elseif ($field['type'] === 'select') : ?>
          <select id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($name); ?>"<?php echo $required ? ' required' : ''; ?>>
            <option value=""><?php echo esc_html($placeholder !== '' ? $placeholder : '—'); ?></option>
            <?php foreach (FormsController::field_options($field, $lang) as $option) : ?>
              <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
            <?php endforeach; ?>
          </select>
        <?php else : ?>
          <input type="<?php echo esc_attr($field['type']); ?>" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($name); ?>" placeholder="<?php echo esc_attr($placeholder); ?>"<?php echo $required ? ' required' : ''; ?><?php echo $field['type'] === 'email' ? ' autocomplete="email"' : ($field['type'] === 'tel' ? ' autocomplete="tel"' : ''); ?>>
        <?php endif; ?>
      <?php endif; ?>
      <span class="intelindev-form__error" data-error-for="<?php echo esc_attr($name); ?>" role="alert"></span>
    </div>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="_lang" value="<?php echo esc_attr($lang); ?>">
  <input type="hidden" name="_ts" value="<?php echo $ts; ?>">
  <input type="hidden" name="_sig" value="<?php echo esc_attr(self::sign($id, $ts)); ?>">
  <input type="hidden" name="_page" value="<?php echo esc_url(self::current_url()); ?>">
  <div class="intelindev-form__hp" aria-hidden="true"><label>Website <input type="text" name="_website" tabindex="-1" autocomplete="off"></label></div>
  <p class="intelindev-form__actions"><button type="submit" class="intelindev-form__submit"><?php echo esc_html($button); ?></button></p>
  <div class="intelindev-form__message" role="status" aria-live="polite"></div>
</form>
        <?php
        return (string) ob_get_clean();
    }
}
