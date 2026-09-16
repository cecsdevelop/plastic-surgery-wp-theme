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

// Formularios ([form slug="…"]): envío por fetch al endpoint REST sin recargar,
// errores por campo y mensaje de éxito. Sin JS el formulario no envía (el
// endpoint responde JSON); el markup sale de FormRenderer.
(function () {
  'use strict';

  function setMessage(form, text, isError) {
    var box = form.querySelector('.intelindev-form__message');
    if (!box) return;
    box.textContent = text || '';
    box.classList.toggle('is-error', !!isError);
    box.classList.toggle('is-success', !!text && !isError);
  }

  function clearErrors(form) {
    Array.prototype.forEach.call(form.querySelectorAll('.intelindev-form__error'), function (el) { el.textContent = ''; });
    Array.prototype.forEach.call(form.querySelectorAll('.has-error'), function (el) { el.classList.remove('has-error'); });
  }

  function showErrors(form, errors) {
    Object.keys(errors || {}).forEach(function (name) {
      var el = form.querySelector('[data-error-for="' + name + '"]');
      if (el) {
        el.textContent = errors[name];
        var field = el.closest('.intelindev-form__field');
        if (field) field.classList.add('has-error');
      }
    });
    var first = form.querySelector('.has-error input, .has-error select, .has-error textarea');
    if (first) first.focus();
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form.intelindev-form');
    if (!form || !window.fetch) return;
    event.preventDefault();

    var button = form.querySelector('.intelindev-form__submit');
    var original = button ? button.textContent : '';
    clearErrors(form);
    setMessage(form, '', false);
    if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }

    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (response) { return response.json().then(function (body) { return { status: response.status, body: body }; }); })
      .then(function (result) {
        var body = result.body || {};
        if (body.ok) {
          if (body.redirect) { window.location.href = body.redirect; return; }
          form.classList.add('is-sent');
          var row = form.querySelector('.row');
          var actions = form.querySelector('.intelindev-form__actions');
          if (row) row.hidden = true;
          if (actions) actions.hidden = true;
          setMessage(form, body.message || form.getAttribute('data-success') || '', false);
          return;
        }
        showErrors(form, body.errors);
        setMessage(form, body.message || form.getAttribute('data-error') || '', true);
      })
      .catch(function () {
        setMessage(form, form.getAttribute('data-error') || '', true);
      })
      .finally(function () {
        if (button) { button.disabled = false; button.removeAttribute('aria-busy'); button.textContent = original; }
      });
  });
})();
