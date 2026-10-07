<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature\Console;

use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleTest extends TestCase
{
    public function test_package_commands_run_on_one_server_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'visits:'));

        $this->assertCount(3, $events);

        foreach ($events as $event) {
            $this->assertTrue($event->withoutOverlapping, $event->command);
            $this->assertTrue($event->onOneServer, $event->command);
        }
    }
}
