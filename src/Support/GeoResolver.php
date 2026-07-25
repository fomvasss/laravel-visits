<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use Illuminate\Support\Facades\Cache;
use Stevebauman\Location\Facades\Location;

/**
 * Wraps stevebauman/location (configured with the local MaxMind mmdb driver by the host app —
 * see config/location.php published by that package) with per-IP caching and graceful
 * degradation: a lookup failure never throws, it just yields an empty result.
 */
class GeoResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(string $ip): array
    {
        $cacheKey = "visits:geo:{$ip}";

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $data = $this->lookup($ip);

        // Cache failures briefly so a transient provider hiccup gets retried soon,
        // successes for the configured TTL (geo rarely changes fast for a given IP).
        Cache::put($cacheKey, $data, $data === [] ? 300 : (int) config('visits.geo.cache_ttl', 86400));

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function lookup(string $ip): array
    {
        return rescue(function () use ($ip) {
            $position = Location::get($ip);

            if (! $position) {
                return [];
            }

            $data = [
                'country_code' => $position->countryCode ?: null,
                'region' => $position->regionName ?: null,
                'city' => $position->cityName ?: null,
                'timezone' => $position->timezone ?: null,
            ];

            if (config('visits.geo.store_coordinates', true)) {
                $data['lat'] = $position->latitude ?: null;
                $data['lng'] = $position->longitude ?: null;
            }

            return $data;
        }, rescue: [], report: false);
    }
}
