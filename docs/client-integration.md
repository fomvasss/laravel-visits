# Client Integration Scenarios

Deeper detail for setups beyond the common same-origin Blade app — see the main [README](../README.md) for installation, the basic JS beacon, and everything else.

## Skipping the JS beacon entirely

`visits.js` is a thin convenience wrapper around `POST /visits/collect` — nothing on the server requires it specifically. Any HTTP client (`fetch`, `axios`, a mobile HTTP client, `curl`) can call the endpoint directly with the same JSON body; every raw-HTTP example in this doc works with no JS file involved at all.

Replicate three things yourself if you skip it:

- **Persist `visitor_id`** from the response (`{"visitor_id": "..."}`) and send it back as the `X-Visitor-Id` header on every subsequent call — without it, each request with no token/cookie creates a brand new `Visitor`.
- **Send the CSRF header** (`X-CSRF-TOKEN`) if the route is still under the `web` middleware group — not needed once `collect.middleware` is swapped away from `web` (see below).
- **Match the expected payload shape**: `{"type": "page_view"|"action", "name": "...", "url": "...", "meta": {...}}` (validated in `CollectController`).

What the beacon adds on top, purely for convenience: the `VisitsQueue` buffer, automatic page-view tracking on page `load`, and silently swallowing failed requests so a network error never breaks the page.

## Full-page HTTP caching (nginx fastcgi_cache/proxy_cache)

If a page is served from an nginx page cache — a product/catalog page cached for minutes or hours, for example — most real visits to it never reach PHP at all, and that breaks the automatic `TrackVisit` middleware in a way that isn't obvious from the app side.

**`TrackVisit` only runs on a cache MISS.** A cache HIT is served by nginx directly; Laravel, and therefore `TrackVisit`, never executes. For a heavily-cached page, this silently undercounts page views — not a bug, just PHP never being in the request path for most visits.

**A cached `Set-Cookie` is a real correctness risk, not just an undercount.** `TrackVisit` sets the visitor cookie on a cache MISS response. If nginx caches that response *including* the `Set-Cookie` header (the default unless explicitly stripped), the visitor token belonging to whoever happened to trigger the cache fill gets baked into the cached page and handed to every other visitor until the cache expires — merging unrelated people into one `Visitor`. Make sure the cache config ignores it:

```nginx
fastcgi_ignore_headers Set-Cookie;
# or, behind proxy_pass instead of fastcgi_pass:
proxy_ignore_headers Set-Cookie;
```

**Use the JS beacon for cached pages instead of relying on the middleware.** `visits.js` runs in the browser regardless of whether the HTML it's embedded in came from cache or origin, and `POST /visits/collect` is a separate, uncached request (nginx page caches only ever cache `GET`/`HEAD` by default). It also resolves identity from `localStorage` first, not the cookie — so even a stale cached `Set-Cookie` doesn't corrupt tracking through this path. Call `Visits.trackPageView()` explicitly (or let the beacon's automatic `load`-event tracking handle it) instead of counting on `TrackVisit` for these routes.

**A cached CSRF meta tag breaks the beacon too, silently.** `csrf-token` in `<head>` is per-session. If it's baked into a cached page, most visitors' browsers send a token that doesn't match their own session, `POST /visits/collect` gets a `419`, and the beacon swallows the failure by design — tracking just stops with no visible error. Since the collect endpoint doesn't actually need CSRF protection (identity comes from `visitor_id`, not the session), exclude it the same way as the [mixed web + API](#mixed-web--api-app) case below, regardless of what the rest of the app does with CSRF:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'visits/collect',
    ]);
})
```

## API-only backends, decoupled SPAs & mobile apps

`POST /visits/collect` runs under the `web` middleware group by default, which brings CSRF/session along — fine for a same-origin Blade app, but it will 419 for a token-authenticated API, a SPA on a different origin, or a native mobile app that has no session at all. Since the identity mechanism is already cookie-free-capable (client-supplied `X-Visitor-Id` in, `visitor_id` in the JSON body out), the only thing actually in the way is that hardcoded `web` group — swap it in config:

```php
// config/visits.php
'collect' => [
    'middleware' => ['api'], // or your own stack — no session/CSRF needed
],
```

`TrackVisit`'s automatic tracking only fires on `GET` requests through the `web` group, so a pure API backend has no page views to auto-track — everything goes through `POST /visits/collect` (client-initiated: a mobile "screen view", a SPA route change) or `Visits::track()` (server-initiated, from inside an API controller action) instead. Note that without the `web` group, `Cookie::queue()` calls are never flushed to the response (no `AddQueuedCookiesToResponse` middleware) — that's expected here, not a bug: the client is expected to persist `visitor_id` itself and always send it back, exactly like the mobile example below.

Mobile / non-browser client, first call (no id yet):

```http
POST /visits/collect
Accept: application/json
Content-Type: application/json

