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
			className: 'dashicons dashicons-' + name,
			style: { fontSize: '20px', width: '20px', height: '20px' },
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
	});
})(window.wp);
