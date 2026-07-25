<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature\Console;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\TestCase;

class PruneCommandTest extends TestCase
{
    public function test_deletes_rows_older_than_retention_window(): void
    {
        config(['visits.retention_days' => 90]);

        $old = Visitor::factory()->create(['last_seen_at' => now()->subDays(100)]);
        $recent = Visitor::factory()->create(['last_seen_at' => now()->subDays(10)]);

        $this->artisan('visits:prune', ['--force' => true])->assertExitCode(0);

        $this->assertNull(Visitor::withBots()->find($old->id));
        $this->assertNotNull(Visitor::withBots()->find($recent->id));
    }

    public function test_days_option_overrides_config(): void
    {
        config(['visits.retention_days' => 90]);

        $visitor = Visitor::factory()->create(['last_seen_at' => now()->subDays(10)]);

        $this->artisan('visits:prune', ['--days' => 5, '--force' => true])->assertExitCode(0);

        $this->assertNull(Visitor::withBots()->find($visitor->id));
    }

    public function test_deletes_events_and_sessions_older_than_retention_too(): void
    {
        $oldSession = Session::factory()->create(['started_at' => now()->subDays(200)]);
        Event::factory()->create([
            'session_id' => $oldSession->id,
            'visitor_id' => $oldSession->visitor_id,
            'created_at' => now()->subDays(200),
        ]);

        $this->artisan('visits:prune', ['--force' => true])->assertExitCode(0);

        $this->assertSame(0, Event::withBots()->count());
        $this->assertSame(0, Session::withBots()->count());
    }

    public function test_declining_the_confirmation_prompt_aborts(): void
    {
        Visitor::factory()->create(['last_seen_at' => now()->subDays(200)]);

        $this->artisan('visits:prune')
            ->expectsConfirmation('Delete visit_events/visit_sessions/visit_visitors older than ' . now()->subDays(90)->toDateString() . ' (90 days)?', 'no')
            ->assertExitCode(0);

        $this->assertSame(1, Visitor::withBots()->count());
    }

    public function test_confirming_the_prompt_deletes(): void
    {
        Visitor::factory()->create(['last_seen_at' => now()->subDays(200)]);

        $this->artisan('visits:prune')
            ->expectsConfirmation('Delete visit_events/visit_sessions/visit_visitors older than ' . now()->subDays(90)->toDateString() . ' (90 days)?', 'yes')
            ->assertExitCode(0);

        $this->assertSame(0, Visitor::withBots()->count());
    }
}
