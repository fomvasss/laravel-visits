# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [0.11.0] - 2026-07-28

### Changed
- Visitor identifier format switched from a 40-char random string to a UUID; `visitor_id.format_regex` now enforces the standard UUID format. Anticipates visitor IDs arriving from external systems (which commonly already use UUIDs) — changed now, while still pre-1.0, before any production data accumulates.

### Added
- `token_resolver` config key — override `TokenResolver` with your own subclass (same override pattern as `models`); the override is picked up consistently everywhere the package resolves a visitor token

## [0.10.0] - 2026-07-27

### Added
- `HasVisits::firstVisitorProfile()`/`latestVisitorProfile()` — relations resolving to the earliest/most-recently-active `Visitor` linked to a host model, alongside the existing `latestVisitEvent()`
- `HasVisits::firstVisitEvent(?string $name = null)` — the earliest-event counterpart to `latestVisitEvent()`
- `Visitor::firstSession()`/`latestSession()` — relations resolving to a visitor's earliest/most recent `Session`, replacing the common `sessions()->oldest()->first()`/`->latest()->first()` host-app pattern

## [0.9.2] - 2026-07-26

### Fixed
- Automatic tracking (`auto_track=true`) could silently never activate on Laravel 11/12's `bootstrap/app.php`-based middleware configuration, with no error to signal it — fixed by registering later in the boot lifecycle, after all providers have run

## [0.9.1] - 2026-07-26

### Changed
- `visits:seed-demo` is now registered on any non-production environment (previously only on `local`/`testing`, so it was missing on staging/demo servers)

## [0.9.0] - 2026-07-26

### Fixed
- Visitor/Event records were written using the raw model class name but read back using any `Relation::morphMap()` alias a host app registers — silently zero matches on `HasVisits` relations despite correctly-linked rows. Hosts without a morphMap are unaffected.

## [0.8.0] - 2026-07-26

### Added
- `visits.page_views` (default `'every'`) — set to `'first_only'` so only a visitor's very first hit writes an `Event` row (later page views still refresh the `Visitor`/`Session`). Generalizes the "track only brand-new visitors" pattern hosts kept hand-rolling into a config toggle.
- Session detail page (`/visits/sessions/{id}`)'s events table is now sortable (Time/Type/Name) — defaults to chronological ascending (oldest first), unlike other sortable tables on the dashboard which default to newest-first, since this one reads as a single session's own timeline

## [0.7.0] - 2026-07-26

### Fixed
- Auth models with a UUID/ULID primary key crashed on every authenticated request (`invalid input syntax for type bigint`), silently dropping the visit — `visit_visitors.user_id`/`visit_sessions.user_id` are now plain `string` columns

### Changed
- Added an index on `(visitor_id, ended_at, last_activity_at)` for `visit_sessions` — speeds up the "find this visitor's currently-open session" lookup, which runs on every tracked event/action and every login
- Added an index on `(type, created_at)` for `visit_events` — speeds up the dashboard's Top Pages panel
- Follow-up "add column" migrations (`search_term`, `route_name`, `path`) merged back into their respective `create_visit_*_table` migrations — one migration file per table instead of a create plus a trail of alters

## [0.6.0] - 2026-07-26

### Added
- Visitor token resolution now falls back to the authenticated request's own known `Visitor` before generating a brand-new one, applied automatically across all entry points. Fixes identity fragmentation for Bearer-token APIs (Sanctum, `supports_credentials: false`) where the visits cookie never round-trips cross-origin — every tracked action for an already-known, logged-in user previously spawned a disconnected anonymous visitor instead of reconnecting to theirs.
- `Visits::identify($user)` — links the current request's visitor to `$user`, the same merge performed on Laravel's own `Login` event, but for identity established without an actual login (e.g. a guest checkout matched/created by email or phone). Deliberately not implemented as a fake `Login` dispatch, so it won't mislead other `Login` listeners a host app adds later.

## [0.5.0] - 2026-07-26

