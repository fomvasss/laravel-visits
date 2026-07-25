<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Console;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Illuminate\Console\Command;

/**
 * Never scheduled automatically — the host wires it into its own scheduler deliberately.
 * Deletion order (events -> sessions -> visitors) matters: each table has a cascadeOnDelete
 * FK to its parent, so deleting an old Visitor/Session also removes any of its still-young
 * children as a side effect, which is fine — it only happens once the parent itself is stale.
 */
class PruneCommand extends Command
{
    protected $signature = 'visits:prune {--days= : Override retention_days from config} {--force : Skip the confirmation prompt}';

    protected $description = 'Delete raw visit_events/visit_sessions/visit_visitors older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('visits.retention_days', 90));
        $cutoff = now()->subDays($days);

        if (! $this->option('force') && ! $this->confirm(
            "Delete visit_events/visit_sessions/visit_visitors older than {$cutoff->toDateString()} ({$days} days)?"
        )) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $events = Event::withoutGlobalScope(WithoutBotsScope::class)
            ->where('created_at', '<', $cutoff)->delete();

        $sessions = Session::withoutGlobalScope(WithoutBotsScope::class)
            ->where('started_at', '<', $cutoff)->delete();

        $visitors = Visitor::withoutGlobalScope(WithoutBotsScope::class)
            ->where('last_seen_at', '<', $cutoff)->delete();

        $this->info("Deleted {$events} event(s), {$sessions} session(s), {$visitors} visitor(s).");

        return self::SUCCESS;
    }
}
