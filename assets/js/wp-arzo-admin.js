/**
 * WP Arzo admin dashboard behavior — feature toggles + live search.
 * Depends on wp-arzo-components.js (custom selects, toast) and wpArzoAdmin (localized).
 */
(function () {
  'use strict';

  var cfg = window.wpArzoAdmin || {};
  var toast = (window.wpArzo && window.wpArzo.toast) ? window.wpArzo.toast : function (m) { console.log(m); };

  // Small shared helpers (used by the REST / Roles / Config pages).
  function apiPost(action, fields) {
    var body = new FormData();
    body.append('action', action);
    Object.keys(fields || {}).forEach(function (k) {
      var v = fields[k];
      if (Array.isArray(v)) { v.forEach(function (item) { body.append(k + '[]', item); }); }
      else { body.append(k, v); }
    });
    return fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  // -------------------------------------------------- Feature toggles
  function bindToggles() {
    document.querySelectorAll('.wpa-feature-toggle').forEach(function (input) {
      input.addEventListener('change', function () {
        var id = input.dataset.feature;
        var enabled = input.checked;
        input.disabled = true;

        var body = new FormData();
        body.append('action', 'wp_arzo_toggle_feature');
        body.append('nonce', cfg.nonce || '');
        body.append('feature', id);
        body.append('enabled', enabled ? '1' : '0');

        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            input.disabled = false;
            if (!res || !res.success) {
              input.checked = !enabled; // revert
              toast((res && res.data && res.data.message) || 'Could not update feature', 'error');
              return;
            }
            var card = document.querySelector('[data-feature-card="' + id + '"]');
            if (card) {
              card.classList.toggle('is-on', enabled);
              var gear = card.querySelector('.wpa-feature-card__settings');
              if (gear) gear.classList.toggle('is-hidden', !enabled);
            }
            var title = (res.data && res.data.title) || 'Feature';
            var manage = res.data && res.data.manageUrl;
            if (enabled && manage) {
              // Tell the user WHERE to configure the feature they just turned on.
              toast(title + ' enabled — set it up next', 'success', 7000, { label: 'Configure →', href: manage });
            } else {
              toast(enabled ? title + ' enabled' : title + ' disabled', 'success');
            }
            // This feature adds an admin page/menu — reload so the menu reflects it, but
            // give the "Configure →" toast time to be read/clicked first.
            if (res.data && res.data.ownsPage) { reload(enabled && manage ? 4000 : 700); }
          })
          .catch(function () {
            input.disabled = false;
            input.checked = !enabled;
            toast('Request failed', 'error');
          });
      });
    });
  }

  // -------------------------------------------------- Live search + category filter
  // Search text and the active sidebar category compose: a card shows only when it
  // matches both. A group heading hides when none of its cards survive the filters.
  var featureQuery = '';
  var featureCat = '*';
  var featureConfigurableOnly = false;

  function applyFeatureFilters() {
    if (!document.getElementById('wpa-feature-grid')) return;
    var empty = document.getElementById('wpa-no-results');
    var anyVisible = false;

    document.querySelectorAll('.wpa-group').forEach(function (group) {
      var inCat = featureCat === '*' || group.getAttribute('data-group') === featureCat;
      var groupHasMatch = false;
      group.querySelectorAll('[data-feature-card]').forEach(function (card) {
        var match = inCat
          && (!featureQuery || (card.dataset.search || '').indexOf(featureQuery) !== -1)
          && (!featureConfigurableOnly || card.dataset.configurable === '1');
        card.hidden = !match;
        if (match) { groupHasMatch = true; anyVisible = true; }
      });
      group.hidden = !groupHasMatch;
    });

    if (empty) empty.hidden = anyVisible;
  }

  function bindSearch() {
    var box = document.getElementById('wpa-feature-search');
    if (box) {
      box.addEventListener('input', function () {
        featureQuery = box.value.trim().toLowerCase();
        applyFeatureFilters();
      });
    }
    var chip = document.getElementById('wpa-config-filter');
    if (chip) {
      var TIP_OFF = 'Show only configurable features';
      var TIP_ON = 'Showing configurable only — click to show all';
      chip.addEventListener('click', function () {
        featureConfigurableOnly = !featureConfigurableOnly;
        chip.setAttribute('aria-pressed', featureConfigurableOnly ? 'true' : 'false');
        chip.classList.toggle('is-active', featureConfigurableOnly);
        var tip = featureConfigurableOnly ? TIP_ON : TIP_OFF;
        chip.setAttribute('aria-label', tip);
        chip.setAttribute('data-wpa-tip', tip);
        applyFeatureFilters();
      });
    }
  }

  // -------------------------------------------------- Category master toggles
  function bindGroupToggles() {
    document.querySelectorAll('.wpa-group-toggle').forEach(function (input) {
      input.addEventListener('change', function () {
        var group = input.dataset.group;
        var enabled = input.checked;
        input.disabled = true;
        var body = new FormData();
        body.append('action', 'wp_arzo_toggle_group');
        body.append('nonce', cfg.nonce || '');
        body.append('group', group);
        body.append('enabled', enabled ? '1' : '0');
        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            input.disabled = false;
            if (!res || !res.success) { input.checked = !enabled; toast((res && res.data && res.data.message) || 'Could not update category', 'error'); return; }
            var section = document.getElementById('group-' + group);
            if (section) {
              section.querySelectorAll('.wpa-feature-toggle').forEach(function (cb) { cb.checked = enabled; });
              section.querySelectorAll('[data-feature-card]').forEach(function (card) {
                card.classList.toggle('is-on', enabled);
                var gear = card.querySelector('.wpa-feature-card__settings');
                if (gear) gear.classList.toggle('is-hidden', !enabled);
              });
            }
            toast(enabled ? 'Category enabled' : 'Category disabled', 'success');
            if (res.data && res.data.ownsPage) { reload(800); }
          })
          .catch(function () { input.disabled = false; input.checked = !enabled; toast('Request failed', 'error'); });
      });
    });
  }

  // --------------------------------------- Conditional settings fields (show_if)
  // A field's data-wpa-showif holds a JSON array of {field, value:[…]} conditions; the
  // field is shown only when EVERY controlling field (wpa_field_<field>) holds one of its
  // listed values. Re-evaluated whenever a controlling field changes.
  function bindSettingsConditionals() {
    var fields = document.querySelectorAll('.wpa-field[data-wpa-showif]');
    if (!fields.length) return;

    function conditionsOf(f) {
      try { return JSON.parse(f.getAttribute('data-wpa-showif')) || []; } catch (e) { return []; }
    }
    function controllerValue(key) {
      var el = document.querySelector('[name="wpa_field_' + key + '"]');
      if (!el) return null;
      if (el.type === 'checkbox') return el.checked ? '1' : '';
      return el.value;
    }
    function apply() {
      fields.forEach(function (f) {
        var show = conditionsOf(f).every(function (c) {
          return (c.value || []).indexOf(controllerValue(c.field)) !== -1;
        });
        f.style.display = show ? '' : 'none';
      });
    }
    var seen = {};
    fields.forEach(function (f) {
      conditionsOf(f).forEach(function (c) { if (c && c.field) { seen[c.field] = true; } });
    });
    Object.keys(seen).forEach(function (key) {
      var el = document.querySelector('[name="wpa_field_' + key + '"]');
      if (el) { el.addEventListener('change', apply); }
    });
    apply();
  }

  // --------------------------------- Dashboard "Configure" drawer (in-place settings)
  // Schema-only features (settings but no dedicated page) open their form in a drawer
  // right on the dashboard — no page bounce. Load + save go through AJAX; the same
  // full-page settings route still works when JS is off (the Configure link's href).
  function bindSettingsDrawer() {
    var drawer = document.getElementById('wpa-settings-drawer');
    if (!drawer) { return; }
    var form = document.getElementById('wpa-settings-drawer-form');
    var fieldsBox = document.getElementById('wpa-settings-drawer-fields');
    var titleEl = document.querySelector('#wpa-settings-drawer-title span');
    var saveBtn = document.getElementById('wpa-settings-drawer-save');
    var nonce = drawer.dataset.nonce || cfg.settingsNonce || '';
    var lastTrigger = null;

    function open() {
      drawer.hidden = false;
      document.documentElement.classList.add('wpa-scroll-locked'); // lock background scroll
    }
    function close() {
      drawer.hidden = true;
      document.documentElement.classList.remove('wpa-scroll-locked');
      fieldsBox.innerHTML = '';
      form.removeAttribute('aria-busy');
      saveBtn.disabled = false;
      if (lastTrigger) { try { lastTrigger.focus(); } catch (e) {} }
    }

    // Visible, keyboard-reachable focusables inside the drawer (skip the sr-only native
    // <select> that the custom listbox replaces).
    function focusables() {
      var sel = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
      return Array.prototype.filter.call(drawer.querySelectorAll(sel), function (el) {
        if (el.classList.contains('wpa-sr-only')) { return false; }
        return el.offsetWidth > 0 || el.offsetHeight > 0 || el === document.activeElement;
      });
    }

    // Trap Tab within the open dialog (WCAG 2.4.3 / modal focus management).
    drawer.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab' || drawer.hidden) { return; }
      var list = focusables();
      if (!list.length) { return; }
      var first = list[0], last = list[list.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    // Scoped show_if handling for the injected fields (mirrors bindSettingsConditionals).
    function bindConditionals(root) {
      var fields = root.querySelectorAll('.wpa-field[data-wpa-showif]');
      if (!fields.length) { return; }
      function conditionsOf(f) { try { return JSON.parse(f.getAttribute('data-wpa-showif')) || []; } catch (e) { return []; } }
      function controllerValue(key) {
        var el = root.querySelector('[name="wpa_field_' + key + '"]');
        if (!el) { return null; }
        if (el.type === 'checkbox') { return el.checked ? '1' : ''; }
        return el.value;
      }
      function apply() {
        fields.forEach(function (f) {
          var show = conditionsOf(f).every(function (c) { return (c.value || []).indexOf(controllerValue(c.field)) !== -1; });
          f.style.display = show ? '' : 'none';
        });
      }
      var seen = {};
      fields.forEach(function (f) { conditionsOf(f).forEach(function (c) { if (c && c.field) { seen[c.field] = true; } }); });
      Object.keys(seen).forEach(function (key) {
        var el = root.querySelector('[name="wpa_field_' + key + '"]');
        if (el) { el.addEventListener('change', apply); }
      });
      apply();
    }

    var featEl = document.getElementById('wpa-settings-drawer-feature');

    // Shimmering placeholder rows while the form loads (feels faster than a spinner).
    function skeleton() {
      var row = '<div class="wpa-skel__row"><div class="wpa-skel__bar wpa-skel__bar--label"></div><div class="wpa-skel__bar wpa-skel__bar--input"></div></div>';
      return '<div class="wpa-skel" aria-hidden="true">' + row + row + row + row + '</div>'
        + '<span class="wpa-sr-only" role="status">Loading settings…</span>';
    }

    function load(id, trigger) {
      lastTrigger = trigger || null;
      featEl.value = id; // submitted with the form on save
      fieldsBox.innerHTML = skeleton();
      form.setAttribute('aria-busy', 'true');
      saveBtn.disabled = true; // can't save a form that hasn't loaded
      if (titleEl) { titleEl.textContent = 'Configure'; }
      open();
      var body = new FormData();
      body.append('action', 'wp_arzo_feature_form');
      body.append('nonce', nonce);
      body.append('feature', id);
      fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res || !res.success) { toast((res && res.data && res.data.message) || 'Could not load settings', 'error'); close(); return; }
          if (titleEl) { titleEl.textContent = res.data.title || 'Configure'; }
          fieldsBox.innerHTML = res.data.fields || '';
          form.removeAttribute('aria-busy');
          saveBtn.disabled = false;
          if (window.wpArzo && wpArzo.initSelects) { wpArzo.initSelects(fieldsBox); }
          bindConditionals(fieldsBox);
          var first = fieldsBox.querySelector('input:not([type=hidden]), select, textarea');
          if (first) { try { first.focus(); } catch (e) {} }
        })
        .catch(function () { toast('Request failed', 'error'); close(); });
    }

    // Intercept Configure clicks on schema-only cards (delegated — cards never re-render).
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest ? e.target.closest('[data-config-drawer]') : null;
      if (!trigger) { return; }
      e.preventDefault();
      load(trigger.getAttribute('data-config-drawer'), trigger);
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      saveBtn.disabled = true;
      var body = new FormData(form); // includes the feature id + every wpa_field_*
      body.append('action', 'wp_arzo_feature_save');
      body.append('nonce', nonce);
      fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          saveBtn.disabled = false;
          if (!res || !res.success) { toast((res && res.data && res.data.message) || 'Could not save settings', 'error'); return; }
          toast((res.data && res.data.title ? res.data.title : 'Settings') + ' saved', 'success');
          close();
        })
        .catch(function () { saveBtn.disabled = false; toast('Request failed', 'error'); });
    });

    drawer.querySelectorAll('[data-settings-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden) { close(); } });
  }

  // SMTP provider presets: pick a provider → auto-fill host / port / encryption.
  var SMTP_PRESETS = {
    gmail:         { host: 'smtp.gmail.com',            port: '587', encryption: 'tls' },
    office365:     { host: 'smtp.office365.com',        port: '587', encryption: 'tls' },
    yahoo:         { host: 'smtp.mail.yahoo.com',       port: '465', encryption: 'ssl' },
    zoho:          { host: 'smtp.zoho.com',             port: '587', encryption: 'tls' },
    icloud:        { host: 'smtp.mail.me.com',          port: '587', encryption: 'tls' },
    fastmail:      { host: 'smtp.fastmail.com',         port: '465', encryption: 'ssl' },
    ses:           { host: 'email-smtp.us-east-1.amazonaws.com', port: '587', encryption: 'tls' },
    sendgrid_smtp: { host: 'smtp.sendgrid.net',         port: '587', encryption: 'tls' },
    mailgun_smtp:  { host: 'smtp.mailgun.org',          port: '587', encryption: 'tls' },
    brevo_smtp:    { host: 'smtp-relay.brevo.com',      port: '587', encryption: 'tls' },
    postmark:      { host: 'smtp.postmarkapp.com',      port: '587', encryption: 'tls' },
    mailjet:       { host: 'in-v3.mailjet.com',         port: '587', encryption: 'tls' }
  };
  function bindSmtpPresets() {
    var providerSel = document.querySelector('[name="wpa_field_provider"]');
    if (!providerSel) return;
    providerSel.addEventListener('change', function () {
      var preset = SMTP_PRESETS[providerSel.value];
      if (!preset) return; // "custom" or unknown — leave fields untouched
      var host = document.querySelector('[name="wpa_field_host"]');
      var port = document.querySelector('[name="wpa_field_port"]');
      var enc  = document.querySelector('[name="wpa_field_encryption"]');
      if (host) host.value = preset.host;
      if (port) port.value = preset.port;
      if (enc && window.wpArzo && wpArzo.setSelectValue) {
        wpArzo.setSelectValue(enc, preset.encryption);
      } else if (enc) {
        enc.value = preset.encryption;
      }
    });
  }

  // -------------------------------------------------- Email Connections
  function connRequest(action, data) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', cfg.connNonce || '');
    Object.keys(data || {}).forEach(function (k) {
      if (k === 'fields' && data[k] && typeof data[k] === 'object') {
        Object.keys(data[k]).forEach(function (fk) { body.append('fields[' + fk + ']', data[k][fk]); });
      } else if (k === 'ids' && Array.isArray(data[k])) {
        data[k].forEach(function (v) { body.append('ids[]', v); });
      } else {
        body.append(k, data[k]);
      }
    });
    return fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function connBuildField(f, value, hasSecret) {
    var wrap = document.createElement('div');
    wrap.className = 'wpa-field';
    var id = 'wpa-cf-' + f.key;

    if (f.type === 'toggle') {
      var lab = document.createElement('label');
      lab.className = 'wpa-toggle';
      var cb = document.createElement('input');
      cb.type = 'checkbox'; cb.className = 'wpa-toggle__input'; cb.setAttribute('role', 'switch');
      cb.checked = (value != null) ? (value == 1 || value === true || value === '1') : (f.default == 1);
      cb.dataset.key = f.key;
      lab.appendChild(cb);
      var track = document.createElement('span'); track.className = 'wpa-toggle__track';
      track.innerHTML = '<span class="wpa-toggle__thumb"></span>'; lab.appendChild(track);
      var tl = document.createElement('span'); tl.className = 'wpa-toggle__label'; tl.textContent = f.label; lab.appendChild(tl);
      wrap.appendChild(lab);
      if (f.help) { var th = document.createElement('p'); th.className = 'wpa-field__help'; th.textContent = f.help; wrap.appendChild(th); }
      return wrap;
    }

    var label = document.createElement('label');
    label.className = 'wpa-field__label'; label.setAttribute('for', id);
    label.textContent = f.label + (f.required ? ' *' : '');
    wrap.appendChild(label);

    var el;
    if (f.type === 'select') {
      el = document.createElement('select');
      el.className = 'wpa-input'; el.setAttribute('data-wpa-select', '');
      var opts = f.options || {};
      var cur = (value != null && value !== '') ? String(value) : String(f.default != null ? f.default : '');
      Object.keys(opts).forEach(function (k) {
        var o = document.createElement('option'); o.value = k; o.textContent = opts[k];
        if (String(k) === cur) { o.selected = true; }
        el.appendChild(o);
      });
    } else {
      el = document.createElement('input');
      el.className = 'wpa-input';
      el.type = f.type === 'password' ? 'password' : (f.type === 'email' ? 'email' : (f.type === 'number' ? 'number' : 'text'));
      if (f.type === 'password') {
        el.value = '';
        el.autocomplete = 'new-password';
        if (hasSecret) { el.placeholder = '••••••  (leave blank to keep current)'; }
      } else {
        el.value = (value != null && value !== '') ? value : (f.default != null ? f.default : '');
      }
      if (f.placeholder && !el.placeholder) { el.placeholder = f.placeholder; }
    }
    el.id = id; el.dataset.key = f.key;
    wrap.appendChild(el);
    if (f.help) { var h = document.createElement('p'); h.className = 'wpa-field__help'; h.textContent = f.help; wrap.appendChild(h); }
    return wrap;
  }

  function bindEmailConnections() {
    var data = window.wpArzoConn;
    if (!data) { return; }
    var picker = document.getElementById('wpa-conn-picker');
    var drawer = document.getElementById('wpa-conn-drawer');
    var form = document.getElementById('wpa-conn-form');
    if (!picker || !drawer || !form) { return; }

    var editing = { id: '', provider: '' };

    function openPicker() { picker.hidden = false; }
    function closeAll() { picker.hidden = true; drawer.hidden = true; }
    function connById(id) { return (data.connections || []).find(function (c) { return c.id === id; }); }

    function openDrawer(provider, conn) {
      var schema = (data.providers || {})[provider];
      if (!schema) { return; }
      editing.id = conn ? conn.id : '';
      editing.provider = provider;
      document.getElementById('wpa-conn-drawer-title').textContent = (conn ? 'Edit ' : 'Add ') + schema.label;
      form.innerHTML = '';
      schema.fields.forEach(function (f) {
        var val = conn ? conn[f.key] : null;
        var hasSecret = conn ? !!conn['_has_' + f.key] : false;
        form.appendChild(connBuildField(f, val, hasSecret));
      });
      if (window.wpArzo && wpArzo.initSelects) { wpArzo.initSelects(form); }
      picker.hidden = true;
      drawer.hidden = false;
    }

    function collect() {
      var out = {};
      form.querySelectorAll('[data-key]').forEach(function (el) {
        var k = el.dataset.key;
        if (el.type === 'checkbox') { out[k] = el.checked ? '1' : '0'; }
        else { out[k] = el.value; }
      });
      return out;
    }

    var addBtn = document.getElementById('wpa-conn-add');
    var addEmpty = document.getElementById('wpa-conn-add-empty');
    if (addBtn) { addBtn.addEventListener('click', openPicker); }
    if (addEmpty) { addEmpty.addEventListener('click', openPicker); }

    picker.querySelectorAll('.wpa-provider-card').forEach(function (card) {
      card.addEventListener('click', function () { openDrawer(card.getAttribute('data-provider'), null); });
    });

    document.querySelectorAll('[data-conn-close]').forEach(function (el) {
      el.addEventListener('click', closeAll);
    });
    var backBtn = document.querySelector('[data-conn-back]');
    if (backBtn) { backBtn.addEventListener('click', function () { drawer.hidden = true; openPicker(); }); }

    var saveBtn = document.getElementById('wpa-conn-save');
    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var fields = collect();
        if (!fields.title || !fields.title.trim()) { alert('Give the connection a name.'); return; }
        saveBtn.disabled = true;
        connRequest('wp_arzo_conn_save', { provider: editing.provider, id: editing.id, fields: fields }).then(function (res) {
          saveBtn.disabled = false;
          if (res && res.success) { location.reload(); }
          else { alert((res && res.data && res.data.message) || 'Could not save the connection.'); }
        }).catch(function () { saveBtn.disabled = false; alert('Request failed.'); });
      });
    }

    document.querySelectorAll('.wpa-conn-edit').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var conn = connById(btn.getAttribute('data-id'));
        if (conn) { openDrawer(conn.provider, conn); }
      });
    });
    document.querySelectorAll('.wpa-conn-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Delete this connection?')) { return; }
        connRequest('wp_arzo_conn_delete', { id: btn.getAttribute('data-id') }).then(function () { location.reload(); });
      });
    });
    document.querySelectorAll('.wpa-conn-primary').forEach(function (btn) {
      btn.addEventListener('click', function () {
        connRequest('wp_arzo_conn_primary', { id: btn.getAttribute('data-id') }).then(function () { location.reload(); });
      });
    });
    document.querySelectorAll('.wpa-conn-test').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var to = prompt('Send a test email to:', cfg.adminEmail || '');
        if (!to) { return; }
        btn.disabled = true;
        var label = btn.innerHTML; btn.textContent = 'Sending…';
        connRequest('wp_arzo_conn_test', { id: btn.getAttribute('data-id'), to: to }).then(function (res) {
          btn.disabled = false; btn.innerHTML = label;
          alert(res && res.success ? 'Test email sent — check the inbox.' : ((res && res.data && res.data.message) || 'Test failed.'));
        }).catch(function () { btn.disabled = false; btn.innerHTML = label; alert('Request failed.'); });
      });
    });
  }

  // First-run email onboarding wizard: provider -> configure -> test -> done.
  function bindEmailOnboarding() {
    var wiz = document.getElementById('wpa-email-wizard');
    if (!wiz) return;
    var data = window.wpArzoConn || {};
    var providers = data.providers || {};
    var form = document.getElementById('wpa-ewiz-form');
    var inds = Array.prototype.slice.call(wiz.querySelectorAll('.wpa-ewiz__step'));
    var order = ['provider', 'configure', 'test', 'done'];
    var chosen = '';   // provider key
    var savedId = '';  // connection id after save

    function showStep(name) {
      order.forEach(function (s) {
        var sec = wiz.querySelector('[data-step="' + s + '"]');
        if (sec) sec.hidden = (s !== name);
      });
      var idx = order.indexOf(name);
      inds.forEach(function (el, i) {
        el.classList.toggle('is-active', i === idx);
        el.classList.toggle('is-done', i < idx);
      });
    }

    function buildForm(provider) {
      var schema = providers[provider];
      if (!schema || !form) return;
      var title = document.getElementById('wpa-ewiz-conf-title');
      if (title) title.textContent = 'Configure ' + schema.label;
      form.innerHTML = '';
      schema.fields.forEach(function (f) {
        form.appendChild(connBuildField(f, null, false));
      });
      if (window.wpArzo && wpArzo.initSelects) { wpArzo.initSelects(form); }
    }

    function collect() {
      var out = {};
      form.querySelectorAll('[data-key]').forEach(function (el) {
        out[el.dataset.key] = (el.type === 'checkbox') ? (el.checked ? '1' : '0') : el.value;
      });
      return out;
    }

    // Step 1: provider cards
    wiz.querySelectorAll('.wpa-ewiz-provider').forEach(function (card) {
      card.addEventListener('click', function () {
        chosen = card.getAttribute('data-provider');
        buildForm(chosen);
        showStep('configure');
      });
    });

    // Step 2: back + save
    var backBtn = wiz.querySelector('[data-ewiz-back]');
    if (backBtn) backBtn.addEventListener('click', function () { showStep('provider'); });

    var saveBtn = document.getElementById('wpa-ewiz-save');
    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var fields = collect();
        if (!fields.title || !fields.title.trim()) { toast('Give the connection a name.', 'error'); return; }
        saveBtn.disabled = true;
        connRequest('wp_arzo_conn_save', { provider: chosen, id: '', fields: fields }).then(function (res) {
          saveBtn.disabled = false;
          if (res && res.success && res.data && res.data.id) {
            savedId = res.data.id;
            showStep('test');
          } else {
            toast((res && res.data && res.data.message) || 'Could not save the connection.', 'error');
          }
        }).catch(function () { saveBtn.disabled = false; toast('Request failed.', 'error'); });
      });
    }

    // Step 3: send test (or skip)
    var testBtn = document.getElementById('wpa-ewiz-test-btn');
    var testMsg = document.getElementById('wpa-ewiz-test-msg');
    if (testBtn) {
      testBtn.addEventListener('click', function () {
        var to = (document.getElementById('wpa-ewiz-test-to') || {}).value || '';
        if (!to.trim()) { if (testMsg) testMsg.textContent = 'Enter a recipient first.'; return; }
        testBtn.disabled = true;
        if (testMsg) { testMsg.style.color = 'var(--arzo-text-muted)'; testMsg.textContent = 'Sending…'; }
        connRequest('wp_arzo_conn_test', { id: savedId, to: to }).then(function (res) {
          testBtn.disabled = false;
          var ok = res && res.success;
          if (testMsg) {
            testMsg.style.color = ok ? 'var(--arzo-success)' : 'var(--arzo-error)';
            testMsg.textContent = ok ? 'Test sent — check the inbox.' : ((res && res.data && res.data.message) || 'Test failed. You can still finish and fix it later.');
          }
          if (ok) { setTimeout(function () { showStep('done'); }, 700); }
        }).catch(function () {
          testBtn.disabled = false;
          if (testMsg) { testMsg.style.color = 'var(--arzo-error)'; testMsg.textContent = 'Request failed.'; }
        });
      });
    }
    var skipBtn = wiz.querySelector('[data-ewiz-skip-test]');
    if (skipBtn) skipBtn.addEventListener('click', function () { showStep('done'); });

    // Step 4: finish -> reload to the connections list
    var finishBtn = document.getElementById('wpa-ewiz-finish');
    if (finishBtn) finishBtn.addEventListener('click', function () { location.reload(); });
  }

  function bindCategoryFilter() {
    var items = document.querySelectorAll('.wpa-cat-filter');
    if (!items.length) return;
    items.forEach(function (item) {
      item.addEventListener('click', function (e) {
        e.preventDefault();
        featureCat = item.getAttribute('data-group-filter') || '*';
        items.forEach(function (i) { i.classList.toggle('is-active', i === item); });
        applyFeatureFilters();
        if (featureCat !== '*') {
          var grid = document.getElementById('wpa-feature-grid');
          if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  // -------------------------------------------------- Backups
  function backupRequest(action, fields) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', cfg.backupNonce || '');
    Object.keys(fields || {}).forEach(function (k) {
      var v = fields[k];
      if (Array.isArray(v)) { v.forEach(function (item) { body.append(k + '[]', item); }); }
      else { body.append(k, v); }
    });
    return fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function bindBackups() {
    var createBtn = document.getElementById('wpa-backup-create');
    if (!createBtn) return;

    createBtn.addEventListener('click', function () {
      var scopeEl = document.getElementById('wpa-backup-scope');
      var scope = scopeEl ? scopeEl.value : 'options';
      var components = [];
      document.querySelectorAll('.wpa-backup-component:checked').forEach(function (c) { components.push(c.value); });
      var prog = document.getElementById('wpa-backup-progress');
      createBtn.disabled = true;
      if (prog) { prog.removeAttribute('hidden'); }
      toast(components.length ? 'Creating snapshot (files can take a while)…' : 'Creating snapshot…', 'info');
      backupRequest('wp_arzo_backup_create', { scope: scope, components: components })
        .then(function (res) {
          createBtn.disabled = false;
          if (prog) { prog.setAttribute('hidden', ''); }
          if (res && res.success) {
            var m = res.data && res.data.manifest;
            var extra = (m && m.files_error) ? (' — files: ' + m.files_error) : '';
            toast('Snapshot created' + extra, m && m.files_error ? 'error' : 'success');
            reload(900);
          } else { toast((res && res.data && res.data.message) || 'Backup failed', 'error'); }
        })
        .catch(function () { createBtn.disabled = false; if (prog) { prog.setAttribute('hidden', ''); } toast('Request failed', 'error'); });
    });

    // ------------------------------------------------ Snapshot diff drawer
    var compareBtn = document.getElementById('wpa-backup-compare');
    var diffDrawer = document.getElementById('wpa-diff-drawer');
    if (compareBtn && diffDrawer) {
      compareBtn.addEventListener('click', function () { diffDrawer.removeAttribute('hidden'); });
      diffDrawer.querySelectorAll('[data-close]').forEach(function (el) {
        el.addEventListener('click', function () { diffDrawer.setAttribute('hidden', ''); });
      });
      var runBtn = document.getElementById('wpa-diff-run');
      runBtn.addEventListener('click', function () {
        var a = document.getElementById('wpa-diff-a').value,
            b = document.getElementById('wpa-diff-b').value,
            out = document.getElementById('wpa-diff-result');
        if (a === b) { out.innerHTML = '<p style="color:var(--arzo-warning);">Pick two different snapshots.</p>'; return; }
        runBtn.disabled = true;
        out.innerHTML = '<p style="color:var(--arzo-text-muted);">Comparing…</p>';
        backupRequest('wp_arzo_backup_diff', { a: a, b: b })
          .then(function (res) {
            runBtn.disabled = false;
            if (!res || !res.success) { out.innerHTML = '<p style="color:var(--arzo-error);">' + esc((res && res.data && res.data.message) || 'Comparison failed.') + '</p>'; return; }
            out.innerHTML = renderDiff(res.data);
          })
          .catch(function () { runBtn.disabled = false; out.innerHTML = '<p style="color:var(--arzo-error);">Request failed.</p>'; });
      });
    }

    function diffSection(title, d, noun) {
      if (!d) return '';
      var total = d.added_count + d.removed_count + d.changed_count;
      var html = '<h3 style="margin:16px 0 6px;">' + esc(title) + '</h3>';
      if (!total) return html + '<p style="color:var(--arzo-text-muted);">No ' + noun + ' changes.</p>';
      html += '<p><span class="wpa-badge wpa-badge--success">+' + d.added_count + ' added</span> ' +
              '<span class="wpa-badge wpa-badge--error">−' + d.removed_count + ' removed</span> ' +
              '<span class="wpa-badge wpa-badge--warning">~' + d.changed_count + ' changed</span></p>';
      ['added', 'removed', 'changed'].forEach(function (kind) {
        if (!d[kind].length) return;
        var more = d[kind + '_count'] - d[kind].length;
        html += '<details style="margin:4px 0;"><summary style="cursor:pointer;color:var(--arzo-text-secondary);">' + kind + ' (' + d[kind + '_count'] + ')</summary>' +
                '<ul style="margin:6px 0 6px 18px;font-family:monospace;font-size:.85em;color:var(--arzo-text-muted);">' +
                d[kind].map(function (k) { return '<li>' + esc(k) + '</li>'; }).join('') +
                (more > 0 ? '<li>… and ' + more + ' more</li>' : '') + '</ul></details>';
      });
      return html;
    }

    function renderDiff(d) {
      var html = '<p style="color:var(--arzo-text-muted);">Base: <strong>' + esc(d.a.label) + '</strong> → Compare: <strong>' + esc(d.b.label) + '</strong></p>';
      (d.notes || []).forEach(function (n) { html += '<p style="color:var(--arzo-warning);">' + esc(n) + '</p>'; });
      if (d.db) {
        html += diffSection('Options', d.db.options, 'option');
        var t = d.db.tables || [];
        html += '<h3 style="margin:16px 0 6px;">Tables</h3>';
        if (!t.length) { html += '<p style="color:var(--arzo-text-muted);">No table changes.</p>'; }
        else {
          html += '<ul style="margin:6px 0 6px 18px;font-family:monospace;font-size:.85em;">' + t.map(function (r) {
            var badge = r.change === 'added' ? 'new table' : (r.change === 'removed' ? 'table removed' : ((r.delta > 0 ? '+' : '') + r.delta + ' rows'));
            return '<li>' + esc(r.table) + ' — ' + esc(badge) + '</li>';
          }).join('') + '</ul>';
        }
      }
      html += diffSection('Files', d.files, 'file');
      return html;
    }

    document.querySelectorAll('.wpa-backup-restore').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Restore this snapshot? A safety snapshot of the current state is taken first.')) return;
        var tr = btn.closest('tr');
        var comps = (tr && tr.dataset.components) || '';
        var withFiles = false;
        if (comps) {
          withFiles = confirm('This snapshot also contains files (' + comps + ').\n\nRestore the files too? Existing files are overwritten; files added since the snapshot stay; config files are never auto-restored. A safety snapshot of the same components is taken first.\n\nOK = database + files · Cancel = database only');
        }
        btn.disabled = true;
        toast(withFiles ? 'Restoring database + files…' : 'Restoring…', 'info');
        backupRequest('wp_arzo_backup_restore', { id: btn.dataset.id, include_files: withFiles ? 1 : 0 })
          .then(function (res) {
            if (res && res.success) { toast((res.data && res.data.message) || 'Snapshot restored', 'success', 6000); reload(1400); }
            else { btn.disabled = false; toast((res && res.data && res.data.message) || 'Restore failed', 'error'); }
          })
          .catch(function () { btn.disabled = false; toast('Request failed', 'error'); });
      });
    });

    document.querySelectorAll('.wpa-backup-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Delete this snapshot permanently?')) return;
        btn.disabled = true;
        backupRequest('wp_arzo_backup_delete', { id: btn.dataset.id })
          .then(function (res) {
            if (res && res.success) {
              toast('Snapshot deleted', 'success');
              var row = btn.closest('tr');
              if (row) row.parentNode.removeChild(row);
            } else { btn.disabled = false; toast((res && res.data && res.data.message) || 'Delete failed', 'error'); }
          })
          .catch(function () { btn.disabled = false; toast('Request failed', 'error'); });
      });
    });
  }

  function reload(delay) { setTimeout(function () { window.location.reload(); }, delay || 600); }

  // -------------------------------------------------- Email log
  function bindEmailLog() {
    var clearBtn = document.getElementById('wpa-email-clear');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        if (!confirm('Clear the entire email log?')) return;
        clearBtn.disabled = true;
        var body = new FormData();
        body.append('action', 'wp_arzo_email_log_clear');
        body.append('nonce', clearBtn.dataset.nonce || '');
        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res && res.success) { toast('Email log cleared', 'success'); reload(600); }
            else { clearBtn.disabled = false; toast('Could not clear log', 'error'); }
          })
          .catch(function () { clearBtn.disabled = false; toast('Request failed', 'error'); });
      });
    }

    var table = document.getElementById('wpa-emaillog-table');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    var search = document.getElementById('wpa-emaillog-search');
    var status = document.getElementById('wpa-emaillog-status');
    var pager = document.getElementById('wpa-emaillog-pager');

    // ---- Row-click detail drawer ---------------------------------------
    var drawer = document.getElementById('wpa-emaillog-drawer');
    var detail = document.getElementById('wpa-emaillog-detail');
    var titleEl = document.getElementById('wpa-emaillog-drawer-title');
    var resendBtn = document.getElementById('wpa-emaillog-resend');

    function closeDrawer() { if (drawer) drawer.hidden = true; }
    if (drawer) {
      drawer.querySelectorAll('[data-maillog-close]').forEach(function (el) {
        el.addEventListener('click', closeDrawer);
      });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden) closeDrawer(); });
    }

    function detailRow(label, value, opts) {
      opts = opts || {};
      var wrap = document.createElement('div');
      wrap.style.cssText = 'display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--arzo-border,rgba(255,255,255,.08));';
      var l = document.createElement('div');
      l.textContent = label;
      l.style.cssText = 'flex:0 0 96px;color:var(--arzo-text-muted);font-size:13px;';
      var v = document.createElement('div');
      v.style.cssText = 'flex:1;min-width:0;word-break:break-word;' + (opts.color ? 'color:' + opts.color + ';' : '');
      if (opts.html) { v.appendChild(opts.html); } else { v.textContent = value; }
      wrap.appendChild(l); wrap.appendChild(v);
      return wrap;
    }

    function openRow(id) {
      if (!drawer || !detail) return;
      detail.innerHTML = '';
      var loading = document.createElement('p');
      loading.textContent = 'Loading…';
      loading.style.color = 'var(--arzo-text-muted)';
      detail.appendChild(loading);
      drawer.hidden = false;
      if (resendBtn) { resendBtn.dataset.id = id; resendBtn.disabled = true; resendBtn.hidden = true; }

      var body = new FormData();
      body.append('action', 'wp_arzo_email_log_detail');
      body.append('nonce', drawer.dataset.nonce || '');
      body.append('id', id);
      fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          detail.innerHTML = '';
          if (!res || !res.success || !res.data) {
            var err = document.createElement('p');
            err.textContent = (res && res.data && res.data.message) || 'Could not load this email.';
            err.style.color = 'var(--arzo-error)';
            detail.appendChild(err);
            return;
          }
          var d = res.data;
          var failed = d.status === 'failed';
          if (titleEl) titleEl.textContent = d.subject || '(no subject)';

          var badge = document.createElement('span');
          badge.className = 'wpa-badge ' + (failed ? 'wpa-badge--error' : 'wpa-badge--success');
          badge.textContent = failed ? 'Failed' : 'Sent';
          detail.appendChild(detailRow('Status', '', { html: badge }));
          detail.appendChild(detailRow('Time', d.time || ''));
          detail.appendChild(detailRow('To', d.to || ''));
          detail.appendChild(detailRow('Subject', d.subject || ''));
          detail.appendChild(detailRow('Connection', d.connection || '—'));
          if (failed && d.error) detail.appendChild(detailRow('Error', d.error, { color: 'var(--arzo-error)' }));
          if (d.headers) {
            var pre = document.createElement('pre');
            pre.textContent = d.headers;
            pre.style.cssText = 'margin:0;white-space:pre-wrap;word-break:break-word;font-size:12px;color:var(--arzo-text-muted);';
            detail.appendChild(detailRow('Headers', '', { html: pre }));
          }
          var bodyPre = document.createElement('pre');
          bodyPre.textContent = d.message || '(empty body)';
          bodyPre.style.cssText = 'margin:0;max-height:320px;overflow:auto;white-space:pre-wrap;word-break:break-word;font-size:13px;background:var(--arzo-surface-2,rgba(255,255,255,.04));padding:12px;border-radius:8px;';
          var bwrap = document.createElement('div');
          bwrap.style.cssText = 'padding-top:12px;';
          var blabel = document.createElement('div');
          blabel.textContent = 'Message';
          blabel.style.cssText = 'color:var(--arzo-text-muted);font-size:13px;margin-bottom:6px;';
          bwrap.appendChild(blabel); bwrap.appendChild(bodyPre);
          detail.appendChild(bwrap);

          if (resendBtn) { resendBtn.hidden = !d.resendable; resendBtn.disabled = !d.resendable; resendBtn.dataset.id = d.id; }
        })
        .catch(function () {
          detail.innerHTML = '';
          var err = document.createElement('p');
          err.textContent = 'Request failed.';
          err.style.color = 'var(--arzo-error)';
          detail.appendChild(err);
        });
    }

    // Delegated row activation — one handler on the <tbody>, so it keeps working
    // after each AJAX page swap replaces the rows.
    if (tbody) {
      tbody.addEventListener('click', function (e) {
        var row = e.target.closest('tr.wpa-email-row');
        if (row && row.getAttribute('data-id')) { openRow(row.getAttribute('data-id')); }
      });
      tbody.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var row = e.target.closest('tr.wpa-email-row');
        if (row && row.getAttribute('data-id')) { e.preventDefault(); openRow(row.getAttribute('data-id')); }
      });
    }

    // Server-side AJAX pagination + filtering (first page is server-rendered).
    if (window.wpArzo && wpArzo.ajaxList && pager && tbody) {
      wpArzo.ajaxList({
        endpoint: 'wp_arzo_email_log_query',
        nonce: pager.dataset.nonce || '',
        ajaxUrl: cfg.ajaxUrl,
        noun: 'email',
        tbody: tbody,
        pager: pager,
        info: document.getElementById('wpa-emaillog-pageinfo'),
        prev: document.getElementById('wpa-emaillog-prev'),
        next: document.getElementById('wpa-emaillog-next'),
        filters: [
          { el: search, key: 'q', on: 'input' },
          { el: status, key: 'status', on: 'change' }
        ]
      });
    }
  }

  // -------------------------------------------------- Activity log
  function bindActivityLog() {
    var btn = document.getElementById('wpa-activity-clear');
    if (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Clear the entire activity log?')) return;
        btn.disabled = true;
        var body = new FormData();
        body.append('action', 'wp_arzo_activity_clear');
        body.append('nonce', btn.dataset.nonce || '');
        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res && res.success) { toast('Activity log cleared', 'success'); reload(600); }
            else { btn.disabled = false; toast('Could not clear log', 'error'); }
          })
          .catch(function () { btn.disabled = false; toast('Request failed', 'error'); });
      });
    }
    bindActivityPager();
  }

  // Server-side AJAX pagination + filtering for the free Activity Log (Events tab).
  // Lives here (not inline in PHP) so it runs after wpArzo (a dependency) is loaded.
  function bindActivityPager() {
    var tbody = document.getElementById('wpa-activity-rows');
    var pager = document.getElementById('wpa-activity-pager');
    if (!tbody || !pager || !window.wpArzo || !wpArzo.ajaxList) return;
    var sev = document.getElementById('wpa-act-sev'),
        act = document.getElementById('wpa-act-action'),
        q = document.getElementById('wpa-act-q'),
        reset = document.getElementById('wpa-act-reset');
    var list = wpArzo.ajaxList({
      endpoint: 'wp_arzo_activity_query',
      nonce: pager.dataset.nonce || '',
      ajaxUrl: cfg.ajaxUrl,
      noun: 'event',
      tbody: tbody,
      pager: pager,
      info: document.getElementById('wpa-activity-pageinfo'),
      prev: document.getElementById('wpa-activity-prev'),
      next: document.getElementById('wpa-activity-next'),
      filters: [
        { el: sev, key: 'sev', on: 'change' },
        { el: act, key: 'fa', on: 'change' },
        { el: q, key: 'q', on: 'input' }
      ]
    });
    // Reset appears only while a filter is active (self-cleaning UI).
    function syncReset() {
      if (!reset) return;
      reset.hidden = !((sev && sev.value) || (act && act.value) || (q && q.value));
    }
    [sev, act].forEach(function (el) { if (el) el.addEventListener('change', syncReset); });
    if (q) q.addEventListener('input', syncReset);
    if (reset) {
      reset.addEventListener('click', function () {
        if (q) q.value = '';
        [sev, act].forEach(function (el) {
          if (!el) return;
          el.value = '';
          if (window.wpArzo && wpArzo.setSelectValue) wpArzo.setSelectValue(el, '');
        });
        syncReset();
        list.load(1);
      });
    }
  }

  // -------------------------------------------------- License activate
  function bindLicense() {
    var btn = document.getElementById('wpa-license-activate');
    if (!btn) return;
    var msg = document.getElementById('wpa-license-msg');
    function note(text, ok) {
      if (!msg) { toast(text, ok ? 'success' : 'error'); return; }
      msg.hidden = false;
      msg.textContent = text;
      msg.className = 'wpa-aside-card__note ' + (ok ? 'is-ok' : 'is-error');
    }
    btn.addEventListener('click', function () {
      var keyEl = document.getElementById('wpa-license-key');
      var key = keyEl ? keyEl.value.trim() : '';
      if (!key) { note('Enter your license key first.', false); return; }
      btn.disabled = true;
      var body = new FormData();
      body.append('action', 'wp_arzo_activate_license');
      body.append('nonce', btn.dataset.nonce || cfg.licenseNonce || '');
      body.append('key', key);
      fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          btn.disabled = false;
          if (res && res.success) { note((res.data && res.data.message) || 'Activated.', true); reload(1000); }
          else { note((res && res.data && res.data.message) || 'Activation failed.', false); }
        })
        .catch(function () { btn.disabled = false; note('Request failed.', false); });
    });
  }

  // -------------------------------------------------- Snippets
  function bindSnippets() {
    document.querySelectorAll('.wpa-snippet-toggle').forEach(function (input) {
      input.addEventListener('change', function () {
        var body = new FormData();
        body.append('action', 'wp_arzo_snippet_toggle');
        body.append('nonce', cfg.snippetNonce || '');
        body.append('id', input.dataset.id);
        body.append('active', input.checked ? '1' : '0');
        input.disabled = true;
        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            input.disabled = false;
            if (res && res.success) { toast(input.checked ? 'Snippet activated' : 'Snippet deactivated', 'success'); }
            else { input.checked = !input.checked; toast((res && res.data && res.data.message) || 'Failed', 'error'); }
          })
          .catch(function () { input.disabled = false; input.checked = !input.checked; toast('Request failed', 'error'); });
      });
    });

    document.querySelectorAll('.wpa-snippet-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Delete this snippet permanently?')) return;
        btn.disabled = true;
        var body = new FormData();
        body.append('action', 'wp_arzo_snippet_delete');
        body.append('nonce', cfg.snippetNonce || '');
        body.append('id', btn.dataset.id);
        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res && res.success) {
              toast('Snippet deleted', 'success');
              var item = btn.closest('.wpa-code-item') || btn.closest('tr');
              if (item && item.classList.contains('is-active')) { location.href = location.pathname + '?page=wp-arzo-snippets'; return; }
              if (item) { item.parentNode.removeChild(item); } else { location.reload(); }
            } else { btn.disabled = false; toast('Delete failed', 'error'); }
          })
          .catch(function () { btn.disabled = false; toast('Request failed', 'error'); });
      });
    });

    bindSnippetEditor();
  }

  // CodeMirror-powered snippet editor (Advanced-Scripts-style) with per-type modes.
  function bindSnippetEditor() {
    var ta = document.getElementById('snp-code');
    if (!ta || !window.wpArzoCM || !window.wp || !wp.codeEditor) { return; }
    var modeFor = {
      php: 'application/x-httpd-php',
      css: 'text/css',
      js: 'text/javascript',
      html: 'htmlmixed'
    };
    var typeSel = document.getElementById('snp-type');
    var startType = typeSel ? typeSel.value : 'php';

    // Clone the localized settings and set the starting mode.
    var settings = JSON.parse(JSON.stringify(window.wpArzoCM));
    settings.codemirror = Object.assign({}, settings.codemirror, {
      mode: modeFor[startType] || 'application/x-httpd-php',
      lineNumbers: true,
      indentUnit: 4,
      tabSize: 4
    });
    var editor;
    try { editor = wp.codeEditor.initialize(ta, settings); } catch (e) { return; }
    if (!editor || !editor.codemirror) { return; }
    var cm = editor.codemirror;

    if (typeSel) {
      typeSel.addEventListener('change', function () {
        cm.setOption('mode', modeFor[typeSel.value] || 'text/plain');
      });
    }
    // Ensure the textarea is synced before the form submits.
    var form = document.getElementById('wpa-snippet-form');
    if (form) { form.addEventListener('submit', function () { cm.save(); }); }
  }

  // -------------------------------------------------- Test email + resend
  function bindEmailExtras() {
    var testBtn = document.getElementById('wpa-test-email-btn');
    if (testBtn) {
      testBtn.addEventListener('click', function () {
        var input = document.getElementById('wpa-test-email');
        var msg = document.getElementById('wpa-test-email-msg');
        var to = input ? input.value.trim() : '';
        if (!to) { if (msg) msg.textContent = 'Enter an email address first.'; return; }
        testBtn.disabled = true;
        var body = new FormData();
        body.append('action', 'wp_arzo_send_test_email');
        body.append('nonce', testBtn.dataset.nonce || '');
        body.append('to', to);
        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            testBtn.disabled = false;
            var ok = res && res.success;
            var text = (res && res.data && res.data.message) || (ok ? 'Sent.' : 'Failed.');
            if (msg) msg.textContent = text;
            toast(text, ok ? 'success' : 'error');
          })
          .catch(function () { testBtn.disabled = false; toast('Request failed', 'error'); });
      });
    }

    document.querySelectorAll('.wpa-email-resend').forEach(function (btn) {
      btn.addEventListener('click', function () {
        btn.disabled = true;
        var body = new FormData();
        body.append('action', 'wp_arzo_email_resend');
        body.append('nonce', btn.dataset.nonce || '');
        body.append('id', btn.dataset.id);
        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            btn.disabled = false;
            toast((res && res.data && res.data.message) || (res && res.success ? 'Resent' : 'Failed'), res && res.success ? 'success' : 'error');
          })
          .catch(function () { btn.disabled = false; toast('Request failed', 'error'); });
      });
    });
  }

  // -------------------------------------------------- Media Cleanup
  function fmtBytes(b) {
    if (!b) return '0 B';
    var u = ['B', 'KB', 'MB', 'GB'], i = Math.floor(Math.log(b) / Math.log(1024));
    return (b / Math.pow(1024, i)).toFixed(i ? 1 : 0) + ' ' + u[i];
  }

  function bindMediaCleanup() {
    var scanBtn = document.getElementById('wpa-media-scan');
    if (!scanBtn) return;

    var items = [];
    var nonce = scanBtn.dataset.nonce || '';
    var total = parseInt(scanBtn.dataset.total, 10) || 0;
    var bar = document.getElementById('wpa-media-bar');
    var progress = document.getElementById('wpa-media-progress');
    var progressLabel = document.getElementById('wpa-media-progress-label');
    var results = document.getElementById('wpa-media-results');
    var tbody = document.querySelector('#wpa-media-table tbody');
    var summary = document.getElementById('wpa-media-summary');
    var delBtn = document.getElementById('wpa-media-delete');
    var selCount = document.getElementById('wpa-media-selcount');

    function scanFrom(offset) {
      var body = new FormData();
      body.append('action', 'wp_arzo_media_scan');
      body.append('nonce', nonce);
      body.append('offset', offset);
      return fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    scanBtn.addEventListener('click', function () {
      items = [];
      scanBtn.disabled = true;
      if (progress) progress.hidden = false;
      if (results) results.hidden = true;

      (function loop(offset) {
        scanFrom(offset).then(function (res) {
          if (!res || !res.success) { scanBtn.disabled = false; toast('Scan failed', 'error'); return; }
          items = items.concat(res.data.items || []);
          var pct = total ? Math.min(100, Math.round(items.length / total * 100)) : 100;
          if (bar) bar.style.width = pct + '%';
          if (progressLabel) progressLabel.textContent = 'Scanned ' + items.length + ' of ' + total + '…';
          if (res.data.count === 20 && items.length < total) {
            loop(offset + res.data.count);
          } else {
            if (progress) progress.hidden = true;
            scanBtn.disabled = false;
            render();
            if (results) results.hidden = false;
          }
        }).catch(function () { scanBtn.disabled = false; toast('Request failed', 'error'); });
      })(0);
    });

    function filtered() {
      var unusedOnly = document.getElementById('wpa-media-unused-only');
      var typeEl = document.getElementById('wpa-media-type');
      var searchEl = document.getElementById('wpa-media-search');
      var uo = unusedOnly ? unusedOnly.checked : true;
      var t = typeEl ? typeEl.value : '';
      var q = searchEl ? searchEl.value.trim().toLowerCase() : '';
      return items.filter(function (it) {
        if (uo && it.used) return false;
        if (t && (it.mime || '').indexOf(t) !== 0) return false;
        if (q && (it.filename || '').toLowerCase().indexOf(q) === -1) return false;
        return true;
      });
    }

    function render() {
      var list = filtered();
      var unusedCount = items.filter(function (i) { return !i.used; }).length;
      var unusedBytes = items.reduce(function (a, i) { return a + (i.used ? 0 : (i.size || 0)); }, 0);
      if (summary) summary.innerHTML = '<strong>' + unusedCount + '</strong> possibly-unused · ' + fmtBytes(unusedBytes) + ' reclaimable';
      tbody.innerHTML = '';
      if (!list.length) { tbody.innerHTML = '<tr class="wpa-backup-empty"><td colspan="6">Nothing matches the current filters.</td></tr>'; updateSel(); return; }
      list.forEach(function (it) {
        var tr = document.createElement('tr');
        var thumb = it.thumb ? '<img src="' + it.thumb + '" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:4px;">' : '<span class="wpa-badge wpa-badge--neutral">' + (it.mime || 'file').split('/')[0] + '</span>';
        tr.innerHTML =
          '<td><input type="checkbox" class="wpa-media-cb" value="' + it.id + '"></td>' +
          '<td><div style="display:flex;align-items:center;gap:10px;">' + thumb + '<div><div style="color:var(--arzo-text-strong);">' + escapeHtml(it.filename || it.title) + '</div><div class="wpa-backup-meta">' + escapeHtml(it.title) + '</div></div></div></td>' +
          '<td>' + escapeHtml(it.mime || '') + '</td>' +
          '<td>' + fmtBytes(it.size) + '</td>' +
          '<td>' + escapeHtml(it.date || '') + '</td>' +
          '<td>' + (it.used
            ? '<span class="wpa-badge wpa-badge--success">In use</span>'
            : '<span class="wpa-badge wpa-badge--warning" title="' + escapeHtml(it.reason) + '">Possibly unused</span>') + '</td>';
        tbody.appendChild(tr);
      });
      tbody.querySelectorAll('.wpa-media-cb').forEach(function (cb) { cb.addEventListener('change', updateSel); });
      updateSel();
    }

    function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    function selectedIds() {
      return Array.prototype.slice.call(tbody.querySelectorAll('.wpa-media-cb:checked')).map(function (c) { return c.value; });
    }
    function updateSel() {
      var n = selectedIds().length;
      if (delBtn) delBtn.disabled = n === 0;
      if (selCount) selCount.textContent = n ? n + ' selected' : '';
    }

    ['wpa-media-unused-only', 'wpa-media-type', 'wpa-media-search'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener(el.tagName === 'INPUT' && el.type === 'search' ? 'input' : 'change', render);
    });
    var allCb = document.getElementById('wpa-media-all');
    if (allCb) allCb.addEventListener('change', function () {
      tbody.querySelectorAll('.wpa-media-cb').forEach(function (cb) { cb.checked = allCb.checked; });
      updateSel();
    });

    if (delBtn) delBtn.addEventListener('click', function () {
      var ids = selectedIds();
      if (!ids.length) return;
      if (!confirm('Permanently delete ' + ids.length + ' attachment(s) and all their image sizes? This cannot be undone.')) return;
      delBtn.disabled = true;
      var body = new FormData();
      body.append('action', 'wp_arzo_media_delete');
      body.append('nonce', delBtn.dataset.nonce || nonce);
      ids.forEach(function (id) { body.append('ids[]', id); });
      fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          delBtn.disabled = false;
          if (res && res.success) {
            toast('Deleted ' + res.data.deleted + ' file(s)', 'success');
            items = items.filter(function (i) { return ids.indexOf(String(i.id)) === -1; });
            render();
          } else { toast('Delete failed', 'error'); }
        })
        .catch(function () { delBtn.disabled = false; toast('Request failed', 'error'); });
    });
  }

  // -------------------------------------------------- REST API keys
  function bindRestKeys() {
    var createBtn = document.getElementById('wpa-rest-create');
    if (!createBtn) return;

    function bindRevoke(btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Revoke this key? Apps using it will stop working immediately.')) return;
        btn.disabled = true;
        apiPost('wp_arzo_rest_key_revoke', { nonce: btn.dataset.nonce || cfg.restNonce || '', id: btn.dataset.id })
          .then(function (res) {
            if (res && res.success) { toast('Key revoked', 'success'); var row = btn.closest('tr'); if (row) row.parentNode.removeChild(row); }
            else { btn.disabled = false; toast((res && res.data && res.data.message) || 'Revoke failed', 'error'); }
          })
          .catch(function () { btn.disabled = false; toast('Request failed', 'error'); });
      });
    }
    document.querySelectorAll('.wpa-rest-revoke').forEach(bindRevoke);

    createBtn.addEventListener('click', function () {
      var labelEl = document.getElementById('wpa-rest-label');
      var userEl = document.getElementById('wpa-rest-user');
      var expEl = document.getElementById('wpa-rest-expires');
      var scopeEl = document.getElementById('wpa-rest-scope');
      createBtn.disabled = true;
      apiPost('wp_arzo_rest_key_create', {
        nonce: createBtn.dataset.nonce || cfg.restNonce || '',
        label: labelEl ? labelEl.value : '',
        user_id: userEl ? userEl.value : '',
        expires_days: expEl ? expEl.value : '0',
        scope: scopeEl ? scopeEl.value : 'full'
      }).then(function (res) {
        createBtn.disabled = false;
        if (!res || !res.success) { toast((res && res.data && res.data.message) || 'Could not create key', 'error'); return; }
        var d = res.data;
        var reveal = document.getElementById('wpa-rest-reveal');
        var newkey = document.getElementById('wpa-rest-newkey');
        if (newkey) newkey.textContent = d.plain;
        if (reveal) reveal.hidden = false;
        if (labelEl) labelEl.value = '';
        // Insert a row so the new key shows in the table without losing the one-time secret.
        var tbody = document.querySelector('#wpa-rest-table tbody');
        if (tbody) {
          var emptyRow = tbody.querySelector('.wpa-backup-empty');
          if (emptyRow) emptyRow.parentNode.removeChild(emptyRow);
          var tr = document.createElement('tr');
          tr.setAttribute('data-key', d.id);
          tr.innerHTML =
            '<td><strong>' + esc(d.label) + '</strong></td>' +
            '<td><code>arzo_' + esc(d.prefix) + '…</code></td>' +
            '<td>' + (d.scope === 'read' ? '<span class="wpa-badge wpa-badge--info">Read-only</span>' : '<span class="wpa-badge wpa-badge--neutral">Full</span>') + '</td>' +
            '<td>' + esc(d.user) + '</td>' +
            '<td>' + esc(d.created) + '</td>' +
            '<td>—</td>' +
            '<td>' + (d.expires ? esc(d.expires) : 'Never') + '</td>' +
            '<td class="wpa-backup-actions"><button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--sm wpa-rest-revoke" data-id="' + esc(d.id) + '" data-nonce="' + esc(createBtn.dataset.nonce || '') + '">Revoke</button></td>';
          tbody.insertBefore(tr, tbody.firstChild);
          bindRevoke(tr.querySelector('.wpa-rest-revoke'));
        }
        toast('Key generated', 'success');
      }).catch(function () { createBtn.disabled = false; toast('Request failed', 'error'); });
    });
  }

  // -------------------------------------------------- Role manager
  function bindRoleManager() {
    var saveBtn = document.getElementById('wpa-role-save');
    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var caps = Array.prototype.slice.call(document.querySelectorAll('.wpa-cap-input:checked')).map(function (c) { return c.value; });
        saveBtn.disabled = true;
        apiPost('wp_arzo_role_save_caps', { nonce: saveBtn.dataset.nonce || cfg.rolesNonce || '', role: saveBtn.dataset.slug, caps: caps })
          .then(function (res) {
            saveBtn.disabled = false;
            toast((res && res.data && res.data.message) || (res && res.success ? 'Saved' : 'Failed'), res && res.success ? 'success' : 'error');
          })
          .catch(function () { saveBtn.disabled = false; toast('Request failed', 'error'); });
      });
    }

    var addBtn = document.getElementById('wpa-role-add');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        var name = (document.getElementById('wpa-role-name') || {}).value || '';
        var slug = (document.getElementById('wpa-role-slug') || {}).value || '';
        var clone = (document.getElementById('wpa-role-clone') || {}).value || '';
        if (!name.trim() || !slug.trim()) { toast('Name and slug are required', 'error'); return; }
        addBtn.disabled = true;
        apiPost('wp_arzo_role_add', { nonce: addBtn.dataset.nonce || cfg.rolesNonce || '', name: name, slug: slug, clone: clone })
          .then(function (res) {
            if (res && res.success) { toast('Role added', 'success'); reload(600); }
            else { addBtn.disabled = false; toast((res && res.data && res.data.message) || 'Failed', 'error'); }
          })
          .catch(function () { addBtn.disabled = false; toast('Request failed', 'error'); });
      });
    }

    document.querySelectorAll('.wpa-role-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Delete this role? Users with only this role will be left without one.')) return;
        btn.disabled = true;
        apiPost('wp_arzo_role_delete', { nonce: btn.dataset.nonce || cfg.rolesNonce || '', slug: btn.dataset.slug })
          .then(function (res) {
            if (res && res.success) { toast('Role deleted', 'success'); var row = btn.closest('tr'); if (row) row.parentNode.removeChild(row); }
            else { btn.disabled = false; toast((res && res.data && res.data.message) || 'Delete failed', 'error'); }
          })
          .catch(function () { btn.disabled = false; toast('Request failed', 'error'); });
      });
    });
  }

  // -------------------------------------------------- Config import / export
  function bindConfigIO() {
    var exportBtn = document.getElementById('wpa-config-export');
    if (exportBtn) {
      exportBtn.addEventListener('click', function () {
        exportBtn.disabled = true;
        apiPost('wp_arzo_config_export', { nonce: exportBtn.dataset.nonce || cfg.configNonce || '' })
          .then(function (res) {
            exportBtn.disabled = false;
            if (!res || !res.success) { toast((res && res.data && res.data.message) || 'Export failed', 'error'); return; }
            var blob = new Blob([res.data.json], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url; a.download = res.data.filename || 'wp-arzo-config.json';
            document.body.appendChild(a); a.click();
            document.body.removeChild(a); URL.revokeObjectURL(url);
            toast('Config exported', 'success');
          })
          .catch(function () { exportBtn.disabled = false; toast('Request failed', 'error'); });
      });
    }

    var importBtn = document.getElementById('wpa-config-import');
    if (importBtn) {
      importBtn.addEventListener('click', function () {
        var fileEl = document.getElementById('wpa-config-file');
        var msg = document.getElementById('wpa-config-msg');
        var file = fileEl && fileEl.files ? fileEl.files[0] : null;
        if (!file) { if (msg) msg.textContent = 'Choose a config file first.'; return; }
        if (!confirm('Import this config? It overwrites your current feature toggles and settings. A safety snapshot is taken first.')) return;
        importBtn.disabled = true;
        var reader = new FileReader();
        reader.onload = function () {
          apiPost('wp_arzo_config_import', { nonce: importBtn.dataset.nonce || cfg.configNonce || '', data: reader.result })
            .then(function (res) {
              if (res && res.success) {
                if (msg) msg.textContent = (res.data && res.data.message) || 'Imported.';
                toast('Config imported', 'success'); reload(1200);
              } else {
                importBtn.disabled = false;
                if (msg) msg.textContent = (res && res.data && res.data.message) || 'Import failed.';
                toast('Import failed', 'error');
              }
            })
            .catch(function () { importBtn.disabled = false; toast('Request failed', 'error'); });
        };
        reader.onerror = function () { importBtn.disabled = false; toast('Could not read the file', 'error'); };
        reader.readAsText(file);
      });
    }
  }

  // Light/dark theme toggle (brand bar). Flips the body class instantly,
  // persists per-user via AJAX. The sun/moon glyphs swap in CSS.
  function bindThemeToggle() {
    var btn = document.getElementById('wpa-theme-toggle');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
      var light = document.body.classList.toggle('wpa-theme-light');
      btn.setAttribute('aria-pressed', light ? 'true' : 'false');
      apiPost('wp_arzo_set_theme', { nonce: cfg.nonce, theme: light ? 'light' : 'dark' })
        .catch(function () { toast('Could not save the theme preference', 'error'); });
    });
  }

  function init() { bindThemeToggle(); bindToggles(); bindGroupToggles(); bindSearch(); bindCategoryFilter(); bindSettingsConditionals(); bindSettingsDrawer(); bindSmtpPresets(); bindEmailConnections(); bindEmailOnboarding(); bindBackups(); bindEmailLog(); bindActivityLog(); bindLicense(); bindSnippets(); bindEmailExtras(); bindMediaCleanup(); bindRestKeys(); bindRoleManager(); bindConfigIO(); }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
