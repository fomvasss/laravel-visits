# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [0.9.1] - 2026-07-26

### Changed
- `visits:seed-demo` was only registered when `APP_ENV` was `local`/`testing`, so it was missing (`Command "visits:seed-demo" is not defined`) on any other non-production environment (e.g. a `develop`/staging server used to demo the dashboard). Now registered whenever the app is not `production`.

## [0.9.0] - 2026-07-26

### Fixed
- `PayloadBuilder`/`VisitorIdentityMerger` wrote `user_type`/`eventable_type` as the raw `::class` FQCN. Any host that registers `Relation::morphMap()` (a common Laravel convention) makes `getMorphClass()` return the registered alias instead — and `HasVisits::visitorProfiles()`/`visitEvents()` (both `morphMany()`) filter by the *current* model's own `getMorphClass()` when reading. Result: on such a host, every `Visitor`/`Event` was written with the FQCN but read back by alias — zero matches, even though the row was otherwise linked correctly, with no error to signal it. Now uses `getMorphClass()` (falls back to the FQCN automatically when no morphMap entry exists, so hosts without a morphMap see no behavior change).

## [0.8.0] - 2026-07-26

### Added
- `visits.page_views` (default `'every'`) — set to `'first_only'` so `TrackVisit` only writes an `Event` row for a visitor's very first hit (no visits cookie on the request yet); every later page view still resolves/refreshes the `Visitor`/`Session` (cookie renewal, geo/device snapshot, session timeout) but skips the `Event` row. Generalizes the custom "track only brand-new visitors" middleware pattern hosts kept hand-rolling into a config toggle. `TokenResolver::hasRequestIdentity()` (new, public) backs the "is this a returning visitor" check.
- Session detail page (`/visits/sessions/{id}`)'s events table is now sortable (Time/Type/Name columns, same `sortable-th` pattern as the Sessions/Visitors lists). Defaults to chronological ascending (oldest first) — unlike every other sortable table on the dashboard (which default to newest-first), this table is one session's own journey read top-to-bottom like a timeline, not an activity feed. `DashboardController::resolveSort()` gained an optional `$defaultDirection` parameter (`'desc'` unless overridden) to support this without changing the Sessions/Visitors lists' existing default.

## [0.7.0] - 2026-07-26

### Fixed
- `visit_visitors.user_id`/`visit_sessions.user_id` were `nullableMorphs()`'s default `unsignedBigInteger` — any auth model with a UUID/ULID primary key (`HasUuids`) crashed `RecordVisitJob` on every authenticated request (`invalid input syntax for type bigint`), silently dropping the visit (no retry with the default `tries=1`). Both columns are now plain `string`, same reasoning already applied to `eventable_id` in 0.2.0. **Existing installs**: this only changes the migration for fresh installs — if you already ran it, alter the columns manually (`ALTER TABLE visit_visitors ALTER COLUMN user_id TYPE varchar(255)`, same for `visit_sessions`; adjust syntax for MySQL/SQLite) instead of re-running migrate.

### Changed
- `visit_sessions` gained an index on `(visitor_id, ended_at, last_activity_at)` — the "find this visitor's currently-open session" lookup (`RecordVisitJob::resolveSession()`, `VisitorIdentityMerger`) runs on every single tracked event/action and every `Login`/`identify()` call; the existing `(visitor_id, started_at)` index didn't match its `WHERE`/`ORDER BY` columns at all.
- `visit_events` gained an index on `(type, created_at)` — the dashboard's Top Pages panel filters `type=page_view` over a date range; previously only separate single-column indexes existed for each. **Existing installs**: both are pure additions (no column changes) — a normal `php artisan migrate` picks them up; only installs that already ran these migrations before this version need to add the two indexes manually (see the two `create_visit_sessions_table`/`create_visit_events_table` migrations for the exact column lists).
- **Breaking (pre-1.0, no production installs yet):** the three follow-up "add column" migrations (`search_term`, `route_name`, `path`) are merged back into their respective `create_visit_*_table` migrations — fewer migration files, one schema per table to read instead of a create + a trail of alters. Anything that already ran the old migration files must drop the `visit_*` tables and re-migrate from scratch; the old migration files no longer exist, so `php artisan migrate` alone won't reconcile an existing install.

## [0.6.0] - 2026-07-26

