<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Support\GeoResolver;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

class GeoResolverTest extends TestCase
{
    public function test_resolves_core_and_meta_fields_from_a_hit(): void
    {
        Location::fake([
            '1.2.3.4' => Position::make([
                'driver' => 'ip-api',
                'country_code' => 'UA',
                'region_name' => 'Kyiv',
                'city_name' => 'Kyiv',
                'timezone' => 'Europe/Kyiv',
                'latitude' => '50.45',
                'longitude' => '30.52',
                'country_name' => 'Ukraine',
                'currency_code' => 'UAH',
                'region_code' => '30',
                'zip_code' => '01001',
                'postal_code' => '01001',
            ]),
        ]);

        $geo = (new GeoResolver())->resolve('1.2.3.4');

        $this->assertSame('UA', $geo['country_code']);
        $this->assertSame('Kyiv', $geo['region']);
        $this->assertSame('Kyiv', $geo['city']);
        $this->assertSame('Europe/Kyiv', $geo['timezone']);
        $this->assertSame('50.45', $geo['lat']);
        $this->assertSame('30.52', $geo['lng']);
        $this->assertSame('Ukraine', $geo['country_name']);
        $this->assertSame('UAH', $geo['currency_code']);
        $this->assertSame('ip-api', $geo['driver']);
    }

    public function test_returns_empty_array_on_miss(): void
    {
        Location::fake([]);

        $geo = (new GeoResolver())->resolve('9.9.9.9');

        $this->assertSame([], $geo);
    }

    public function test_returns_empty_array_when_driver_throws(): void
    {
        // Position::$driver is a non-nullable typed property — accessing it before
        // initialization throws an Error. GeoResolver must degrade gracefully, never bubble up.
        Location::fake([
            '1.2.3.4' => Position::make(['country_code' => 'UA']),
        ]);

        $geo = (new GeoResolver())->resolve('1.2.3.4');

        $this->assertSame([], $geo);
    }

    public function test_omits_coordinates_when_store_coordinates_disabled(): void
    {
        config(['visits.geo.store_coordinates' => false]);

        Location::fake([
            '1.2.3.4' => Position::make(['driver' => 'ip-api', 'country_code' => 'UA', 'latitude' => '50.45', 'longitude' => '30.52']),
        ]);

        $geo = (new GeoResolver())->resolve('1.2.3.4');

        $this->assertArrayNotHasKey('lat', $geo);
        $this->assertArrayNotHasKey('lng', $geo);
    }

    public function test_caches_successful_lookup_for_configured_ttl(): void
    {
        Cache::spy();

        Location::fake([
            '1.2.3.4' => Position::make(['driver' => 'ip-api', 'country_code' => 'UA']),
        ]);

        (new GeoResolver())->resolve('1.2.3.4');

        Cache::shouldHaveReceived('put')
            ->withArgs(fn ($key, $value, $ttl) => $key === 'visits:geo:1.2.3.4' && $ttl === (int) config('visits.geo.cache_ttl'))
            ->once();
    }

    public function test_caches_a_miss_briefly(): void
    {
        Cache::spy();

        Location::fake([]);

        (new GeoResolver())->resolve('9.9.9.9');

        Cache::shouldHaveReceived('put')
            ->withArgs(fn ($key, $value, $ttl) => $key === 'visits:geo:9.9.9.9' && $ttl === 300)
            ->once();
    }

    public function test_serves_second_lookup_from_cache_without_calling_driver(): void
    {
        $cached = ['country_code' => 'UA', 'driver' => 'ip-api'];

        Cache::put('visits:geo:1.2.3.4', $cached, 3600);

        $geo = (new GeoResolver())->resolve('1.2.3.4');

        $this->assertSame($cached, $geo);
    }
}
