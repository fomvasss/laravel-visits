<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\Fixtures\TestOrder;
use Fomvasss\Visits\Tests\Fixtures\TestUser;
use Fomvasss\Visits\Tests\TestCase;

class HasVisitsTest extends TestCase
{
    public function test_visit_events_returns_all_events_for_the_eventable(): void
    {
        $order = TestOrder::create(['title' => 'Order #1']);

        Event::factory()->create([
            'type' => Event::TYPE_ACTION,
            'name' => 'order.placed',
            'eventable_type' => TestOrder::class,
            'eventable_id' => $order->id,
        ]);
        Event::factory()->create([
            'type' => Event::TYPE_ACTION,
            'name' => 'order.paid',
            'eventable_type' => TestOrder::class,
            'eventable_id' => $order->id,
        ]);
        Event::factory()->create(); // unrelated event

        $this->assertCount(2, $order->visitEvents);
    }

    public function test_latest_visit_event_returns_the_most_recent(): void
    {
        $order = TestOrder::create(['title' => 'Order #1']);

        Event::factory()->create([
            'eventable_type' => TestOrder::class,
            'eventable_id' => $order->id,
            'name' => 'order.placed',
            'created_at' => now()->subDay(),
        ]);
        $latest = Event::factory()->create([
            'eventable_type' => TestOrder::class,
            'eventable_id' => $order->id,
            'name' => 'order.paid',
            'created_at' => now(),
        ]);

        $this->assertSame($latest->id, $order->latestVisitEvent()->first()->id);
    }

    public function test_latest_visit_event_filters_by_name(): void
    {
        $order = TestOrder::create(['title' => 'Order #1']);

        $placed = Event::factory()->create([
            'eventable_type' => TestOrder::class,
            'eventable_id' => $order->id,
            'name' => 'order.placed',
            'created_at' => now()->subDay(),
        ]);
        Event::factory()->create([
            'eventable_type' => TestOrder::class,
            'eventable_id' => $order->id,
            'name' => 'order.paid',
            'created_at' => now(),
        ]);

        $this->assertSame($placed->id, $order->latestVisitEvent('order.placed')->first()->id);
    }

    public function test_visitor_profiles_returns_every_visitor_linked_to_the_user(): void
    {
        $user = TestUser::create(['name' => 'Vas', 'email' => 'vas@example.test']);

        Visitor::factory()->create(['user_type' => TestUser::class, 'user_id' => $user->id]);
        Visitor::factory()->create(['user_type' => TestUser::class, 'user_id' => $user->id]);
        Visitor::factory()->create(); // unrelated visitor

        $this->assertCount(2, $user->visitorProfiles);
    }
}
