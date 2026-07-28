<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature\Http;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\TestCase;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

class WhoAmIControllerTest extends TestCase
{
    public function test_returns_json_with_expected_shape(): void
    {
        Location::fake([]);

        $response = $this->getJson(route('visits.whoami'));

        $response->assertOk();
        $response->assertJsonStructure([
            'ip', 'visitor_id', 'user_agent', 'bot', 'device', 'geo', 'locale', 'referrer', 'tracking_params',
        ]);
    }

    public function test_writes_nothing_to_the_database(): void
    {
        Location::fake([]);

        $this->getJson(route('visits.whoami'))->assertOk();

        $this->assertSame(0, Visitor::withBots()->count());
        $this->assertSame(0, Session::withBots()->count());
        $this->assertSame(0, Event::withBots()->count());
    }

    public function test_sets_no_cookie(): void
    {
        Location::fake([]);

        $response = $this->getJson(route('visits.whoami'));

        $response->assertOk();
        $response->assertCookieMissing((string) config('visits.cookie.name'));
    }

    public function test_reflects_the_supplied_client_token_without_persisting_it(): void
    {
        Location::fake([]);
        $token = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $response = $this->getJson(route('visits.whoami'), ['X-Visitor-Id' => $token]);

        $response->assertOk();
        $response->assertJson(['visitor_id' => $token]);
        $this->assertSame(0, Visitor::withBots()->where('token', $token)->count());
    }

    public function test_reachable_even_when_dashboard_is_disabled(): void
    {
        Location::fake([]);
        config(['visits.dashboard.enabled' => false]);

        $this->getJson(route('visits.whoami'))->assertOk();
    }

    public function test_ip_query_param_overrides_the_geo_lookup(): void
    {
        Location::fake([
            '198.51.100.20' => Position::make(['driver' => 'ip-api', 'country_code' => 'DE']),
        ]);

        $response = $this->getJson(route('visits.whoami', ['ip' => '198.51.100.20']));

        $response->assertOk();
        $response->assertJson(['ip' => '198.51.100.20', 'geo' => ['country_code' => 'DE']]);
    }

    public function test_invalid_ip_query_param_is_ignored(): void
    {
        Location::fake([]);

        $response = $this->getJson(route('visits.whoami', ['ip' => 'not-an-ip']));

        $response->assertOk();
        $response->assertJsonMissing(['ip' => 'not-an-ip']);
    }

    public function test_geo_is_null_when_the_lookup_misses(): void
    {
        Location::fake([]); // guarantees a geo miss

        $response = $this->getJson(route('visits.whoami'));

        $response->assertOk();
        $response->assertJson(['geo' => null]);
    }

    public function test_empty_tracking_params_encode_as_objects_not_arrays(): void
    {
        Location::fake([]);

        $response = $this->getJson(route('visits.whoami'));

        $response->assertOk();
        // a naive response()->json() would render an empty PHP array as `[]`, not `{}` — a
        // type-inconsistent shape for API consumers. Unlike geo (null when unknown),
        // "no UTM params present" is itself a normal, meaningful empty state, so this one
        // legitimately stays an (empty) object rather than becoming null.
        $this->assertStringContainsString('"utm":{}', $response->getContent());
        $this->assertStringContainsString('"extra":{}', $response->getContent());
    }
}
