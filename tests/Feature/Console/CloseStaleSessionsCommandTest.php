<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature\Console;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Support\Carbon;

class CloseStaleSessionsCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_closes_a_session_past_the_timeout_and_computes_duration(): void
    {
        config(['visits.session_timeout_minutes' => 30]);

        Carbon::setTestNow('2026-03-10 12:00:00');
        $session = Session::factory()->create([
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        Carbon::setTestNow('2026-03-10 13:00:00'); // now() far past the timeout since last_activity_at

        $this->artisan('visits:close-stale-sessions')->assertExitCode(0);

        $session->refresh();
        $this->assertNotNull($session->ended_at);
        $this->assertSame((int) $session->started_at->diffInSeconds($session->last_activity_at), $session->duration_seconds);
        $this->assertTrue($session->ended_at->equalTo($session->last_activity_at));
    }

    public function test_sets_exit_url_from_the_latest_page_view(): void
    {
        config(['visits.session_timeout_minutes' => 30]);

        Carbon::setTestNow('2026-03-10 12:00:00');
        $session = Session::factory()->create([
            'started_at' => now()->subHours(2),
            'last_activity_at' => now()->subHours(2),
            'ended_at' => null,
        ]);

        Event::factory()->create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'type' => Event::TYPE_PAGE_VIEW,
            'url' => 'https://example.test/first',
            'created_at' => now()->subHours(2),
        ]);
        Event::factory()->create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'type' => Event::TYPE_PAGE_VIEW,
            'url' => 'https://example.test/last',
            'created_at' => now()->subMinutes(90),
        ]);

        $this->artisan('visits:close-stale-sessions')->assertExitCode(0);

        $this->assertSame('https://example.test/last', $session->refresh()->exit_url);
    }

    public function test_leaves_active_sessions_within_timeout_untouched(): void
    {
        config(['visits.session_timeout_minutes' => 30]);

        $session = Session::factory()->create([
            'last_activity_at' => now()->subMinutes(5),
            'ended_at' => null,
        ]);

        $this->artisan('visits:close-stale-sessions')->assertExitCode(0);

        $this->assertNull($session->refresh()->ended_at);
    }

    public function test_ignores_already_closed_sessions(): void
    {
        config(['visits.session_timeout_minutes' => 30]);

        $closedAt = now()->subDay();
        $session = Session::factory()->create([
            'last_activity_at' => now()->subDays(2),
            'ended_at' => $closedAt,
            'duration_seconds' => 123,
        ]);

        $this->artisan('visits:close-stale-sessions')->assertExitCode(0);

        $session->refresh();
        $this->assertSame(123, $session->duration_seconds, 'an already-closed session must not be recomputed');
    }

    public function test_closes_bot_sessions_too(): void
    {
        config(['visits.session_timeout_minutes' => 30]);

        $session = Session::factory()->create([
            'is_bot' => true,
            'last_activity_at' => now()->subHours(2),
            'ended_at' => null,
        ]);

        $this->artisan('visits:close-stale-sessions')->assertExitCode(0);

        $this->assertNotNull($session->refresh()->ended_at);
    }
}
