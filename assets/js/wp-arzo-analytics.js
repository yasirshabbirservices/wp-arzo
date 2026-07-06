/**
 * WP Arzo — Analytics beacon (cookieless, privacy-first).
 *
 * Sends a single lightweight page-hit to the WP Arzo collector, plus — when the
 * server enables it (WP Arzo Pro event tracking) — outbound / download / email /
 * form / custom interaction events via delegated listeners. No cookies, no
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

        // Shared transport: sendBeacon (text/plain) with a keepalive fetch fallback.
        function send(payload) {
            var sent = false;
            if (navigator.sendBeacon) {
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
        }

        // ---- Page hit -----------------------------------------------------
        send(JSON.stringify({
            p: location.pathname + location.search,
            t: document.title || '',
            r: document.referrer || '',
            f: cfg.is404 ? 1 : 0,
            s: cfg.search || ''
        }));

        // ---- Interaction events (opt-in, driven by server rules) ----------
        var ev = cfg.events || {};
        var selectors = Array.isArray(ev.selectors) ? ev.selectors : [];
        var wantAuto = ev.outbound || ev.downloads || ev.email;
        var wantAttr = true; // data-wpa-track always works once any tracking is on
        var trackingOn = wantAuto || ev.forms || selectors.length;
        if (!trackingOn) {
            return;
        }

        var exts = String(ev.exts || 'pdf,zip,doc,docx,xls,xlsx,ppt,pptx,csv,mp3,mp4,rar,gz,dmg,exe,pkg')
            .split(',').map(function (s) { return s.trim().toLowerCase(); }).filter(Boolean);

        function sendEvent(type, name, target) {
            send(JSON.stringify({
                k: 'e',
                e: type,
                n: String(name || '').slice(0, 190),
                p: location.pathname + location.search,
                u: String(target || '').slice(0, 255)
            }));
        }

        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) {
                return;
            }

            // 1) Explicit no-code marker: data-wpa-track="Event name"
            if (wantAttr) {
                var marked = t.closest('[data-wpa-track]');
                if (marked) {
                    var label = marked.getAttribute('data-wpa-track') || 'Custom event';
                    sendEvent('custom', label, marked.getAttribute('href') || marked.getAttribute('data-wpa-target') || '');
                    return;
                }
            }

            // 2) Configured custom selectors (first match wins)
            for (var i = 0; i < selectors.length; i++) {
                var rule = selectors[i];
                if (!rule || !rule.sel) { continue; }
                var hit;
                try { hit = t.closest(rule.sel); } catch (_) { hit = null; }
                if (hit) {
                    sendEvent(rule.type || 'custom', rule.name || rule.sel, hit.getAttribute('href') || rule.sel);
                    return;
                }
            }

            // 3) Automatic anchor-based tracking
            if (!wantAuto) { return; }
            var a = t.closest('a[href]');
            if (!a) { return; }
            var href = a.getAttribute('href') || '';

            if (/^mailto:/i.test(href)) {
                if (ev.email) { sendEvent('mailto', href.replace(/^mailto:/i, ''), href); }
                return;
            }
            if (/^tel:/i.test(href)) {
                if (ev.email) { sendEvent('tel', href.replace(/^tel:/i, ''), href); }
                return;
            }

            var url;
            try { url = new URL(a.href, location.href); } catch (_) { return; }
            if (!/^https?:$/.test(url.protocol)) { return; }

            var ext = (url.pathname.split('.').pop() || '').toLowerCase();
            if (ev.downloads && url.pathname.indexOf('.') !== -1 && exts.indexOf(ext) !== -1) {
                sendEvent('download', url.pathname.split('/').pop() || url.pathname, url.href);
                return;
            }
            if (ev.outbound && url.host && url.host !== location.host) {
                sendEvent('outbound', url.host, url.href);
            }
        }, true);

        if (ev.forms) {
            document.addEventListener('submit', function (e) {
                var f = e.target;
                if (!f || f.tagName !== 'FORM') { return; }
                var name = f.getAttribute('name') || f.getAttribute('id') || f.getAttribute('action') || 'form';
                sendEvent('form', name, f.getAttribute('action') || location.pathname);
            }, true);
        }
    } catch (e) {
        /* never break the page over analytics */
    }
})();
