/**
 * Editor de campos de Formularios (solo admin): agregar/quitar/ordenar filas y
 * mostrar solo los inputs que aplican al tipo (opciones para select, valor
 * para hidden, sin placeholder en checkbox).
 */
(function ($) {
  'use strict';

  $(function () {
    var $table = $('#pswpt-form-fields');
    var template = document.getElementById('pswpt-form-field-template');
    if (!$table.length || !template) return;
    var $body = $table.children('tbody'); // children: las tablas de idiomas anidadas tienen su propio tbody
    // Solo las filas de campo: cada una contiene una tabla anidada de idiomas
    // con sus propios <tr>, que no deben contarse ni sincronizarse.
    var ROW = 'tr.pswpt-form-field';
    var next = $body.children(ROW).length;

    function syncRow($row) {
      var type = $row.find('.pswpt-form-field__type').val();
      $row.attr('data-type', type);
      $row.find('.pswpt-form-field__only-select').toggle(type === 'select');
      $row.find('.pswpt-form-field__only-hidden').toggle(type === 'hidden');
      $row.find('.pswpt-form-field__not-hidden').toggle(type !== 'hidden');
      $row.find('.pswpt-form-field__not-checkbox').toggle(type !== 'hidden' && type !== 'checkbox');
    }

    $body.children(ROW).each(function () { syncRow($(this)); });
    $table.on('change', '.pswpt-form-field__type', function () { syncRow($(this).closest(ROW)); });

    $('#pswpt-form-field-add').on('click', function () {
      var html = template.innerHTML.replace(/__i__/g, String(next++));
      var $row = $(html).appendTo($body);
      syncRow($row);
      $row.find('input[type="text"]').first().trigger('focus');
    });

    $table.on('click', '.pswpt-form-field-remove', function () {
      $(this).closest(ROW).remove();
    });

    // Ordenar arrastrando la manija (jQuery UI sortable viene con WP admin).
    if ($.fn.sortable) {
      $body.sortable({ items: '> ' + ROW, handle: '.pswpt-form-field__handle', axis: 'y', cursor: 'move' });
    }
  });
})(jQuery);
