<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature\Dashboard;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\StatDaily;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Support\Carbon;

class DashboardControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_index_shows_correct_totals_and_excludes_bots(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        Visitor::factory()->create(['is_bot' => false, 'first_seen_at' => now(), 'utm_source' => 'google']);
        Visitor::factory()->create(['is_bot' => false, 'first_seen_at' => now(), 'utm_source' => 'google']);
        Visitor::factory()->create(['is_bot' => true, 'first_seen_at' => now()]);

        $this->artisan('visits:aggregate', ['--date' => 'today']);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('totals', fn ($totals) => $totals[StatDaily::METRIC_VISITORS] === 2);
    }

    public function test_index_session_breakdown_by_utm_source(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $visitor = Visitor::factory()->create(['first_seen_at' => now()]);
        Session::factory()->create(['visitor_id' => $visitor->id, 'started_at' => now(), 'utm_source' => 'google']);
        Session::factory()->create(['visitor_id' => $visitor->id, 'started_at' => now(), 'utm_source' => 'google']);

        $this->artisan('visits:aggregate', ['--date' => 'today']);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('breakdowns', fn ($breakdowns) => ($breakdowns['utm_source']['google'] ?? null) === 2);
    }

    public function test_campaigns_page_breaks_down_every_utm_dimension(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $visitor = Visitor::factory()->create(['first_seen_at' => now()]);
        Session::factory()->create([
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring_sale',
            'utm_term' => 'shoes',
            'utm_content' => 'banner_a',
            'ref' => 'partner_123',
        ]);

        $this->artisan('visits:aggregate', ['--date' => 'today']);

        $response = $this->get(route('visits.campaigns'));

        $response->assertOk();
        $response->assertViewHas('breakdowns', function ($breakdowns) {
            return ($breakdowns['utm_source']['google'] ?? null) === 1
                && ($breakdowns['utm_medium']['cpc'] ?? null) === 1
                && ($breakdowns['utm_campaign']['spring_sale'] ?? null) === 1
                && ($breakdowns['utm_term']['shoes'] ?? null) === 1
                && ($breakdowns['utm_content']['banner_a'] ?? null) === 1
                && ($breakdowns['ref']['partner_123'] ?? null) === 1;
        });
    }

    public function test_campaigns_page_defaults_to_sessions_breakdown(): void
    {
        $response = $this->get(route('visits.campaigns'));

        $response->assertOk();
        $response->assertViewHas('breakdownMetric', 'sessions');
    }

    public function test_campaigns_page_can_switch_to_conversions(): void
    {
        $response = $this->get(route('visits.campaigns', ['breakdown_metric' => 'conversions']));

        $response->assertOk();
        $response->assertViewHas('breakdownMetric', 'conversions');
    }

    public function test_index_reports_bot_session_summary(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        Session::factory()->create(['is_bot' => false, 'started_at' => now()]);
        Session::factory()->create(['is_bot' => true, 'started_at' => now()]);
        Session::factory()->create(['is_bot' => true, 'started_at' => now()]);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('botSessions', 2);
        $response->assertViewHas('botPercentage', fn ($pct) => abs($pct - 66.7) < 0.1);
    }

    public function test_index_map_includes_only_sessions_with_coordinates(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        Session::factory()->create(['started_at' => now(), 'lat' => '50.4501', 'lng' => '30.5234']);
        Session::factory()->create(['started_at' => now(), 'lat' => null, 'lng' => null]);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('mapMarkers', fn ($markers) => $markers->count() === 1
            && $markers->first()['lat'] === 50.4501);
    }

    public function test_index_online_now_counts_open_recently_active_sessions(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        Session::factory()->create(['ended_at' => null, 'last_activity_at' => now()->subMinutes(2)]);
        Session::factory()->create(['ended_at' => now(), 'last_activity_at' => now()->subMinutes(1)]);
        Session::factory()->create(['ended_at' => null, 'last_activity_at' => now()->subMinutes(20)]);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('onlineNow', 1);
    }

    public function test_index_online_now_respects_configured_window(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');
        config(['visits.dashboard.online_window_minutes' => 30]);

        Session::factory()->create(['ended_at' => null, 'last_activity_at' => now()->subMinutes(20)]);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('onlineNow', 1);
    }

    public function test_index_top_pages_ranks_page_views_by_path(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');
        $session = Session::factory()->create(['started_at' => now()]);

        Event::factory()->count(3)->create([
            'session_id' => $session->id, 'visitor_id' => $session->visitor_id,
            'type' => Event::TYPE_PAGE_VIEW, 'url' => 'https://example.test/popular', 'path' => '/popular', 'created_at' => now(),
        ]);
        Event::factory()->create([
            'session_id' => $session->id, 'visitor_id' => $session->visitor_id,
            'type' => Event::TYPE_PAGE_VIEW, 'url' => 'https://example.test/rare', 'path' => '/rare', 'created_at' => now(),
        ]);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('topPages', fn ($pages) => $pages->count() === 2
            && $pages->first()['path'] === '/popular'
            && $pages->first()['count'] === 3);
    }

    public function test_index_top_pages_groups_different_query_strings_under_the_same_path(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');
        $session = Session::factory()->create(['started_at' => now()]);

        Event::factory()->create([
            'session_id' => $session->id, 'visitor_id' => $session->visitor_id,
            'type' => Event::TYPE_PAGE_VIEW, 'url' => 'https://example.test/catalog?sort=price', 'path' => '/catalog', 'created_at' => now(),
        ]);
        Event::factory()->create([
            'session_id' => $session->id, 'visitor_id' => $session->visitor_id,
            'type' => Event::TYPE_PAGE_VIEW, 'url' => 'https://example.test/catalog?color=red', 'path' => '/catalog', 'created_at' => now(),
        ]);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('topPages', fn ($pages) => $pages->count() === 1
            && $pages->first()['path'] === '/catalog'
            && $pages->first()['count'] === 2);
    }

    public function test_index_top_pages_excludes_actions_and_bots(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');
        $session = Session::factory()->create(['started_at' => now()]);

        Event::factory()->create([
            'session_id' => $session->id, 'visitor_id' => $session->visitor_id,
            'type' => Event::TYPE_ACTION, 'name' => 'newsletter.subscribed', 'url' => 'https://example.test/checkout', 'created_at' => now(),
        ]);
        Event::factory()->bot()->create([
            'session_id' => $session->id, 'visitor_id' => $session->visitor_id,
            'type' => Event::TYPE_PAGE_VIEW, 'url' => 'https://example.test/bot-hit', 'created_at' => now(),
        ]);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('topPages', fn ($pages) => $pages->isEmpty());
    }

    public function test_index_map_excludes_bots(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        Session::factory()->create(['started_at' => now(), 'lat' => '50.45', 'lng' => '30.52', 'is_bot' => false]);
        Session::factory()->create(['started_at' => now(), 'lat' => '10.0', 'lng' => '10.0', 'is_bot' => true]);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('mapMarkers', fn ($markers) => $markers->count() === 1);
    }

    public function test_index_map_respects_the_marker_limit(): void
    {
        config(['visits.dashboard.map_marker_limit' => 2]);
        Carbon::setTestNow('2026-03-10 12:00:00');

        Session::factory()->count(3)->create(['started_at' => now(), 'lat' => '10.0', 'lng' => '10.0']);

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('mapMarkers', fn ($markers) => $markers->count() === 2);
    }

    public function test_index_shows_a_message_when_no_location_data_present(): void
    {
        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertSee('No location data for this period');
        $response->assertDontSee('id="visits-map"', false);
    }

    public function test_index_defaults_to_a_thirty_day_range(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('from', fn ($from) => $from->toDateString() === '2026-02-09');
        $response->assertViewHas('to', fn ($to) => $to->toDateString() === '2026-03-10');
    }

    public function test_default_range_is_configurable(): void
    {
        config(['visits.dashboard.default_range_days' => 7]);
        Carbon::setTestNow('2026-03-10 12:00:00');

        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('from', fn ($from) => $from->toDateString() === '2026-03-04');
    }

    public function test_index_breakdown_metric_defaults_to_sessions(): void
    {
        $response = $this->get(route('visits.index'));

        $response->assertOk();
        $response->assertViewHas('breakdownMetric', StatDaily::METRIC_SESSIONS);
    }

    public function test_index_breakdown_metric_switches_to_conversions_and_adds_action_dimension(): void
    {
        $response = $this->get(route('visits.index', ['breakdown_metric' => 'conversions']));

        $response->assertOk();
        $response->assertViewHas('breakdownMetric', StatDaily::METRIC_CONVERSIONS);
        $response->assertViewHas('breakdowns', fn ($breakdowns) => array_key_exists('name', $breakdowns));
    }

    public function test_sessions_list_excludes_bots_by_default_and_includes_with_with_bots_flag(): void
    {
        Session::factory()->create(['is_bot' => false]);
        Session::factory()->create(['is_bot' => true]);

        $this->get(route('visits.sessions'))
            ->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1);

        $this->get(route('visits.sessions', ['with_bots' => 1]))
            ->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 2);
    }

    public function test_sessions_list_filters_by_country_code(): void
    {
        Session::factory()->create(['country_code' => 'UA']);
        Session::factory()->create(['country_code' => 'US']);

        $this->get(route('visits.sessions', ['country_code' => 'UA']))
            ->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1
                && $sessions->first()->country_code === 'UA');
    }

    public function test_sessions_list_filters_by_visitor_id(): void
    {
        $visitor = Visitor::factory()->create();
        Session::factory()->create(['visitor_id' => $visitor->id]);
        Session::factory()->create(); // unrelated visitor

        $this->get(route('visits.sessions', ['visitor_id' => $visitor->id]))
            ->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1
                && $sessions->first()->visitor_id === $visitor->id);
    }

    public function test_visitor_page_links_session_count_to_the_filtered_sessions_list(): void
    {
        $visitor = Visitor::factory()->create();
        Session::factory()->count(2)->create(['visitor_id' => $visitor->id]);

        $response = $this->get(route('visits.visitor', $visitor->id));

        $response->assertOk();
        $response->assertSee(route('visits.sessions', ['visitor_id' => $visitor->id]), false);
    }

    public function test_sessions_list_filters_by_ip(): void
    {
        Session::factory()->create(['ip' => '203.0.113.10']);
        Session::factory()->create(['ip' => '203.0.113.20']);

        $this->get(route('visits.sessions', ['ip' => '203.0.113.10']))
            ->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1
                && $sessions->first()->ip === '203.0.113.10');
    }

    public function test_sessions_list_can_sort_by_ip(): void
    {
        Session::factory()->create(['ip' => '203.0.113.20']);
        Session::factory()->create(['ip' => '203.0.113.10']);

        $response = $this->get(route('visits.sessions', ['sort' => 'ip', 'direction' => 'asc']));

        $response->assertOk();
        $response->assertViewHas('sessions', fn ($sessions) => $sessions->first()->ip === '203.0.113.10');
    }

    public function test_sessions_list_defaults_to_started_at_descending(): void
    {
        $older = Session::factory()->create(['started_at' => now()->subDays(2)]);
        $newer = Session::factory()->create(['started_at' => now()->subHour()]);

        $response = $this->get(route('visits.sessions'));

        $response->assertOk();
        $response->assertViewHas('sort', 'started_at');
        $response->assertViewHas('direction', 'desc');
        $response->assertViewHas('sessions', fn ($sessions) => $sessions->first()->id === $newer->id);
    }

    public function test_sessions_list_can_sort_by_page_views_ascending(): void
    {
        $few = Session::factory()->create(['page_views_count' => 1]);
        $many = Session::factory()->create(['page_views_count' => 10]);

        $response = $this->get(route('visits.sessions', ['sort' => 'page_views_count', 'direction' => 'asc']));

        $response->assertOk();
        $response->assertViewHas('sessions', fn ($sessions) => $sessions->first()->id === $few->id
            && $sessions->last()->id === $many->id);
    }

    public function test_sessions_list_can_sort_by_country_code(): void
    {
        Session::factory()->create(['country_code' => 'US']);
        Session::factory()->create(['country_code' => 'DE']);

        $response = $this->get(route('visits.sessions', ['sort' => 'country_code', 'direction' => 'asc']));

        $response->assertOk();
        $response->assertViewHas('sessions', fn ($sessions) => $sessions->first()->country_code === 'DE');
    }

    public function test_sessions_list_can_sort_by_referrer_host(): void
    {
        Session::factory()->create(['referrer_host' => 'newsletter.com']);
        Session::factory()->create(['referrer_host' => 'facebook.com']);

        $response = $this->get(route('visits.sessions', ['sort' => 'referrer_host', 'direction' => 'asc']));

        $response->assertOk();
        $response->assertViewHas('sessions', fn ($sessions) => $sessions->first()->referrer_host === 'facebook.com');
    }

    public function test_sessions_list_ignores_an_unwhitelisted_sort_column(): void
    {
        $response = $this->get(route('visits.sessions', ['sort' => 'user_agent']));

        $response->assertOk();
        $response->assertViewHas('sort', 'started_at');
    }

    public function test_sessions_list_uses_simple_paginate(): void
    {
        config(['visits.dashboard.per_page' => 2]);
        Session::factory()->count(3)->create();

        $response = $this->get(route('visits.sessions'));

        $response->assertOk();
        $response->assertViewHas('sessions', function ($sessions) {
            return $sessions->count() === 2 && $sessions->hasMorePages();
        });
    }

    public function test_visitors_list_defaults_to_last_seen_at_descending(): void
    {
        $older = Visitor::factory()->create(['last_seen_at' => now()->subDays(2)]);
        $newer = Visitor::factory()->create(['last_seen_at' => now()->subHour()]);

        $response = $this->get(route('visits.visitors'));

        $response->assertOk();
        $response->assertViewHas('sort', 'last_seen_at');
        $response->assertViewHas('visitors', fn ($visitors) => $visitors->first()->id === $newer->id);
    }

    public function test_visitors_list_can_sort_by_sessions_count(): void
    {
        $quiet = Visitor::factory()->create();
        Session::factory()->create(['visitor_id' => $quiet->id]);

        $active = Visitor::factory()->create();
        Session::factory()->count(3)->create(['visitor_id' => $active->id]);

        $response = $this->get(route('visits.visitors', ['sort' => 'sessions_count', 'direction' => 'desc']));

        $response->assertOk();
        $response->assertViewHas('visitors', fn ($visitors) => $visitors->first()->id === $active->id);
    }

    public function test_visitors_list_can_sort_by_utm_source(): void
    {
        Visitor::factory()->create(['utm_source' => 'newsletter']);
        Visitor::factory()->create(['utm_source' => 'facebook']);

        $response = $this->get(route('visits.visitors', ['sort' => 'utm_source', 'direction' => 'asc']));

        $response->assertOk();
        $response->assertViewHas('visitors', fn ($visitors) => $visitors->first()->utm_source === 'facebook');
    }

    public function test_visitors_list_includes_sessions_count(): void
    {
        $visitor = Visitor::factory()->create();
        Session::factory()->count(2)->create(['visitor_id' => $visitor->id]);

        $response = $this->get(route('visits.visitors'));

        $response->assertOk();
        $response->assertViewHas('visitors', function ($visitors) use ($visitor) {
            $found = $visitors->firstWhere('id', $visitor->id);

            return $found !== null && (int) $found->sessions_count === 2;
        });
    }

    public function test_visitors_list_returning_only_filter(): void
    {
        $returning = Visitor::factory()->create();
        Session::factory()->count(2)->create(['visitor_id' => $returning->id]);

        $oneTime = Visitor::factory()->create();
        Session::factory()->create(['visitor_id' => $oneTime->id]);

        $response = $this->get(route('visits.visitors', ['returning_only' => 1]));

        $response->assertOk();
        $response->assertViewHas('visitors', function ($visitors) use ($returning, $oneTime) {
            return $visitors->firstWhere('id', $returning->id) !== null
                && $visitors->firstWhere('id', $oneTime->id) === null;
        });
    }

    public function test_visitors_list_token_search(): void
    {
        $visitor = Visitor::factory()->create(['token' => 'findmeplease1234567890abcdefgh']);
        Visitor::factory()->create();

        $response = $this->get(route('visits.visitors', ['token' => 'findmeplease']));

        $response->assertOk();
        $response->assertViewHas('visitors', fn ($visitors) => $visitors->count() === 1
            && $visitors->first()->id === $visitor->id);
    }

    public function test_show_page_links_coordinates_to_a_map_when_present(): void
    {
        $session = Session::factory()->create(['lat' => '50.4501', 'lng' => '30.5234']);

        $response = $this->get(route('visits.show', $session->id));

        $response->assertOk();
        $response->assertSee('https://www.google.com/maps?q=50.4501000,30.5234000', false);
    }

    public function test_show_page_hides_coordinates_when_absent(): void
    {
        $session = Session::factory()->create(['lat' => null, 'lng' => null]);

        $response = $this->get(route('visits.show', $session->id));

        $response->assertOk();
        $response->assertDontSee('Coordinates');
    }

    public function test_show_returns_session_with_bot_events_included(): void
    {
        $session = Session::factory()->create();
        Event::factory()->create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'is_bot' => false]);
        Event::factory()->create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'is_bot' => true]);

        $response = $this->get(route('visits.show', $session->id));

        $response->assertOk();
        $response->assertViewHas('session', fn ($s) => $s->events->count() === 2);
    }

    public function test_show_finds_a_bot_session_itself(): void
    {
        $session = Session::factory()->create(['is_bot' => true]);

        $this->get(route('visits.show', $session->id))->assertOk();
    }

    public function test_visitor_page_links_coordinates_to_a_map_when_present(): void
    {
        $visitor = Visitor::factory()->create(['lat' => '50.4501', 'lng' => '30.5234']);

        $response = $this->get(route('visits.visitor', $visitor->id));

        $response->assertOk();
        $response->assertSee('https://www.google.com/maps?q=50.4501000,30.5234000', false);
    }

    public function test_visitor_page_hides_coordinates_when_absent(): void
    {
        $visitor = Visitor::factory()->create(['lat' => null, 'lng' => null]);

        $response = $this->get(route('visits.visitor', $visitor->id));

        $response->assertOk();
        $response->assertDontSee('Coordinates');
    }

    public function test_show_visitor_returns_visitor_with_all_sessions(): void
    {
        $visitor = Visitor::factory()->create();
        Session::factory()->create(['visitor_id' => $visitor->id, 'is_bot' => false]);
        Session::factory()->create(['visitor_id' => $visitor->id, 'is_bot' => true]);

        $response = $this->get(route('visits.visitor', $visitor->id));

        $response->assertOk();
        $response->assertViewHas('visitor', fn ($v) => $v->sessions->count() === 2);
    }

    public function test_show_visitor_404s_for_unknown_id(): void
    {
        $this->get(route('visits.visitor', 999999))->assertNotFound();
    }

    public function test_live_page_renders(): void
    {
        $this->get(route('visits.live'))->assertOk();
    }

    public function test_live_feed_returns_events_created_after_since(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $session = Session::factory()->create(['lat' => '50.45', 'lng' => '30.52']);
        $old = Event::factory()->create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'created_at' => now()->subMinutes(5),
        ]);
        $recent = Event::factory()->create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'name' => 'order.placed',
            'type' => Event::TYPE_ACTION,
            'created_at' => now(),
        ]);

        $response = $this->getJson(route('visits.live.feed', ['since' => now()->subMinute()->toIso8601String()]));

        $response->assertOk();
        $events = $response->json('events');
        $this->assertCount(1, $events);
        $this->assertSame('order.placed', $events[0]['name']);
        $this->assertSame(50.45, $events[0]['lat']);
    }

    public function test_live_feed_excludes_events_without_session_coordinates(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $session = Session::factory()->create(['lat' => null, 'lng' => null]);
        Event::factory()->create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'created_at' => now(),
        ]);

        $response = $this->getJson(route('visits.live.feed', ['since' => now()->subMinute()->toIso8601String()]));

        $response->assertOk();
        $response->assertJson(['events' => []]);
    }

    public function test_live_feed_excludes_bots(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $session = Session::factory()->create(['lat' => '50.45', 'lng' => '30.52']);
        Event::factory()->bot()->create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'created_at' => now(),
        ]);

        $response = $this->getJson(route('visits.live.feed', ['since' => now()->subMinute()->toIso8601String()]));

        $response->assertOk();
        $response->assertJson(['events' => []]);
    }

    public function test_live_feed_defaults_since_to_thirty_seconds_ago(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $session = Session::factory()->create(['lat' => '50.45', 'lng' => '30.52']);
        Event::factory()->create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'created_at' => now()->subSeconds(45),
        ]);
        $recent = Event::factory()->create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'created_at' => now()->subSeconds(10),
        ]);

        $response = $this->getJson(route('visits.live.feed'));

        $response->assertOk();
        $this->assertCount(1, $response->json('events'));
    }

    public function test_live_feed_degrades_gracefully_on_an_unparseable_since(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $session = Session::factory()->create(['lat' => '50.45', 'lng' => '30.52']);
        Event::factory()->create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'created_at' => now()->subSeconds(10),
        ]);

        // this is exactly the value a client would send if it forgot to URL-encode a '+00:00'
        // offset — the '+' becomes a literal space, which Carbon can't parse
        $response = $this->getJson(route('visits.live.feed', ['since' => '2026-03-10T11:59:00 00:00']));

        $response->assertOk();
        $this->assertCount(1, $response->json('events'));
    }

    public function test_live_stream_route_exists(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('visits.live.stream'));
    }

    public function test_live_stream_returns_sse_headers(): void
    {
        // sse_max_duration=0 makes the stream loop's while-condition false immediately (the
        // deadline is already "now" by the time it's checked) — the callback returns without
        // ever sleeping, so this test can't hang regardless of the loop body.
        config(['visits.live.sse_max_duration' => 0]);

        $response = $this->get(route('visits.live.stream'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    }

    public function test_whoami_page_renders_and_writes_nothing(): void
    {
        \Stevebauman\Location\Facades\Location::fake([]);

        $response = $this->get(route('visits.me'));

        $response->assertOk();
        $response->assertViewHas('data', fn ($data) => array_key_exists('ip', $data));
        $this->assertSame(0, Visitor::withBots()->count());
    }

    public function test_whoami_page_looks_up_a_requested_ip(): void
    {
        \Stevebauman\Location\Facades\Location::fake([
            '198.51.100.20' => \Stevebauman\Location\Position::make(['driver' => 'ip-api', 'country_code' => 'DE']),
        ]);

        $response = $this->get(route('visits.me', ['ip' => '198.51.100.20']));

        $response->assertOk();
        $response->assertViewHas('data', fn ($data) => $data['ip'] === '198.51.100.20' && $data['geo']['country_code'] === 'DE');
        $response->assertViewHas('ipError', null);
    }

    public function test_whoami_page_shows_an_error_for_an_invalid_ip(): void
    {
        \Stevebauman\Location\Facades\Location::fake([]);

        $response = $this->get(route('visits.me', ['ip' => 'not-an-ip']));

        $response->assertOk();
        $response->assertViewHas('ipError', fn ($error) => $error !== null);
        // falls back to the real request IP rather than erroring out
        $response->assertViewHas('data', fn ($data) => $data['ip'] !== 'not-an-ip');
    }

    public function test_whoami_page_shows_device_model_and_browser_engine_when_known(): void
    {
        \Stevebauman\Location\Facades\Location::fake([]);

        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Linux; Android 14; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36'
        )->get(route('visits.me'));

        $response->assertOk();
        $response->assertSee('Samsung', false);
        $response->assertSee('Blink', false);
    }

    public function test_whoami_page_links_coordinates_to_a_map_when_present(): void
    {
        \Stevebauman\Location\Facades\Location::fake([
            '198.51.100.20' => \Stevebauman\Location\Position::make([
                'driver' => 'ip-api', 'country_code' => 'DE', 'latitude' => '52.52', 'longitude' => '13.405',
            ]),
        ]);

        $response = $this->get(route('visits.me', ['ip' => '198.51.100.20']));

        $response->assertOk();
        $response->assertSee('https://www.google.com/maps?q=52.52,13.405', false);
    }
}
