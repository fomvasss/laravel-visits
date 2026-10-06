# Rollups, sessions & retention

Three commands keep the data usable. Two are scheduled by the package, one is always up to you. Full signatures: [Artisan commands](../reference/commands.md).

## Rollups — `visits:aggregate`

Overview and Campaigns never scan raw events. They read `visit_stats_daily`: one row per date, tenant, metric, dimension and value.

```bash
php artisan visits:aggregate # today
php artisan visits:aggregate --date=yesterday
php artisan visits:aggregate --date=2026-09-01
php artisan visits:aggregate --from=2026-09-01 --to=2026-09-30
```

For each day and each tenant it deletes the existing rows and inserts freshly computed ones, so running it again is safe. Days are calendar days in the app timezone.

| Metric | Counts | Rows of the day by |
|---|---|---|
| `visitors` | New visitors | `first_seen_at` |
| `sessions` | Sessions | `started_at` |
| `page_views` | `page_view` events | `created_at` |
| `conversions` | `action` events | `created_at` |

Bots are excluded from every metric. Each metric gets a total row (`dimension = ''`) plus one row per value of every dimension in `aggregate.dimensions`. Visitors are grouped by their first-touch attribution and their last-known geo and device, sessions by their own columns, events by their session's columns plus the event `name`.

> [!WARNING]
> Don't re-aggregate dates older than your retention window. After `visits:prune` removed the raw rows, a new run for such a day replaces its rollups with zeros.

## Closing sessions — `visits:close-stale-sessions`

```bash
php artisan visits:close-stale-sessions
```

Finds sessions with `ended_at = null` whose `last_activity_at` is older than `session_timeout_minutes` and sets:

- `ended_at` — the last activity time
- `duration_seconds` — from `started_at` to the last activity
- `exit_url` — URL of the session's last page view

Tracking does not depend on it — a new event after the timeout opens a new session anyway. Without the command sessions just never get an end, duration or exit URL, and "online now" is unaffected.

## Retention — `visits:prune`

```bash
php artisan visits:prune # older than retention_days (90)
php artisan visits:prune --days=180
php artisan visits:prune --force # no confirmation prompt
```

Deletes events created before the cutoff, sessions started before it and visitors last seen before it. Foreign keys cascade, so deleting an old session or visitor also deletes its newer children. `visit_stats_daily` is not touched — the dashboard totals for old days survive.

`--days=0` (or `retention_days = 0`) means a cutoff of "now" — everything is deleted.

## Scheduling

With `schedule.enabled = true` (default, `VISITS_SCHEDULE_ENABLED`) the service provider registers:

| Command | Frequency |
|---|---|
| `visits:close-stale-sessions` | every five minutes |
| `visits:aggregate --date=today` | every five minutes |
| `visits:aggregate --date=yesterday` | daily at 00:10 |

So the dashboard lags real traffic by up to five minutes plus the queue delay. The yesterday run picks up events that arrived after the last run of the previous day.

`visits:prune` is never scheduled by the package — deleting data stays an explicit decision:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('visits:prune --force')->daily()->when(fn () => config('visits.retention_days') > 0);
```

`--force` is required here: under the scheduler there is no terminal, the confirmation prompt answers "no" and nothing is deleted.

### Custom frequencies

To change the frequencies, or when the commands already are in your own schedule (otherwise they run twice), turn the package schedule off and add them yourself:

```env
VISITS_SCHEDULE_ENABLED=false
```

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('visits:close-stale-sessions')->everyFiveMinutes()->onOneServer();
Schedule::command('visits:aggregate --date=today')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('visits:aggregate --date=yesterday')->dailyAt('00:10')->onOneServer();
```

The package schedule uses neither `withoutOverlapping()` nor `onOneServer()`. On several servers running `schedule:run`, define the schedule yourself as above.
