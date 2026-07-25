<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for the whole package — tracking middleware, listeners,
    | dashboard, whoami endpoint. Set to false to disable everything without
    | uninstalling the package.
    |
    */

    'enabled' => env('VISITS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Model Overrides
    |--------------------------------------------------------------------------
    |
    | Override any model with your own subclass (add scopes/traits/relations —
    | e.g. a multi-tenancy global scope on top of tenant_id) without forking
    | the package. Every internal relation and write path resolves models
    | through this config, so an override is picked up consistently
    | everywhere, not just where rows get created.
    |
    */

    'models' => [
        'visitor' => \Fomvasss\Visits\Models\Visitor::class,
        'session' => \Fomvasss\Visits\Models\Session::class,
        'event' => \Fomvasss\Visits\Models\Event::class,
        'stat_daily' => \Fomvasss\Visits\Models\StatDaily::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Connection/queue name used to dispatch RecordVisitJob, the job that
    | does all the heavy lifting (bot/geo detection, DB writes) off the
    | request/response cycle.
    |
    */

    'queue' => [
        'connection' => env('VISITS_QUEUE_CONNECTION', config('queue.default')),
        'queue' => env('VISITS_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Visitor Cookie
    |--------------------------------------------------------------------------
    |
    | The durable identity cookie set on the visitor's browser. TTL is long
    | by design — this is a returning-visitor identifier, not a session
    | cookie.
    |
    */

    'cookie' => [
        'name' => 'visits_token',
        'ttl_minutes' => 60 * 24 * 365 * 2, // 2 years
    ],

    /*
    |--------------------------------------------------------------------------
    | Reset Identity On Logout
    |--------------------------------------------------------------------------
    |
    | If true, Visitor.user_id/user_type is set to null on Logout — useful
    | for shared/kiosk devices. Default false preserves attribution
    | continuity for the typical single-user device.
    |
    */

    'reset_identity_on_logout' => false,

    /*
    |--------------------------------------------------------------------------
    | Session Timeout
    |--------------------------------------------------------------------------
    |
    | Minutes of inactivity after which a Session is considered closed by
    | the visits:close-stale-sessions command.
    |
    */

    'session_timeout_minutes' => 30,

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | Routes the TrackVisit middleware never tracks (path patterns, same
    | syntax as Route::is()).
    |
    */

    'exclude_paths' => [
        'admin/*',
        '_debugbar/*',
        'horizon/*',
        'up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded IPs
    |--------------------------------------------------------------------------
    |
    | Never tracked regardless of entry point (automatic page view, JS beacon,
    | or a server-side Visits::track() call) — typically the office/internal
    | network. Literal IPs and/or CIDR ranges, IPv4 or IPv6:
    | ['203.0.113.42', '198.51.100.0/24', '2001:db8::/32'].
    |
    */

    'exclude_ips' => [],

    /*
    |--------------------------------------------------------------------------
    | Tracking Params (UTM / ref / extra_params)
    |--------------------------------------------------------------------------
    |
    | core: column => query parameter name. Real, indexed columns on
    |   Visitor/Session.
    | extra_keys: query params always captured into the extra_params json
    |   bucket — ad-platform click IDs by default (see below). High-cardinality
    |   and platform-specific, not worth a dedicated column each, but worth
    |   keeping: the practical use is sending the ID back to that platform's
    |   own conversion API (e.g. Google Ads Enhanced Conversions) so it can
    |   match the conversion to the ad/campaign that drove it.
    | extra_pattern: optional regex (full PCRE pattern with delimiters, matched
    |   with preg_match) — any query param whose NAME matches it, and isn't
    |   already in core, is also captured into extra_params. Set to null to
    |   disable (the default — extra_keys above already covers the common
    |   ad platforms). Example: '/^aff_/' would capture every "aff_*" param
    |   (aff_id, aff_sub, ...) used by an affiliate network of your own that
    |   isn't one of the platforms extra_keys already lists.
    |
    */

    'tracking_params' => [
        'core' => [
            'utm_source' => 'utm_source',
            'utm_medium' => 'utm_medium',
            'utm_campaign' => 'utm_campaign',
            'utm_term' => 'utm_term',
            'utm_content' => 'utm_content',
            'ref' => 'ref',
        ],
        'extra_keys' => [
            'gclid',     // Google Ads
            'fbclid',    // Meta / Facebook Ads
            'msclkid',   // Microsoft / Bing Ads
            'ttclid',    // TikTok Ads
            'yclid',     // Yandex Direct
            'twclid',    // Twitter / X Ads
            'li_fat_id', // LinkedIn Ads
        ],
        'extra_pattern' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Engines
    |--------------------------------------------------------------------------
    |
    | Organic search keyword extraction — not part of tracking_params above,
    | since it reads the *referrer* URL's query string, not the current
    | request's own. host_contains: query_param — any referrer whose host
    | contains the key has its keyword captured from that query param, into
    | Visitor.search_term (first-touch) / Session.search_term (last-touch,
    | same pattern as UTM). Most search engines stop sending a real referrer
    | over HTTPS-to-HTTPS navigation ("keyword not provided") — this only
    | catches the cases where one still arrives.
    |
    */

    'search_engines' => [
        'google.' => 'q',
        'bing.com' => 'q',
        'duckduckgo.com' => 'q',
        'search.yahoo.com' => 'p',
        'yandex.' => 'text',
    ],

    /*
    |--------------------------------------------------------------------------
    | Geo
    |--------------------------------------------------------------------------
    |
    | Geo lookups go through stevebauman/location (driver configured in the
    | host app's own config/location.php). cache_ttl avoids repeating a
    | lookup for the same IP on every request.
    |
    */

    'geo' => [
        'cache_ttl' => 60 * 60 * 24, // 1 day, seconds
        // Whether to store lat/lng (approximate, city-level). Disable for privacy-conscious deployments.
        'store_coordinates' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Detection
    |--------------------------------------------------------------------------
    |
    | matomo/device-detector compiles its regex rules on first use and
    | caches the result here.
    |
    */

    'device_detection' => [
        'cache_dir' => storage_path('framework/cache/device-detector'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Three independent layers, all in Laravel throttle format
    | ("max,decay_minutes"):
    | endpoint: throttles POST /visits/collect, keyed by IP.
    | visitor_budget: per-visitor soft-cap enforced inside RecordVisitJob,
    |   keyed by visitor token — catches server-side/middleware tracking,
    |   which has no HTTP throttle route.
    | whoami: throttles GET /visits/whoami, keyed by IP — public and
    |   unauthenticated by design, so unlike collect it has no per-token
    |   budget to fall back on.
    |
    */

    'rate_limit' => [
        'endpoint' => '60,1',
        'visitor_budget' => '120,1',
        'whoami' => '60,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Collect Endpoint
    |--------------------------------------------------------------------------
    |
    | middleware: for POST /visits/collect (the JS beacon / custom
    |   client-side actions ingest route). Defaults to 'web' for the common
    |   case: a Blade/session app where the beacon runs same-origin. For an
    |   API-only backend or a decoupled SPA/mobile client on a different
    |   origin, swap this to something without CSRF/session (e.g. ['api']) —
    |   the token flow (X-Visitor-Token header in, visitor_token in the JSON
    |   body out) already works without cookies; only the 'web' group's CSRF
    |   check gets in the way. The throttle from rate_limit.endpoint above is
    |   appended automatically, no need to repeat it here.
    | allowed_origins: null (default) accepts requests from anywhere. Set to
    |   an array (e.g. ['https://example.com']) to reject any request whose
    |   Origin (falling back to Referer's scheme+host when Origin is absent)
    |   isn't in the list. This is CORS's server-side counterpart, not a
    |   replacement for it — CORS controls what a *browser* will let a page
    |   fetch cross-origin; this controls what the *server* accepts,
    |   regardless of client. Both headers are attacker-controlled and
    |   trivially spoofed by any non-browser client (curl, a script), so
    |   treat this as filtering casual/accidental misuse (a copy of the
    |   beacon left on a decommissioned domain, a stray embed), never as
    |   authentication.
    |
    */

    'collect' => [
        'middleware' => ['web'],
        'allowed_origins' => null,
        // 'allowed_origins' => ['https://example.com', 'https://app.example.com'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | On by default — the service provider registers visits:close-stale-sessions
    | and visits:aggregate itself, on the fixed frequencies documented in the
    | README's "Scheduling" section, so a fresh install keeps the dashboard's
    | rollups current and closes stale sessions without any routes/console.php
    | edits. Turn off if you want different frequencies, or already added
    | these to your own scheduler — this would otherwise run them twice.
    |
    | visits:prune is deliberately never included here even when enabled —
    | it deletes rows, and opting into that should always be a separate,
    | explicit decision (add it to your own scheduler when you're ready).
    |
    */

    'schedule' => [
        'enabled' => env('VISITS_SCHEDULE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Raw visit_events/visit_sessions/visit_visitors older than this are
    | eligible for visits:prune. Pruning is never automatic — the host
    | schedules visits:prune explicitly.
    |
    */

    'retention_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Aggregate Dimensions
    |--------------------------------------------------------------------------
    |
    | Dimensions visits:aggregate breaks visit_stats_daily rollups down by,
    | in addition to the always-present total ('' dimension) row.
    |
    */

    'aggregate' => [
        'dimensions' => [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref', 'referrer_host',
            'country_code', 'device_type', 'browser', 'client_type', 'name',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent
    |--------------------------------------------------------------------------
    |
    | When require_consent is true, TrackVisit checks resolver — a
    | fully-qualified class implementing
    | Fomvasss\Visits\Contracts\ConsentResolverInterface — before tracking
    | a page view.
    |
    */

    'consent' => [
        'require_consent' => false,
        'resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | The built-in /visits dashboard (Overview, Campaigns, Sessions,
    | Visitors, session/visitor detail, Whoami). No auth by default —
    | middleware should include auth/can: in production.
    |
    */

    'dashboard' => [
        'enabled' => env('VISITS_DASHBOARD_ENABLED', true),
        'path' => env('VISITS_DASHBOARD_PATH', 'visits'),
        'middleware' => ['web'],
        'per_page' => env('VISITS_DASHBOARD_PER_PAGE', 50),
        // Overview/Campaigns date range when no ?from=/?to= is given.
        'default_range_days' => env('VISITS_DASHBOARD_DEFAULT_RANGE_DAYS', 30),
        // Overview's session map (Leaflet). Default tile server is OpenStreetMap's own
        // (fine for light internal-dashboard traffic) — swap to a paid provider (Mapbox,
        // MapTiler, ...) if this dashboard gets heavy use, per OSM's tile usage policy.
        'map_tile_url' => env('VISITS_DASHBOARD_MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        // Max markers rendered — capped so a busy site doesn't try to plot thousands at once.
        'map_marker_limit' => env('VISITS_DASHBOARD_MAP_MARKER_LIMIT', 300),
        // Overview's "Top Pages" panel — how many of the most-visited URLs to show.
        'top_pages_limit' => env('VISITS_DASHBOARD_TOP_PAGES_LIMIT', 10),
        // Overview's "online now" count — a session counts as online if still open
        // (ended_at is null) and active within this many minutes.
        'online_window_minutes' => env('VISITS_DASHBOARD_ONLINE_WINDOW_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Live Activity Page
    |--------------------------------------------------------------------------
    |
    | The /visits/live page — recent events shown as fading pulses on a map.
    | Not true real-time either way: events go through a queue before landing
    | in the database, so a pulse reflects "recently processed", not the
    | instant it happened.
    |
    | transport:
    |   'poll' (default) — the browser fetches /visits/live/feed every
    |     poll_interval_ms. Works anywhere, costs one short request per
    |     interval per open tab.
    |   'sse' — the browser opens one long-lived connection to
    |     /visits/live/stream (Server-Sent Events) and the server pushes
    |     updates as they're found. Lower latency, no wasted "nothing new"
    |     requests — but the connection holds one PHP-FPM worker per open tab
    |     for sse_max_duration seconds (the browser's EventSource reconnects
    |     automatically after that). Only enable this if your hosting can
    |     afford held-open connections (e.g. a generous FPM pool, or Octane).
    |
    */

    'live' => [
        'enabled' => env('VISITS_LIVE_ENABLED', true),
        'transport' => env('VISITS_LIVE_TRANSPORT', 'poll'), // poll | sse
        // How often the browser polls the feed endpoint (transport=poll).
        'poll_interval_ms' => env('VISITS_LIVE_POLL_INTERVAL_MS', 10000),
        // Max events returned per poll/push.
        'feed_limit' => env('VISITS_LIVE_FEED_LIMIT', 50),
        // How often the server checks for new events within one SSE connection (transport=sse).
        'sse_check_interval' => env('VISITS_LIVE_SSE_CHECK_INTERVAL', 2),
        // How long one SSE connection stays open before the server closes it and the browser's
        // EventSource reconnects — keeps any single PHP-FPM worker from being held forever.
        'sse_max_duration' => env('VISITS_LIVE_SSE_MAX_DURATION', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Whoami Endpoint
    |--------------------------------------------------------------------------
    |
    | Public, read-only ifconfig.me-style endpoint (IP/geo/device/UTM
    | detection for the current request) — writes nothing, sets no cookie.
    | Separate path/middleware from the dashboard on purpose: other
    | projects/services should be able to call this even when the dashboard
    | itself is locked behind auth.
    |
    */

    'whoami' => [
        'enabled' => env('VISITS_WHOAMI_ENABLED', true),
        'path' => env('VISITS_WHOAMI_PATH', 'visits/whoami'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolver
    |--------------------------------------------------------------------------
    |
    | Generic tenant/multi-domain marker, nullable. The package never
    | filters by it on its own — the host app is responsible for setting
    | and scoping it if needed.
    |
    */

    'tenant_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | User Display Name
    |--------------------------------------------------------------------------
    |
    | user_type/user_id is polymorphic — could be App\Models\User, Admin,
    | Client, anything the host uses — so the package can't assume a field
    | name for "the user's display name". A class-string implementing
    | Fomvasss\Visits\Contracts\UserDisplayNameResolverInterface — never a
    | Closure, a Closure can't survive `php artisan config:cache`.
    |
    | Defaults to the package's own resolver (tries `name`, falls back to
    | `email`, then null). Point this at your own class for anything else:
    | a different single attribute, combining fields, calling a
    | fullname()-style accessor, etc.
    |
    */

    'user_display_resolver' => \Fomvasss\Visits\Support\Resolvers\DefaultUserDisplayNameResolver::class,

];
