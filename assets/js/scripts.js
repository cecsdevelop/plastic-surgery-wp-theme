(function () {
  'use strict';

  // Header sticky: agrega .is-scrolled a .site-header pasado un umbral de scroll.
  // Vanilla JS (no depende de jQuery) para que este único listener no dependa de
  // que el bundle de jQuery haya cargado.
  var header = document.querySelector('.site-header');
  if (!header) return;

  var threshold = parseInt(header.getAttribute('data-scroll-threshold'), 10) || 80;
  var ticking = false;

  function applyScrolled() {
    header.classList.toggle('is-scrolled', window.scrollY > threshold);
    ticking = false;
  }

  window.addEventListener('scroll', function () {
    if (!ticking) {
      window.requestAnimationFrame(applyScrolled);
      ticking = true;
    }
  }, { passive: true });

  applyScrolled();
})();
