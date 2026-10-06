# Configuration

Publish the file to change anything:

```bash
php artisan vendor:publish --tag=visits-config
```

All keys live in `config/visits.php`. Keys without an env variable can only be changed in the published file. Class names are stored as strings, never closures, so the config survives `php artisan config:cache`.

## General

| Key | Env | Default | Description |
|---|---|---|---|
| `enabled` | `VISITS_ENABLED` | `true` | Turns tracking off: `TrackVisit` and `Visits::track()` do nothing, `POST /visits/collect` returns `{"visitor_id": null}`, the `Login`/`Logout` listeners and `Visits::identify()` do nothing, and `TrackVisit` is not added to the `web` group. The dashboard and `/visits/whoami` routes stay registered — turn them off with `dashboard.enabled` and `whoami.enabled` |
| `models.visitor` | — | `Fomvasss\Visits\Models\Visitor` | Visitor model class. Overrides must extend the package model ([Custom models](usage/customization.md#overriding-models)) |
| `models.session` | — | `Fomvasss\Visits\Models\Session` | Session model class |
| `models.event` | — | `Fomvasss\Visits\Models\Event` | Event model class |
| `models.stat_daily` | — | `Fomvasss\Visits\Models\StatDaily` | Daily rollup model class |
| `token_resolver` | — | `Fomvasss\Visits\Support\TokenResolver` | Class that resolves and generates visitor ids, bound in the container in place of `TokenResolver`. Must extend it ([Visitor identity](usage/identity.md#custom-token-resolver)) |
| `queue.connection` | `VISITS_QUEUE_CONNECTION` | `config('queue.default')` | Queue connection for `RecordVisitJob` |
| `queue.queue` | `VISITS_QUEUE` | `'default'` | Queue name for `RecordVisitJob` |

## Identity

| Key | Env | Default | Description |
|---|---|---|---|
| `cookie.name` | — | `'visits_visitor_id'` | Name of the visitor id cookie. Laravel's `EncryptCookies` encrypts it like any other cookie |
| `cookie.ttl_minutes` | — | `1051200` (2 years) | Cookie lifetime; re-queued on every tracked request |
| `visitor_id.format_regex` | — | UUID, `/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i` | Format a visitor id must match to be accepted — from the `X-Visitor-Id` header, the `visitor_id` input, the cookie, `inheritFrom` and the authenticated-user fallback. Anything else is silently ignored. The `token` column holds at most 64 characters |
| `reset_identity_on_logout` | — | `false` | On `Logout`, clear `user_type`/`user_id` on the current visitor (shared or kiosk devices) |
| `session_timeout_minutes` | — | `30` | Inactivity after which a new event opens a new session instead of joining the current one; also the cutoff for `visits:close-stale-sessions` |

## Tracking

| Key | Env | Default | Description |
|---|---|---|---|
| `auto_track` | — | `true` | Append `TrackVisit` to the `web` group. `false`: attach the `track-visits` middleware alias to the routes you want tracked ([Page views](usage/page-views.md)) |
| `exclude_paths` | — | `['admin/*', '_debugbar/*', 'horizon/*', 'up', 'storage/*']` | Path patterns (`Request::is()` syntax) `TrackVisit` never tracks. Checked whenever `TrackVisit` runs, including routes it was attached to by alias. `'storage'` without `/*` matches only `/storage` itself |
| `page_views` | — | `'every'` | `'first_only'`: `TrackVisit` writes an `Event` only for a request that carries no visitor id yet; later requests still refresh the visitor and session ([Page views](usage/page-views.md#first-page-view-only)) |
| `exclude_ips` | — | `[]` | IPs and CIDR ranges (IPv4/IPv6) never recorded, from any entry point: `['203.0.113.42', '198.51.100.0/24', '2001:db8::/32']` |
| `tracking_params.core` | — | `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `ref` | Map `column => query parameter`. Only these six columns exist — renaming the query parameter works (`'ref' => 'aff'`), adding a column does not |
| `tracking_params.extra_keys` | — | `gclid`, `fbclid`, `msclkid`, `ttclid`, `yclid`, `twclid`, `li_fat_id` | Query parameters always captured into the `extra_params` JSON column |
| `tracking_params.extra_pattern` | — | `null` | PCRE pattern with delimiters (`'/^aff_/'`); any other query parameter whose name matches is captured into `extra_params` too |
| `search_engines` | — | `google.` `q`, `bing.com` `q`, `duckduckgo.com` `q`, `search.yahoo.com` `p`, `yandex.` `text` | `host substring => query parameter`: the search keyword is read from the referrer URL of a matching host ([Campaign attribution](usage/attribution.md#search-keywords)) |

## Geo & device

| Key | Env | Default | Description |
|---|---|---|---|
| `geo.cache_ttl` | — | `86400` | Seconds a successful geo lookup is cached per IP. A failed lookup is cached for 300 seconds |
| `geo.store_coordinates` | — | `true` | Store `lat`/`lng`. With `false` the Overview map and the Live page have nothing to show |
| `device_detection.cache_dir` | — | `storage_path('framework/cache/device-detector')` | Not read by the current code — device detection runs without a persistent rule cache |

## Rate limits

All values use Laravel's throttle format `"max,decay_minutes"`.

| Key | Env | Default | Description |
|---|---|---|---|
| `rate_limit.endpoint` | — | `'60,1'` | `throttle` middleware appended to `POST /visits/collect` |
| `rate_limit.visitor_budget` | — | `'120,1'` | Events per visitor id, checked inside `RecordVisitJob` for every entry point. Over the budget the job writes nothing |
| `rate_limit.whoami` | — | `'60,1'` | `throttle` middleware appended to `GET /visits/whoami` |

## Collect endpoint

| Key | Env | Default | Description |
|---|---|---|---|
| `collect.middleware` | — | `['web']` | Middleware for `POST /visits/collect`; the throttle from `rate_limit.endpoint` is appended automatically. Use `['api']` (or exclude the route from CSRF) for API-only backends, other origins and mobile apps ([Client integration](guides/client-integration.md)) |
| `collect.allowed_origins` | — | `null` | Array of origins (`'https://example.com'`). When set, a request whose `Origin` (or the scheme and host of `Referer`) is not listed gets `403`. A filter against casual misuse, not authentication |

## Scheduling & retention

| Key | Env | Default | Description |
|---|---|---|---|
| `schedule.enabled` | `VISITS_SCHEDULE_ENABLED` | `true` | Register `visits:close-stale-sessions` and `visits:aggregate` in the scheduler ([Rollups](usage/maintenance.md#scheduling)) |
| `retention_days` | — | `90` | Default age for `visits:prune`. Pruning never runs on its own |
| `aggregate.dimensions` | — | `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `ref`, `referrer_host`, `country_code`, `device_type`, `browser`, `client_type`, `name` | Dimensions `visits:aggregate` writes to `visit_stats_daily` besides the totals. Removing one empties the matching dashboard panel |

## Consent

| Key | Env | Default | Description |
|---|---|---|---|
| `consent.require_consent` | — | `false` | Ask `consent.resolver` before `TrackVisit` tracks a request |
| `consent.resolver` | — | `null` | Class implementing `ConsentResolverInterface`. With `require_consent` on and no resolver, nothing is tracked by `TrackVisit` ([Consent](usage/consent.md)) |

## Dashboard

| Key | Env | Default | Description |
|---|---|---|---|
| `dashboard.enabled` | `VISITS_DASHBOARD_ENABLED` | `true` | Register the dashboard routes |
| `dashboard.path` | `VISITS_DASHBOARD_PATH` | `'visits'` | URL prefix of the dashboard |
| `dashboard.middleware` | — | `['web']` | Middleware for every dashboard route. No auth by default |
| `dashboard.per_page` | `VISITS_DASHBOARD_PER_PAGE` | `50` | Rows per page on Sessions and Visitors |
| `dashboard.default_range_days` | `VISITS_DASHBOARD_DEFAULT_RANGE_DAYS` | `30` | Date range of Overview and Campaigns without `?from=`/`?to=` |
| `dashboard.map_tile_url` | `VISITS_DASHBOARD_MAP_TILE_URL` | `'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'` | Leaflet tile URL for the Overview and Live maps. Switch to a paid tile provider for heavy use, per OpenStreetMap's tile usage policy |
| `dashboard.map_marker_limit` | `VISITS_DASHBOARD_MAP_MARKER_LIMIT` | `300` | Most recent sessions plotted on the Overview map |
| `dashboard.top_pages_limit` | `VISITS_DASHBOARD_TOP_PAGES_LIMIT` | `10` | Rows in the Top pages panel |
| `dashboard.online_window_minutes` | `VISITS_DASHBOARD_ONLINE_WINDOW_MINUTES` | `5` | An open session active within this many minutes counts as "online now" |

## Live page

| Key | Env | Default | Description |
|---|---|---|---|
| `live.enabled` | `VISITS_LIVE_ENABLED` | `true` | Register the Live page routes |
| `live.transport` | `VISITS_LIVE_TRANSPORT` | `'poll'` | `'poll'` or `'sse'` ([Dashboard](usage/dashboard.md#live-page)) |
| `live.poll_interval_ms` | `VISITS_LIVE_POLL_INTERVAL_MS` | `10000` | Polling interval of the browser (`poll`) |
| `live.feed_limit` | `VISITS_LIVE_FEED_LIMIT` | `50` | Maximum events per poll or push |
| `live.sse_check_interval` | `VISITS_LIVE_SSE_CHECK_INTERVAL` | `2` | Seconds between database checks inside one SSE connection |
| `live.sse_max_duration` | `VISITS_LIVE_SSE_MAX_DURATION` | `300` | Seconds one SSE connection stays open before the browser reconnects |

## Whoami

| Key | Env | Default | Description |
|---|---|---|---|
| `whoami.enabled` | `VISITS_WHOAMI_ENABLED` | `true` | Register the public `GET /visits/whoami` endpoint |
| `whoami.path` | `VISITS_WHOAMI_PATH` | `'visits/whoami'` | Its path, independent of `dashboard.path` |
| `whoami.middleware` | — | `['web']` | Its middleware; the throttle from `rate_limit.whoami` is appended |

## Other

| Key | Env | Default | Description |
|---|---|---|---|
| `tenant_resolver` | — | `null` | Reserved; the package does not read it ([Multi-tenancy](usage/customization.md#multi-tenancy)) |
| `user_display_resolver` | — | `DefaultUserDisplayNameResolver` | Class implementing `UserDisplayNameResolverInterface` that turns the linked user into a display name on the dashboard. The default returns `name`, then `email`, then `null` |

> [!NOTE]
> The collect endpoint path `visits/collect` is fixed — it does not follow `dashboard.path`.
