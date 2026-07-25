<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Database\Factories;

use Fomvasss\Visits\Database\Factories\Concerns\HasDemoLocations;
use Fomvasss\Visits\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    use HasDemoLocations;

    protected $model = Visitor::class;

    public function definition(): array
    {
        $firstSeen = fake()->dateTimeBetween('-30 days', '-1 hours');
        $utmSource = fake()->optional(0.6)->randomElement(['google', 'facebook', 'newsletter', 'twitter', 'partner_site']);
        $location = fake()->randomElement($this->demoLocations());
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
            'utm_term' => $utmSource ? fake()->optional(0.3)->randomElement(['running shoes', 'laptop bag', 'wireless mouse']) : null,
            'utm_content' => $utmSource ? fake()->optional(0.3)->randomElement(['banner_a', 'banner_b', 'text_link', 'carousel_1']) : null,
            'ref' => fake()->optional(0.1)->numerify('partner_###'),
            'extra_params' => fake()->optional(0.2)->passthrough([
                fake()->randomElement(['gclid', 'fbclid', 'msclkid', 'ttclid']) => Str::random(20),
            ]),
            'country_code' => $location['country_code'],
            'region' => $location['region'],
            'city' => $location['city'],
            'timezone' => $location['timezone'],
            'lat' => $this->jitterCoordinate($location['lat']),
            'lng' => $this->jitterCoordinate($location['lng']),
            'geo_meta' => ['country_name' => $location['country_name'], 'driver' => 'demo-seed'],
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
