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

  function init() { bindToggles(); bindSearch(); }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
