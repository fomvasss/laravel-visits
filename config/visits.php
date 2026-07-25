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
    | Two independent layers, both in Laravel throttle format
    | ("max,decay_minutes"):
    | endpoint: throttles POST /visits/collect, keyed by IP.
    | visitor_budget: per-visitor soft-cap enforced inside RecordVisitJob,
    |   keyed by visitor token — catches server-side/middleware tracking,
    |   which has no HTTP throttle route.
    |
    */

    'rate_limit' => [
        'endpoint' => '60,1',
        'visitor_budget' => '120,1',
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
            'country_code', 'device_type', 'browser', 'client_type', 'action',
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
