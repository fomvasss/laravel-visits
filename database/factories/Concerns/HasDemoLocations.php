<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Database\Factories\Concerns;

/**
 * Shared by VisitorFactory and SessionFactory so demo data looks plausible on the dashboard's
 * session map — country_code, city, timezone and lat/lng must come from the SAME picked location,
 * not independent random calls (that's how you get a "US" row with coordinates in the Indian
 * Ocean). Real city centers, not fake()->latitude()/longitude() — those are uniformly random
 * over the whole globe and land in the ocean/uninhabited areas far more often than not.
 *
 * The actual list lives in data/demo-locations.json (not inline here) — keeps this class short
 * and the data easy to scan/edit on its own.
 */
trait HasDemoLocations
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private static ?array $demoLocationsCache = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function demoLocations(): array
    {
        if (self::$demoLocationsCache === null) {
            $path = __DIR__ . '/../data/demo-locations.json';
            self::$demoLocationsCache = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        }

        return self::$demoLocationsCache;
    }

    /**
     * Small random offset (roughly +/-15km) so visitors in the same city aren't all plotted on
     * the exact same pixel — still close enough to stay on land for every city in the list above.
     */
    protected function jitterCoordinate(float $value): float
    {
        return round($value + fake()->randomFloat(4, -0.15, 0.15), 7);
    }
}
