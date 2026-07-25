<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature;

use Fomvasss\Visits\Facades\Visits;
use Fomvasss\Visits\Jobs\RecordVisitJob;
use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Tests\Fixtures\TestOrder;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Queue;

class VisitsManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Request::class, Request::create('https://example.test/checkout', 'POST'));
    }

    public function test_track_dispatches_an_action_event_with_the_given_eventable_and_meta(): void
    {
        Queue::fake();

        $order = TestOrder::create(['title' => 'Order #1']);

        Visits::track('order.placed', $order, ['amount' => 42.5]);

        Queue::assertPushed(RecordVisitJob::class, function ($job) use ($order) {
            return $job->payload->type === Event::TYPE_ACTION
                && $job->payload->action === 'order.placed'
                && $job->payload->eventableType === TestOrder::class
                && $job->payload->eventableId === $order->id
                && $job->payload->meta === ['amount' => 42.5];
        });
    }

    public function test_track_queues_the_visitor_cookie(): void
    {
        Queue::fake();

        Visits::track('lead.created');

        $queued = collect(Cookie::getQueuedCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('visits.cookie.name'));

        $this->assertNotNull($queued);
        $this->assertNotEmpty($queued->getValue());
    }

    public function test_track_does_nothing_when_package_disabled(): void
    {
        Queue::fake();
        config(['visits.enabled' => false]);

        Visits::track('lead.created');

        Queue::assertNotPushed(RecordVisitJob::class);
    }

    public function test_track_works_without_an_eventable_model(): void
    {
        Queue::fake();

        Visits::track('newsletter.subscribed');

        Queue::assertPushed(RecordVisitJob::class, fn ($job) => $job->payload->action === 'newsletter.subscribed'
            && $job->payload->eventableType === null);
    }
}
