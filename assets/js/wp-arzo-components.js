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

  // ------------------------------------------------------- Client-side table pager
  // A filter-aware pager for lists that render every row server-side (Email Log,
  // Activity Log …). It OWNS row visibility: after your filter decides which rows
  // match, call pager.setMatches(matchingRowsArray) — the pager shows one page at a
  // time and hides the rest. It self-hides when everything fits on one page
  // (show-only-when-needed) and keeps the no-JS/server-rendered first paint (all
  // rows show until JS runs). Tokens/classes only.
  //
  //   var pager = wpArzo.tablePager(allRows, { perPage: 25, mountAfter: tableCard });
  //   function apply() { …; pager.setMatches(allRows.filter(isVisible)); }
  //
  wpArzo.tablePager = function (allRows, opts) {
    opts = opts || {};
    allRows = Array.prototype.slice.call(allRows || []);
    var perPage = Math.max(1, opts.perPage || 25);
    var noun = opts.noun || 'item';
    var page = 1;
    var matches = allRows.slice();

    var bar = document.createElement('div');
    bar.className = 'wpa-pager';
    var info = document.createElement('span');
    info.className = 'wpa-pager__info';
    var nav = document.createElement('span');
    nav.className = 'wpa-pager__nav';
    var prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'wpa-btn wpa-btn--ghost wpa-btn--sm';
    prev.textContent = '← Prev';
    var next = document.createElement('button');
    next.type = 'button';
    next.className = 'wpa-btn wpa-btn--ghost wpa-btn--sm';
    next.textContent = 'Next →';
    nav.appendChild(prev);
    nav.appendChild(next);
    bar.appendChild(info);
    bar.appendChild(nav);

    function pageCount() { return Math.max(1, Math.ceil(matches.length / perPage)); }
    function render() {
      var pages = pageCount();
      if (page > pages) { page = pages; }
      if (page < 1) { page = 1; }
      var start = (page - 1) * perPage;
      allRows.forEach(function (r) { r.hidden = true; });
      matches.slice(start, start + perPage).forEach(function (r) { r.hidden = false; });
      info.textContent = matches.length
        ? ('Page ' + page + ' of ' + pages + ' · ' + matches.length + ' ' + noun + (matches.length === 1 ? '' : 's'))
        : '';
      prev.disabled = page <= 1;
      next.disabled = page >= pages;
      // Only surface the pager when there's more than one page to move between.
      bar.style.display = matches.length > perPage ? 'flex' : 'none';
    }
    prev.addEventListener('click', function () { if (page > 1) { page--; render(); } });
    next.addEventListener('click', function () { if (page < pageCount()) { page++; render(); } });

    if (opts.mountAfter && opts.mountAfter.parentNode) {
      opts.mountAfter.parentNode.insertBefore(bar, opts.mountAfter.nextSibling);
    }
    render();

    return {
      element: bar,
      setMatches: function (m) { matches = Array.prototype.slice.call(m || []); page = 1; render(); },
      refresh: render
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