### Added
- `TokenResolver::resolve()` now falls back to the authenticated request's own known `Visitor` (via `HasVisits::visitorProfiles()` on the auth model) before generating a brand-new one — no new parameter, applies automatically across all three entry points. Fixes identity fragmentation for Bearer-token APIs (Sanctum personal access tokens, `supports_credentials: false`), where the visits cookie never round-trips cross-origin: every `Visits::track()` call for an already-known, logged-in user (a purchase, a profile update, ...) previously spawned a disconnected anonymous `Visitor` instead of reconnecting to theirs. Checked after `inheritFrom` — an explicit choice still wins over this implicit one.
- `Visits::identify($user)` — links the current request's `Visitor` to `$user`, the same merge `MergeVisitorIdentity` performs on Laravel's own `Login` event, but for identity established *without* an actual login (a guest checkout matching/creating a `User` by email or phone, for example). Deliberately not implemented as a fake `Login` dispatch — that would mislead any other `Login` listener a host app adds later into treating a form submission as an authenticated sign-in. `MergeVisitorIdentity`'s merge logic is extracted into a new `Support\VisitorIdentityMerger`, shared by both the `Login` listener and `identify()`.

## [0.5.0] - 2026-07-26

### Added
- `Visits::track()`/`VisitsManager::track()` gained an `?string $inheritFrom` parameter — when the current request carries no identity signal of its own (no header, no cookie; typically a payment-gateway webhook), the visitor is inherited from `$eventable`'s own prior event with that name instead of misattributing to a brand-new visitor. Never overrides a real request-derived identity.
- `TokenResolver::resolve()` gained an optional `?callable $fallback` third parameter (consulted only when header/input and cookie both come up empty), which `inheritFrom` above is built on
- README/README.uk: worked examples for reading package data back through `HasVisits` — `visitor`/`session`/attribution/geo/device off an eventable model's own events (`Order`), and cross-device aggregates off the auth model (`User`'s `visitorProfiles`)
- README/README.uk: JS-beacon variant of the "order paid" step in the funnel example (a payment-gateway "thank you" redirect page instead of a server-to-server webhook), with the `eventable`/reliability trade-offs spelled out

### Fixed
- README/README.uk's `latestVisitEvent()` example was missing `->first()` — the method returns a `MorphOne` relation (query builder), not the `Event` model directly

## [0.4.0] - 2026-07-26

