<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature\Http;

use Fomvasss\Visits\Jobs\RecordVisitJob;
use Fomvasss\Visits\Tests\Fixtures\AlwaysConsentResolver;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

class TrackVisitMiddlewareTest extends TestCase
{
    /**
     * Orchestra Testbench's own HTTP Kernel lazily syncs its default middleware groups into
     * the router the first time it's resolved (on the first request in the test), which
     * *replaces* the 'web' group array — silently wiping out whatever VisitsServiceProvider
     * pushed onto it earlier during provider boot. Real Laravel apps don't hit this because
     * their Kernel builds the group before any package provider boots, so pushMiddlewareToGroup()
     * always appends onto an already-final array. To test the middleware's own behavior
     * without depending on that harness-specific timing, attach it directly via its
     * 'track-visits' alias (registered unconditionally in registerMiddleware()) alongside 'web'.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-page', fn () => 'ok')->middleware(['web', 'track-visits']);
        Route::post('/test-post', fn () => 'ok')->middleware(['web', 'track-visits']);
    }

    public function test_get_request_dispatches_record_visit_job_and_sets_cookie(): void
    {
        Queue::fake();

        $response = $this->get('/test-page');

        $response->assertOk();
        $response->assertCookie((string) config('visits.cookie.name'));
        Queue::assertPushed(RecordVisitJob::class);
    }

    public function test_non_get_requests_are_not_tracked(): void
    {
        Queue::fake();

        $this->post('/test-post')->assertOk();

        Queue::assertNotPushed(RecordVisitJob::class);
    }

    public function test_excluded_paths_are_not_tracked(): void
    {
        Queue::fake();
        Route::get('/horizon/test', fn () => 'ok')->middleware(['web', 'track-visits']);
        config(['visits.exclude_paths' => ['horizon/*']]);

        $this->get('/horizon/test')->assertOk();

        Queue::assertNotPushed(RecordVisitJob::class);
    }

    public function test_tracking_disabled_globally_skips_dispatch(): void
    {
        Queue::fake();
        config(['visits.enabled' => false]);

        $this->get('/test-page')->assertOk();

        Queue::assertNotPushed(RecordVisitJob::class);
    }

    public function test_consent_required_without_consent_skips_dispatch(): void
    {
        Queue::fake();
        config([
            'visits.consent.require_consent' => true,
            'visits.consent.resolver' => AlwaysConsentResolver::class,
        ]);

        $this->get('/test-page')->assertOk();

        Queue::assertNotPushed(RecordVisitJob::class);
    }

    public function test_consent_required_with_consent_dispatches(): void
    {
        Queue::fake();
        config([
            'visits.consent.require_consent' => true,
            'visits.consent.resolver' => AlwaysConsentResolver::class,
        ]);

        $this->withCookie('consent', '1')->get('/test-page')->assertOk();

        Queue::assertPushed(RecordVisitJob::class);
    }

    public function test_reuses_existing_visitor_cookie_as_the_token(): void
    {
        Queue::fake();
        $token = str_repeat('z', 40);

        $this->withCookie((string) config('visits.cookie.name'), $token)
            ->get('/test-page')
            ->assertOk();

        Queue::assertPushed(RecordVisitJob::class, fn ($job) => $job->payload->token === $token);
    }
}
