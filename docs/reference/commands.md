# Artisan commands

| Command | Scheduled by the package | Description |
|---|---|---|
| `visits:aggregate` | yes (`schedule.enabled`) | Rebuild `visit_stats_daily` for one or more days |
| `visits:close-stale-sessions` | yes (`schedule.enabled`) | Close sessions past `session_timeout_minutes` |
| `visits:prune` | never | Delete raw rows older than the retention window |
| `visits:seed-demo` | never | Generate demo data; not registered in `production` |

Background and scheduling: [Rollups, sessions & retention](../usage/maintenance.md).

## `visits:aggregate`

```
visits:aggregate {--date=} {--from=} {--to=} {--force}
```

| Option | Description |
|---|---|
| `--date` | `today` (default), `yesterday`, or any date `Carbon::parse()` understands |
| `--from` | First day of a range; takes precedence over `--date` |
| `--to` | Last day of the range; defaults to `--from` |
| `--force` | Also recompute days older than `retention_days` |

For each day and each distinct `tenant_id` of `visit_visitors`: deletes that day's rows and inserts new totals and dimension counts for `visitors`, `sessions`, `page_views` and `conversions`, bots excluded. Prints `Aggregated YYYY-MM-DD` per day. A day that starts before `now() - retention_days` is skipped with a warning unless `--force` is given: its raw rows may already be pruned.

## `visits:close-stale-sessions`

```
visits:close-stale-sessions
```

Sets `ended_at` (= `last_activity_at`), `duration_seconds` and `exit_url` (last page view) on every open session whose `last_activity_at` is older than `session_timeout_minutes`, bots included, in chunks of 500. Prints `Closed N stale session(s).`

## `visits:prune`

```
visits:prune {--days= : Override retention_days from config} {--force : Skip the confirmation prompt}
```

| Option | Description |
|---|---|
| `--days` | Retention in days; defaults to `retention_days` (90). `0` deletes everything |
| `--force` | Skip the confirmation. Required when run non-interactively (scheduler, CI) — otherwise the prompt is answered "no" and nothing is deleted |

Deletes, bots included: events with `created_at`, sessions with `started_at`, visitors with `last_seen_at` before the cutoff. Foreign keys cascade to the children of deleted sessions and visitors. Rollups are kept. Prints the three counts.

## `visits:seed-demo`

```
visits:seed-demo {--days=30} {--visitors=150} {--fresh} {--force}
```

| Option | Description |
|---|---|
| `--days` | Spread the visitors' `first_seen_at` over this many past days |
| `--visitors` | Number of visitors to create |
| `--fresh` | Delete all rows of the four `visit_*` tables first (asks for confirmation) |
| `--force` | Skip the `--fresh` confirmation |

Creates visitors with one to three sessions and one to six events each, with consistent geo (real cities), device and UTM data, about 3% bots, then runs `visits:aggregate` over the seeded range. Registered only outside the `production` environment; requires `fakerphp/faker`.

> [!WARNING]
> `--fresh` deletes your real tracking data as well, not only earlier demo rows.
