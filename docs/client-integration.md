# Client Integration Scenarios

Deeper detail for setups beyond the common same-origin Blade app — see the main [README](../README.md) for installation, the basic JS beacon, and everything else.

## API-only backends, decoupled SPAs & mobile apps

`POST /visits/collect` runs under the `web` middleware group by default, which brings CSRF/session along — fine for a same-origin Blade app, but it will 419 for a token-authenticated API, a SPA on a different origin, or a native mobile app that has no session at all. Since the identity mechanism is already cookie-free-capable (client-supplied `X-Visitor-Token` in, `visitor_token` in the JSON body out), the only thing actually in the way is that hardcoded `web` group — swap it in config:

```php
// config/visits.php
'collect' => [
    'middleware' => ['api'], // or your own stack — no session/CSRF needed
],
```

`TrackVisit`'s automatic tracking only fires on `GET` requests through the `web` group, so a pure API backend has no page views to auto-track — everything goes through `POST /visits/collect` (client-initiated: a mobile "screen view", a SPA route change) or `Visits::track()` (server-initiated, from inside an API controller action) instead. Note that without the `web` group, `Cookie::queue()` calls are never flushed to the response (no `AddQueuedCookiesToResponse` middleware) — that's expected here, not a bug: the client is expected to persist `visitor_token` itself and always send it back, exactly like the mobile example below.

Mobile / non-browser client, first call (no token yet):

```http
POST /visits/collect
Accept: application/json
Content-Type: application/json

{"type": "page_view", "url": "app://home"}
```

```json
{ "visitor_token": "aBc123..." }
```

Persist `visitor_token` (Keychain/EncryptedSharedPreferences, or `localStorage` for a web SPA) and send it back on every subsequent call:

```http
POST /visits/collect
X-Visitor-Token: aBc123...
Content-Type: application/json

{"type": "action", "name": "order.placed", "meta": {"amount": 42}}
```

## Mixed web + API app

If the same backend serves both a Blade/session web app and a decoupled SPA/mobile API (e.g. a storefront with a web checkout *and* a mobile app hitting the same backend), swapping `collect.middleware` to `['api']` would lose session/cookie continuity for the web side. Keep it as `['web']` and instead exclude the route from CSRF validation — the token flow doesn't need CSRF protection either way, and the rest of the `web` group (session, cookies) still benefits browser traffic:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'visits/collect',
    ]);
})
```

One route now serves both: the web beacon still gets a cookie (`web` group's `AddQueuedCookiesToResponse`), a mobile/SPA client just never has one and relies entirely on `X-Visitor-Token` — exactly as in the API-only example above.

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
   'allowed_headers' => ['Content-Type', 'X-Visitor-Token', 'Accept'],
   ```

A cookie set by the API is useless to a page on a different origin anyway (and `visits.js` deliberately fetches with `credentials: 'same-origin'`, so it won't even try) — identity relies entirely on `localStorage` + `X-Visitor-Token`, exactly the mechanism above.

Separately, `visits.collect.allowed_origins` (`null` by default) lets the *server* reject requests whose `Origin`/`Referer` isn't in an allowlist, regardless of client. This is not a replacement for CORS (CORS is what a *browser* permits; this is what the *server* accepts) and both headers are trivially spoofed by any non-browser client — it only filters casual/accidental misuse, such as a copy of the beacon left on a decommissioned domain.
