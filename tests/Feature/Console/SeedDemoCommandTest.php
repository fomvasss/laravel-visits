<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature\Console;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\StatDaily;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\TestCase;

class SeedDemoCommandTest extends TestCase
{
    public function test_registered_in_testing_environment(): void
    {
        $this->assertTrue($this->app->environment('testing'));
        $this->assertContains('visits:seed-demo', array_keys($this->app[\Illuminate\Contracts\Console\Kernel::class]->all()));
    }

    public function test_generates_a_coherent_visitor_session_event_chain(): void
    {
        $this->artisan('visits:seed-demo', ['--visitors' => 3, '--days' => 5, '--no-interaction' => true])
            ->assertExitCode(0);

        $this->assertSame(3, Visitor::withBots()->count());
        $this->assertGreaterThan(0, Session::withBots()->count());
        $this->assertGreaterThan(0, Event::withBots()->count());

        foreach (Session::withBots()->get() as $session) {
            $this->assertTrue(
                $session->started_at->lessThanOrEqualTo($session->ended_at),
                'a session must never end before it started'
            );
        }

        foreach (Event::withBots()->get() as $event) {
            $session = Session::withBots()->find($event->session_id);
            $this->assertTrue($event->created_at->between($session->started_at, $session->ended_at));
        }

        // the command runs visits:aggregate over the seeded range, so rollups shouldn't be empty
        $this->assertGreaterThan(0, StatDaily::count());
    }

    public function test_fresh_option_truncates_existing_data_first(): void
    {
        Visitor::factory()->create();

        $this->artisan('visits:seed-demo', [
            '--visitors' => 1,
            '--days' => 1,
            '--fresh' => true,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, Visitor::withBots()->count());
    }

    public function test_fresh_without_force_can_be_declined(): void
    {
        $existing = Visitor::factory()->create();

        $this->artisan('visits:seed-demo', ['--visitors' => 1, '--days' => 1, '--fresh' => true])
            ->expectsConfirmation(
                'This deletes existing visit_visitors/visit_sessions/visit_events/visit_stats_daily rows. Continue?',
                'no'
            )
            ->assertExitCode(0);

        $this->assertNotNull(Visitor::withBots()->find($existing->id));
        $this->assertSame(1, Visitor::withBots()->count(), 'declining --fresh must not seed new data either');
    }

    public function test_visitor_last_seen_at_reflects_its_latest_session_activity(): void
    {
        $this->artisan('visits:seed-demo', ['--visitors' => 1, '--days' => 1, '--no-interaction' => true])
            ->assertExitCode(0);

        $visitor = Visitor::withBots()->first();
        $latestActivity = Session::withBots()->where('visitor_id', $visitor->id)->max('last_activity_at');

        $this->assertSame($latestActivity, $visitor->last_seen_at->toDateTimeString());
    }
}
