/**
 * Detalle de un caso de Patient Gallery: lightbox .pg-lb (recorre TODAS las
 * fotos del caso con flechas — .pgb/.pga son botones individuales, no un
 * dialog por par) y guardado local de "casos guardados" para los botones
 * .pgd-save (breadcrumb + mobile bar, mismo caso ⇒ mismo estado). Vanilla,
 * sin librerías — reemplaza a Magnific Popup/jQuery del plugin original.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'pswptSavedGalleryCases';

  function getSaved() {
    try {
      var raw = window.localStorage.getItem(STORAGE_KEY);
      var list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function setSaved(list) {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    } catch (e) {
      // localStorage bloqueado/no disponible (navegación privada): el botón
      // sigue respondiendo visualmente, solo no persiste entre visitas.
    }
  }

  function initSaveButtons() {
    var buttons = document.querySelectorAll('.pgd-save');
    if (!buttons.length) {
      return;
    }
    var saved = getSaved();

    function sync(cid) {
      var isSaved = saved.indexOf(cid) !== -1;
      document.querySelectorAll('.pgd-save[data-cid="' + cid + '"]').forEach(function (button) {
        button.setAttribute('aria-pressed', isSaved ? 'true' : 'false');
        button.textContent = isSaved ? button.getAttribute('data-saved-label') : button.getAttribute('data-save-label');
      });
    }

    buttons.forEach(function (button) {
      button.setAttribute('data-save-label', button.textContent);
      sync(button.getAttribute('data-cid'));
      button.addEventListener('click', function () {
        var cid = button.getAttribute('data-cid');
        if (!cid) {
          return;
        }
        var index = saved.indexOf(cid);
        if (index === -1) {
          saved.push(cid);
        } else {
          saved.splice(index, 1);
        }
        setSaved(saved);
        sync(cid);
      });
    });
  }

  function initLightbox() {
    var lb = document.querySelector('.pg-lb');
    var triggers = document.querySelectorAll('.pgb, .pga');
    if (!lb || !triggers.length) {
      return;
    }

    var img = lb.querySelector('.pg-lb-img');
    var caption = lb.querySelector('.pg-lb-cap');
    var count = lb.querySelector('.pg-lb-count');
    var caseLabel = caption ? caption.textContent : '';
    var photos = Array.prototype.map.call(triggers, function (button) {
      var photoImg = button.querySelector('img');
      return { src: photoImg.currentSrc || photoImg.src, alt: photoImg.alt };
    });
    var current = 0;

    function show(index) {
      current = (index + photos.length) % photos.length;
      var photo = photos[current];
      img.src = photo.src;
      img.alt = photo.alt;
      if (count) {
        count.textContent = (current + 1) + ' / ' + photos.length;
      }
      if (caption) {
        caption.textContent = caseLabel;
      }
    }

    function open(index) {
      show(index);
      lb.hidden = false;
      document.body.style.overflow = 'hidden';
    }

    function close() {
      lb.hidden = true;
      document.body.style.overflow = '';
    }

    triggers.forEach(function (button, index) {
      button.addEventListener('click', function () {
        open(index);
      });
    });

    lb.querySelectorAll('[data-close]').forEach(function (el) {
      el.addEventListener('click', close);
    });

    var prevBtn = lb.querySelector('.pg-lb-prev');
    var nextBtn = lb.querySelector('.pg-lb-next');
    if (prevBtn) {
      prevBtn.addEventListener('click', function () { show(current - 1); });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () { show(current + 1); });
    }

    document.addEventListener('keydown', function (event) {
      if (lb.hidden) {
        return;
      }
      if (event.key === 'Escape') { close(); }
      if (event.key === 'ArrowLeft') { show(current - 1); }
      if (event.key === 'ArrowRight') { show(current + 1); }
    });
  }

  initSaveButtons();
  initLightbox();
})();
