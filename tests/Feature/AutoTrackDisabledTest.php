<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature;

use Fomvasss\Visits\Http\Middleware\TrackVisit;
use Fomvasss\Visits\Tests\TestCase;

class AutoTrackDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('visits.auto_track', false);
    }

    public function test_track_visit_is_not_pushed_onto_the_web_group_when_auto_track_is_disabled(): void
    {
        $webGroup = app('router')->getMiddlewareGroups()['web'] ?? [];

        $this->assertNotContains(TrackVisit::class, $webGroup);
    }

    public function test_track_visits_alias_still_resolves_for_manual_attachment(): void
    {
        $this->assertSame(TrackVisit::class, app('router')->getMiddleware()['track-visits'] ?? null);
    }
}
