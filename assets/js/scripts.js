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

  // Scrollers horizontales (proyectos, testimonios): las flechas [data-scroll-prev|next]
  // de la misma sección desplazan la lista [data-scroller] una tarjeta; el resto es CSS (scroll-snap).
  document.addEventListener('click', function (event) {
    var arrow = event.target.closest('[data-scroll-prev], [data-scroll-next]');
    if (!arrow) return;
    var section = arrow.closest('section');
    var list = section && section.querySelector('[data-scroller]');
    if (!list) return;
    var card = list.firstElementChild;
    var step = card ? card.getBoundingClientRect().width + 30 : list.clientWidth * 0.8;
    list.scrollBy({ left: arrow.hasAttribute('data-scroll-prev') ? -step : step, behavior: 'smooth' });
  });

  // Menú móvil: .site-nav-toggle abre/cierra la navegación (< 992px, ver CSS).
  var navToggle = document.querySelector('.site-nav-toggle');
  if (navToggle) {
    var setNavOpen = function (open) {
      document.body.classList.toggle('nav-open', open);
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    navToggle.addEventListener('click', function () {
      setNavOpen(!document.body.classList.contains('nav-open'));
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && document.body.classList.contains('nav-open')) setNavOpen(false);
    });
  }

  // Modal del CTA (<dialog> nativo). El contenido (formulario, script de CRM,
  // HTML, shortcode) NO viene en la página: se pide al endpoint REST
  // (data-cta-modal-src) en el primer clic y se inyecta re-creando los <script>
  // para que ejecuten recién ahí. Así ni el HTML ni el JS de terceros cargan
  // hasta que alguien abre el modal, y el token antispam del formulario es fresco.
  var modalState = null; // null | 'loading' | 'loaded'

  function injectHtml(target, html) {
    var fragment = document.createRange().createContextualFragment(html);
    // createContextualFragment deja los <script> inertes: se re-crean para que corran.
    Array.prototype.forEach.call(fragment.querySelectorAll('script'), function (old) {
      // Un script externo ya presente en el documento (ej. api.js de Turnstile
      // cargado por un formulario de la página) no se carga dos veces.
      var src = old.getAttribute('src');
      if (src && document.querySelector('script[src="' + src.replace(/"/g, '\\"') + '"]')) {
        old.parentNode.removeChild(old);
        return;
      }
      var script = document.createElement('script');
      Array.prototype.forEach.call(old.attributes, function (attr) {
        script.setAttribute(attr.name, attr.value);
      });
      script.textContent = old.textContent;
      old.parentNode.replaceChild(script, old);
    });
    target.innerHTML = '';
    target.appendChild(fragment);
    if (window.intelindevTurnstileRender) window.intelindevTurnstileRender();
  }

  function loadModalContent(dialog) {
    if (modalState) return;
    var target = dialog.querySelector('[data-cta-modal-content]');
    var src = dialog.getAttribute('data-cta-modal-src');
    if (!target || !src || !window.fetch) return;

    modalState = 'loading';
    target.textContent = dialog.getAttribute('data-loading') || '';

    fetch(src, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) { return response.json(); })
      .then(function (body) {
        if (!body || typeof body.html !== 'string' || body.html === '') throw new Error('empty');
        injectHtml(target, body.html);
        modalState = 'loaded';
        var focusable = target.querySelector('input, select, textarea, button, a[href]');
        if (focusable) focusable.focus();
      })
      .catch(function () {
        modalState = null; // permite reintentar en el próximo clic
        target.textContent = dialog.getAttribute('data-error') || '';
      });
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

// Cloudflare Turnstile: api.js se carga con ?onload=intelindevTurnstileRender&render=explicit
// solo donde hay un formulario protegido; esta función renderiza los widgets que
// todavía no lo están (también los que llegan después, ej. al abrir el modal).
window.intelindevTurnstileRender = function () {
  if (!window.turnstile) return;
  Array.prototype.forEach.call(document.querySelectorAll('.cf-turnstile:not([data-widget-id])'), function (el) {
    var id = window.turnstile.render(el, { sitekey: el.getAttribute('data-sitekey'), language: el.getAttribute('data-language') || 'auto' });
    if (id) el.setAttribute('data-widget-id', id);
  });
};

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
        // El token de Turnstile es de un solo uso: nuevo desafío para reintentar.
        var widget = form.querySelector('.cf-turnstile[data-widget-id]');
        if (widget && window.turnstile) window.turnstile.reset(widget.getAttribute('data-widget-id'));
      })
      .catch(function () {
        setMessage(form, form.getAttribute('data-error') || '', true);
      })
      .finally(function () {
        if (button) { button.disabled = false; button.removeAttribute('aria-busy'); button.textContent = original; }
      });
  });
})();
