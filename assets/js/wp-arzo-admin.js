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
            toast(enabled ? 'Feature enabled' : 'Feature disabled', 'success');
            // This feature owns a dedicated admin page — reload so the menu/tabs
            // reflect the new visibility.
            if (res.data && res.data.ownsPage) { reload(700); }
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

  function applyFeatureFilters() {
    if (!document.getElementById('wpa-feature-grid')) return;
    var empty = document.getElementById('wpa-no-results');
    var anyVisible = false;

    document.querySelectorAll('.wpa-group').forEach(function (group) {
      var inCat = featureCat === '*' || group.getAttribute('data-group') === featureCat;
      var groupHasMatch = false;
      group.querySelectorAll('[data-feature-card]').forEach(function (card) {
        var match = inCat && (!featureQuery || (card.dataset.search || '').indexOf(featureQuery) !== -1);
        card.hidden = !match;
        if (match) { groupHasMatch = true; anyVisible = true; }
      });
      group.hidden = !groupHasMatch;
    });

    if (empty) empty.hidden = anyVisible;
  }

  function bindSearch() {
    var box = document.getElementById('wpa-feature-search');
    if (!box) return;
    box.addEventListener('input', function () {
      featureQuery = box.value.trim().toLowerCase();
      applyFeatureFilters();
    });
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
    Object.keys(fields || {}).forEach(function (k) { body.append(k, fields[k]); });
    return fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function bindBackups() {
    var createBtn = document.getElementById('wpa-backup-create');
    if (!createBtn) return;

    createBtn.addEventListener('click', function () {
      var scopeEl = document.getElementById('wpa-backup-scope');
      var scope = scopeEl ? scopeEl.value : 'options';
      createBtn.disabled = true;
      toast('Creating snapshot…', 'info');
      backupRequest('wp_arzo_backup_create', { scope: scope })
        .then(function (res) {
          createBtn.disabled = false;
          if (res && res.success) { toast('Snapshot created', 'success'); reload(700); }
          else { toast((res && res.data && res.data.message) || 'Backup failed', 'error'); }
        })
        .catch(function () { createBtn.disabled = false; toast('Request failed', 'error'); });
    });

    document.querySelectorAll('.wpa-backup-restore').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Restore this snapshot? A safety snapshot of the current state is taken first.')) return;
        btn.disabled = true;
        toast('Restoring…', 'info');
        backupRequest('wp_arzo_backup_restore', { id: btn.dataset.id })
          .then(function (res) {
            if (res && res.success) { toast('Snapshot restored', 'success'); reload(900); }
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
    var btn = document.getElementById('wpa-email-clear');
    if (!btn) return;
    btn.addEventListener('click', function () {
      if (!confirm('Clear the entire email log?')) return;
      btn.disabled = true;
      var body = new FormData();
      body.append('action', 'wp_arzo_email_log_clear');
      body.append('nonce', btn.dataset.nonce || '');
      fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success) { toast('Email log cleared', 'success'); reload(600); }
          else { btn.disabled = false; toast('Could not clear log', 'error'); }
        })
        .catch(function () { btn.disabled = false; toast('Request failed', 'error'); });
    });
  }

  // -------------------------------------------------- Activity log
  function bindActivityLog() {
    var btn = document.getElementById('wpa-activity-clear');
    if (!btn) return;
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
            if (res && res.success) { toast('Snippet deleted', 'success'); var row = btn.closest('tr'); if (row) row.parentNode.removeChild(row); }
            else { btn.disabled = false; toast('Delete failed', 'error'); }
          })
          .catch(function () { btn.disabled = false; toast('Request failed', 'error'); });
      });
    });
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
      createBtn.disabled = true;
      apiPost('wp_arzo_rest_key_create', {
        nonce: createBtn.dataset.nonce || cfg.restNonce || '',
        label: labelEl ? labelEl.value : '',
        user_id: userEl ? userEl.value : ''
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
            '<td>' + esc(d.user) + '</td>' +
            '<td>' + esc(d.created) + '</td>' +
            '<td>—</td>' +
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

  function init() { bindToggles(); bindGroupToggles(); bindSearch(); bindCategoryFilter(); bindSettingsConditionals(); bindSmtpPresets(); bindBackups(); bindEmailLog(); bindActivityLog(); bindLicense(); bindSnippets(); bindEmailExtras(); bindMediaCleanup(); bindRestKeys(); bindRoleManager(); bindConfigIO(); }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
