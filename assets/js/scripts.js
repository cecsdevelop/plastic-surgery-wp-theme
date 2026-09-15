(function () {
  'use strict';

  // Header sticky: agrega .is-scrolled a .site-header pasado un umbral de scroll.
  // Solo si el admin activó sticky (header.php imprime .site-header--sticky y
  // data-scroll-threshold): con el header estático no se registra ningún listener.
  // Vanilla JS (no depende de jQuery) para que este único listener no dependa de
  // que el bundle de jQuery haya cargado.
  var header = document.querySelector('.site-header--sticky');
  if (header) {
    var threshold = parseInt(header.getAttribute('data-scroll-threshold'), 10);
    if (isNaN(threshold)) threshold = 80;
    var ticking = false;

    var applyScrolled = function () {
      header.classList.toggle('is-scrolled', window.scrollY > threshold);
      ticking = false;
    };

    window.addEventListener('scroll', function () {
      if (!ticking) {
        window.requestAnimationFrame(applyScrolled);
        ticking = true;
      }
    }, { passive: true });

    applyScrolled();
  }

  // Modal del CTA (<dialog> nativo). El contenido (script de CRM, HTML, shortcode)
  // viene en un <template> inerte y se inyecta en el primer clic: los <script>
  // se re-crean para que el navegador los ejecute recién ahí, así el JS del CRM
  // no carga hasta que alguien abre el modal.
  var modalLoaded = false;

  function loadModalContent(dialog) {
    if (modalLoaded) return;
    modalLoaded = true;

    var template = document.getElementById('header-cta-modal-template');
    var target = dialog.querySelector('[data-cta-modal-content]');
    if (!template || !target) return;

    var fragment = document.importNode(template.content, true);
    var scripts = fragment.querySelectorAll('script');
    Array.prototype.forEach.call(scripts, function (old) {
      var script = document.createElement('script');
      Array.prototype.forEach.call(old.attributes, function (attr) {
        script.setAttribute(attr.name, attr.value);
      });
      script.textContent = old.textContent;
      old.parentNode.replaceChild(script, old);
    });
    target.appendChild(fragment);
  }

  document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-cta-modal]');
    if (opener) {
      var dialog = document.getElementById(opener.getAttribute('data-cta-modal'));
      if (!dialog || typeof dialog.showModal !== 'function') return;
      event.preventDefault();
      loadModalContent(dialog);
      dialog.showModal();
      return;
    }

    var closer = event.target.closest('[data-cta-modal-close]');
    if (closer) {
      var open = closer.closest('dialog');
      if (open) open.close();
      return;
    }

    // Clic en el backdrop (fuera de la caja) cierra.
    if (event.target instanceof HTMLDialogElement && event.target.open) {
      event.target.close();
    }
  });

  // ESC: el <dialog> ya lo cierra por su cuenta; esto cubre entornos donde la
  // tecla llega sin keyCode y el navegador no dispara el evento "cancel".
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var open = document.querySelector('dialog.header-cta-modal[open]');
    if (open) open.close();
  });
})();
