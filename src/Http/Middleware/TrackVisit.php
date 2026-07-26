<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Http\Middleware;

use Closure;
use Fomvasss\Visits\Jobs\RecordVisitJob;
use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Support\PayloadBuilder;
use Fomvasss\Visits\Support\TokenResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Only does what must happen synchronously: resolve/generate the visitor token and queue the
 * cookie on the response (a job dispatched afterwards can't influence the response). Everything
 * else — geo, device/bot detection, DB writes — happens in RecordVisitJob.
 */
class TrackVisit
{
    public function __construct(
        private readonly TokenResolver $tokenResolver,
        private readonly PayloadBuilder $payloadBuilder,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! $this->shouldTrack($request)) {
            return $next($request);
        }

        $isReturningVisitor = $this->tokenResolver->hasRequestIdentity($request);
        $token = $this->tokenResolver->resolve($request);

        Cookie::queue(
            (string) config('visits.cookie.name'),
            $token,
            (int) config('visits.cookie.ttl_minutes'),
        );

        $recordEvent = ! ($isReturningVisitor && config('visits.page_views', 'every') === 'first_only');

        $payload = $this->payloadBuilder->build($request, $token, Event::TYPE_PAGE_VIEW, recordEvent: $recordEvent);

        RecordVisitJob::dispatch($payload)
            ->onConnection(config('visits.queue.connection'))
            ->onQueue(config('visits.queue.queue'));

        return $next($request);
    }

    private function shouldTrack(Request $request): bool
    {
        if (! config('visits.enabled', true) || ! $request->isMethod('GET')) {
            return false;
        }

        if ($this->isOwnRoute($request)) {
            return false;
        }

        foreach ((array) config('visits.exclude_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        if (config('visits.consent.require_consent') && ! $this->hasConsent($request)) {
            return false;
        }

        return true;
    }

    /**
     * The dashboard/whoami routes run under the 'web' group like any other route (so their own
     * sessions/CSRF/etc. work normally), which would otherwise self-track: browsing your own
     * /visits dashboard would generate page_view rows about viewing the dashboard. Checked
     * against the configured paths directly (not a static default in exclude_paths) so this
     * stays correct even if dashboard.path/whoami.path are customized.
     */
    private function isOwnRoute(Request $request): bool
    {
        $dashboardPath = trim((string) config('visits.dashboard.path', 'visits'), '/');
        $whoamiPath = trim((string) config('visits.whoami.path', 'visits/whoami'), '/');

        return $request->is($dashboardPath)
            || $request->is($dashboardPath . '/*')
            || $request->is($whoamiPath);
    }

    private function hasConsent(Request $request): bool
    {
        $resolverClass = config('visits.consent.resolver');

        if (! $resolverClass) {
            return false;
        }

        return app($resolverClass)->hasConsent($request);
    }
}
