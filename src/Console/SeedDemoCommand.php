<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Console;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Support\ModelResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dev/testing tool only — generates realistic-looking Visitor -> Session -> Event chains via
 * the package factories (coherent geo/device/UTM per visitor, sessions clamped inside the
 * visitor's lifetime, events clamped inside their session), then runs visits:aggregate over the
 * seeded range so the dashboard rollups aren't empty.
 */
class SeedDemoCommand extends Command
{
    protected $signature = 'visits:seed-demo
        {--days=30 : Spread visitors\' first_seen_at over this many past days}
        {--visitors=150 : Number of visitors to create}
        {--fresh : Delete existing visit_* rows first}';

    protected $description = 'Seed realistic-looking demo data for local dashboard testing';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $visitorCount = (int) $this->option('visitors');

        if ($this->option('fresh')) {
            if (! $this->confirm('This deletes existing visit_visitors/visit_sessions/visit_events/visit_stats_daily rows. Continue?')) {
                return self::SUCCESS;
            }

            $this->truncate();
        }

        $visitorClass = ModelResolver::visitor();
        $sessionClass = ModelResolver::session();
        $eventClass = ModelResolver::event();

        $bar = $this->output->createProgressBar($visitorCount);
        $bar->start();

        for ($i = 0; $i < $visitorCount; $i++) {
            $visitor = $visitorClass::factory()->create([
                'first_seen_at' => now()->subDays(random_int(0, $days)),
            ]);

            $lastActivity = $visitor->first_seen_at;

            foreach (range(1, random_int(1, 3)) as $ignored) {
                $started = fake()->dateTimeBetween($visitor->first_seen_at, 'now');

                $session = $sessionClass::factory()->create([
                    'visitor_id' => $visitor->id,
                    'started_at' => $started,
                    // inherit the visitor's attribution/geo/device for a coherent record —
                    // same idea as last-touch-inherits-from-visitor in the real pipeline
                    'utm_source' => $visitor->utm_source,
                    'utm_medium' => $visitor->utm_medium,
                    'referrer_host' => $visitor->first_referrer_host,
                    'referrer_url' => $visitor->first_referrer_url,
                    'country_code' => $visitor->country_code,
                    'city' => $visitor->city,
                    'timezone' => $visitor->timezone,
                    'device_type' => $visitor->device_type,
                    'platform' => $visitor->platform,
                    'browser' => $visitor->browser,
                    'client_type' => $visitor->client_type,
                    'is_bot' => $visitor->is_bot,
                ]);

                $pageViews = 0;

                foreach (range(1, random_int(1, 6)) as $ignored2) {
                    $event = $eventClass::factory()->create([
                        'session_id' => $session->id,
                        'visitor_id' => $visitor->id,
                        'is_bot' => $visitor->is_bot,
                        'created_at' => fake()->dateTimeBetween($session->started_at, $session->ended_at),
                    ]);

                    if ($event->type === Event::TYPE_PAGE_VIEW) {
                        $pageViews++;
                    }
                }

                $session->update(['page_views_count' => $pageViews]);

                if ($session->last_activity_at->gt($lastActivity)) {
                    $lastActivity = $session->last_activity_at;
                }
            }

            $visitor->update(['last_seen_at' => $lastActivity]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->call('visits:aggregate', [
            '--from' => now()->subDays($days)->toDateString(),
            '--to' => now()->toDateString(),
        ]);

        $this->info("Seeded {$visitorCount} visitors over {$days} days.");

        return self::SUCCESS;
    }

    private function truncate(): void
    {
        DB::table('visit_events')->delete();
        DB::table('visit_sessions')->delete();
        DB::table('visit_visitors')->delete();
        DB::table('visit_stats_daily')->delete();
    }
}