{"type": "page_view", "url": "app://home"}
```

```json
{ "visitor_id": "aBc123..." }
```

Persist `visitor_id` (Keychain/EncryptedSharedPreferences, or `localStorage` for a web SPA) and send it back on every subsequent call:

```http
POST /visits/collect
X-Visitor-Id: aBc123...
Content-Type: application/json

{"type": "action", "name": "order.placed", "meta": {"amount": 42}}
```

## Handing off an identifier from another system (landing page, legacy `anon_id`)

If some other system already assigns a visitor identifier before the package ever sees the request — a separate landing page, a legacy tracker's `anon_id`, a mobile SDK's own device ID — that identifier can be handed straight to `TokenResolver` instead of letting it generate a fresh one, so the two stay linked as the same `Visitor` from the very first hit.

The simplest case: the landing page links to the main site with the existing ID as a query param, no JS involved on the receiving end. `TokenResolver` reads `visitor_id` via `$request->input()`, which covers the query string on a plain `GET`:

```
https://landing.example.com  →  https://main-site.example.com/?visitor_id=<anon_id>
```

`TrackVisit`'s first hit on `main-site.example.com` picks it up, and the response cookie carries it forward for every page after — the query param is only needed on that one handoff link.

If the landing page and main site share a registrable domain (`landing.example.com` / `example.com`), an alternative avoids the URL param entirely: have the landing page set the package's own cookie name (`visits.cookie.name`) with `Domain=.example.com` directly — the main site then picks it up through the normal cookie fallback, no query string required.

**Format constraint:** the incoming value must match `visits.visitor_id.format_regex` (default `^[a-zA-Z0-9]{20,64}$` — no dashes, no underscores) or it's silently ignored and a fresh id is generated instead. A UUID-shaped `anon_id` (`550e8400-e29b-...`) fails this by default — either strip the dashes before handing it off, or widen the regex:

```php
// config/visits.php
'visitor_id' => [
    'format_regex' => '/^[a-zA-Z0-9-]{20,64}$/', // allow UUIDs
],
```

The regex exists as more than a style choice — the value ends up in a DB column, a cookie and (in this scenario) a URL, so it also bounds length/charset against garbage or oversized input. Widen it deliberately, not by removing the constraint altogether.

Same trust caveat as the header/body id flow above: a client-suppliable identifier is not authenticated — fine for attribution continuity, not a security boundary.

## Mixed web + API app

If the same backend serves both a Blade/session web app and a decoupled SPA/mobile API (e.g. a storefront with a web checkout *and* a mobile app hitting the same backend), swapping `collect.middleware` to `['api']` would lose session/cookie continuity for the web side. Keep it as `['web']` and instead exclude the route from CSRF validation — the id flow doesn't need CSRF protection either way, and the rest of the `web` group (session, cookies) still benefits browser traffic:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'visits/collect',
    ]);
})
```

One route now serves both: the web beacon still gets a cookie (`web` group's `AddQueuedCookiesToResponse`), a mobile/SPA client just never has one and relies entirely on `X-Visitor-Id` — exactly as in the API-only example above.

## Cross-origin frontend (different domain than the API)

Two things are required beyond what's above, or the beacon silently talks to the wrong host or gets blocked by the browser:

1. **`endpoint` must be an absolute URL.** The default (`/visits/collect`) is relative to whatever page the script runs on — fine when the frontend and API share an origin, wrong host entirely otherwise:

   ```html
   <script>
     window.VisitsConfig = { endpoint: 'https://api.example.com/visits/collect' };
   </script>
   ```

2. **CORS must be configured on the API side** — this is the host app's own `config/cors.php`, not a `visits.*` setting. `Content-Type: application/json` makes this a "non-simple" request, so the browser sends a preflight `OPTIONS` first:

   ```php
   // config/cors.php
   'paths' => ['visits/collect', /* ... */],
   'allowed_origins' => ['https://app.example.com'],
   'allowed_headers' => ['Content-Type', 'X-Visitor-Id', 'Accept'],
   ```

A cookie set by the API is useless to a page on a different origin anyway (and `visits.js` deliberately fetches with `credentials: 'same-origin'`, so it won't even try) — identity relies entirely on `localStorage` + `X-Visitor-Id`, exactly the mechanism above.

Separately, `visits.collect.allowed_origins` (`null` by default) lets the *server* reject requests whose `Origin`/`Referer` isn't in an allowlist, regardless of client. This is not a replacement for CORS (CORS is what a *browser* permits; this is what the *server* accepts) and both headers are trivially spoofed by any non-browser client — it only filters casual/accidental misuse, such as a copy of the beacon left on a decommissioned domain.
