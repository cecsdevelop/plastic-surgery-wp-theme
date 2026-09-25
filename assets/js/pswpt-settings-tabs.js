(function ($) {
  'use strict';

  function activateTab($root, targetSelector) {
    var $buttons = $root.find('.pswpt-tabs-nav .nav-link');
    var $panes = $root.find('.pswpt-tabs-container .tab-pane');
    var $target = $panes.filter(targetSelector);

    if (!$target.length) {
      $buttons = $root.find('.pswpt-tabs-nav .nav-link').first();
      var fallback = $buttons.data('bs-target') || '#content-general';
      $target = $panes.filter(fallback);
    }

    $buttons.removeClass('active').attr('aria-selected', 'false');
    $panes.removeClass('show active');

    var targetId = $target.attr('id');
    if (targetId) {
      $root.find('.pswpt-tabs-nav .nav-link[data-bs-target="#' + targetId + '"]').addClass('active').attr('aria-selected', 'true');
    }
    $target.addClass('show active');

    // options.php redirige a _wp_http_referer (sin fragmento) tras guardar; con el
    // hash en el referer, add_query_arg lo conserva y se vuelve a la misma pestaña.
    var $referer = $root.find('input[name="_wp_http_referer"]');
    if ($referer.length && targetId) {
      $referer.val($referer.val().replace(/#.*$/, '') + '#' + targetId);
    }
  }

  $(function () {
    var $form = $('.pswpt-settings-form');
    if (!$form.length) {
      return;
    }

    var $nav = $form.find('.pswpt-tabs-nav');
    var $panes = $form.find('.pswpt-tabs-container .tab-pane');
    if (!$nav.length || !$panes.length) {
      return;
    }

    $nav.on('click', '.nav-link', function (e) {
      e.preventDefault();
      var $btn = $(this);
      var target = $btn.attr('data-bs-target');
      if (!target) {
        return;
      }

      activateTab($form, target);

      if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', target);
      }
    });

    var initialHash = window.location.hash;
    if (initialHash && $panes.filter(initialHash).length) {
      activateTab($form, initialHash);
    } else {
      activateTab($form, '#content-general');
    }

    $(document).on('click', '.alert .btn-close', function (e) {
      e.preventDefault();
      $(this).closest('.alert').remove();
    });
  });
})(jQuery);
