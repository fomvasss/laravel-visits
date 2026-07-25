# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
