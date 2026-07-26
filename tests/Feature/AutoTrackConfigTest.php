<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature;

use Fomvasss\Visits\Http\Middleware\TrackVisit;
use Fomvasss\Visits\Tests\TestCase;

class AutoTrackConfigTest extends TestCase
{
    public function test_auto_track_pushes_track_visit_onto_the_web_group_by_default(): void
    {
        $webGroup = app('router')->getMiddlewareGroups()['web'] ?? [];

        $this->assertContains(TrackVisit::class, $webGroup);
    }

    public function test_track_visits_alias_is_always_registered(): void
    {
        $this->assertSame(TrackVisit::class, app('router')->getMiddleware()['track-visits'] ?? null);
    }
}
