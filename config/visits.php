<?php

declare(strict_types=1);

return [

    'enabled' => env('VISITS_ENABLED', true),

    // Override any model with your own subclass (add scopes/traits/relations — e.g. a
    // multi-tenancy global scope on top of tenant_id) without forking the package. Every
    // internal relation and write path resolves models through this config, so an override
    // is picked up consistently everywhere, not just where rows get created.
    'models' => [
        'visitor' => \Fomvasss\Visits\Models\Visitor::class,
        'session' => \Fomvasss\Visits\Models\Session::class,
        'event' => \Fomvasss\Visits\Models\Event::class,
        'stat_daily' => \Fomvasss\Visits\Models\StatDaily::class,
    ],

    // Queue connection/name used to dispatch RecordVisitJob.
    'queue' => [
        'connection' => env('VISITS_QUEUE_CONNECTION', config('queue.default')),
        'queue' => env('VISITS_QUEUE', 'default'),
    ],

    // Visitor identity cookie.
    'cookie' => [
        'name' => 'visits_token',
        'ttl_minutes' => 60 * 24 * 365 * 2, // 2 years
    ],

    // If true, Visitor.user_id/user_type is set to null on Logout (shared/kiosk devices).
    // Default false — preserves attribution continuity for the typical single-user device.
    'reset_identity_on_logout' => false,

    // Minutes of inactivity after which a Session is considered closed by visits:close-stale-sessions.
    'session_timeout_minutes' => 30,

    // Routes the TrackVisit middleware never tracks (path patterns, same syntax as Route::is()).
    'exclude_paths' => [
        'admin/*',
        '_debugbar/*',
        'horizon/*',
        'up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking params (UTM / ref / extra_params)
    |--------------------------------------------------------------------------
    |
    | core: column => query parameter name. Real, indexed columns on Visitor/Session.
    | extra_keys: query params always captured into the extra_params json bucket.
    | extra_pattern: optional regex — any query param matching it (and not in core)
    |   is also captured into extra_params. Set to null to disable.
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
            'gclid', 'fbclid', 'msclkid', 'ttclid', 'yclid', 'twclid', 'li_fat_id',
        ],
        'extra_pattern' => null,
    ],

    'geo' => [
        // Cache resolved geo data by IP to avoid repeated mmdb reads.
        'cache_ttl' => 60 * 60 * 24, // 1 day, seconds
        // Whether to store lat/lng (approximate, city-level). Disable for privacy-conscious deployments.
        'store_coordinates' => true,
    ],

    'device_detection' => [
        // Cache directory for matomo/device-detector's compiled regex rules.
        'cache_dir' => storage_path('framework/cache/device-detector'),
    ],

    'rate_limit' => [
        // Throttle on POST /visits/collect, keyed by IP. Laravel throttle format: "max,decay_minutes".
        'endpoint' => '60,1',
        // Per-visitor soft-cap enforced inside RecordVisitJob, keyed by visitor token.
        'visitor_budget' => '120,1',
    ],

    // Raw visit_events/visit_sessions/visit_visitors older than this are eligible for visits:prune.
    // Pruning is never automatic — the host schedules visits:prune explicitly.
    'retention_days' => 90,

    'aggregate' => [
        'dimensions' => [
            'utm_source', 'utm_medium', 'utm_campaign', 'referrer_host',
            'country_code', 'device_type', 'browser', 'client_type', 'action',
        ],
    ],

    'consent' => [
        'require_consent' => false,
        // Fully-qualified class implementing Fomvasss\Visits\Contracts\ConsentResolverInterface.
        'resolver' => null,
    ],

    'dashboard' => [
        'enabled' => env('VISITS_DASHBOARD_ENABLED', true),
        'path' => env('VISITS_DASHBOARD_PATH', 'visits'),
        'middleware' => ['web'],
    ],

    // Generic tenant/multi-domain marker, nullable. The package never filters by it on its own —
    // the host app is responsible for setting and scoping it if needed.
    'tenant_resolver' => null,
];
