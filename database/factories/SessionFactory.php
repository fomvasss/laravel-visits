<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Database\Factories;

use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Session>
 */
class SessionFactory extends Factory
{
    protected $model = Session::class;

    public function definition(): array
    {
        $started = fake()->dateTimeBetween('-30 days', 'now');
        $duration = fake()->numberBetween(10, 1800);
        $ended = (clone $started)->modify("+{$duration} seconds");
        $utmSource = fake()->optional(0.5)->randomElement(['google', 'facebook', 'newsletter', 'twitter', 'partner_site']);

        return [
            // standalone default — the seed command overrides this to chain onto an
            // already-created Visitor and inherit its geo/device for a coherent demo record
            'visitor_id' => Visitor::factory(),
            'started_at' => $started,
            'last_activity_at' => $ended,
            'ended_at' => $ended,
            'landing_url' => fake()->randomElement([
                'https://example.test/', 'https://example.test/pricing', 'https://example.test/blog/post-1',
            ]),
            'referrer_url' => $utmSource ? "https://{$utmSource}.com/" : null,
            'referrer_host' => $utmSource ? "{$utmSource}.com" : null,
            'utm_source' => $utmSource,
            'utm_medium' => $utmSource ? fake()->randomElement(['cpc', 'social', 'email', 'referral']) : null,
            'ip' => fake()->ipv4(),
            'country_code' => fake()->randomElement(['US', 'UA', 'DE', 'GB', 'PL', 'FR']),
            'city' => fake()->city(),
            'timezone' => 'Europe/Kyiv',
            'locale' => fake()->randomElement(['uk', 'en', 'de', 'pl']),
            'browser_language' => fake()->randomElement(['uk', 'en', 'de', 'pl']),
            'device_type' => fake()->randomElement(['desktop', 'smartphone', 'tablet']),
            'platform' => fake()->randomElement(['Windows', 'macOS', 'iOS', 'Android']),
            'browser' => fake()->randomElement(['Chrome', 'Safari', 'Firefox', 'Mobile Safari', 'Chrome Mobile']),
            'client_type' => 'browser',
            'user_agent' => fake()->userAgent(),
            'page_views_count' => 0,
            'duration_seconds' => $duration,
            'is_bot' => fake()->boolean(3),
        ];
    }
}
