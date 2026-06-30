/**
 * WP Arzo admin dashboard behavior — feature toggles + live search.
 * Depends on wp-arzo-components.js (custom selects, toast) and wpArzoAdmin (localized).
 */
(function () {
  'use strict';

  var cfg = window.wpArzoAdmin || {};
  var toast = (window.wpArzo && window.wpArzo.toast) ? window.wpArzo.toast : function (m) { console.log(m); };

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
          })
          .catch(function () {
            input.disabled = false;
            input.checked = !enabled;
            toast('Request failed', 'error');
          });
      });
    });
  }

  // -------------------------------------------------- Live search
  function bindSearch() {
    var box = document.getElementById('wpa-feature-search');
    if (!box) return;
    var empty = document.getElementById('wpa-no-results');

    box.addEventListener('input', function () {
      var q = box.value.trim().toLowerCase();
      var anyVisible = false;

      document.querySelectorAll('.wpa-group').forEach(function (group) {
        var groupHasMatch = false;
        group.querySelectorAll('[data-feature-card]').forEach(function (card) {
          var match = !q || (card.dataset.search || '').indexOf(q) !== -1;
          card.hidden = !match;
          if (match) { groupHasMatch = true; anyVisible = true; }
        });
        group.hidden = !groupHasMatch;
      });

      if (empty) empty.hidden = anyVisible;
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

  function init() { bindToggles(); bindSearch(); bindBackups(); bindEmailLog(); }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
