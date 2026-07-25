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

        $token = $this->tokenResolver->resolve($request);

        Cookie::queue(
            (string) config('visits.cookie.name'),
            $token,
            (int) config('visits.cookie.ttl_minutes'),
        );

        $payload = $this->payloadBuilder->build($request, $token, Event::TYPE_PAGE_VIEW);

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

    private function hasConsent(Request $request): bool
    {
        $resolverClass = config('visits.consent.resolver');

        if (! $resolverClass) {
            return false;
        }

        return app($resolverClass)->hasConsent($request);
    }
}
