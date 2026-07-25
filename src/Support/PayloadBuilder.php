<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use Fomvasss\Visits\DTO\VisitPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Single builder shared by the page-view middleware, the /visits/collect ingest endpoint
 * (JS beacon + custom actions posted from JS) and Visits::track() (custom actions from PHP) —
 * one place assembling the payload so all three entry points stay consistent.
 */
class PayloadBuilder
{
    public function __construct(
        private readonly TrackingParamsExtractor $paramsExtractor,
        private readonly SearchTermExtractor $searchTermExtractor,
        private readonly LocaleResolver $localeResolver,
    ) {
    }

    public function build(
        Request $request,
        string $token,
        string $type,
        ?string $name = null,
        ?Model $eventable = null,
        ?array $meta = null,
        ?string $url = null,
    ): VisitPayload {
        $locale = $this->localeResolver->resolve($request);
        $authUser = $request->user();
        $referrer = $request->header('referer');

        return new VisitPayload(
            token: $token,
            type: $type,
            name: $name,
            // explicit $url wins — the /visits/collect endpoint's own URL is never the page the
            // JS beacon is reporting (SPA route change), the client sends the real one instead.
            url: $url ?? $request->fullUrl(),
            // same reasoning: only capture the *current* request's matched route when nothing
            // overrode the URL — otherwise this would resolve to visits.collect itself, not the
            // page the client is reporting.
            routeName: $url === null ? $request->route()?->getName() : null,
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
            referrer: $referrer,
            searchTerm: $this->searchTermExtractor->extract($referrer),
            utm: $this->paramsExtractor->extractCore($request),
            extraParams: $this->paramsExtractor->extractExtra($request),
            locale: $locale['locale'],
            browserLanguage: $locale['browser_language'],
            authUserType: $authUser ? $authUser::class : null,
            authUserId: $authUser?->getAuthIdentifier(),
            eventableType: $eventable ? $eventable::class : null,
            eventableId: $eventable?->getKey(),
            meta: $meta,
        );
    }
}
