<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature\Console;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\StatDaily;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Support\Carbon;

class AggregateCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function stat(string $metric, string $dimension, string $value): ?int
    {
        return StatDaily::where([
            'date' => '2026-03-10',
            'metric' => $metric,
            'dimension' => $dimension,
            'dimension_value' => $value,
        ])->value('count');
    }

    public function test_aggregates_visitor_totals_and_utm_breakdown(): void
    {
        Visitor::factory()->create(['is_bot' => false, 'first_seen_at' => now(), 'utm_source' => 'google']);
        Visitor::factory()->create(['is_bot' => false, 'first_seen_at' => now(), 'utm_source' => 'google']);
        Visitor::factory()->create(['is_bot' => false, 'first_seen_at' => now(), 'utm_source' => 'facebook']);

        $this->artisan('visits:aggregate', ['--date' => 'today'])->assertExitCode(0);

        $this->assertSame(3, $this->stat(StatDaily::METRIC_VISITORS, '', ''));
        $this->assertSame(2, $this->stat(StatDaily::METRIC_VISITORS, 'utm_source', 'google'));
        $this->assertSame(1, $this->stat(StatDaily::METRIC_VISITORS, 'utm_source', 'facebook'));
    }

    public function test_bots_are_excluded_from_every_metric(): void
    {
        $visitor = Visitor::factory()->create(['is_bot' => false, 'first_seen_at' => now()]);
        Visitor::factory()->create(['is_bot' => true, 'first_seen_at' => now()]);

        $session = Session::factory()->create(['visitor_id' => $visitor->id, 'is_bot' => false, 'started_at' => now()]);
        Session::factory()->create(['visitor_id' => $visitor->id, 'is_bot' => true, 'started_at' => now()]);

        Event::factory()->create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'is_bot' => false, 'type' => Event::TYPE_PAGE_VIEW, 'created_at' => now()]);
        Event::factory()->create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'is_bot' => true, 'type' => Event::TYPE_PAGE_VIEW, 'created_at' => now()]);

        $this->artisan('visits:aggregate', ['--date' => 'today'])->assertExitCode(0);

        $this->assertSame(1, $this->stat(StatDaily::METRIC_VISITORS, '', ''));
        $this->assertSame(1, $this->stat(StatDaily::METRIC_SESSIONS, '', ''));
        $this->assertSame(1, $this->stat(StatDaily::METRIC_PAGE_VIEWS, '', ''));
    }

    public function test_separates_page_views_from_conversions(): void
    {
        $visitor = Visitor::factory()->create(['first_seen_at' => now()->subDay()]);
        $session = Session::factory()->create(['visitor_id' => $visitor->id, 'started_at' => now()->subDay()]);

        Event::factory()->create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'type' => Event::TYPE_PAGE_VIEW, 'action' => null, 'created_at' => now()]);
        Event::factory()->create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'type' => Event::TYPE_ACTION, 'action' => 'order.placed', 'created_at' => now()]);

        $this->artisan('visits:aggregate', ['--date' => 'today'])->assertExitCode(0);

        $this->assertSame(1, $this->stat(StatDaily::METRIC_PAGE_VIEWS, '', ''));
        $this->assertSame(1, $this->stat(StatDaily::METRIC_CONVERSIONS, '', ''));
        $this->assertSame(1, $this->stat(StatDaily::METRIC_CONVERSIONS, 'action', 'order.placed'));
    }

    public function test_rerunning_replaces_rather_than_duplicates(): void
    {
        Visitor::factory()->create(['first_seen_at' => now()]);

        $this->artisan('visits:aggregate', ['--date' => 'today'])->assertExitCode(0);
        Visitor::factory()->create(['first_seen_at' => now()]);
        $this->artisan('visits:aggregate', ['--date' => 'today'])->assertExitCode(0);

        $this->assertSame(2, $this->stat(StatDaily::METRIC_VISITORS, '', ''));
        $this->assertSame(1, StatDaily::where([
            'date' => '2026-03-10',
            'metric' => StatDaily::METRIC_VISITORS,
            'dimension' => '',
            'dimension_value' => '',
        ])->count(), 'delete+insert must not leave duplicate total rows behind');
    }

    public function test_from_to_range_aggregates_each_day_separately(): void
    {
        Visitor::factory()->create(['first_seen_at' => Carbon::parse('2026-03-08 10:00:00')]);
        Visitor::factory()->create(['first_seen_at' => Carbon::parse('2026-03-09 10:00:00')]);

        $this->artisan('visits:aggregate', ['--from' => '2026-03-08', '--to' => '2026-03-09'])->assertExitCode(0);

        $this->assertSame(1, StatDaily::where(['date' => '2026-03-08', 'metric' => StatDaily::METRIC_VISITORS, 'dimension' => '', 'dimension_value' => ''])->value('count'));
        $this->assertSame(1, StatDaily::where(['date' => '2026-03-09', 'metric' => StatDaily::METRIC_VISITORS, 'dimension' => '', 'dimension_value' => ''])->value('count'));
    }
}
