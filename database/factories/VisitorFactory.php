<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Database\Factories;

use Fomvasss\Visits\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    public function definition(): array
    {
        $firstSeen = fake()->dateTimeBetween('-30 days', '-1 hours');
        $utmSource = fake()->optional(0.6)->randomElement(['google', 'facebook', 'newsletter', 'twitter', 'partner_site']);
        $country = fake()->randomElement($this->countries());
        $device = fake()->randomElement($this->devices());

        return [
            'token' => Str::random(40),
            'tenant_id' => '',
            'first_seen_at' => $firstSeen,
            'last_seen_at' => $firstSeen,
            'first_landing_url' => fake()->randomElement([
                'https://example.test/', 'https://example.test/pricing',
                'https://example.test/blog/post-1', 'https://example.test/features',
            ]),
            'first_referrer_url' => $utmSource ? "https://{$utmSource}.com/" : null,
            'first_referrer_host' => $utmSource ? "{$utmSource}.com" : null,
            'utm_source' => $utmSource,
            'utm_medium' => $utmSource ? fake()->randomElement(['cpc', 'social', 'email', 'referral']) : null,
            'utm_campaign' => $utmSource ? fake()->optional(0.5)->randomElement(['spring_sale', 'launch_2026', 'retargeting']) : null,
            'ref' => fake()->optional(0.1)->numerify('partner_###'),
            'extra_params' => fake()->optional(0.15)->passthrough(['gclid' => Str::random(20)]),
            'country_code' => $country['country_code'],
            'region' => $country['region'],
            'city' => $country['city'],
            'timezone' => $country['timezone'],
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'geo_meta' => ['country_name' => $country['country_name'], 'driver' => 'demo-seed'],
            'locale' => fake()->randomElement(['uk', 'en', 'de', 'pl']),
            'browser_language' => fake()->randomElement(['uk', 'en', 'de', 'pl']),
            'device_type' => $device['device_type'],
            'platform' => $device['platform'],
            'browser' => $device['browser'],
            'client_type' => 'browser',
            'device_meta' => $device['meta'],
            'is_bot' => false,
        ];
    }

    public function bot(): static
    {
        return $this->state(fn () => ['is_bot' => true]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function countries(): array
    {
        return [
            ['country_code' => 'US', 'country_name' => 'United States', 'region' => 'California', 'city' => 'Los Angeles', 'timezone' => 'America/Los_Angeles'],
            ['country_code' => 'UA', 'country_name' => 'Ukraine', 'region' => 'Kyiv', 'city' => 'Kyiv', 'timezone' => 'Europe/Kyiv'],
            ['country_code' => 'DE', 'country_name' => 'Germany', 'region' => 'Berlin', 'city' => 'Berlin', 'timezone' => 'Europe/Berlin'],
            ['country_code' => 'GB', 'country_name' => 'United Kingdom', 'region' => 'England', 'city' => 'London', 'timezone' => 'Europe/London'],
            ['country_code' => 'PL', 'country_name' => 'Poland', 'region' => 'Mazovia', 'city' => 'Warsaw', 'timezone' => 'Europe/Warsaw'],
            ['country_code' => 'FR', 'country_name' => 'France', 'region' => 'Ile-de-France', 'city' => 'Paris', 'timezone' => 'Europe/Paris'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function devices(): array
    {
        return [
            ['device_type' => 'desktop', 'platform' => 'Windows', 'browser' => 'Chrome', 'meta' => ['platform_version' => '10', 'browser_version' => '120.0', 'browser_engine' => 'Blink']],
            ['device_type' => 'desktop', 'platform' => 'macOS', 'browser' => 'Safari', 'meta' => ['platform_version' => '14.5', 'browser_version' => '17.0', 'browser_engine' => 'WebKit']],
            ['device_type' => 'smartphone', 'platform' => 'iOS', 'browser' => 'Mobile Safari', 'meta' => ['device_family' => 'Apple', 'device_model' => 'iPhone', 'platform_version' => '17.0', 'browser_version' => '17.0', 'browser_engine' => 'WebKit']],
            ['device_type' => 'smartphone', 'platform' => 'Android', 'browser' => 'Chrome Mobile', 'meta' => ['device_family' => 'Samsung', 'device_model' => 'Galaxy S23', 'platform_version' => '14', 'browser_version' => '120.0', 'browser_engine' => 'Blink']],
            ['device_type' => 'tablet', 'platform' => 'iOS', 'browser' => 'Mobile Safari', 'meta' => ['device_family' => 'Apple', 'device_model' => 'iPad', 'platform_version' => '17.0', 'browser_version' => '17.0', 'browser_engine' => 'WebKit']],
        ];
    }
}
