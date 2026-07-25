<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Database\Factories\Concerns\HasDemoLocations;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\TestCase;

class HasDemoLocationsTest extends TestCase
{
    use HasDemoLocations;

    private function assertCoordinatesMatchAKnownCityFor(string $countryCode, float $lat, float $lng): void
    {
        $candidates = collect($this->demoLocations())->where('country_code', $countryCode);

        $this->assertTrue(
            $candidates->contains(fn ($city) => abs($city['lat'] - $lat) < 0.5 && abs($city['lng'] - $lng) < 0.5),
            "no known city for {$countryCode} is near ({$lat}, {$lng})"
        );
    }

    public function test_visitor_factory_coordinates_match_a_known_city_for_its_country(): void
    {
        $visitors = Visitor::factory()->count(80)->create();

        foreach ($visitors as $visitor) {
            $this->assertCoordinatesMatchAKnownCityFor($visitor->country_code, (float) $visitor->lat, (float) $visitor->lng);
        }
    }

    public function test_session_factory_coordinates_match_a_known_city_for_its_country(): void
    {
        $sessions = Session::factory()->count(80)->create();

        foreach ($sessions as $session) {
            $this->assertCoordinatesMatchAKnownCityFor($session->country_code, (float) $session->lat, (float) $session->lng);
        }
    }
}
