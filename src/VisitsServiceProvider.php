<?php

declare(strict_types=1);

namespace Fomvasss\Visits;

use Fomvasss\Visits\Console\AggregateCommand;
use Fomvasss\Visits\Console\CloseStaleSessionsCommand;
use Fomvasss\Visits\Console\PruneCommand;
use Fomvasss\Visits\Console\SeedDemoCommand;
use Fomvasss\Visits\Http\Controllers\CollectController;
use Fomvasss\Visits\Http\Controllers\DashboardController;
use Fomvasss\Visits\Http\Controllers\WhoAmIController;
use Fomvasss\Visits\Http\Middleware\TrackVisit;
use Fomvasss\Visits\Listeners\MergeVisitorIdentity;
use Fomvasss\Visits\Listeners\ResetVisitorIdentity;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VisitsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/visits.php', 'visits');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'visits');

        $this->publishes([
            __DIR__ . '/../config/visits.php' => config_path('visits.php'),
        ], 'visits-config');

        $this->publishes([
            __DIR__ . '/../resources/js/visits.js' => public_path('vendor/visits/visits.js'),
        ], 'visits-assets');

        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CloseStaleSessionsCommand::class,
                AggregateCommand::class,
                PruneCommand::class,
            ]);

            // dev/testing tool only — never registered outside local/testing
            if ($this->app->environment('local', 'testing')) {
                $this->commands([
                    SeedDemoCommand::class,
                ]);
            }

            if (config('visits.schedule.enabled', false)) {
                $this->registerSchedule();
            }
        }
    }

    /**
     * On by default (visits.schedule.enabled) so a fresh install doesn't silently need a
     * hand-copied routes/console.php entry to keep rollups fresh and sessions closing. Fixed
     * frequencies matching the README's own recommendation — different needs (or a host that
     * already scheduled these itself) should turn the flag off rather than this growing a
     * config-driven cron DSL. visits:prune is deliberately excluded even here: deleting rows
     * should always be a separate, explicit opt-in, never a side effect of this flag.
     */
    private function registerSchedule(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('visits:close-stale-sessions')->everyFiveMinutes();
            $schedule->command('visits:aggregate --date=today')->everyFiveMinutes();
            $schedule->command('visits:aggregate --date=yesterday')->dailyAt('00:10');
        });
    }

    private function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('track-visits', TrackVisit::class);

        if (! config('visits.enabled', true) || ! config('visits.auto_track', true)) {
            return;
        }

        // guard against double-registration: boot() can run more than once per process
        // under Octane, and pushMiddlewareToGroup() would otherwise append duplicates,
        // causing RecordVisitJob to be dispatched more than once per request.
        $webGroup = $router->getMiddlewareGroups()['web'] ?? [];

        if (! in_array(TrackVisit::class, $webGroup, true)) {
            $router->pushMiddlewareToGroup('web', TrackVisit::class);
        }
    }

    private function registerRoutes(): void
    {
        $collectMiddleware = (array) config('visits.collect.middleware', ['web']);
        $collectMiddleware[] = 'throttle:' . config('visits.rate_limit.endpoint', '60,1');

        Route::middleware($collectMiddleware)
            ->post('visits/collect', CollectController::class)
            ->name('visits.collect');

        if (config('visits.whoami.enabled', true)) {
            $whoamiMiddleware = (array) config('visits.whoami.middleware', ['web']);
            $whoamiMiddleware[] = 'throttle:' . config('visits.rate_limit.whoami', '60,1');

            Route::middleware($whoamiMiddleware)
                ->get(config('visits.whoami.path', 'visits/whoami'), WhoAmIController::class)
                ->name('visits.whoami');
        }

        if (config('visits.dashboard.enabled', true)) {
            Route::middleware(config('visits.dashboard.middleware', ['web']))
                ->prefix(config('visits.dashboard.path', 'visits'))
                ->name('visits.')
                ->group(function () {
                    Route::get('/', [DashboardController::class, 'index'])->name('index');
                    Route::get('/campaigns', [DashboardController::class, 'campaigns'])->name('campaigns');
                    Route::get('/sessions', [DashboardController::class, 'sessions'])->name('sessions');
                    Route::get('/sessions/{id}', [DashboardController::class, 'show'])->name('show');
                    Route::get('/visitors', [DashboardController::class, 'visitors'])->name('visitors');
                    Route::get('/visitors/{id}', [DashboardController::class, 'showVisitor'])->name('visitor');
                    Route::get('/me', [DashboardController::class, 'whoami'])->name('me');
                    if (config('visits.live.enabled', true)) {
                        Route::get('/live', [DashboardController::class, 'live'])->name('live');
                        Route::get('/live/feed', [DashboardController::class, 'liveFeed'])->name('live.feed');
                        Route::get('/live/stream', [DashboardController::class, 'liveStream'])->name('live.stream');
                    }
                });
        }
    }

    private function registerListeners(): void
    {
        Event::listen(Login::class, MergeVisitorIdentity::class);
        Event::listen(Logout::class, ResetVisitorIdentity::class);
    }
}
