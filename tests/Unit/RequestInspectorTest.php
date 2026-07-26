<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Support\DeviceResolver;
use Fomvasss\Visits\Support\GeoResolver;
use Fomvasss\Visits\Support\LocaleResolver;
use Fomvasss\Visits\Support\RequestInspector;
use Fomvasss\Visits\Support\TokenResolver;
use Fomvasss\Visits\Support\TrackingParamsExtractor;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

class RequestInspectorTest extends TestCase
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private function inspector(): RequestInspector
    {
        return new RequestInspector(new DeviceResolver(), new GeoResolver(), new LocaleResolver(), new TrackingParamsExtractor());
    }

    public function test_inspects_ip_device_geo_and_locale(): void
    {
        Location::fake([
            '203.0.113.10' => Position::make(['driver' => 'ip-api', 'country_code' => 'UA', 'country_name' => 'Ukraine']),
        ]);

        $request = Request::create('https://example.test/?utm_source=google', 'GET', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $request->headers->set('User-Agent', self::DESKTOP_UA);

        $data = $this->inspector()->inspect($request);

        $this->assertSame('203.0.113.10', $data['ip']);
        $this->assertFalse($data['bot']['is_bot']);
        $this->assertSame('Chrome', $data['device']['browser']);
        $this->assertSame('UA', $data['geo']['country_code']);
        $this->assertSame('Ukraine', $data['geo']['country_name']);
        $this->assertSame('google', $data['tracking_params']['utm']['utm_source']);
    }

    public function test_reports_bot_traffic(): void
    {
        Location::fake([]);

        $request = Request::create('https://example.test/', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $data = $this->inspector()->inspect($request);

        $this->assertTrue($data['bot']['is_bot']);
        $this->assertSame('Googlebot', $data['bot']['bot_name']);
    }

    public function test_geo_is_null_when_the_lookup_misses(): void
    {
        Location::fake([]);

        $data = $this->inspector()->inspect(Request::create('/'));

        $this->assertNull($data['geo']);
    }

    public function test_visitor_id_is_null_when_nothing_supplied(): void
    {
        Location::fake([]);
        $request = Request::create('/');

        $data = $this->inspector()->inspect($request);

        $this->assertNull($data['visitor_id']);
    }

    public function test_visitor_id_reflects_the_cookie_as_is_without_generating_one(): void
    {
        Location::fake([]);
        $request = Request::create('/');
        $request->cookies->set((string) config('visits.cookie.name'), 'not-a-valid-format-but-shown-anyway');

        $data = $this->inspector()->inspect($request);

        $this->assertSame('not-a-valid-format-but-shown-anyway', $data['visitor_id']);
    }

    public function test_visitor_id_prefers_client_header_over_cookie(): void
    {
        Location::fake([]);
        $request = Request::create('/');
        $request->headers->set(TokenResolver::HEADER, 'header-token');
        $request->cookies->set((string) config('visits.cookie.name'), 'cookie-token');

        $data = $this->inspector()->inspect($request);

        $this->assertSame('header-token', $data['visitor_id']);
    }

    public function test_ip_override_changes_geo_and_reported_ip_but_not_device(): void
    {
        Location::fake([
            '203.0.113.10' => Position::make(['driver' => 'ip-api', 'country_code' => 'US']),
            '198.51.100.20' => Position::make(['driver' => 'ip-api', 'country_code' => 'DE']),
        ]);

        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $request->headers->set('User-Agent', self::DESKTOP_UA);

        $data = $this->inspector()->inspect($request, '198.51.100.20');

        $this->assertSame('198.51.100.20', $data['ip']);
        $this->assertSame('DE', $data['geo']['country_code']);
        $this->assertSame('Chrome', $data['device']['browser'], 'device detection still reflects the real request UA');
    }
}
