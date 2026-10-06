# Client integration scenarios

Setups beyond a same-origin Blade app: calling the endpoint without the JS beacon, full-page caches, API-only backends, mobile apps, identifiers from other systems, and frontends on another origin. Basics of the beacon: [JS beacon](../usage/js-beacon.md).

## Skipping the JS beacon

`visits.js` is a thin wrapper around `POST /visits/collect`. Any HTTP client — `fetch`, axios, a mobile HTTP client, `curl` — can call the endpoint directly. Do three things yourself:

- **Keep the visitor id.** Store `visitor_id` from the response (`{"visitor_id": "..."}`) and send it back as the `X-Visitor-Id` header (or a `visitor_id` body field) on every later call. Without it, every call without a cookie creates a new visitor.
- **Send the CSRF header** (`X-CSRF-TOKEN`) while the route runs under the `web` group — not needed once `collect.middleware` no longer includes `web`, or the route is excluded from CSRF (below).
- **Send the expected body**, validated by `CollectController`:

| Field | Rules |
|---|---|
| `type` | `page_view` (default) or `action` |
| `name` | string up to 255 characters; required when `type` is `action` |
| `url` | string up to 2048 characters; stored as the event URL and path. Always send it: when omitted, the collect URL itself is stored and `route_name` becomes `visits.collect` |
| `meta` | object |

Responses: `200 {"visitor_id": "..."}`, `403 {"visitor_id": null}` when `collect.allowed_origins` rejects the origin, `422` for invalid fields, `429` from the throttle, `200 {"visitor_id": null}` when `visits.enabled` is off.

UTM parameters and click IDs are read from the collect request's own query string, not from the body — append them to the endpoint URL to attribute a session: `POST /visits/collect?utm_source=newsletter`.

What the beacon adds on top: the `VisitsQueue` buffer, the automatic page view on `load`, and swallowing failed requests so tracking never breaks the page.

## Full-page HTTP caching

If pages are served from an nginx page cache (`fastcgi_cache`, `proxy_cache`), most visits never reach PHP.

**`TrackVisit` runs only on a cache miss.** A cache hit is served by nginx; Laravel never runs. Heavily cached pages are undercounted.

**A cached `Set-Cookie` merges visitors.** `TrackVisit` sets the visitor cookie on the miss response. If nginx caches that response with its `Set-Cookie` header (the default unless told otherwise), the visitor id of whoever filled the cache is served to everyone who gets the cached page, until it expires — unrelated people become one visitor. Strip it:

```nginx
fastcgi_ignore_headers Set-Cookie;
# or behind proxy_pass:
proxy_ignore_headers Set-Cookie;
```

**Use the beacon for cached pages.** `visits.js` runs in the browser whether the HTML came from the cache or not, and `POST /visits/collect` is never cached (page caches only cache `GET`/`HEAD` by default). The beacon sends its `localStorage` id as `X-Visitor-Id`, which beats any cookie, so even a stale cached cookie doesn't corrupt tracking on this path. Call `Visits.trackPageView()` or keep `autoTrackPageView` on, and exclude these routes from `TrackVisit` (`exclude_paths`, or `auto_track = false`).

**A cached CSRF token breaks the beacon silently.** The `csrf-token` meta tag is per session. Baked into a cached page, it doesn't match most visitors' sessions, the collect request gets `419`, and the beacon swallows the error. The endpoint doesn't need CSRF protection — identity comes from the visitor id, not the session — so exclude it:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'visits/collect',
    ]);
})
```

## API-only backends, decoupled SPAs and mobile apps

`POST /visits/collect` runs under `web` by default: session and CSRF, so a token-authenticated API, an SPA on another origin or a native app without a session gets `419`. The identity flow already works without cookies (`X-Visitor-Id` in, `visitor_id` out), so swap the middleware:

```php
// config/visits.php
'collect' => [
    'middleware' => ['api'],
],
```

A pure API backend has no page views for `TrackVisit` — page views and screens come from the client through `POST /visits/collect`, conversions from `Visits::track()` in your API controllers. Without the `web` group no cookie is written to the response (no `AddQueuedCookiesToResponse`): the client keeps the id itself.

First call from a mobile app, no id yet:

```http
POST /visits/collect
Accept: application/json
Content-Type: application/json

