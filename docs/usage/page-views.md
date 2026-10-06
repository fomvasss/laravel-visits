# Page views

The `TrackVisit` middleware records a `page_view` event for server-rendered pages. Which entry point fits depends on what is on the other end:

| Application | Page views | Custom actions |
|---|---|---|
| Blade / server-rendered site | `TrackVisit`, automatic | `Visits::track()` |
| SPA (same or other origin) | [JS beacon](js-beacon.md) on route change | `Visits::track()` on the backend, or the beacon |
| Mobile app | `POST /visits/collect` from the app ([Client integration](../guides/client-integration.md)) | `Visits::track()` on the backend, or the endpoint |
| Pages behind a full-page cache | JS beacon — the middleware runs only on cache misses | `Visits::track()` |

`TrackVisit` never sees API requests: it only runs in the `web` group, and a `GET /api/products` is a data fetch, not a page view.

## Automatic tracking

With `auto_track = true` (the default) `TrackVisit` is appended to the `web` middleware group. It tracks a request when all of these hold:

- `visits.enabled` is `true`
- the method is `GET`
- the path is not the package's own dashboard (`dashboard.path` and everything below it) or the whoami endpoint (`whoami.path`)
- the path matches none of `exclude_paths`
- consent is given, if `consent.require_consent` is on ([Consent](consent.md))

For each tracked request it resolves the visitor id, queues the visitor cookie and dispatches `RecordVisitJob` with type `page_view`, the full URL, and the matched route name.

```php
// config/visits.php
'exclude_paths' => [
    'admin/*',
    '_debugbar/*',
    'horizon/*',
    'up',
    'storage/*',
    'livewire/*',
],
```

Patterns use `Request::is()` syntax. Remember the trailing `/*`: `'storage'` matches only `/storage`, not `/storage/photo.png`.

> [!NOTE]
> The middleware is registered in `booted()`, after every provider has run. Laravel 11+ apps configured through `bootstrap/app.php` replace middleware groups during boot, and an earlier registration could be wiped out silently (fixed in 0.9.2).

## Tracking selected routes only

Set `auto_track` to `false` to switch from "everything except `exclude_paths`" to "only where attached". The middleware stays registered under the `track-visits` alias:

```php
Route::middleware(['web', 'track-visits'])->group(function () {
    Route::get('/', HomeController::class);
    Route::get('/pricing', PricingController::class);
});
```

All other checks still apply on these routes, including `exclude_paths` and `GET` only.

## First page view only

```php
// config/visits.php
'page_views' => 'first_only',
```

For apps that care about attribution (referrer, UTM, geo, device — captured once) plus their own `Visits::track()` calls, not a full page-view trail. `TrackVisit` still runs on every request and the job still refreshes the visitor and session (cookie, last activity, session timeout), but an `Event` row is written only when the request arrives without a visitor id — no `X-Visitor-Id`, no valid `visitor_id` input, no cookie.

Consequences:

- `Session.page_views_count` stays at 0 or 1
- Top pages and the Live page show only first hits
- a logged-in user on a new device whose id came from the [authenticated-user fallback](identity.md#logged-in-users-on-bearer-token-apis) still counts as "no visitor id on the request" and gets a page view recorded

This setting affects only `TrackVisit`. Page views sent through `POST /visits/collect` are always recorded.

## Excluding IPs

```php
'exclude_ips' => ['203.0.113.42', '198.51.100.0/24', '2001:db8::/32'],
```

Checked inside `RecordVisitJob`, so it applies to every entry point — the middleware, the collect endpoint and `Visits::track()`. The request still gets a visitor cookie; nothing is written to the database. The IP is `$request->ip()` — behind a load balancer or Cloudflare configure Laravel's trusted proxies, or every visitor has the proxy's IP.

## Full-page caches

`TrackVisit` runs only when PHP runs. A page served from an nginx `fastcgi_cache`/`proxy_cache` hit is not counted, and a cached `Set-Cookie` header hands one visitor id to everyone who gets that cached page. See [Client integration — full-page HTTP caching](../guides/client-integration.md#full-page-http-caching).
