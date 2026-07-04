/**
 * WP Arzo Component Library — behavior
 *
 * Dependency-free, accessible enhancers for the wpa- component CSS. Progressive:
 * the custom select upgrades a real <select>, so it still works if JS is disabled.
 *
 * Public API: window.wpArzo.toast(message, type), window.wpArzo.initSelects(root)
 */
(function () {
  'use strict';

  var wpArzo = window.wpArzo || {};

  // ------------------------------------------------------------------ Toast
  function ensureToastRegion() {
    var region = document.querySelector('.wpa-toast-region');
    if (!region) {
      region = document.createElement('div');
      region.className = 'wpa-toast-region';
      region.setAttribute('role', 'status');
      region.setAttribute('aria-live', 'polite');
      document.body.appendChild(region);
    }
    return region;
  }

  var TOAST_ICONS = {
    success: '<polyline points="20 6 9 17 4 12"/>',
    error: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    info: '<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/>'
  };

  wpArzo.toast = function (message, type, duration, action) {
    type = type || 'success';
    // An actionable toast (with a link) lingers longer so it can be clicked.
    duration = duration || (action && action.href ? 7000 : 3200);
    var region = ensureToastRegion();
    var toast = document.createElement('div');
    toast.className = 'wpa-toast wpa-toast--' + type;
    var icon = TOAST_ICONS[type] || TOAST_ICONS.info;
    toast.innerHTML =
      '<svg class="wpa-icon wpa-icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
      'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      icon + '</svg><span></span>';
    toast.querySelector('span').textContent = message;
    if (action && action.href) {
      var link = document.createElement('a');
      link.href = action.href;
      link.textContent = action.label || 'Open →';
      link.className = 'wpa-toast__action';
      toast.appendChild(link);
    }
    region.appendChild(toast);
    setTimeout(function () {
      toast.style.transition = 'opacity .2s, transform .2s';
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-8px)';
      setTimeout(function () { toast.remove(); }, 220);
    }, duration);
  };

  // ------------------------------------------------------- Custom Select
  // Enhances <select data-wpa-select> into an accessible listbox.
  function buildSelect(native) {
    if (native.dataset.wpaEnhanced) return;
    native.dataset.wpaEnhanced = '1';

    var wrap = document.createElement('div');
    wrap.className = 'wpa-select';
    native.parentNode.insertBefore(wrap, native);
    wrap.appendChild(native);
    native.classList.add('wpa-sr-only');

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'wpa-select__trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    var labelSpan = document.createElement('span');
    labelSpan.className = 'wpa-select__value';
    trigger.appendChild(labelSpan);
    trigger.insertAdjacentHTML('beforeend',
      '<svg class="wpa-icon wpa-icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
      'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<polyline points="6 9 12 15 18 9"/></svg>');

    var menu = document.createElement('ul');
    menu.className = 'wpa-select__menu';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;

    var options = Array.prototype.slice.call(native.options);
    options.forEach(function (opt, i) {
      var li = document.createElement('li');
      li.className = 'wpa-select__option';
      li.setAttribute('role', 'option');
      li.setAttribute('data-value', opt.value);
      li.id = (native.id || 'wpa-sel-' + Math.random().toString(36).slice(2)) + '-opt' + i;
      li.textContent = opt.textContent;
      li.setAttribute('aria-selected', opt.selected ? 'true' : 'false');
      if (opt.disabled) li.setAttribute('aria-disabled', 'true');
      menu.appendChild(li);
    });

    wrap.appendChild(trigger);
    wrap.appendChild(menu);

    function syncLabel() {
      var sel = native.options[native.selectedIndex];
      labelSpan.textContent = sel ? sel.textContent : '';
    }
    syncLabel();

    var activeIndex = native.selectedIndex < 0 ? 0 : native.selectedIndex;

    function open() {
      menu.hidden = false;
      wrap.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      setActive(native.selectedIndex < 0 ? 0 : native.selectedIndex);
      document.addEventListener('click', onDocClick, true);
    }
    function close() {
      menu.hidden = true;
      wrap.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
      document.removeEventListener('click', onDocClick, true);
    }
    function onDocClick(e) { if (!wrap.contains(e.target)) close(); }

    function setActive(i) {
      var items = menu.children;
      if (i < 0) i = 0;
      if (i > items.length - 1) i = items.length - 1;
      activeIndex = i;
      for (var k = 0; k < items.length; k++) items[k].classList.toggle('is-active', k === i);
      menu.setAttribute('aria-activedescendant', items[i].id);
      items[i].scrollIntoView({ block: 'nearest' });
    }

    function choose(i) {
      if (native.options[i] && native.options[i].disabled) return;
      native.selectedIndex = i;
      Array.prototype.forEach.call(menu.children, function (li, k) {
        li.setAttribute('aria-selected', k === i ? 'true' : 'false');
      });
      syncLabel();
      native.dispatchEvent(new Event('change', { bubbles: true }));
      close();
      trigger.focus();
    }

    trigger.addEventListener('click', function () {
      menu.hidden ? open() : close();
    });

    menu.addEventListener('click', function (e) {
      var li = e.target.closest('.wpa-select__option');
      if (!li) return;
      choose(Array.prototype.indexOf.call(menu.children, li));
    });

    trigger.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (menu.hidden) open(); else setActive(activeIndex + 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (menu.hidden) open(); else setActive(activeIndex - 1);
      }
    });

    menu.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { close(); trigger.focus(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
      else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(activeIndex); }
    });
    menu.tabIndex = -1;
  }

  wpArzo.initSelects = function (root) {
    (root || document).querySelectorAll('select[data-wpa-select]').forEach(buildSelect);
  };

  // Programmatically set an (optionally enhanced) <select>'s value and refresh the
  // custom listbox UI + fire a change event. Returns true if the value existed.
  wpArzo.setSelectValue = function (nativeSelect, value) {
    if (!nativeSelect) return false;
    nativeSelect.value = value;
    if (nativeSelect.value !== value) return false; // no such option
    var wrap = nativeSelect.closest('.wpa-select');
    if (wrap) {
      var lbl = wrap.querySelector('.wpa-select__value');
      var opt = nativeSelect.options[nativeSelect.selectedIndex];
      if (lbl && opt) lbl.textContent = opt.textContent;
      var menu = wrap.querySelector('.wpa-select__menu');
      if (menu) {
        Array.prototype.forEach.call(menu.children, function (li) {
          li.setAttribute('aria-selected', li.getAttribute('data-value') === value ? 'true' : 'false');
        });
      }
    }
    nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  };

  // ------------------------------------------------------- Collapse/accordion
  // <div class="wpa-collapse is-open"><button class="wpa-collapse__head">…</button>
  //   <div class="wpa-collapse__body">…</div></div>  — click the head to toggle.
  wpArzo.initCollapses = function (root) {
    (root || document).querySelectorAll('.wpa-collapse__head').forEach(function (head) {
      if (head.dataset.wpaCollapseBound) { return; }
      head.dataset.wpaCollapseBound = '1';
      var box = head.closest('.wpa-collapse');
      head.setAttribute('aria-expanded', box && box.classList.contains('is-open') ? 'true' : 'false');
      head.addEventListener('click', function (e) {
        e.preventDefault();
        if (!box) { return; }
        var open = box.classList.toggle('is-open');
        head.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  };

  // --------------------------------------------------- Server-side AJAX list pager
  // Reusable controller for admin list tables that paginate + filter server-side
  // (Email Log, Activity Log, …). The first page is rendered server-side (works with
  // no JS); this then binds the filter inputs + Prev/Next to an admin-ajax endpoint
  // that returns { html, total, pages, paged } and swaps the <tbody> in place — the
  // DOM only ever holds one page. This is the project's required list pattern.
  //
  // Markup contract: a pager element carrying data-paged / data-pages (so Prev/Next
  // know the real page count on the first click), containing an info span + Prev/Next
  // buttons. Pass those elements in.
  //
  //   wpArzo.ajaxList({
  //     endpoint: 'wp_arzo_email_log_query', nonce: n, ajaxUrl: url, noun: 'email',
  //     tbody: tbodyEl, pager: pagerEl, info: infoEl, prev: prevBtn, next: nextBtn,
  //     filters: [ { el: search, key: 'q', on: 'input' }, { el: status, key: 'status', on: 'change' } ],
  //     onLoad: function (data) { /* re-sync anything derived from the rows */ }
  //   });
  //
  wpArzo.ajaxList = function (opts) {
    opts = opts || {};
    var pager = opts.pager;
    var page = parseInt(pager && pager.dataset.paged, 10) || 1;
    var pages = parseInt(pager && pager.dataset.pages, 10) || 1;
    var noun = opts.noun || 'item';
    var ajaxUrl = opts.ajaxUrl || window.ajaxurl;
    var t, busy = false, seq = 0;

    function body(p) {
      var b = new FormData();
      b.append('action', opts.endpoint);
      b.append('nonce', opts.nonce || '');
      b.append('paged', p || 1);
      (opts.filters || []).forEach(function (f) {
        if (f.el) { b.append(f.key, f.el.value != null ? f.el.value : ''); }
      });
      if (typeof opts.extra === 'function') {
        var ex = opts.extra() || {};
        Object.keys(ex).forEach(function (k) { b.append(k, ex[k]); });
      }
      return b;
    }
    function paint(d) {
      page = d.paged; pages = d.pages;
      if (opts.tbody) { opts.tbody.innerHTML = d.html; }
      if (opts.info) {
        opts.info.textContent = d.total
          ? ('Page ' + page + ' of ' + pages + ' · ' + d.total + ' ' + noun + (d.total === 1 ? '' : 's'))
          : '';
      }
      if (opts.prev) { opts.prev.disabled = page <= 1; }
      if (opts.next) { opts.next.disabled = page >= pages; }
      if (pager) { pager.style.display = pages > 1 ? 'flex' : 'none'; }
      if (typeof opts.onLoad === 'function') { opts.onLoad(d); }
    }
    function load(p) {
      var my = ++seq; // sequence guard: only the most recent request may paint
      busy = true;
      return fetch(ajaxUrl, { method: 'POST', body: body(p), credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (my !== seq) { return; } // superseded by a newer request — drop this stale response
          busy = false;
          if (res && res.success) { paint(res.data); }
        })
        .catch(function () { if (my === seq) { busy = false; } });
    }

    (opts.filters || []).forEach(function (f) {
      if (!f.el) { return; }
      if (f.on === 'input') {
        f.el.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { load(1); }, 300); });
      } else {
        f.el.addEventListener('change', function () { load(1); });
      }
    });
    if (opts.prev) { opts.prev.addEventListener('click', function () { if (!busy && page > 1) { load(page - 1); } }); }
    if (opts.next) { opts.next.addEventListener('click', function () { if (!busy && page < pages) { load(page + 1); } }); }

    return {
      load: load,
      reload: function () { return load(page); },
      page: function () { return page; }
    };
  };

  // -------------------------------------------------------------- Boot
  function init() { wpArzo.initSelects(document); wpArzo.initCollapses(document); }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.wpArzo = wpArzo;
})();
