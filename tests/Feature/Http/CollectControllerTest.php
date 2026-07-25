<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature\Http;

use Fomvasss\Visits\Jobs\RecordVisitJob;
use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class CollectControllerTest extends TestCase
{
    public function test_page_view_is_accepted_and_returns_visitor_token(): void
    {
        Queue::fake();

        $response = $this->postJson('/visits/collect', [
            'url' => 'https://spa.example.test/dashboard',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['visitor_token']);
        $this->assertNotEmpty($response->json('visitor_token'));

        Queue::assertPushed(RecordVisitJob::class, fn ($job) => $job->payload->type === Event::TYPE_PAGE_VIEW
            && $job->payload->url === 'https://spa.example.test/dashboard');
    }

    public function test_action_requires_a_name(): void
    {
        Queue::fake();

        $response = $this->postJson('/visits/collect', ['type' => Event::TYPE_ACTION]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
        Queue::assertNotPushed(RecordVisitJob::class);
    }

    public function test_valid_action_is_dispatched(): void
    {
        Queue::fake();

        $response = $this->postJson('/visits/collect', [
            'type' => Event::TYPE_ACTION,
            'name' => 'newsletter.subscribed',
            'meta' => ['source' => 'footer'],
        ]);

        $response->assertOk();
        Queue::assertPushed(RecordVisitJob::class, fn ($job) => $job->payload->type === Event::TYPE_ACTION
            && $job->payload->name === 'newsletter.subscribed'
            && $job->payload->meta === ['source' => 'footer']);
    }

    public function test_rejects_invalid_type(): void
    {
        $response = $this->postJson('/visits/collect', ['type' => 'not_a_real_type']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_returns_null_token_when_package_disabled(): void
    {
        Queue::fake();
        config(['visits.enabled' => false]);

        $response = $this->postJson('/visits/collect', []);

        $response->assertOk();
        $response->assertJson(['visitor_token' => null]);
        Queue::assertNotPushed(RecordVisitJob::class);
    }

    public function test_reuses_client_supplied_token_header(): void
    {
        Queue::fake();
        $token = str_repeat('x', 40);

        $response = $this->postJson('/visits/collect', [], ['X-Visitor-Token' => $token]);

        $response->assertOk();
        $response->assertJson(['visitor_token' => $token]);
    }
}
