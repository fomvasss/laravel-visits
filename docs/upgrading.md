# Upgrading

The package is pre-1.0: minor versions may break. Composer's caret on `0.x` stays within one minor version (`^0.11` allows `0.11.*` only), so raise the constraint by hand and read the notes below. The full list of changes is in the [changelog](https://github.com/fomvasss/laravel-visits/blob/master/CHANGELOG.md).

> [!WARNING]
> Before 1.0 the migrations were edited in place instead of adding new ones (0.2.0, 0.7.0). `php artisan migrate` does not change tables that were created by an older version — apply the listed schema changes yourself.

## To 0.12

No breaking changes.

- 0.12.0: `storage/*` is in the default `exclude_paths`. A published config keeps your own list — add it there.
- 0.12.1: the first two requests of a new visitor no longer race into a failed job.
- 0.12.2: `browser_language` no longer stores `*`.

## To 0.11

**Visitor ids are UUIDs.** `TokenResolver::generate()` returns a UUID and `visitor_id.format_regex` accepts only UUIDs. Ids issued by older versions (40 alphanumeric characters) in cookies, `localStorage` and mobile apps no longer pass the check: each existing visitor gets a new id and a new `Visitor` row on their next visit, and the authenticated-user fallback cannot reuse old rows either. To keep old ids working, accept both formats:

```php
// config/visits.php
'visitor_id' => [
    'format_regex' => '/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[a-zA-Z0-9]{40})$/i',
],
```

A published config from an older version still contains the old regex `/^[a-zA-Z0-9]{20,64}$/`, which rejects every newly generated UUID because of the dashes — every request becomes a new visitor. Update the key.

New: the `token_resolver` config key.

## To 0.10

No breaking changes. New relations: `HasVisits::firstVisitorProfile()`, `latestVisitorProfile()`, `firstVisitEvent()`, `Visitor::firstSession()`, `latestSession()`.

## To 0.9

- 0.9.0: `user_type` and `eventable_type` are written with `getMorphClass()`. Apps that use `Relation::morphMap()` have older rows with the full class name, which `HasVisits` relations no longer match. Update them:

  ```php
  use App\Models\Order;
  use App\Models\User;
  use Illuminate\Support\Facades\DB;

  DB::table('visit_visitors')->where('user_type', User::class)->update(['user_type' => (new User)->getMorphClass()]);
  DB::table('visit_sessions')->where('user_type', User::class)->update(['user_type' => (new User)->getMorphClass()]);
  DB::table('visit_events')->where('eventable_type', Order::class)->update(['eventable_type' => (new Order)->getMorphClass()]);
  ```

- 0.9.1: `visits:seed-demo` is available in every environment except `production`.
- 0.9.2: automatic tracking works on Laravel 11+ apps configured through `bootstrap/app.php`. If you registered `TrackVisit` by hand as a workaround, remove that.

## To 0.8

No breaking changes. New: `page_views` config key.

## To 0.7

Schema changes for tables created before 0.7.0:

- `visit_visitors.user_id` and `visit_sessions.user_id` are `string` (were `unsignedBigInteger`) — required for UUID/ULID user keys
- new indexes `visit_sessions (visitor_id, ended_at, last_activity_at)` and `visit_events (type, created_at)`
- the separate migrations adding `search_term`, `route_name` and `path` were merged into the create migrations and deleted — an install that ran them keeps the columns, but its `migrations` table lists files that no longer exist

## To 0.6

No breaking changes. New: authenticated-user fallback in visitor resolution, `Visits::identify()`.

## To 0.5

No breaking changes. New: `inheritFrom` argument of `Visits::track()`.

## To 0.4

**Identifier renamed from "token" to "id"** in everything a client sees:

| Before | After |
|---|---|
| `X-Visitor-Token` header | `X-Visitor-Id` |
| `visitor_token` input / JSON key | `visitor_id` |
| `visits_token` cookie | `visits_visitor_id` |
| `visits_token` `localStorage` key (`visits.js`) | `visits_visitor_id` |

Update clients that read or send the old names. Because the cookie and the `localStorage` key are renamed, every browser gets a new visitor once. Re-publish `visits.js` (`--force`).

New: `auto_track`, `visitor_id.format_regex`, `visit_events.path`. Top pages group by `path`; events recorded before 0.4.0 have no path and are not counted there.

## To 0.3

No breaking changes. New columns `search_term` and `route_name` (see 0.7 for how they ended up in the create migrations).

## To 0.2

- `visit_events.action` is renamed to `visit_events.name`, and every API follows (`Visits::track()` writes `name`).
- `visit_events.eventable_id` is a `string` (was `unsignedBigInteger`).
- `TrackVisit` no longer tracks the dashboard and whoami routes.
