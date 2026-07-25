<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Support\LocaleResolver;
use Fomvasss\Visits\Support\PayloadBuilder;
use Fomvasss\Visits\Support\TrackingParamsExtractor;
use Fomvasss\Visits\Tests\Fixtures\TestOrder;
use Fomvasss\Visits\Tests\Fixtures\TestUser;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Http\Request;

class PayloadBuilderTest extends TestCase
{
    private function builder(): PayloadBuilder
    {
        return new PayloadBuilder(new TrackingParamsExtractor(), new LocaleResolver());
    }

    public function test_builds_page_view_payload_from_request(): void
    {
        $request = Request::create('https://example.test/pricing?utm_source=google', 'GET');
        $request->headers->set('referer', 'https://google.com/search');
        $request->headers->set('User-Agent', 'TestAgent/1.0');

        $payload = $this->builder()->build($request, 'tok123', Event::TYPE_PAGE_VIEW);

        $this->assertSame('tok123', $payload->token);
        $this->assertSame(Event::TYPE_PAGE_VIEW, $payload->type);
        $this->assertSame('https://example.test/pricing?utm_source=google', $payload->url);
        $this->assertSame('https://google.com/search', $payload->referrer);
        $this->assertSame('TestAgent/1.0', $payload->userAgent);
        $this->assertSame(['utm_source' => 'google'], $payload->utm);
        $this->assertNull($payload->authUserType);
        $this->assertNull($payload->authUserId);
        $this->assertNull($payload->eventableType);
    }

    public function test_explicit_url_wins_over_request_full_url(): void
    {
        $request = Request::create('https://example.test/visits/collect', 'POST');

        $payload = $this->builder()->build(
            $request,
            'tok123',
            Event::TYPE_PAGE_VIEW,
            url: 'https://spa.example.test/dashboard',
        );

        $this->assertSame('https://spa.example.test/dashboard', $payload->url);
    }

    public function test_captures_authenticated_user(): void
    {
        $user = new TestUser(['id' => 42]);
        $user->exists = true;
        $user->id = 42;

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $payload = $this->builder()->build($request, 'tok123', Event::TYPE_PAGE_VIEW);

        $this->assertSame(TestUser::class, $payload->authUserType);
        $this->assertSame(42, $payload->authUserId);
    }

    public function test_captures_eventable_model_and_meta_for_actions(): void
    {
        $order = new TestOrder(['id' => 7]);
        $order->exists = true;
        $order->id = 7;

        $request = Request::create('/');

        $payload = $this->builder()->build(
            $request,
            'tok123',
            Event::TYPE_ACTION,
            name: 'order.placed',
            eventable: $order,
            meta: ['amount' => 99.5],
        );

        $this->assertSame('order.placed', $payload->name);
        $this->assertSame(TestOrder::class, $payload->eventableType);
        $this->assertSame(7, $payload->eventableId);
        $this->assertSame(['amount' => 99.5], $payload->meta);
    }
}
