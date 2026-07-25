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
                && $job->payload->name === 'order.placed'
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

        Queue::assertPushed(RecordVisitJob::class, fn ($job) => $job->payload->name === 'newsletter.subscribed'
            && $job->payload->eventableType === null);
    }

    public function test_whoami_uses_the_current_request_by_default(): void
    {
        \Stevebauman\Location\Facades\Location::fake([]);

        $data = Visits::whoami();

        $this->assertArrayHasKey('ip', $data);
        $this->assertArrayHasKey('device', $data);
        $this->assertArrayHasKey('geo', $data);
    }

    public function test_whoami_accepts_an_explicit_request(): void
    {
        \Stevebauman\Location\Facades\Location::fake([]);

        $request = Request::create('/?utm_source=newsletter', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $data = Visits::whoami($request);

        $this->assertTrue($data['bot']['is_bot']);
        $this->assertSame('newsletter', $data['tracking_params']['utm']['utm_source']);
    }

    public function test_whoami_writes_nothing_to_the_database(): void
    {
        \Stevebauman\Location\Facades\Location::fake([]);

        Visits::whoami();

        $this->assertSame(0, \Fomvasss\Visits\Models\Visitor::withBots()->count());
    }

    public function test_whoami_accepts_an_explicit_ip_to_look_up(): void
    {
        \Stevebauman\Location\Facades\Location::fake([
            '198.51.100.20' => \Stevebauman\Location\Position::make(['driver' => 'ip-api', 'country_code' => 'DE']),
        ]);

        $data = Visits::whoami(ip: '198.51.100.20');

        $this->assertSame('198.51.100.20', $data['ip']);
        $this->assertSame('DE', $data['geo']['country_code']);
    }
}
