/**
 * Campos compartidos de los paneles Intelindev (solo admin): visibilidad
 * condicional, selector de imagen y color picker de WP (Iris). Se aplica a
 * cualquier formulario con la clase .intelindev-panel-form; las pestañas las
 * maneja intelindev-settings-tabs.js.
 *
 * Visibilidad condicional: data-show-if en cualquier elemento del form.
 *   data-show-if="opt[sticky]"           visible si el checkbox está marcado
 *   data-show-if="opt[cta_type]=page"    visible si el radio/select vale "page"
 *   data-show-if="opt[cta_type]=a|b"     visible si vale a o b
 *   data-show-if="opt[cta_type]!=none"   visible si NO vale "none"
 */
(function ($) {
  'use strict';

  function fieldValue($form, name) {
    var $field = $form.find('[name="' + name + '"]');
    if (!$field.length) {
      return '';
    }
    if ($field.is(':checkbox')) {
      return $field.is(':checked') ? '1' : '';
    }
    if ($field.is(':radio')) {
      return $field.filter(':checked').val() || '';
    }
    return $field.val() || '';
  }

  function applyConditions($form) {
    $form.find('[data-show-if]').each(function () {
      var rule = String($(this).data('showIf'));
      var match = rule.match(/^(.+?)(!=|=)(.+)$/);
      var value = fieldValue($form, match ? match[1] : rule);
      var show;
      if (!match) {
        show = value !== '' && value !== '0';
      } else {
        var expected = match[3].split('|');
        show = (expected.indexOf(value) !== -1) === (match[2] === '=');
      }
      $(this).toggle(show);
    });
  }

  function initMediaFields($form) {
    var i18n = window.intelindevAdminFields || {};

    $form.on('click', '.intelindev-media-upload', function (e) {
      e.preventDefault();
      var $field = $(this).closest('.intelindev-media-field');
      var frame = wp.media({
        title: i18n.mediaTitle || 'Seleccionar imagen',
        library: { type: 'image' },
        button: { text: i18n.mediaButton || 'Usar esta imagen' },
        multiple: false
      });
      frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
        $field.find('input[type="hidden"]').val(att.id);
        $field.find('.intelindev-media-preview').attr('src', url).prop('hidden', false);
        $field.find('.intelindev-media-remove').prop('hidden', false);
      });
      frame.open();
    });

    $form.on('click', '.intelindev-media-remove', function (e) {
      e.preventDefault();
      var $field = $(this).closest('.intelindev-media-field');
      $field.find('input[type="hidden"]').val('0');
      $field.find('.intelindev-media-preview').attr('src', '').prop('hidden', true);
      $(this).prop('hidden', true);
    });
  }

  // Galería: input oculto con IDs separados por coma + lista de miniaturas
  // (campo "gallery" de los CPT de contenido, ver General/ContentTypeController).
  function initGalleryFields($form) {
    var i18n = window.intelindevAdminFields || {};

    function syncIds($field) {
      var ids = $field.find('.intelindev-gallery-list > li').map(function () { return $(this).data('id'); }).get();
      $field.find('input[type="hidden"]').val(ids.join(','));
    }

    $form.on('click', '.intelindev-gallery-add', function (e) {
      e.preventDefault();
      var $field = $(this).closest('.intelindev-gallery-field');
      var frame = wp.media({
        title: i18n.galleryTitle || 'Agregar imágenes',
        library: { type: 'image' },
        button: { text: i18n.galleryButton || 'Agregar' },
        multiple: 'add'
      });
      frame.on('select', function () {
        var $list = $field.find('.intelindev-gallery-list');
        frame.state().get('selection').each(function (att) {
          var data = att.toJSON();
          if ($list.find('li[data-id="' + data.id + '"]').length) {
            return;
          }
          var url = (data.sizes && data.sizes.thumbnail) ? data.sizes.thumbnail.url : data.url;
          $list.append('<li data-id="' + data.id + '"><img src="' + url + '" alt="" /><button type="button" class="intelindev-gallery-remove" aria-label="' + (i18n.remove || 'Quitar') + '">&times;</button></li>');
        });
        syncIds($field);
      });
      frame.open();
    });

    $form.on('click', '.intelindev-gallery-remove', function (e) {
      e.preventDefault();
      var $field = $(this).closest('.intelindev-gallery-field');
      $(this).closest('li').remove();
      syncIds($field);
    });
  }

  var RGBA = /^rgba?\(/i;

  function initColorInput($input) {
    if ($input.data('intelindevColorReady')) {
      return;
    }
    $input.data('intelindevColorReady', true);

    $input.wpColorPicker({
      // Iris reescribe el input con el hex del color (pierde el alpha). Si el
      // valor lo tipeó el usuario como rgba(), se restaura después de que Iris
      // termine (setTimeout 0) para poder guardar transparencias.
      change: function () {
        var raw = $input.data('rawColor');
        if (!raw) {
          return;
        }
        setTimeout(function () {
          $input.val(raw);
          $input.closest('.wp-picker-container').find('.wp-color-result').css('background-color', raw);
        }, 0);
      },
      clear: function () {
        $input.data('rawColor', null);
      }
    });

    var $container = $input.closest('.wp-picker-container');

    // En 'input' (cada tecla, siempre antes del 'change' que dispara a Iris al
    // perder el foco) queda registrado lo que tipeó el usuario. No se escucha
    // 'change': para entonces Iris ya reescribió el valor a hex.
    $input.on('input keyup', function () {
      var value = $.trim($input.val());
      $input.data('rawColor', RGBA.test(value) ? value : null);
    });
    // Interacción con el picker (cuadro, barra, paleta) = color elegido, no tipeado.
    $container.on('mousedown', '.iris-picker', function () {
      $input.data('rawColor', null);
    });

    // Swatch inicial con rgba guardado (Iris solo pinta hex).
    if (RGBA.test($.trim($input.val()))) {
      $input.data('rawColor', $.trim($input.val()));
      $container.find('.wp-color-result').css('background-color', $input.val());
    }

    // open()/close() de wpColorPicker hacen iris("toggle") por dentro, e Iris
    // además muestra el picker por su cuenta en el primer foco del input: con
    // solo el toggle, la visibilidad y la clase wp-picker-open se desfasan y
    // quedan pickers "fantasma". Se consulta la clase antes de llamar y se
    // fuerza el estado final de Iris.
    var $toggler = $container.find('.wp-color-result');
    function isOpen() { return $toggler.hasClass('wp-picker-open'); }
    function open() {
      if (isOpen()) { return; }
      $input.wpColorPicker('open');
      $input.iris('show');
    }
    function close() {
      if (!isOpen()) { return; }
      $input.wpColorPicker('close');
      $input.iris('hide');
    }

    // Abre al pasar el mouse (con un retardo corto para no parpadear al cruzar
    // la tabla) o al enfocar; cierra al salir con el mouse si el input no tiene
    // el foco, y al perder el foco si el mouse no está encima.
    var hoverTimer = null;
    $container.on('mouseenter', function () {
      hoverTimer = setTimeout(open, 150);
    });
    $container.on('mouseleave', function () {
      clearTimeout(hoverTimer);
      if (!$input.is(':focus')) {
        close();
      }
    });
    $input.on('focus', open);
    $input.on('blur', function () {
      setTimeout(function () {
        if (!$container.is(':hover')) {
          close();
        }
      }, 0);
    });
  }

  $(function () {
    var $form = $('.intelindev-panel-form');
    if (!$form.length) {
      return;
    }

    $form.on('change', 'input, select', function () {
      applyConditions($form);
    });
    applyConditions($form);

    initMediaFields($form);
    initGalleryFields($form);

    $form.find('.intelindev-color-input').each(function () {
      initColorInput($(this));
    });
  });

  // Para filas agregadas dinámicamente (ej. filas del footer).
  window.intelindevAdminFields = $.extend(window.intelindevAdminFields || {}, {
    refresh: function ($root) {
      var $form = $root.closest('.intelindev-panel-form');
      applyConditions($form);
      $root.find('.intelindev-color-input').each(function () {
        initColorInput($(this));
      });
    }
  });
})(jQuery);
