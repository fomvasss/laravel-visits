# Database tables

Four tables, created by the package migrations (loaded automatically, no publishing). `user_id` and `eventable_id` are strings, so UUID and ULID keys work.

## `visit_visitors`

One row per visitor id. First-touch columns are written once; geo, device and locale hold the last known values.

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `token` | string(64), unique | Visitor id |
| `first_seen_at` / `last_seen_at` | timestamp | First and latest recorded activity |
| `first_landing_url` | text | URL of the first recorded request |
| `first_referrer_url` | string(2048) | Referrer of the first request |
| `first_referrer_host` | string, index | Its host |
| `utm_source` (index), `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` | string | First-touch UTM |
| `ref` | string, index | First-touch `ref` |
| `search_term` | string, index | First-touch search keyword |
| `extra_params` | json | First-touch click IDs and pattern matches |
| `country_code` (string(2), index), `region`, `city`, `timezone` | string | Last known geo |
| `lat`, `lng` | decimal(10,7) | Last known coordinates |
| `geo_meta` | json | Driver-specific geo fields |
| `locale` | string(10), index | App locale of the latest request |
| `browser_language` | string(10) | First `Accept-Language` entry |
| `device_type`, `client_type` (index), `platform`, `browser` | string | Last known device |
| `device_meta` | json | Brand, model, OS/browser version, engine |
| `is_bot` | bool, index | Latest request was a bot |
| `user_type`, `user_id` | string, index | Linked user |
| `tenant_id` | string, default `''`, index | Set by the app, never by the package |
| `created_at`, `updated_at` | timestamp | |

## `visit_sessions`

One browsing session. Attribution and geo/device are a snapshot from the moment the session opened.

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `visitor_id` | FK `visit_visitors`, cascade on delete | |
| `started_at` | timestamp, index | Opened |
| `last_activity_at` | timestamp, index | Latest event |
| `ended_at` | timestamp, nullable | Set by `visits:close-stale-sessions` |
| `duration_seconds` | unsigned int, nullable | Set by `visits:close-stale-sessions` |
| `page_views_count` | unsigned int | Recorded page views |
| `landing_url` | text | URL of the first request |
| `exit_url` | text | Last page view, set when closed |
| `referrer_url` (string(2048)), `referrer_host` (index) | string | Referrer of the first request |
| `utm_source` (index), `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `ref` | string | Last-touch attribution |
| `search_term` | string | Last-touch search keyword |
| `extra_params` | json | Last-touch click IDs |
| `ip` | ip address, index | |
| `country_code` (index), `region`, `city`, `timezone`, `lat`, `lng`, `geo_meta` | | Geo snapshot |
| `locale`, `browser_language` | string(10) | |
| `device_type` (index), `client_type` (index), `platform`, `browser`, `device_meta` | | Device snapshot |
| `user_agent` | text | |
| `is_bot` | bool, index | Sticky: true once any request of the session was a bot |
| `user_type`, `user_id` | string, index | User snapshot |
| `created_at`, `updated_at` | timestamp | |

Indexes: `(visitor_id, started_at)`, `(visitor_id, ended_at, last_activity_at)`.

## `visit_events`

One page view or action. No `updated_at`.

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `session_id` | FK `visit_sessions`, cascade on delete | |
| `visitor_id` | FK `visit_visitors`, cascade on delete | Denormalized for direct queries |
| `type` | string(20), index | `page_view` or `action` |
| `name` | string, index | Action name; `null` for page views from `TrackVisit` |
| `url` | text | Full URL |
| `path` | string, index | Path without query string — Top pages groups by it |
| `route_name` | string, index | Laravel route name; `null` for collect events |
| `is_bot` | bool, index | |
| `bot_name`, `bot_category` | string | For bots |
| `eventable_type`, `eventable_id` | string, index | Model passed to `Visits::track()` |
| `meta` | json | |
| `created_at` | timestamp, index | |

Indexes: `(eventable_type, eventable_id)`, `(session_id, created_at)`, `(name, created_at)`, `(type, created_at)`.

## `visit_stats_daily`

Rollups written by `visits:aggregate`.

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `date` | date, index | Day |
| `tenant_id` | string, default `''` | |
| `metric` | string(40) | `visitors`, `sessions`, `page_views`, `conversions` |
| `dimension` | string(40), default `''` | `''` for the total, otherwise a name from `aggregate.dimensions` |
| `dimension_value` | string, default `''` | |
| `count` | unsigned bigint | |

Unique key: `(date, tenant_id, metric, dimension, dimension_value)`.

Referrer URL and host are cut to their column length before saving (since 0.12.0), so a huge `Referer` header no longer fails the visit.

> [!WARNING]
> Other request-derived strings are not cut: UTM/`ref` values, `search_term` and `path` (all 255 characters) and the `name` passed to `Visits::track()`. On a database in strict mode a longer value fails `RecordVisitJob` and the visit is lost — `failed_jobs` shows `Data too long` (MySQL) or `value too long` (PostgreSQL).