{"type": "page_view", "url": "app://home"}
```

```json
{"visitor_id": "7d0c3b1e-4f7e-4a8e-9a43-2a4f5b8c1d20"}
```

Store the id (Keychain, EncryptedSharedPreferences, `localStorage` for a web SPA) and send it on every later call:

```http
POST /visits/collect
Accept: application/json
Content-Type: application/json
X-Visitor-Id: 7d0c3b1e-4f7e-4a8e-9a43-2a4f5b8c1d20

{"type": "action", "name": "order.placed", "meta": {"amount": 42}}
```

To connect server-side `Visits::track()` calls of logged-in users to the same visitor, use `HasVisits` on the user model — see [Logged-in users on Bearer-token APIs](../usage/identity.md#logged-in-users-on-bearer-token-apis). Or have the client send its `X-Visitor-Id` header to your API too: the header wins over everything else in `Visits::track()` as well.

## Mixed web and API app

When one backend serves both a Blade web app and an SPA or mobile API, switching `collect.middleware` to `['api']` would drop the cookie for the web side. Keep `['web']` and exclude the route from CSRF instead (same snippet as for cached pages above). The web beacon still gets its cookie through the `web` group; mobile and SPA clients rely on `X-Visitor-Id`.

## Handing off an identifier from another system

When another system assigns a visitor id first — a separate landing page, a legacy tracker's `anon_id`, a mobile SDK's device id — pass it to the package so both stay one visitor from the first hit.

The simplest way is a query parameter on the link to the main site. `visitor_id` is read with `$request->input()`, which includes the query string:

```
https://landing.example.com  →  https://example.com/?visitor_id=7d0c3b1e-4f7e-4a8e-9a43-2a4f5b8c1d20
```

`TrackVisit` picks it up on the first hit and the cookie carries it on; the parameter is needed only on that one link.

**The id must match `visits.visitor_id.format_regex`** — a UUID by default — or it is ignored and a new id is generated. A UUID passes as is. For any other format, change the regex or the [token resolver](../usage/identity.md#custom-token-resolver):

```php
// config/visits.php
'visitor_id' => [
    'format_regex' => '/^[A-Za-z0-9_-]{20,64}$/',
],
```

Keep a bound on length and characters — the value ends up in a 64-character column, a cookie and URLs.

**Sharing the cookie across subdomains.** If the landing page and the main site share a registrable domain, the landing page can set the package's cookie itself (`visits_visitor_id`, `Domain=.example.com`). Laravel's `EncryptCookies` discards any cookie it cannot decrypt, so a cookie written by another system is ignored unless its name is excluded from encryption:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->encryptCookies(except: ['visits_visitor_id']);
})
```

With that exception the package's own cookie is stored unencrypted too. The cookie set by the package has no `Domain` attribute — it stays on the host that set it.

Either way the id is not authenticated: fine for attribution continuity, not a security boundary.

## Frontend on another origin

Two things beyond the API-only setup, or the beacon talks to the wrong host or gets blocked:

1. **`endpoint` must be absolute.** The default `/visits/collect` is relative to the page:

   ```html
   <script>
       window.VisitsConfig = { endpoint: 'https://api.example.com/visits/collect' };
   </script>
   ```

2. **CORS on the API** — the app's own `config/cors.php`, not a `visits.*` key. `Content-Type: application/json` makes it a non-simple request with a preflight `OPTIONS`:

   ```php
   // config/cors.php
   'paths' => ['api/*', 'visits/collect'],
   'allowed_origins' => ['https://app.example.com'],
   'allowed_headers' => ['Content-Type', 'Accept', 'X-Visitor-Id', 'X-CSRF-TOKEN'],
   ```

   `X-CSRF-TOKEN` is needed only if the SPA page has a `csrf-token` meta tag — the beacon sends the header whenever it finds one.

A cookie set by the API is useless to a page on another origin, and `visits.js` fetches with `credentials: 'same-origin'`, so it doesn't try — identity rests on `localStorage` and `X-Visitor-Id`.

Separately, `visits.collect.allowed_origins` (default `null`) lets the server reject requests whose `Origin` (or `Referer` scheme and host) is not listed:

```php
'collect' => [
    'middleware' => ['api'],
    'allowed_origins' => ['https://app.example.com'],
],
```

This is not CORS — CORS is what a browser permits, this is what the server accepts — and both headers are trivially forged by non-browser clients. It filters casual misuse, such as a copy of the beacon left on an old domain. A request with neither header is rejected once the list is set.
