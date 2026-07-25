<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests;

use Fomvasss\Visits\VisitsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [VisitsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');

        $app['config']->set('visits.enabled', true);
        $app['config']->set('visits.dashboard.middleware', ['web']);

        // stevebauman/location's own config isn't merged automatically (the host app is
        // expected to publish it) — LocationManager's constructor needs a driver class to
        // resolve even in tests using Location::fake(), which swaps the instance afterwards.
        $app['config']->set('location.driver', \Stevebauman\Location\Drivers\IpApi::class);
        $app['config']->set('location.fallbacks', []);
        $app['config']->set('location.position', \Stevebauman\Location\Position::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->createFixtureTables();
    }

    private function createFixtureTables(): void
    {
        Schema::create('test_users', function ($table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('test_orders', function ($table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });
    }
}
