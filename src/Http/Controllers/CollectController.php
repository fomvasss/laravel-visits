<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Http\Controllers;

use Fomvasss\Visits\Jobs\RecordVisitJob;
use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Support\OriginValidator;
use Fomvasss\Visits\Support\PayloadBuilder;
use Fomvasss\Visits\Support\TokenResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;

/**
 * Ingest endpoint for the JS beacon (SPA route changes, client-side custom events) — the
 * client-supplied side of the "resolve visitor token" flow, mirror of TrackVisit middleware.
 * Always returns visitor_id in the JSON body, not only via Set-Cookie, so a cross-origin
 * SPA or a native/mobile client (no reliable cookie jar) can persist it itself.
 */
class CollectController extends Controller
{
    public function __construct(
        private readonly TokenResolver $tokenResolver,
        private readonly PayloadBuilder $payloadBuilder,
        private readonly OriginValidator $originValidator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (! config('visits.enabled', true)) {
            return response()->json(['visitor_id' => null]);
        }

        if (! $this->originValidator->isAllowed($request)) {
            return response()->json(['visitor_id' => null], 403);
        }

        $validated = $request->validate([
            'type' => 'nullable|in:' . Event::TYPE_PAGE_VIEW . ',' . Event::TYPE_ACTION,
            'name' => 'nullable|string|max:255|required_if:type,' . Event::TYPE_ACTION,
            'url' => 'nullable|string|max:2048',
            'meta' => 'nullable|array',
        ]);

        $token = $this->tokenResolver->resolve($request);

        Cookie::queue(
            (string) config('visits.cookie.name'),
            $token,
            (int) config('visits.cookie.ttl_minutes'),
        );

        $payload = $this->payloadBuilder->build(
            request: $request,
            token: $token,
            type: $validated['type'] ?? Event::TYPE_PAGE_VIEW,
            name: $validated['name'] ?? null,
            meta: $validated['meta'] ?? null,
            url: $validated['url'] ?? null,
        );

        RecordVisitJob::dispatch($payload)
            ->onConnection(config('visits.queue.connection'))
            ->onQueue(config('visits.queue.queue'));

        return response()->json(['visitor_id' => $token]);
    }
}
