<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

/**
 * Organic search keyword extraction from a referrer URL — see config('visits.search_engines').
 * Operates on the *referrer*'s query string, not the current request's own, which is why this
 * is separate from TrackingParamsExtractor.
 */
class SearchTermExtractor
{
    public function extract(?string $referrerUrl): ?string
    {
        if (! $referrerUrl) {
            return null;
        }

        $host = parse_url($referrerUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        foreach ((array) config('visits.search_engines', []) as $hostContains => $param) {
            if (! str_contains($host, (string) $hostContains)) {
                continue;
            }

            parse_str((string) parse_url($referrerUrl, PHP_URL_QUERY), $query);
            $term = $query[$param] ?? null;

            return is_string($term) && $term !== '' ? $term : null;
        }

        return null;
    }
}
