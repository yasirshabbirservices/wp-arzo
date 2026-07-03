/**
 * WP Arzo — Analytics beacon (cookieless, privacy-first).
 *
 * Sends a single lightweight page-hit to the WP Arzo collector. No cookies, no
 * localStorage, no personal data — the server derives a daily-rotating salted
 * visitor hash and discards the IP. Honors Do-Not-Track client-side too.
 */
(function () {
    'use strict';

    try {
        var cfg = window.wpArzoAnalytics;
        if (!cfg || !cfg.endpoint) {
            return;
        }

        // Client-side Do-Not-Track courtesy check (server enforces its own policy).
        var dnt = navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack;
        if (dnt === '1' || dnt === 'yes') {
            return;
        }

        // Skip obvious non-visitors (headless/prerender).
        if (navigator.webdriver) {
            return;
        }

        var payload = JSON.stringify({
            p: location.pathname + location.search,
            t: document.title || '',
            r: document.referrer || '',
            f: cfg.is404 ? 1 : 0,
            s: cfg.search || ''
        });

        var sent = false;
        if (navigator.sendBeacon) {
            // sendBeacon posts as text/plain; the collector reads the raw body.
            sent = navigator.sendBeacon(cfg.endpoint, payload);
        }
        if (!sent) {
            fetch(cfg.endpoint, {
                method: 'POST',
                body: payload,
                keepalive: true,
                credentials: 'same-origin',
                headers: { 'Content-Type': 'text/plain' }
            }).catch(function () {});
        }
    } catch (e) {
        /* never break the page over analytics */
    }
})();
