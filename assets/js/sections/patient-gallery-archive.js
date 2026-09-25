/**
 * Listado de Patient Gallery: filtro real de dos niveles (categoría → chips
 * de sub-procedimiento, cirujano, y "View Saved" como cuarta dimensión) +
 * la barra de "casos guardados" (contador, ver guardados, vaciar lista,
 * enviar por email) + el guardado local de las tarjetas (.pgf) — todo
 * comparte el mismo localStorage que patient-gallery-single.js, así que
 * guardar/quitar un caso desde cualquier lado mantiene el mismo estado en
 * todos. Comportamiento calcado de producción (navegado en vivo el
 * 2026-09-25, con 0 y con 1 caso guardado, para confirmar qué cambia en
 * cada estado).
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ */
  /* Lista de guardados: única fuente de verdad, con "suscriptores" que se  */
  /* vuelven a pintar solos cada vez que cambia (desde una tarjeta, desde   */
  /* la barra, o desde un link ?favorited= entrante).                      */
  /* ------------------------------------------------------------------ */

  var STORAGE_KEY = 'pswptSavedGalleryCases';
  var list = [];
  var subscribers = [];

  function load() {
    try {
      var raw = window.localStorage.getItem(STORAGE_KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function persist() {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    } catch (e) {
      // localStorage bloqueado/no disponible: todo sigue respondiendo
      // visualmente, solo no persiste entre visitas.
    }
  }

  function notify() {
    subscribers.forEach(function (fn) { fn(list); });
  }

  function isSaved(cid) {
    return list.indexOf(cid) !== -1;
  }

  function toggle(cid) {
    var index = list.indexOf(cid);
    if (index === -1) {
      list.push(cid);
    } else {
      list.splice(index, 1);
    }
    persist();
    notify();
  }

  function addMany(cids) {
    var changed = false;
    cids.forEach(function (cid) {
      if (cid && list.indexOf(cid) === -1) {
        list.push(cid);
        changed = true;
      }
    });
    if (changed) {
      persist();
      notify();
    }
  }

  function clearAllSaved() {
    list = [];
    persist();
    notify();
  }

  list = load();

  /* ------------------------------------------------------------------ */
  /* Botones .pgf / .pgd-save (tarjetas del grid y — si está en la misma    */
  /* página — el single): togglean, y su texto/aria-pressed se resincroniza */
  /* cada vez que la lista cambia, sin importar quién la cambió.           */
  /* ------------------------------------------------------------------ */

  function initSaveButtons() {
    var buttons = document.querySelectorAll('.pgf, .pgd-save');
    if (!buttons.length) {
      return;
    }

    buttons.forEach(function (button) {
      button.setAttribute('data-save-label', button.textContent);
      button.addEventListener('click', function () {
        toggle(button.getAttribute('data-cid'));
      });
    });

    subscribers.push(function () {
      buttons.forEach(function (button) {
        var cid = button.getAttribute('data-cid');
        var saved = isSaved(cid);
        button.setAttribute('aria-pressed', saved ? 'true' : 'false');
        button.textContent = saved ? button.getAttribute('data-saved-label') : button.getAttribute('data-save-label');
      });
    });
  }

  /* ------------------------------------------------------------------ */
  /* Barra de "casos guardados"                                           */
  /* ------------------------------------------------------------------ */

  function initSavedBar(filterApi) {
    var bar = document.getElementById('pg-saved-bar');
    if (!bar) {
      return;
    }

    var countEl = document.getElementById('pg-fav-count');
    var viewBtn = document.getElementById('pg-view-saved');
    var emailBtn = document.getElementById('pg-fav-emailbtn');
    var consultLink = document.getElementById('pg-fav-consult');
    var clearBtn = document.getElementById('pg-clear-saved');
    var hint = document.getElementById('pg-saved-hint');
    var form = document.getElementById('pg-fav-email');
    var statusEl = document.getElementById('pg-fee-status');
    var sendBtn = document.getElementById('pg-fee-send');
    var mbSavedCount = document.getElementById('pg-mb-savedn');
    var lastCount = list.length;

    function render() {
      var count = list.length;
      if (countEl) {
        countEl.textContent = String(count);
        if (count !== lastCount) {
          countEl.classList.remove('pg-pop');
          // Reinicia la animación (una clase ya presente no se re-dispara sola).
          void countEl.offsetWidth;
          countEl.classList.add('pg-pop');
        }
        lastCount = count;
      }
      if (mbSavedCount) {
        mbSavedCount.textContent = String(count);
      }
      [viewBtn, emailBtn, consultLink, clearBtn].forEach(function (el) {
        if (el) {
          el.hidden = count === 0;
        }
      });
      if (hint) {
        hint.innerHTML = count === 0 ? hint.getAttribute('data-hint-empty') : hint.getAttribute('data-hint-filled');
      }
      if (consultLink) {
        try {
          var url = new URL(consultLink.href, window.location.origin);
          url.searchParams.set('favorited_gallery_cases', list.join(','));
          consultLink.href = url.toString();
        } catch (e) {
          // href inválido (no debería pasar, viene de get_permalink()): se deja como está.
        }
      }
      if (count === 0 && form) {
        form.hidden = true;
        if (emailBtn) {
          emailBtn.classList.remove('active');
        }
      }
    }

    subscribers.push(render);
    render();

    var mbSavedBtn = document.getElementById('pg-mb-saved');
    [viewBtn, mbSavedBtn].forEach(function (button) {
      if (button) {
        button.addEventListener('click', function () {
          if (filterApi) {
            filterApi.toggleFavorites();
          }
        });
      }
    });

    if (emailBtn && form) {
      emailBtn.addEventListener('click', function () {
        form.hidden = !form.hidden;
        emailBtn.classList.toggle('active', !form.hidden);
      });
    }

    if (clearBtn) {
      clearBtn.addEventListener('click', clearAllSaved);
    }

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (statusEl) {
          statusEl.textContent = '';
        }
        if (sendBtn) {
          sendBtn.disabled = true;
        }

        var data = new FormData(form);
        list.forEach(function (cid) { data.append('cases[]', cid); });

        fetch(bar.getAttribute('data-endpoint'), { method: 'POST', body: data })
          .then(function (response) { return response.json().then(function (json) { return { status: response.status, json: json }; }); })
          .then(function (result) {
            if (statusEl) {
              statusEl.textContent = result.json && result.json.message ? result.json.message : '';
            }
            if (result.json && result.json.ok) {
              form.reset();
            }
          })
          .catch(function () {
            if (statusEl) {
              statusEl.textContent = form.getAttribute('data-error');
            }
          })
          .finally(function () {
            if (sendBtn) {
              sendBtn.disabled = false;
            }
          });
      });
    }
  }

  /* ------------------------------------------------------------------ */
  /* Filtro de dos niveles + "View Saved" como cuarta dimensión            */
  /* ------------------------------------------------------------------ */

  function initFilter() {
    var box = document.querySelector('.pg-filterbox');
    var grid = document.getElementById('pg-grid');
    if (!box || !grid) {
      return;
    }

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.pg-card'));
    var tabs = Array.prototype.slice.call(box.querySelectorAll('.pg-cat'));
    var subrows = Array.prototype.slice.call(box.querySelectorAll('.pg-subs-group'));
    var surgeonChips = Array.prototype.slice.call(box.querySelectorAll('#pg-surgeons .pg-chip'));
    var divider = box.querySelector('.pg-divider');
    var hint = box.querySelector('.pg-hint');
    var count = box.querySelector('.pg-count');
    var sheetApply = box.querySelector('.pg-sheet-apply');
    var sheetClose = box.querySelector('.pg-sheet-close');
    var viewSavedBtn = document.getElementById('pg-view-saved');
    var mbFilters = document.getElementById('pg-mb-filters');
    var mbCount = document.getElementById('pg-mb-count');
    var rebuild = document.querySelector('.pg-rebuild');

    var total = cards.length;
    var state = { parent: null, child: null, surgeon: null, favorites: false };

    function chipsFor(row) {
      return row ? Array.prototype.slice.call(row.querySelectorAll('.pg-chip')) : [];
    }

    function resolveDeepLink(id) {
      var parentTab = tabs.filter(function (tab) { return tab.getAttribute('data-filter') === id; })[0];
      if (parentTab) {
        return { parent: id, child: null };
      }
      for (var i = 0; i < subrows.length; i++) {
        var chip = subrows[i].querySelector('.pg-chip[data-sub-filter="' + id + '"]');
        if (chip) {
          return { parent: subrows[i].getAttribute('data-parent'), child: id };
        }
      }
      return null;
    }

    function render() {
      tabs.forEach(function (tab) {
        var isAll = tab.getAttribute('data-filter') === 'all';
        var active = isAll ? state.parent === null : tab.getAttribute('data-filter') === state.parent;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      subrows.forEach(function (row) {
        var isActiveGroup = row.getAttribute('data-parent') === state.parent;
        row.hidden = !isActiveGroup;
        chipsFor(row).forEach(function (chip) {
          chip.classList.toggle('active', isActiveGroup && chip.getAttribute('data-sub-filter') === state.child);
        });
      });

      surgeonChips.forEach(function (chip) {
        chip.classList.toggle('active', chip.getAttribute('data-surgeon-filter') === state.surgeon);
      });

      if (viewSavedBtn) {
        viewSavedBtn.classList.toggle('active', state.favorites);
      }

      var visible = 0;
      cards.forEach(function (card) {
        var parents = ' ' + (card.getAttribute('data-parents') || '') + ' ';
        var cats = ' ' + (card.getAttribute('data-cats') || '') + ' ';
        var surgeon = card.getAttribute('data-surgeon') || '';
        var cid = card.getAttribute('data-i') !== null && card.querySelector('.pgf') ? card.querySelector('.pgf').getAttribute('data-cid') : null;

        var matchParent = state.parent === null || parents.indexOf(' ' + state.parent + ' ') !== -1;
        var matchChild = state.child === null || cats.indexOf(' ' + state.child + ' ') !== -1;
        var matchSurgeon = state.surgeon === null || surgeon === state.surgeon;
        var matchFavorites = !state.favorites || (cid !== null && isSaved(cid));
        var show = matchParent && matchChild && matchSurgeon && matchFavorites;

        card.classList.toggle('pg-hide', !show);
        if (show) {
          visible++;
        }
      });

      var hasFilter = state.parent !== null || state.surgeon !== null || state.favorites;
      if (hint) {
        hint.hidden = hasFilter;
      }
      if (count) {
        if (!hasFilter) {
          count.textContent = visible === 1 ? count.getAttribute('data-tpl-one') : count.getAttribute('data-tpl-all').replace('%d', visible);
        } else {
          var labels = [];
          if (state.parent !== null) {
            var activeTab = tabs.filter(function (tab) { return tab.getAttribute('data-filter') === state.parent; })[0];
            if (activeTab) {
              labels.push(activeTab.getAttribute('data-label'));
            }
          }
          if (state.child !== null) {
            var activeChip = box.querySelector('.pg-chip[data-sub-filter="' + state.child + '"]');
            if (activeChip) {
              labels.push(activeChip.textContent);
            }
          }
          if (state.surgeon !== null) {
            labels.push(state.surgeon);
          }
          if (state.favorites) {
            labels.push(count.getAttribute('data-favorites-label'));
          }
          count.innerHTML = '';
          count.appendChild(document.createTextNode(count.getAttribute('data-tpl-filtered').replace('%visible%', visible).replace('%total%', total)));
          var sep1 = document.createElement('span');
          sep1.className = 'sep';
          sep1.textContent = '·';
          count.appendChild(sep1);
          count.appendChild(document.createTextNode(labels.join(', ')));
          var sep2 = document.createElement('span');
          sep2.className = 'sep';
          sep2.textContent = '·';
          count.appendChild(sep2);
          var clear = document.createElement('button');
          clear.type = 'button';
          clear.className = 'clear';
          clear.textContent = count.getAttribute('data-clear-label');
          clear.addEventListener('click', clearAll);
          count.appendChild(clear);
        }
      }
      if (sheetApply) {
        sheetApply.textContent = sheetApply.getAttribute('data-tpl').replace('%d', visible);
      }
      if (mbCount) {
        mbCount.textContent = mbCount.getAttribute('data-tpl').replace('%d', visible);
      }
    }

    function clearAll() {
      state = { parent: null, child: null, surgeon: null, favorites: false };
      render();
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var id = tab.getAttribute('data-filter');
        if (id === 'all' || id === state.parent) {
          state.parent = null;
          state.child = null;
        } else {
          state.parent = id;
          state.child = null;
        }
        render();
      });
    });

    subrows.forEach(function (row) {
      chipsFor(row).forEach(function (chip) {
        chip.addEventListener('click', function () {
          var id = chip.getAttribute('data-sub-filter');
          state.child = id === state.child ? null : id;
          render();
        });
      });
    });

    surgeonChips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var name = chip.getAttribute('data-surgeon-filter');
        state.surgeon = name === state.surgeon ? null : name;
        render();
      });
    });

    [sheetApply, sheetClose].forEach(function (button) {
      if (button && rebuild) {
        button.addEventListener('click', function () {
          rebuild.classList.remove('pg-sheet-open');
        });
      }
    });

    if (mbFilters && rebuild) {
      mbFilters.addEventListener('click', function () {
        rebuild.classList.add('pg-sheet-open');
      });
    }

    if (divider) {
      divider.hidden = false;
    }

    subscribers.push(render);

    // Deep-link ?pgcat={id} (categoría padre o sub-procedimiento), igual que producción.
    var params = new URLSearchParams(window.location.search);
    var pgcat = params.get('pgcat');
    if (pgcat) {
      var resolved = resolveDeepLink(pgcat);
      if (resolved) {
        state.parent = resolved.parent;
        state.child = resolved.child;
      }
    }

    render();

    return {
      toggleFavorites: function () {
        state.favorites = !state.favorites;
        render();
      }
    };
  }

  /* ------------------------------------------------------------------ */
  /* ?favorited=slug1,slug2 (del email de "Send My Favorites"): repuebla   */
  /* el localStorage con esos casos al abrir el link, sin pisar los que ya */
  /* tuviera guardados el visitante en este navegador.                     */
  /* ------------------------------------------------------------------ */

  function initFavoritesDeepLink() {
    var params = new URLSearchParams(window.location.search);
    var favorited = params.get('favorited');
    if (!favorited) {
      return;
    }
    addMany(favorited.split(',').map(function (slug) { return slug.trim(); }).filter(Boolean));
  }

  initSaveButtons();
  var filterApi = initFilter();
  initSavedBar(filterApi);
  initFavoritesDeepLink();
  notify();
})();
