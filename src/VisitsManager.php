<?php

declare(strict_types=1);

namespace Fomvasss\Visits;

use Fomvasss\Visits\Jobs\RecordVisitJob;
use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Support\PayloadBuilder;
use Fomvasss\Visits\Support\RequestInspector;
use Fomvasss\Visits\Support\TokenResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Entry point for custom actions from host application code:
 *
 *   Visits::track('order.placed', $order, ['amount' => $order->total]);
 *
 * Goes through the same async pipeline as page views — resolves the visitor token from the
 * current request (cookie/client header), attaches to whichever Session is currently open.
 */
class VisitsManager
{
    public function __construct(
        private readonly Request $request,
        private readonly TokenResolver $tokenResolver,
        private readonly PayloadBuilder $payloadBuilder,
        private readonly RequestInspector $requestInspector,
    ) {
    }

    public function track(string $action, ?Model $eventable = null, ?array $meta = null): void
    {
        if (! config('visits.enabled', true)) {
            return;
        }

        $token = $this->tokenResolver->resolve($this->request);

        // the calling route may not have gone through TrackVisit (e.g. a POST-only checkout
        // route, which the middleware never tracks) — queue the cookie here too so a
        // first-ever interaction that happens to be a custom action still gets one.
        Cookie::queue(
            (string) config('visits.cookie.name'),
            $token,
            (int) config('visits.cookie.ttl_minutes'),
        );

        $payload = $this->payloadBuilder->build(
            request: $this->request,
            token: $token,
            type: Event::TYPE_ACTION,
            action: $action,
            eventable: $eventable,
            meta: $meta,
        );

        RecordVisitJob::dispatch($payload)
            ->onConnection(config('visits.queue.connection'))
            ->onQueue(config('visits.queue.queue'));
    }

    /**
     * Read-only "what do we see about the current request" snapshot — IP/geo/device/bot/UTM
     * detection, same pipeline RecordVisitJob uses. Writes nothing, sets no cookie. Useful for
     * host code (or another project depending on this package) that wants this detection
     * without going through the /visits/whoami HTTP endpoint.
     *
     * @param  string|null  $ip  look up geo for this IP instead of the request's own
     * @return array<string, mixed>
     */
    public function whoami(?Request $request = null, ?string $ip = null): array
    {
        return $this->requestInspector->inspect($request ?? $this->request, $ip);
    }
}
