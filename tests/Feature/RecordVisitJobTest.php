<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature;

use Fomvasss\Visits\DTO\VisitPayload;
use Fomvasss\Visits\Events\ConversionRecorded;
use Fomvasss\Visits\Events\VisitRecorded;
use Fomvasss\Visits\Jobs\RecordVisitJob;
use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Support\GeoResolver;
use Fomvasss\Visits\Tests\Fixtures\OverriddenVisitor;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;

class RecordVisitJobTest extends TestCase
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const GOOGLEBOT_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    private function payload(array $overrides = []): VisitPayload
    {
        $defaults = [
            'token' => 'tok_' . str_repeat('a', 36),
            'type' => Event::TYPE_PAGE_VIEW,
            'name' => null,
            'url' => 'https://example.test/pricing',
            'routeName' => null,
            'ip' => '203.0.113.10',
            'userAgent' => self::DESKTOP_UA,
            'referrer' => null,
            'searchTerm' => null,
            'utm' => [],
            'extraParams' => [],
            'locale' => 'en',
            'browserLanguage' => 'en',
            'authUserType' => null,
            'authUserId' => null,
            'eventableType' => null,
            'eventableId' => null,
            'meta' => null,
        ];

        $attrs = array_merge($defaults, $overrides);

        return new VisitPayload(...$attrs);
    }

    /**
     * Location::fake() swaps the facade root — calling it a second time in the same test
     * would try to wrap the already-swapped fake instance and blow up, so each test calls
     * this exactly once with whatever canned response(s) it needs (empty = no geo data,
     * same as every real lookup missing).
     */
    private function fakeGeo(array $responses = []): void
    {
        \Stevebauman\Location\Facades\Location::fake($responses);
    }

    public function test_first_page_view_creates_visitor_session_and_event(): void
    {

        $this->fakeGeo();
        RecordVisitJob::dispatchSync($this->payload([
            'utm' => ['utm_source' => 'google', 'utm_medium' => 'cpc'],
        ]));

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Session::count());
        $this->assertSame(1, Event::count());

        $visitor = Visitor::first();
        $this->assertSame('google', $visitor->utm_source);
        $this->assertSame('cpc', $visitor->utm_medium);
        $this->assertSame('https://example.test/pricing', $visitor->first_landing_url);
        $this->assertNotNull($visitor->first_seen_at);

        $session = Session::first();
        $this->assertSame('google', $session->utm_source);
        $this->assertSame($visitor->id, $session->visitor_id);
        $this->assertSame(1, $session->page_views_count);

        $event = Event::first();
        $this->assertSame(Event::TYPE_PAGE_VIEW, $event->type);
        $this->assertSame($session->id, $event->session_id);
        $this->assertSame($visitor->id, $event->visitor_id);
    }

    public function test_excluded_ip_records_nothing(): void
    {
        $this->fakeGeo();
        config(['visits.exclude_ips' => ['203.0.113.10']]);

        RecordVisitJob::dispatchSync($this->payload(['ip' => '203.0.113.10']));

        $this->assertSame(0, Visitor::count());
        $this->assertSame(0, Session::count());
        $this->assertSame(0, Event::count());
    }

    public function test_excluded_ip_range_records_nothing(): void
    {
        $this->fakeGeo();
        config(['visits.exclude_ips' => ['203.0.113.0/24']]);

        RecordVisitJob::dispatchSync($this->payload(['ip' => '203.0.113.99']));

        $this->assertSame(0, Visitor::count());
    }

    public function test_non_excluded_ip_still_records(): void
    {
        $this->fakeGeo();
        config(['visits.exclude_ips' => ['203.0.113.10']]);

        RecordVisitJob::dispatchSync($this->payload(['ip' => '198.51.100.5']));

        $this->assertSame(1, Visitor::count());
    }

    public function test_second_visit_within_timeout_reuses_the_same_session(): void
    {

        $this->fakeGeo();
        $token = 'tok_' . str_repeat('b', 36);

        RecordVisitJob::dispatchSync($this->payload(['token' => $token]));
        RecordVisitJob::dispatchSync($this->payload(['token' => $token, 'url' => 'https://example.test/features']));

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Session::count());
        $this->assertSame(2, Event::count());

        $this->assertSame(2, Session::first()->page_views_count);
    }

    public function test_new_session_created_after_timeout_expires(): void
    {

        $this->fakeGeo();
        $token = 'tok_' . str_repeat('c', 36);
        config(['visits.session_timeout_minutes' => 30]);

        Carbon::setTestNow('2026-01-01 12:00:00');
        RecordVisitJob::dispatchSync($this->payload(['token' => $token]));

        Carbon::setTestNow('2026-01-01 12:45:00'); // 45 min later, past the 30 min timeout
        RecordVisitJob::dispatchSync($this->payload(['token' => $token]));

        Carbon::setTestNow();

        $this->assertSame(1, Visitor::count());
        $this->assertSame(2, Session::count());
    }

    public function test_utm_is_first_touch_on_visitor_but_last_touch_on_session(): void
    {

        $this->fakeGeo();
        $token = 'tok_' . str_repeat('d', 36);
        config(['visits.session_timeout_minutes' => 30]);

        Carbon::setTestNow('2026-01-01 12:00:00');
        RecordVisitJob::dispatchSync($this->payload([
            'token' => $token,
            'utm' => ['utm_source' => 'google'],
        ]));

        // new session (past timeout), different utm this time
        Carbon::setTestNow('2026-01-02 12:00:00');
        RecordVisitJob::dispatchSync($this->payload([
            'token' => $token,
            'utm' => ['utm_source' => 'newsletter'],
        ]));

        Carbon::setTestNow();

        $visitor = Visitor::first();
        $this->assertSame('google', $visitor->utm_source, 'first-touch must never be overwritten');

        $sessions = Session::orderBy('started_at')->get();
        $this->assertSame('google', $sessions[0]->utm_source);
        $this->assertSame('newsletter', $sessions[1]->utm_source, 'a new session takes last-touch from the request');
    }

    public function test_search_term_is_first_touch_on_visitor_but_last_touch_on_session(): void
    {
        $this->fakeGeo();
        $token = 'tok_' . str_repeat('s', 36);
        config(['visits.session_timeout_minutes' => 30]);

        Carbon::setTestNow('2026-01-01 12:00:00');
        RecordVisitJob::dispatchSync($this->payload([
            'token' => $token,
            'searchTerm' => 'laravel tracking',
        ]));

        // new session (past timeout), different keyword this time
        Carbon::setTestNow('2026-01-02 12:00:00');
        RecordVisitJob::dispatchSync($this->payload([
            'token' => $token,
            'searchTerm' => 'laravel visits package',
        ]));

        Carbon::setTestNow();

        $visitor = Visitor::first();
        $this->assertSame('laravel tracking', $visitor->search_term, 'first-touch must never be overwritten');

        $sessions = Session::orderBy('started_at')->get();
        $this->assertSame('laravel tracking', $sessions[0]->search_term);
        $this->assertSame('laravel visits package', $sessions[1]->search_term, 'a new session takes last-touch from the request');
    }

    public function test_session_without_search_term_inherits_visitors_first_touch(): void
    {
        $this->fakeGeo();
        $token = 'tok_' . str_repeat('t', 36);

        Carbon::setTestNow('2026-01-01 12:00:00');
        RecordVisitJob::dispatchSync($this->payload([
            'token' => $token,
            'searchTerm' => 'laravel tracking',
        ]));

        // new session, no search term on the request this time
        Carbon::setTestNow('2026-01-02 12:00:00');
        RecordVisitJob::dispatchSync($this->payload(['token' => $token, 'searchTerm' => null]));

        Carbon::setTestNow();

        $sessions = Session::orderBy('started_at')->get();
        $this->assertSame('laravel tracking', $sessions[1]->search_term, 'no search term on the request => inherit from visitor');
    }

    public function test_session_without_new_utm_inherits_visitors_first_touch(): void
    {

        $this->fakeGeo();
        $token = 'tok_' . str_repeat('e', 36);

        Carbon::setTestNow('2026-01-01 12:00:00');
        RecordVisitJob::dispatchSync($this->payload([
            'token' => $token,
            'utm' => ['utm_source' => 'google'],
        ]));

        // new session, no utm on the request this time
        Carbon::setTestNow('2026-01-02 12:00:00');
        RecordVisitJob::dispatchSync($this->payload(['token' => $token, 'utm' => []]));

        Carbon::setTestNow();

        $sessions = Session::orderBy('started_at')->get();
        $this->assertSame('google', $sessions[1]->utm_source, 'no utm on the request => inherit from visitor');
    }

    public function test_bot_flag_is_sticky_within_a_session(): void
    {

        $this->fakeGeo();
        $token = 'tok_' . str_repeat('f', 36);

        RecordVisitJob::dispatchSync($this->payload(['token' => $token, 'userAgent' => self::DESKTOP_UA]));
        RecordVisitJob::dispatchSync($this->payload(['token' => $token, 'userAgent' => self::GOOGLEBOT_UA]));

        $this->assertSame(1, Session::withBots()->count());
        $this->assertTrue(Session::withBots()->first()->is_bot);
    }

    public function test_geo_lookup_is_skipped_for_bot_traffic(): void
    {
        $this->partialMock(GeoResolver::class, function ($mock) {
            $mock->shouldNotReceive('resolve');
        });

        RecordVisitJob::dispatchSync($this->payload(['userAgent' => self::GOOGLEBOT_UA]));

        $this->assertTrue(Visitor::withBots()->first()->is_bot);
    }

    public function test_over_budget_requests_are_silently_dropped(): void
    {

        $this->fakeGeo();
        config(['visits.rate_limit.visitor_budget' => '1,1']);
        $token = 'tok_' . str_repeat('g', 36);

        RecordVisitJob::dispatchSync($this->payload(['token' => $token]));
        RecordVisitJob::dispatchSync($this->payload(['token' => $token, 'url' => 'https://example.test/second']));

        $this->assertSame(1, Event::count(), 'the second request exceeded the per-visitor budget and must be dropped');
    }

    public function test_device_and_geo_meta_are_assembled_on_visitor_and_session(): void
    {
        \Stevebauman\Location\Facades\Location::fake([
            '203.0.113.10' => \Stevebauman\Location\Position::make([
                'driver' => 'ip-api',
                'country_code' => 'UA',
                'country_name' => 'Ukraine',
            ]),
        ]);

        RecordVisitJob::dispatchSync($this->payload());

        $visitor = Visitor::first();
        $this->assertSame('UA', $visitor->country_code);
        $this->assertSame('Ukraine', $visitor->geo_meta['country_name']);
        $this->assertSame('Chrome', $visitor->browser);
        $this->assertSame('Windows', $visitor->platform);
        $this->assertArrayHasKey('browser_version', $visitor->device_meta);
        $this->assertArrayHasKey('browser_engine', $visitor->device_meta);
    }

    public function test_model_override_is_respected_end_to_end(): void
    {

        $this->fakeGeo();
        config(['visits.models.visitor' => OverriddenVisitor::class]);

        RecordVisitJob::dispatchSync($this->payload());

        $session = Session::first();
        $this->assertInstanceOf(OverriddenVisitor::class, $session->visitor);
    }

    public function test_visit_recorded_event_is_dispatched(): void
    {

        $this->fakeGeo();
        EventFacade::fake([VisitRecorded::class]);

        RecordVisitJob::dispatchSync($this->payload());

        EventFacade::assertDispatched(VisitRecorded::class);
    }

    public function test_conversion_recorded_only_dispatched_for_eventable_actions(): void
    {

        $this->fakeGeo();
        EventFacade::fake([ConversionRecorded::class]);

        RecordVisitJob::dispatchSync($this->payload()); // plain page view
        EventFacade::assertNotDispatched(ConversionRecorded::class);

        RecordVisitJob::dispatchSync($this->payload([
            'type' => Event::TYPE_ACTION,
            'name' => 'order.placed',
            'eventableType' => 'App\\Models\\Order',
            'eventableId' => 1,
        ]));
        EventFacade::assertDispatched(ConversionRecorded::class);
    }

    public function test_action_event_does_not_increment_page_views_count(): void
    {

        $this->fakeGeo();
        $token = 'tok_' . str_repeat('h', 36);

        RecordVisitJob::dispatchSync($this->payload(['token' => $token]));
        RecordVisitJob::dispatchSync($this->payload([
            'token' => $token,
            'type' => Event::TYPE_ACTION,
            'name' => 'newsletter.subscribed',
        ]));

        $this->assertSame(1, Session::first()->page_views_count);
    }
}