### Added
- `Visits::track()`/`VisitsManager::track()` gained an `?string $inheritFrom` parameter — when the current request carries no identity signal of its own (typically a payment-gateway webhook), the visitor is inherited from `$eventable`'s own prior event with that name instead of misattributing to a brand-new visitor
- `TokenResolver::resolve()` gained an optional `?callable $fallback` parameter (the building block `inheritFrom` above is built on)
- README/README.uk: worked examples for reading package data back through `HasVisits`, and a JS-beacon variant of the "order paid" funnel step

### Fixed
- README/README.uk's `latestVisitEvent()` example was missing `->first()` — the method returns a relation, not the `Event` model directly

## [0.4.0] - 2026-07-26

### Added
- `visits.visitor_id.format_regex` — the accepted client-supplied identifier format is now configurable (previously hardcoded), so identifiers like UUIDs from another system can be accepted without normalizing them first
- `visits.auto_track` (default `true`) — set to `false` to stop automatic tracking on every `web` route and instead attach the `track-visits` middleware alias to only the routes you want tracked
- `Event.path` — the `url` column's path component only, without the query string
- Expanded documentation: full-page HTTP caching considerations, handing off an identifier from another system, a geo/device field reference, architecture/internals for contributors, and a "When to Use This (vs GA4, Plausible, Matomo)" comparison

### Changed
- **Breaking:** the client-facing visitor identifier is renamed from "token" to "id" throughout — `X-Visitor-Token` header → `X-Visitor-Id`, `visitor_token` input/JSON key → `visitor_id`, `visits_token` cookie → `visits_visitor_id`. Update any client code that reads/sends the old names.
- Overview's "Top Pages" panel now groups by path instead of the raw URL — a page with filter/sort query params previously fragmented into one row per parameter combination. Events recorded before this change are excluded from the panel (not backfilled).

## [0.3.0] - 2026-07-26

### Added
- Overview dashboard: "Top pages" panel and an "online now" indicator (open sessions active within the last few minutes, configurable)
- `Visitor.search_term`/`Session.search_term` — organic search keyword extracted from a known search engine's referrer URL
- `visits.exclude_ips` — literal IPs and/or CIDR ranges (IPv4/IPv6) never tracked regardless of entry point
- `Event.route_name` — the matched Laravel route name for page views, alongside the existing raw `url` column

## [0.2.0] - 2026-07-26

### Added
- Full README + `docs/client-integration.md` — installation, integration guides, security considerations
- `VisitorCreated`, `SessionStarted`, `VisitorIdentified` events
- Session-locations map on the Overview dashboard (marker clustering, fullscreen toggle)
- Live activity page (`/visits/live`) — recent events as fading pulse markers on a map, plus a scrolling event log
- Campaigns dashboard page (`/visits/campaigns`) — all UTM/`ref` dimensions in one place
- `visits.collect.middleware`/`visits.collect.allowed_origins` — customize middleware and add an `Origin`/`Referer` allowlist for the public collect endpoint independently of the rest of the app
- `visits.schedule.enabled` (default `true`) — auto-registers scheduled cleanup/aggregation commands
- `visits.rate_limit.whoami` — dedicated throttle for the public `/visits/whoami` endpoint
- `window.VisitsQueue` — GTM `dataLayer`-style buffer in `visits.js`, safe to push to before the script has loaded
- `visits.dashboard.default_range_days`, `map_tile_url`, `map_marker_limit` config keys
- `user_display_resolver` config key — customize how a visitor's linked user is displayed on the dashboard
- Demo data seeder now generates geographically coherent locations (previously country/city/coordinates were picked independently)

### Changed
- **Breaking:** `Event.action` renamed to `Event.name` throughout
- **Breaking:** `visit_events.eventable_id` changed from `unsignedBigInteger` to `string`, so eventable models with non-bigint primary keys (UUID/ULID) are supported
- `TrackVisit` no longer tracks the package's own dashboard/whoami routes

### Fixed
- `HasVisits::latestVisitEvent()` — "latest" is now computed among matching-name rows, not always the globally-latest event

## [0.1.0] - 2026-07-25

Initial release: async-first visitor/session/pageview tracking, cookie + client-token identity, geo/device/bot detection, UTM attribution, custom conversion events, rollup analytics, and a built-in dashboard (Overview, Sessions, Visitors, per-record detail).
