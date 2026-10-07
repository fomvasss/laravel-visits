# Routes & middleware

## Routes

| Method | URI | Name | Middleware | Registered when |
|---|---|---|---|---|
| `POST` | `visits/collect` | `visits.collect` | `collect.middleware` + `throttle:{rate_limit.endpoint}` | always |
| `GET` | `{whoami.path}` (`visits/whoami`) | `visits.whoami` | `whoami.middleware` + `throttle:{rate_limit.whoami}` | `whoami.enabled` |
| `GET` | `{dashboard.path}` | `visits.index` | `dashboard.middleware` | `dashboard.enabled` |
| `GET` | `{dashboard.path}/campaigns` | `visits.campaigns` | `dashboard.middleware` | `dashboard.enabled` |
| `GET` | `{dashboard.path}/sessions` | `visits.sessions` | `dashboard.middleware` | `dashboard.enabled` |
| `GET` | `{dashboard.path}/sessions/{id}` | `visits.show` | `dashboard.middleware` | `dashboard.enabled` |
| `GET` | `{dashboard.path}/visitors` | `visits.visitors` | `dashboard.middleware` | `dashboard.enabled` |
| `GET` | `{dashboard.path}/visitors/{id}` | `visits.visitor` | `dashboard.middleware` | `dashboard.enabled` |
| `GET` | `{dashboard.path}/me` | `visits.me` | `dashboard.middleware` | `dashboard.enabled` |
| `GET` | `{dashboard.path}/live` | `visits.live` | `dashboard.middleware` | `dashboard.enabled` and `live.enabled` |
| `GET` | `{dashboard.path}/live/feed` | `visits.live.feed` | `dashboard.middleware` | `dashboard.enabled` and `live.enabled` |
| `GET` | `{dashboard.path}/live/stream` | `visits.live.stream` | `dashboard.middleware` | `dashboard.enabled` and `live.enabled` |

With `visits.enabled = false` only `POST /visits/collect` is registered (it answers `{"visitor_id": null}`); the dashboard and whoami routes are not (since 0.13.6). The collect path is fixed; only the whoami and dashboard paths are configurable.

## `POST visits/collect`

Request body (JSON or form):

| Field | Rules |
|---|---|
| `type` | `nullable`, `page_view` or `action`; `page_view` when omitted |
| `name` | `nullable`, string, max 255, required if `type` is `action` |
| `url` | `nullable`, string, max 2048 |
| `referrer` | `nullable`, string, max 2048 |
| `meta` | `nullable`, array |
| `visitor_id` | optional, alternative to the `X-Visitor-Id` header |

Headers: `X-Visitor-Id` (visitor id), `X-CSRF-TOKEN` (under the `web` group), `Accept: application/json` (JSON validation errors).

| Status | Body | When |
|---|---|---|
| `200` | `{"visitor_id": "<uuid>"}` | Accepted and queued |
| `200` | `{"visitor_id": null}` | `visits.enabled` is `false` |
| `403` | `{"visitor_id": null}` | `collect.allowed_origins` is set and the origin is not listed (checked before validation) |
| `419` | | CSRF token mismatch under the `web` group |
| `422` | validation errors | Invalid fields |
| `429` | | Throttled |

The response also queues the visitor cookie (written only under the `web` group). Details: [Client integration](../guides/client-integration.md).

## `GET visits/whoami`

Query: `ip` (optional, valid IPv4/IPv6; anything else is ignored). Returns the snapshot described in [Whoami](../usage/whoami.md), encoded with `JSON_FORCE_OBJECT` so empty groups are `{}`.

## Dashboard query parameters

| Route | Parameters |
|---|---|
| `visits.index`, `visits.campaigns` | `from`, `to` (dates), `tenant`, `breakdown_metric` (`sessions` or `conversions`) |
| `visits.sessions` | `from`, `to`, `visitor_id`, `country_code`, `device_type`, `utm_source`, `ip`, `with_bots`, `sort` (`started_at`, `page_views_count`, `duration_seconds`, `country_code`, `device_type`, `utm_source`, `referrer_host`, `ip`), `direction` |
| `visits.visitors` | `from`, `to`, `token` (partial match), `country_code`, `device_type`, `utm_source`, `returning_only`, `with_bots`, `sort` (`first_seen_at`, `last_seen_at`, `sessions_count`, `country_code`, `device_type`, `utm_source`), `direction` |
| `visits.show` | `sort` (`created_at`, `type`, `name`), `direction` (default `asc`) |
| `visits.me` | `ip` |
| `visits.live.feed`, `visits.live.stream` | `since` (ISO 8601; default 30 seconds ago) |

`visits.live.feed` returns `{"events": [...], "since": "<ISO 8601>"}`; each event has `session_id`, `lat`, `lng`, `city`, `country_code`, `type`, `name`, `created_at`. `visits.live.stream` sends the same object as Server-Sent Events `data:` frames.

## Middleware

| Class | Alias | Registered |
|---|---|---|
| `Fomvasss\Visits\Http\Middleware\TrackVisit` | `track-visits` | Alias always; appended to the `web` group when `visits.enabled` and `auto_track` are `true` |

Both registrations happen in the application's `booted()` callback, after all providers have booted. See [Page views](../usage/page-views.md).
