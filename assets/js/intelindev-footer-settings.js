/**
 * Apariencia → Intelindev Footer (solo admin): alta/baja de filas de la
 * estructura. Los campos comunes (tabs, colores, condicionales) los manejan
 * intelindev-settings-tabs.js e intelindev-admin-fields.js.
 */
(function ($) {
  'use strict';

  $(function () {
    var $table = $('#intelindev-footer-rows');
    var $add = $('#intelindev-footer-row-add');
    var template = document.getElementById('intelindev-footer-row-template');
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
        $(this).find('.intelindev-footer-row__index').text(i + 1);
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

    $table.on('click', '.intelindev-footer-row-remove', function () {
      $(this).closest('tr').remove();
      renumber();
    });

    renumber();
  });
})(jQuery);
