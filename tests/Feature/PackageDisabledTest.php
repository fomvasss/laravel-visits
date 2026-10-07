<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature;

use Fomvasss\Visits\Facades\Visits;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class PackageDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('visits.enabled', false);
    }

    public function test_dashboard_and_whoami_are_not_registered(): void
    {
        $this->assertFalse(Route::has('visits.index'));
        $this->assertFalse(Route::has('visits.whoami'));
        $this->assertTrue(Route::has('visits.collect'));
    }

    public function test_whoami_returns_nothing(): void
    {
        $this->assertSame([], Visits::whoami());
    }
}
