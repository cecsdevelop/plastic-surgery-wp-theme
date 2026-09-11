jQuery(document).ready(function($) {
  // Toggle sub-menu ONLY when clicking the icon area (after pseudo-element)
  $(document).on('click', 'li.menu-item-has-children > a', function(e) {
    var $a = $(this);
    var aWidth = $a.outerWidth();
    // Si el click fue en los últimos 24px del enlace (donde está el icono)
    if (e.pageX >= $a.offset().left + aWidth - 24) {
      var $li = $a.closest('li.menu-item-has-children');
      var $submenu = $li.children('ul.sub-menu');
      if ($submenu.length) {
        e.preventDefault();
        $submenu.slideToggle(180);
        $li.toggleClass('is-open');
      }
    }
  });
  // Cerrar submenús al hacer click fuera
  $(document).on('click', function(e) {
    var $target = $(e.target);
    if (!$target.closest('li.menu-item-has-children').length) {
      $('li.menu-item-has-children ul.sub-menu').slideUp(180);
      $('li.menu-item-has-children').removeClass('is-open');
    }
  });
});
