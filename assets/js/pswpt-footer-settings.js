/**
 * Apariencia → pswpt Footer (solo admin): alta/baja de filas de la
 * estructura. Los campos comunes (tabs, colores, condicionales) los manejan
 * pswpt-settings-tabs.js e pswpt-admin-fields.js.
 */
(function ($) {
  'use strict';

  $(function () {
    var $table = $('#pswpt-footer-rows');
    var $add = $('#pswpt-footer-row-add');
    var template = document.getElementById('pswpt-footer-row-template');
    if (!$table.length || !$add.length || !template) {
      return;
    }

    var $body = $table.find('tbody');
    var maxRows = parseInt($table.data('maxRows'), 10) || 6;
    // Índice para los name= de filas nuevas: siempre mayor que cualquiera
    // existente para no pisar otra fila; el sanitize reindexa al guardar.
    var next = $body.find('tr').length;

    function renumber() {
      $body.find('tr').each(function (i) {
        $(this).find('.pswpt-footer-row__index').text(i + 1);
      });
      $add.prop('disabled', $body.find('tr').length >= maxRows);
    }

    $add.on('click', function () {
      if ($body.find('tr').length >= maxRows) {
        return;
      }
      var html = template.innerHTML.replace(/__i__/g, String(next++));
      $body.append(html);
      renumber();
    });

    $table.on('click', '.pswpt-footer-row-remove', function () {
      $(this).closest('tr').remove();
      renumber();
    });

    renumber();
  });
})(jQuery);
