/**
 * Repeater de pares antes/después del metabox "Case details"
 * (PatientGalleryController::render_case_meta_box). Solo se enqueda en la
 * pantalla de edición de patient_gallery. Reusa el mismo patrón de
 * wp.media que pswpt-admin-fields.js (campo "media"), adaptado a dos fotos
 * por fila en vez de una.
 */
(function ($) {
  'use strict';

  $(function () {
    var $wrap = $('[data-pswpt-pg-pairs]');
    if (!$wrap.length) {
      return;
    }

    var i18n = window.pswptPatientGallery || {};
    var $list = $wrap.find('.pswpt-pg-pairs__list');
    var templateEl = $wrap.find('.pswpt-pg-pair-template')[0];

    function reindex() {
      $list.children('[data-pswpt-pg-pair]').each(function (index) {
        $(this).find('.pswpt-pg-pair__url').each(function () {
          var side = $(this).closest('[data-side]').data('side');
          $(this).attr('name', 'pswpt_pg_pairs[' + index + '][' + side + ']');
        });
      });
    }

    $wrap.on('click', '.pswpt-pg-pairs__add', function (e) {
      e.preventDefault();
      if (!templateEl) {
        return;
      }
      // <template> content lives in an inert DocumentFragment (templateEl.content),
      // not as regular childNodes — jQuery's .contents()/.clone() on the
      // <template> element itself would come back empty.
      $list.append(document.importNode(templateEl.content, true));
      reindex();
    });

    $wrap.on('click', '.pswpt-pg-pair__remove', function (e) {
      e.preventDefault();
      $(this).closest('[data-pswpt-pg-pair]').remove();
      reindex();
    });

    $wrap.on('click', '.pswpt-pg-pair__upload', function (e) {
      e.preventDefault();
      var $field = $(this).closest('.pswpt-pg-pair__image');
      var frame = wp.media({
        title: i18n.mediaTitle || 'Select photo',
        library: { type: 'image' },
        button: { text: i18n.mediaButton || 'Use this photo' },
        multiple: false
      });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        var previewUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
        $field.find('.pswpt-pg-pair__url').val(attachment.url);
        $field.find('.pswpt-pg-pair__preview').attr('src', previewUrl).prop('hidden', false);
        $field.find('.pswpt-pg-pair__clear').prop('hidden', false);
      });
      frame.open();
    });

    $wrap.on('click', '.pswpt-pg-pair__clear', function (e) {
      e.preventDefault();
      var $field = $(this).closest('.pswpt-pg-pair__image');
      $field.find('.pswpt-pg-pair__url').val('');
      $field.find('.pswpt-pg-pair__preview').attr('src', '').prop('hidden', true);
      $(this).prop('hidden', true);
    });
  });
})(jQuery);
