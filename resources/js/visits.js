/**
 * Visits JS beacon — for SPA route changes and client-side custom events that the plain
 * server-side middleware can't see. No build step: include directly or via vendor:publish.
 *
 * Usage:
 *   Visits.trackPageView();               // call on every SPA route change
 *   Visits.track('newsletter.subscribed', { plan: 'pro' });
 *
 * Token handling: visitor_token is read from localStorage first (client-controlled — the
 * reason this exists at all is cross-origin/cookie-unreliable setups), sent as
 * X-Visitor-Token so it takes priority over any cookie on the server side. The server's JSON
 * response always includes visitor_token, which is what gets persisted here.
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'visits_token';
    var config = window.VisitsConfig || {};
    var endpoint = config.endpoint || '/visits/collect';

    function getToken() {
        try {
            return window.localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function setToken(token) {
        try {
            if (token) {
                window.localStorage.setItem(STORAGE_KEY, token);
            }
        } catch (e) {
            // localStorage unavailable (private mode, disabled) — falls back to whatever
            // cookie the server-side middleware manages instead.
        }
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : null;
    }

    function send(payload) {
        var headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
        var token = getToken();
        var csrf = csrfToken();

        if (token) {
            headers['X-Visitor-Token'] = token;
        }

        if (csrf) {
            headers['X-CSRF-TOKEN'] = csrf;
        }

        return fetch(endpoint, {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.ok ? res.json() : null;
            })
            .then(function (data) {
                if (data && data.visitor_token) {
                    setToken(data.visitor_token);
                }

                return data;
            })
            .catch(function () {
                // fire-and-forget by design — a failed beacon must never break the page
            });
    }

    function trackPageView(url) {
        return send({ type: 'page_view', url: url || window.location.href });
    }

    function track(name, meta) {
        return send({ type: 'action', name: name, url: window.location.href, meta: meta || null });
    }

    window.Visits = { track: track, trackPageView: trackPageView };

    if (config.autoTrackPageView !== false) {
        if (document.readyState === 'complete') {
            trackPageView();
        } else {
            window.addEventListener('load', function () {
                trackPageView();
            });
        }
    }
})(window, document);
