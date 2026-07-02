/**
 * WP Arzo → WordPress command palette bridge.
 *
 * Registers WP Arzo destinations (feature pages, Settings tabs, console tools)
 * into WordPress's native command palette — the store behind the admin-bar
 * Ctrl/⌘-K node (`core/commands`, WP 6.3+). We don't build our own overlay: the
 * user opens the one palette they already know and finds WP Arzo alongside core
 * commands. Data comes from `window.wpArzoCommands` (localized in PHP).
 *
 * Deps (enqueued in PHP): wp-commands, wp-data, wp-element, wp-dom-ready.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.data || !wp.domReady) {
		return;
	}

	var payload = window.wpArzoCommands || {};
	var commands = payload.commands || [];
	if (!commands.length) {
		return;
	}

	var createElement = wp.element && wp.element.createElement;

	// Render a dashicon as the command icon. The palette's icon slot expects an
	// element; a dashicon <span> renders crisply and avoids bundling SVGs into JS.
	function dashicon(name) {
		if (!createElement || !name) {
			return undefined;
		}
		return createElement('span', {
			// Sizing + brand-accent color live in CSS (var(--arzo-accent)) — see
			// enqueue_command_palette(); no inline styles, tokens only.
			className: 'dashicons dashicons-' + name + ' wpa-cmd-icon',
			'aria-hidden': 'true',
		});
	}

	wp.domReady(function () {
		var store = wp.data.dispatch('core/commands');
		if (!store || typeof store.registerCommand !== 'function') {
			return;
		}

		commands.forEach(function (cmd) {
			if (!cmd || !cmd.id || !cmd.url) {
				return;
			}
			store.registerCommand({
				name: 'wp-arzo/' + cmd.id,
				label: cmd.label || cmd.id,
				searchLabel: (payload.group || 'WP Arzo') + ' ' + (cmd.label || cmd.id),
				icon: dashicon(cmd.icon),
				callback: function (args) {
					if (args && typeof args.close === 'function') {
						args.close();
					}
					if (cmd.newTab) {
						window.open(cmd.url, '_blank', 'noopener');
					} else {
						window.location.href = cmd.url;
					}
				},
			});
		});

		// Theme toggle — flips the body class live and persists per user.
		if (payload.themeNonce && payload.ajaxUrl) {
			store.registerCommand({
				name: 'wp-arzo/toggle-theme',
				label: payload.themeLabel || 'WP Arzo: toggle light / dark theme',
				searchLabel: (payload.group || 'WP Arzo') + ' theme light dark toggle',
				icon: dashicon('lightbulb'),
				callback: function (args) {
					if (args && typeof args.close === 'function') {
						args.close();
					}
					var light = document.body.classList.toggle('wpa-theme-light');
					var btn = document.getElementById('wpa-theme-toggle');
					if (btn) {
						btn.setAttribute('aria-pressed', light ? 'true' : 'false');
					}
					var body = new FormData();
					body.append('action', 'wp_arzo_set_theme');
					body.append('nonce', payload.themeNonce);
					body.append('theme', light ? 'light' : 'dark');
					window.fetch(payload.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' });
				},
			});
		}
	});
})(window.wp);