### Added
- `docs/client-integration.md`: handing off an already-existing identifier from another system (a separate landing page, a legacy tracker's `anon_id`) to become the same `Visitor` from the first hit on the main site — via `?visitor_id=...` on the handoff link, or a shared cookie on a common registrable domain
- `visits.visitor_id.format_regex` — the accepted client-supplied identifier format is now configurable (previously hardcoded to `^[a-zA-Z0-9]{20,64}$`), so identifiers like UUIDs from another system can be accepted without normalizing them first
- `visits.auto_track` (default `true`) — set to `false` to stop `TrackVisit` from auto-attaching to the global `web` middleware group (denylist via `exclude_paths`) and instead attach the `track-visits` alias to only the specific routes you want tracked (allowlist)
- `Event.path` — the `url` column's path component only (no query string), computed once at write time
- `docs/client-integration.md`: full-page HTTP caching (nginx `fastcgi_cache`/`proxy_cache`) — why `TrackVisit` undercounts on cache HITs, the cached-`Set-Cookie` identity-leak risk, using the JS beacon for cached routes instead, and the cached-CSRF-meta-tag failure mode
- README/README.uk: expanded "Geo & Device Detection" with the exact field list captured — core columns vs `geo_meta`/`device_meta` JSON bucket contents, the `Visitor` (last-known, mutable) vs `Session` (immutable snapshot) split, and where bot detail (`bot_name`/`bot_category`) actually lives
- README/README.uk: "When to Use This (vs GA4, Plausible, Matomo)" — honest positioning, not a feature-count comparison
- `docs/architecture.md` — contributor-level internals: what differs between the three entry points, the full `RecordVisitJob` sequence, the data model (ER diagram), every Support class's single responsibility, and the events/listeners table
- README/README.uk: a "Contents" table of links at the top — both had grown to ~20 top-level sections
- README/README.uk: Mermaid flowchart in "How It Works" showing the sync/async boundary (token resolve + cookie queue happen inline; bot/geo/device detection and all DB writes happen in the queued `RecordVisitJob`) — GitHub renders it natively

### Changed
- **Breaking:** the client-facing visitor identifier is renamed from "token" to "id" throughout, to stop implying an auth/security artifact for what is an unauthenticated, spoofable, attribution-only value — `X-Visitor-Token` header → `X-Visitor-Id`, `visitor_token` input/JSON key → `visitor_id`, `visits_token` cookie name → `visits_visitor_id`. Update any client code that reads/sends the old names.
- Overview's "Top Pages" panel now groups by `Event.path` instead of the raw `url` — a page with filter/sort query-string params (e.g. a product catalog) previously fragmented into one row per parameter combination; now it counts as one page. Events recorded before this change have `path = null` and are excluded from the panel (not backfilled).
- README/README.uk: dashboard screenshot moved from deep inside the "Dashboard" section up to right under the intro paragraph, so it's visible without scrolling past a dozen other sections first
- README/README.uk: "When to Use This" moved from right after Features down to just before Security Considerations — a decision-support/comparison section, better placed after the reader has actually seen what the package does than blocking the install/usage flow at the top
- README/README.uk/composer.json: intro description rewritten — the package has grown well past "pageview tracking" (campaign attribution, conversions on your own models, live map, rollup analytics, a full dashboard); the one-liner now says so instead of underselling it

## [0.3.0] - 2026-07-26

### Added
- Overview dashboard: "Top pages" panel (most-visited URLs in the selected range) and an "online now" indicator (open sessions active within `dashboard.online_window_minutes`, default 5)
- `Visitor.search_term`/`Session.search_term` — organic search keyword extracted from a known search engine's referrer URL (`config('visits.search_engines')`); first-touch on Visitor, last-touch on Session, same pattern as UTM
- `visits.exclude_ips` — literal IPs and/or CIDR ranges (IPv4/IPv6) never tracked regardless of entry point, checked centrally in `RecordVisitJob`
- `Event.route_name` — the matched Laravel route name for automatic page views and server-triggered `Visits::track()` calls, alongside the existing raw `url` column (`null` for `POST /visits/collect`, whose own route is never the page being reported)
- `visits.dashboard.top_pages_limit` config key
- README/README.uk: how to switch `stevebauman/location` to the local MaxMind (GeoLite2) driver, and what the "Breakdown by: Sessions vs Conversions" toggle actually counts

## [0.2.0] - 2026-07-26

### Added
- Full `README.md` + `README.uk.md` + `docs/client-integration.md` — installation, features, API-only/decoupled SPA/mobile/cross-origin/mixed web+API integration guides, security considerations
- `LICENSE.md` (MIT)
- `CHANGELOG.md`
- Dashboard screenshots + animated GIF (`art/`)
- `VisitorCreated`, `SessionStarted`, `VisitorIdentified` events
- Session-locations map on the Overview dashboard page (Leaflet, marker clustering with dynamic size/color thresholds, fullscreen toggle)
- Live activity page (`/visits/live`) — recent events as fading pulse markers on a map, poll or Server-Sent Events transport (`visits.live.*` config), plus a scrolling event log linking back to each session
- Campaigns dashboard page (`/visits/campaigns`) — all UTM/`ref` dimensions in one place
- `visits.collect.middleware` — customize middleware for `POST /visits/collect` independently of the rest of the app (API-only backends, decoupled SPAs, mobile apps)
- `visits.collect.allowed_origins` — optional server-side `Origin`/`Referer` allowlist for the collect endpoint
- `visits.schedule.enabled` (default `true`) — auto-registers `visits:close-stale-sessions`/`visits:aggregate` on a fixed schedule
- `visits.rate_limit.whoami` — dedicated throttle for the public `/visits/whoami` endpoint
- GTM `dataLayer`-style `window.VisitsQueue` buffer in `visits.js`, safe to push to before the script has loaded
- `visits.dashboard.default_range_days`, `map_tile_url`, `map_marker_limit` config keys
- `DefaultUserDisplayNameResolver` and a single `user_display_resolver` config knob (replaces the old `user_display_attribute`/`user_display_resolver` pair)
- Realistic demo data: `HasDemoLocations` trait + `demo-locations.json` (~50 real cities) so factory-generated geo/country/coordinates are coherent

### Changed
- **Breaking:** `Event.action` renamed to `Event.name` throughout (DB column, DTO, job, manager, facade, controller, `HasVisits::latestVisitEvent()`, config, dashboard views, JS beacon)
- **Breaking:** `visit_events.eventable_id` changed from `unsignedBigInteger` to `string`, so eventable models with non-bigint primary keys (UUID/ULID) are supported
- Migration column order standardized (id → types → content/bool → json → timestamps → soft-deletes → relations/FK → indexes)
- `config/visits.php` comments reformatted to Laravel's standard boxed-header style
- `TrackVisit` no longer tracks the package's own dashboard/whoami routes — previously, browsing `/visits` itself generated page-view rows about viewing the dashboard
- `/visits/whoami`'s `geo` field is `null` (not `[]`) when the lookup fails, distinct from `tracking_params.utm`/`.extra` which stay `{}` when legitimately empty

### Fixed
- `HasVisits::latestVisitEvent()` — the `ofMany` name filter is now applied to the subquery itself, not just the outer query, so "latest" is computed among matching-name rows instead of always resolving to the globally-latest event
- `VisitorFactory`/`SessionFactory` generated geographically incoherent demo data (country/city/coordinates picked independently)

## [0.1.0] - 2026-07-25

Initial release: async-first visitor/session/pageview tracking, cookie + client-token identity, geo/device/bot detection, UTM attribution, custom conversion events, rollup analytics, and a built-in dashboard (Overview, Sessions, Visitors, per-record detail).
