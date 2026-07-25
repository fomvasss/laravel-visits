<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\TestCase;

class ExcludesBotsByDefaultTest extends TestCase
{
    public function test_bot_visitor_excluded_from_default_query(): void
    {
        Visitor::factory()->create(['is_bot' => false]);
        Visitor::factory()->create(['is_bot' => true]);

        $this->assertSame(1, Visitor::count());
    }

    public function test_with_bots_includes_everything(): void
    {
        Visitor::factory()->create(['is_bot' => false]);
        Visitor::factory()->create(['is_bot' => true]);

        $this->assertSame(2, Visitor::withBots()->count());
    }

    public function test_only_bots_returns_just_bots(): void
    {
        Visitor::factory()->create(['is_bot' => false]);
        $bot = Visitor::factory()->create(['is_bot' => true]);

        $result = Visitor::onlyBots()->get();

        $this->assertCount(1, $result);
        $this->assertSame($bot->id, $result->first()->id);
    }

    public function test_bot_session_excluded_from_default_query(): void
    {
        Session::factory()->create(['is_bot' => false]);
        Session::factory()->create(['is_bot' => true]);

        $this->assertSame(1, Session::count());
        $this->assertSame(2, Session::withBots()->count());
    }

    public function test_bot_event_excluded_from_default_query(): void
    {
        Event::factory()->create(['is_bot' => false]);
        Event::factory()->bot()->create();

        $this->assertSame(1, Event::count());
        $this->assertSame(2, Event::withBots()->count());
        $this->assertSame(1, Event::onlyBots()->count());
    }

    public function test_find_respects_bot_scope_by_default(): void
    {
        $bot = Visitor::factory()->create(['is_bot' => true]);

        $this->assertNull(Visitor::find($bot->id));
        $this->assertNotNull(Visitor::withBots()->find($bot->id));
    }
}
