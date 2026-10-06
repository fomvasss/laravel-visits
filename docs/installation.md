# Installation

## Requirements

- PHP ^8.3
- Laravel ^12 or ^13
- A queue worker — every visit is recorded by a queued `RecordVisitJob`
- The scheduler (`schedule:run`) — closes stale sessions and refreshes the dashboard rollups

Installed automatically as dependencies: `stevebauman/location` (geo lookup) and `matomo/device-detector` (device, browser and bot detection).

## Install

```bash
composer require fomvasss/laravel-visits
php artisan migrate
```

The service provider and the `Visits` facade alias are auto-discovered. Migrations are loaded from the package directly — there is nothing to publish to run them. They create four tables: `visit_visitors`, `visit_sessions`, `visit_events`, `visit_stats_daily` (see [Database tables](reference/database.md)).

After this:

- `TrackVisit` is appended to the `web` middleware group — every `GET` request through `web` dispatches a `RecordVisitJob` ([Page views](usage/page-views.md))
- `POST /visits/collect`, `GET /visits/whoami` and the dashboard under `/visits` are registered ([Routes](reference/routes.md))
- `visits:close-stale-sessions` and `visits:aggregate` are added to the scheduler ([Rollups, sessions & retention](usage/maintenance.md))
- listeners on Laravel's `Login`/`Logout` events link visitors to users ([Visitor identity](usage/identity.md))

> [!WARNING]
> The dashboard has no authentication by default. Set `dashboard.middleware` (for example `['web', 'auth']`) before deploying anywhere except local — see [Dashboard](usage/dashboard.md#securing-the-dashboard).

## Publish the config

```bash
php artisan vendor:publish --tag=visits-config
```

Publishes `config/visits.php`. Every key is described in [Configuration](configuration.md).

## Publish the JS beacon (optional)

Only needed for SPA route changes, client-side events or pages served from a full-page cache — see [JS beacon](usage/js-beacon.md).

```bash
php artisan vendor:publish --tag=visits-assets
```

Copies `resources/js/visits.js` to `public/vendor/visits/visits.js`. A copy does not follow package updates — re-publish with `--force` after `composer update`, or symlink the file instead.

## Queue worker

`RecordVisitJob` is dispatched to `visits.queue.connection` / `visits.queue.queue` (by default the app's default connection and the `default` queue). With the `sync` driver the job runs inside the request, which works but loses the point of async tracking.

A dedicated queue keeps the highest-volume, lowest-priority job away from the rest of your jobs:

```env
VISITS_QUEUE=visits
```

```bash
php artisan queue:work --queue=visits
```

See the [Production checklist](guides/production.md) for a Horizon supervisor example.

## Scheduler

With `schedule.enabled` (the default) the package registers its own schedule — the host only needs the usual `schedule:run` cron entry. `visits:prune` is never scheduled automatically. Details: [Rollups, sessions & retention](usage/maintenance.md).

## Geo provider

Out of the box `stevebauman/location` uses its default HTTP driver chain, one external request per uncached IP. For a local, no-outbound-request lookup switch it to MaxMind GeoLite2 — see [Geo, device & bot detection](usage/geo-device.md#maxmind-geolite2).

## Demo data

To see the dashboard filled before real traffic arrives (not available in the `production` environment):

```bash
php artisan visits:seed-demo --visitors=150 --days=30
```

The command uses the package model factories, which need `fakerphp/faker` — present in a default Laravel app as a dev dependency, but not required by this package. See [Artisan commands](reference/commands.md#visitsseed-demo).
