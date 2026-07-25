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
        $response->assertViewHas('breakdowns', fn ($breakdowns) => array_key_exists('action', $breakdowns));
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
}
