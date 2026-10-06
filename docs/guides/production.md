# Production checklist

Collected from integrating the package into live projects. Each item is a decision — not every app needs all of them.

## Before deploying

- [ ] **Dashboard behind auth** — `dashboard.middleware` with `auth` and a gate ([Dashboard](../usage/dashboard.md#securing-the-dashboard))
- [ ] **Whoami decided** — keep it public with the throttle, protect it with `whoami.middleware`, or `whoami.enabled = false` ([Whoami](../usage/whoami.md))
- [ ] **Queue worker** processes `visits.queue` ([below](#a-dedicated-queue))
- [ ] **Scheduler** runs `schedule:run` every minute; `visits:prune --force` scheduled if you want retention ([Rollups](../usage/maintenance.md#scheduling))
- [ ] **Real client IPs** — behind a load balancer, CDN or Cloudflare configure Laravel's trusted proxies; otherwise every visit has the proxy's IP and geo
- [ ] **Geo provider** — the default HTTP chain makes one external request per uncached IP; MaxMind GeoLite2 is local ([Geo](../usage/geo-device.md#maxmind-geolite2))
- [ ] **Office IPs** in `exclude_ips`
- [ ] **`exclude_paths`** covers non-page routes of your app (Livewire, file downloads, health checks, admin)
- [ ] **Full-page cache** — `Set-Cookie` ignored by the cache, beacon on cached pages ([Client integration](client-integration.md#full-page-http-caching))
- [ ] **Shared cache store** on several servers — geo results and the visitor budget use the default cache store

## Migrations

`php artisan migrate` runs the package migrations together with the app's. In a project with unrelated pending or broken migrations, run only the package's:

```bash
php artisan migrate --path=vendor/fomvasss/laravel-visits/database/migrations
```

## A dedicated queue

`RecordVisitJob` runs for every page view — the highest-volume, lowest-priority job in most apps. Give it its own queue so a traffic spike doesn't delay emails or payments:

```env
VISITS_QUEUE=visits
```

With Horizon, a small supervisor of its own:

```php
// config/horizon.php
'defaults' => [
    // ...
    'supervisor-visits' => [
        'connection' => 'redis',
        'queue' => ['visits'],
        'balance' => 'auto',
        'maxProcesses' => 2,
        'memory' => 128,
        'tries' => 1,
        'timeout' => 30,
    ],
],
```

`tries => 1`: a retry of an analytics job is worth little, and a job that failed after writing the event would record it twice. The job usually takes milliseconds; the slow part is an uncached geo lookup over HTTP (3-second timeouts per provider in `stevebauman/location`'s default config, times the fallbacks).

Horizon reads supervisors at start — restart it after the change.

## Identity on token APIs

- [ ] User model uses `HasVisits` — logged-in users of a Bearer-token API reconnect to their visitor ([Visitor identity](../usage/identity.md#logged-in-users-on-bearer-token-apis))
- [ ] Logins that issue tokens without `Auth::login()` dispatch `Login` themselves; guest checkouts call `Visits::identify()` ([Visitor identity](../usage/identity.md#identifying-without-a-login))
- [ ] `collect.middleware` fits the clients: `['web']` for same-origin Blade, `['api']` for SPAs on other origins and mobile apps, `['web']` plus a CSRF exception for both ([Client integration](client-integration.md))

## Minimal tracking on a busy site

If you need attribution and conversions rather than a page-view trail:

```php
'page_views' => 'first_only',
```

Plus explicit `Visits::track()` calls at the points that matter — lead form, order placed, payment confirmed (with `inheritFrom` in the webhook). See [Page views](../usage/page-views.md#first-page-view-only) and [Custom events](../usage/custom-events.md).

## "I don't see any visits"

1. **Is a worker running?** `RecordVisitJob` waiting in the queue looks exactly like no tracking. Check `queue:failed` too.
2. **Is `TrackVisit` in the `web` group?**

   ```bash
   php artisan tinker --execute="var_dump(in_array(\Fomvasss\Visits\Http\Middleware\TrackVisit::class, app('router')->getMiddlewareGroups()['web'] ?? [], true));"
   ```

   On versions before 0.9.2 the registration could be lost on Laravel 11+ apps configured through `bootstrap/app.php` — upgrade.
3. **Is the path excluded?** `exclude_paths`, the dashboard path itself, non-`GET` requests and missing consent are all skipped silently.
4. **Is it a bot?** Bot rows are hidden by default; the Sessions list has *Include bots*.
5. **Is the IP excluded or over the budget?** `exclude_ips` and `rate_limit.visitor_budget` drop events without a trace — a load test from one machine with one visitor id hits the budget fast.
6. **Overview empty, lists not?** Overview reads rollups — run `visits:aggregate` or wait for the scheduler.
7. **Demo data mixed in?** `visits:seed-demo` rows use `example.test` URLs.

## Pre-1.0 versions

The package is `0.x`. Composer's caret on `0.x` does not cross minor versions: `^0.11` allows `0.11.*` only. Raise the constraint by hand to pick up a new minor version, and read [Upgrading](../upgrading.md) first.
